<?php

use Carbon\Carbon;

/**
 * Function helper to add flash notification.
 *
 * @param  null|string  $message  The flashed message.
 * @param  string  $level  Level/type of message
 * @return void
 */
function flash($message = null, $level = 'info')
{
    $session = app('session');
    if (!is_null($message)) {
        $session->flash('flash_notification.message', $message);
        $session->flash('flash_notification.level', $level);
    }
}

/**
 * Format number to string.
 *
 * @return string
 */
function format_number(float $number)
{
    $precision = config('money.precision');
    $decimalSeparator = config('money.decimal_separator');
    $thousandsSeparator = config('money.thousands_separator');

    $number = number_format($number, $precision, $decimalSeparator, $thousandsSeparator);

    return str_replace('-', '- ', $number);
}

function number_step()
{
    $precision = config('money.precision');
    if ($precision == 0) {
        return '1';
    }
    $decimalZero = str_pad('0.', $precision + 1, '0', STR_PAD_RIGHT);

    return $decimalZero.'1';
}

/**
 * Get balance amount based on transactions.
 *
 * @param  string|null  $perDate
 * @param  string|null  $startDate
 * @return float
 */
function balance($perDate = null, $startDate = null, $categoryId = null, $bookId = null)
{
    $transactionQuery = DB::table('transactions');
    if ($perDate) {
        $transactionQuery->where('date', '<=', $perDate);
    }
    if ($startDate) {
        $transactionQuery->where('date', '>=', $startDate);
    }
    if ($categoryId) {
        $transactionQuery->where('category_id', $categoryId);
    }
    if ($bookId) {
        $transactionQuery->where('book_id', $bookId);
    }
    $transactions = $transactionQuery->where('creator_id', auth()->id())->get();

    return $transactions->sum(function ($transaction) {
        return $transaction->in_out ? $transaction->amount : -$transaction->amount;
    });
}

function get_week_numbers(string $year): array
{
    $lastWeekOfTheYear = Carbon::parse($year.'-01-01')->weeksInYear();

    return range(0, $lastWeekOfTheYear);
}

function calculate_folder_size(string $absolutePath)
{
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $cmd = sprintf('powershell -Command "(Get-ChildItem -Path %s -Recurse | Measure-Object -Property Length -Sum).Sum"', escapeshellarg($absolutePath));
    } else {
        $cmd = sprintf('du -sb %s', escapeshellarg($absolutePath));
    }

    $output = trim(shell_exec($cmd));
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        $output = trim(preg_replace('/\s.*$/', '', $output));
    }

    return is_numeric($output) ? (int) $output : false;
}

function format_size_units($bytes)
{
    if ($bytes >= 1073741824) {
        $bytes = number_format($bytes / 1073741824, 2).' GB';
    } elseif ($bytes >= 1048576) {
        $bytes = number_format($bytes / 1048576, 2).' MB';
    } elseif ($bytes >= 1024) {
        $bytes = number_format($bytes / 1024, 2).' KB';
    } elseif ($bytes > 1) {
        $bytes = $bytes.' bytes';
    } elseif ($bytes == 1) {
        $bytes = $bytes.' byte';
    } else {
        $bytes = '0 bytes';
    }

    return $bytes;
}

// Ref: https://stackoverflow.com/a/11807179
function convert_to_bytes(string $from): ?int
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $number = substr($from, 0, -2);
    $suffix = strtoupper(substr($from, -2));

    // B or no suffix
    if (is_numeric(substr($suffix, 0, 1))) {
        return null;
    }

    $exponent = array_flip($units)[$suffix] ?? null;
    if ($exponent === null) {
        return null;
    }

    return $number * (1024 ** $exponent);
}

function get_percent($numerator, $denominator)
{
    $formatedString = 0;

    if ($denominator) {
        $formatedString = number_format(($numerator / $denominator * 100), 2);
    }

    return $formatedString;
}

/**
 * Format transaction to WhatsApp markdown message.
 *
 * @param  \App\Transaction  $transaction
 * @return string
 */
function formatTransactionForWhatsApp(\App\Transaction $transaction): string
{
    // Get transaction type icon and label
    $typeIcon = $transaction->in_out ? '🤲' : '📤';
    $typeLabel = $transaction->in_out ? 'PEMASUKAN' : 'PENGELUARAN';

    // Format date to Indonesian format
    $date = \Carbon\Carbon::parse($transaction->date);
    $dayName = $date->isoFormat('dddd');
    if ($dayName == 'Minggu') {
        $dayName = 'Ahad';
    }
    $formattedDate = $dayName . ', ' . $date->isoFormat('D MMMM Y');

    // Format amount
    $amount = 'Rp ' . format_number($transaction->amount);

    // Get partner name or default to "Hamba Allah"
    $partnerName = $transaction->partner ? $transaction->partner->name : 'Hamba Allah';

    // Get category name or default
    $categoryName = $transaction->category ? $transaction->category->name : __('transaction.no_category');

    // Get bank account or payment method
    $paymentMethod = $transaction->bankAccount->name ?? __('transaction.cash');

    // Build the message
    $message = "*{$formattedDate}*\n";
    $message .= "* {$categoryName} dari {$partnerName}: {$amount}";

    // Add payment method if not cash
    if ($paymentMethod !== __('transaction.cash')) {
        $message .= " ({$paymentMethod})";
    }

    // Add description if available
    if (!empty($transaction->description)) {
        $message .= "\n  Keterangan: {$transaction->description}";
    }

    return $message;
}

/**
 * Format transactions for a period into WhatsApp markdown report.
 *
 * @param  \Illuminate\Database\Eloquent\Collection  $transactions
 * @param  string  $startDate
 * @param  string  $endDate
 * @param  float|null  $startBalance
 * @param  string|null  $organizationName
 * @param  string|null  $organizationLocation
 * @return string
 */
function formatTransactionsForWhatsAppReport(
    $transactions,
    string $startDate,
    string $endDate,
    ?float $startBalance = null,
    ?string $organizationName = null,
    ?string $organizationLocation = null
): string {
    $startDate = \Carbon\Carbon::parse($startDate);
    $endDate = \Carbon\Carbon::parse($endDate);

    // Default organization info
    $orgName = $organizationName ?? 'MUSHOLLA/MASJID';
    $orgLocation = $organizationLocation ?? '';

    // Build header
    $message = "Assalamualaikum Warahmatullahi Wabarakatuh\n\n\n\n";
    $message .= "*LAPORAN KEUANGAN*\n\n";
    $message .= "*{$orgName}*\n\n";
    if ($orgLocation) {
        $message .= "🕌 {$orgLocation}\n\n\n\n";
    }

    // Period
    $periodText = "*🗓️ Periode: " . $startDate->isoFormat('DD') . " - " . $endDate->isoFormat('DD MMMM Y') . "*\n\n\n\n";
    $message .= $periodText;

    $message .= "Berikut kami sampaikan rincian keuangan {$orgName} untuk periode ini:\n\n\n\n";

    // Start balance
    if ($startBalance !== null) {
        $prevDate = $startDate->copy()->subDay();
        $message .= "*Saldo Awal (per " . $prevDate->isoFormat('DD MMMM Y') . "):*\n";
        $message .= "*Rp " . format_number($startBalance) . "*\n\n\n\n";
    }

    // Group transactions by type and date
    $incomeTransactions = $transactions->where('in_out', 1)->groupBy('date');
    $spendingTransactions = $transactions->where('in_out', 0)->groupBy('date');

    // PEMASUKAN section
    if ($incomeTransactions->isNotEmpty()) {
        $message .= "*🤲 PEMASUKAN*\n";
        $message .= "Berikut rincian infaq yang masuk:\n\n\n\n";

        foreach ($incomeTransactions as $date => $dayTransactions) {
            $carbonDate = \Carbon\Carbon::parse($date);
            $dayName = $carbonDate->isoFormat('dddd');
            if ($dayName == 'Minggu') {
                $dayName = 'Ahad';
            }
            $formattedDate = $dayName . ', ' . $carbonDate->isoFormat('D MMMM Y');

            $message .= "*{$formattedDate}*\n";

            foreach ($dayTransactions as $transaction) {
                $partnerName = $transaction->partner ? $transaction->partner->name : 'Hamba Allah';
                $categoryName = $transaction->category ? $transaction->category->name : 'Infaq';
                $amount = 'Rp ' . format_number($transaction->amount);
                $paymentMethod = $transaction->bankAccount->name ?? __('transaction.cash');

                $message .= "* {$categoryName} dari {$partnerName}: {$amount}";

                if ($paymentMethod !== __('transaction.cash')) {
                    $message .= " ({$paymentMethod})";
                }
                $message .= "\n";
            }
            $message .= "\n\n";
        }

        $totalIncome = $transactions->where('in_out', 1)->sum('amount');
        $message .= "*Total Pemasukan: Rp " . format_number($totalIncome) . "*\n\n\n\n";
    }

    // PENGELUARAN section
    if ($spendingTransactions->isNotEmpty()) {
        $message .= "*📤 PENGELUARAN*\n";
        $message .= "Berikut rincian pengeluaran:\n\n\n\n";

        foreach ($spendingTransactions as $date => $dayTransactions) {
            $carbonDate = \Carbon\Carbon::parse($date);
            $dayName = $carbonDate->isoFormat('dddd');
            if ($dayName == 'Minggu') {
                $dayName = 'Ahad';
            }
            $formattedDate = $dayName . ', ' . $carbonDate->isoFormat('D MMMM Y');

            $message .= "*{$formattedDate}*\n";

            $dayTotal = 0;
            foreach ($dayTransactions as $transaction) {
                $categoryName = $transaction->category ? $transaction->category->name : 'Pengeluaran';
                $amount = 'Rp ' . format_number($transaction->amount);
                $paymentMethod = $transaction->bankAccount->name ?? '';

                $message .= "* {$categoryName}: {$amount}";

                if ($paymentMethod && $paymentMethod !== __('transaction.cash')) {
                    $message .= " ({$paymentMethod})";
                }
                $message .= "\n";

                $dayTotal += $transaction->amount;
            }

            if ($dayTransactions->count() > 1) {
                $message .= "\nTotal: *Rp " . format_number($dayTotal) . "*\n";
            }
            $message .= "\n\n";
        }

        $totalSpending = $transactions->where('in_out', 0)->sum('amount');
        $message .= "*Total Pengeluaran: Rp " . format_number($totalSpending) . "*\n\n\n\n";
    }

    // End balance
    $totalIncome = $transactions->where('in_out', 1)->sum('amount');
    $totalSpending = $transactions->where('in_out', 0)->sum('amount');
    $endBalance = ($startBalance ?? 0) + $totalIncome - $totalSpending;

    $message .= "*💰 SALDO AKHIR KAS (per " . $endDate->isoFormat('DD MMMM Y') . ")*\n";
    $message .= "*Rp " . format_number($endBalance) . "*\n\n\n\n";

    // Footer
    $message .= "Demikian laporan kas ini kami sampaikan.\n\n\n\n";
    $message .= "Terima kasih kepada para jama'ah dan donatur yang telah menyisihkan hartanya untuk kemakmuran {$orgName}. ";
    $message .= "Semoga Allah SWT membalas setiap kebaikan yang diberikan.\n\n\n\n";
    $message .= "Jazakumullahu Khairan Katsiran.\n\n\n\n";
    $message .= "Hormat kami,\n";
    $message .= "*DKM {$orgName}*";

    return $message;
}
