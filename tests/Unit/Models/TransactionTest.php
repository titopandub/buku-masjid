<?php

namespace Tests\Unit\Models;

use App\Models\BankAccount;
use App\Models\Book;
use App\Models\Category;
use App\Models\File;
use App\Models\Partner;
use App\Transaction;
use App\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_transaction_has_belongs_to_creator_relation()
    {
        $transaction = factory(Transaction::class)->make();

        $this->assertInstanceOf(User::class, $transaction->creator);
        $this->assertEquals($transaction->creator_id, $transaction->creator->id);
    }

    /** @test */
    public function a_transaction_has_belongs_to_category_relation()
    {
        $user = $this->loginAsUser();
        $category = factory(Category::class)->create(['creator_id' => $user->id]);
        $transaction = factory(Transaction::class)->make(['category_id' => $category->id]);

        $this->assertInstanceOf(Category::class, $transaction->category);
        $this->assertEquals($transaction->category_id, $transaction->category->id);
    }

    /** @test */
    public function transaction_model_has_belongs_to_partner_relation()
    {
        $user = $this->loginAsUser();
        $partner = factory(Partner::class)->create();
        $transaction = factory(Transaction::class)->make(['partner_id' => $partner->id]);

        $this->assertInstanceOf(Partner::class, $transaction->partner);
        $this->assertEquals($transaction->partner_id, $transaction->partner->id);
    }

    /** @test */
    public function a_transaction_has_belongs_to_bank_account_relation()
    {
        $user = $this->loginAsUser();
        $bankAccount = factory(BankAccount::class)->create(['creator_id' => $user->id]);
        $transaction = factory(Transaction::class)->make(['bank_account_id' => $bankAccount->id]);

        $this->assertInstanceOf(BankAccount::class, $transaction->bankAccount);
        $this->assertEquals($transaction->bank_account_id, $transaction->bankAccount->id);
    }

    /** @test */
    public function a_transaction_has_belongs_to_book_relation()
    {
        $user = $this->loginAsUser();
        $book = factory(Book::class)->create(['creator_id' => $user->id]);
        $transaction = factory(Transaction::class)->make(['book_id' => $book->id]);

        $this->assertInstanceOf(Book::class, $transaction->book);
        $this->assertEquals($transaction->book_id, $transaction->book->id);
    }

    /** @test */
    public function a_transaction_has_type_attribute()
    {
        $transaction = factory(Transaction::class)->make(['in_out' => 1]);
        $this->assertEquals(__('transaction.income'), $transaction->type);

        $transaction->in_out = 0;
        $this->assertEquals(__('transaction.spending'), $transaction->type);
    }

    /** @test */
    public function transaction_model_has_date_alert_attribute()
    {
        Carbon::setTestNow('2024-10-20');
        $transaction = factory(Transaction::class)->make(['date' => '2024-10-22']);
        $this->assertEquals(
            '<i class="fe fe-alert-circle text-danger" title="'.__('transaction.forward_date_alert').'"></i>',
            $transaction->date_alert
        );

        $transaction->date = '2024-10-20';
        $this->assertEquals('', $transaction->date_alert);

        $transaction->date = '2024-10-19';
        $this->assertEquals('', $transaction->date_alert);
        Carbon::setTestNow();
    }

    /** @test */
    public function a_transaction_has_amount_string_attribute()
    {
        $amount = 1099.00;

        $transaction = factory(Transaction::class)->make([
            'in_out' => 1,
            'amount' => $amount,
        ]);
        $this->assertEquals(format_number($amount), $transaction->amount_string);

        $transaction->in_out = 0;
        $this->assertEquals(format_number(-$amount), $transaction->amount_string);
    }

    /** @test */
    public function a_transaction_has_year_month_and_date_only_attribute()
    {
        $transaction = factory(Transaction::class)->make(['date' => '2017-01-31']);

        $this->assertEquals('2017', $transaction->year);
        $this->assertEquals('01', $transaction->month);
        $this->assertEquals(Carbon::parse('2017-01-31')->isoFormat('MMM'), $transaction->month_name);
        $this->assertEquals('31', $transaction->date_only);
    }

    /** @test */
    public function a_transaction_has_day_name_attribute()
    {
        $date = '2017-01-31';
        $transaction = factory(Transaction::class)->make(['date' => $date]);

        $this->assertEquals(Carbon::parse($date)->isoFormat('dddd'), $transaction->day_name);

        $transaction = factory(Transaction::class)->make(['date' => null]);
        $this->assertEquals(null, $transaction->day_name);
    }

    /** @test */
    public function a_transaction_has_change_day_name_minggu_to_ahad_attribute()
    {
        $date = '2017-01-29';
        $transaction = factory(Transaction::class)->make(['date' => $date]);

        $this->assertEquals('Ahad', $transaction->day_name);
    }

    /** @test */
    public function transaction_model_has_has_many_files_relation()
    {
        $transaction = factory(Transaction::class)->create();
        $file = File::create([
            'fileable_id' => $transaction->id,
            'fileable_type' => 'transactions',
            'type_code' => 'image',
            'file_path' => 'File title',
            'title' => 'File title',
            'description' => 'Some transaction description',
        ]);

        $this->assertInstanceOf(Collection::class, $transaction->files);
        $this->assertInstanceOf(File::class, $transaction->files->first());
    }

    /** @test */
    public function transaction_can_be_formatted_for_whatsapp()
    {
        $user = $this->loginAsUser();
        $category = factory(Category::class)->create([
            'creator_id' => $user->id,
            'name' => 'Infaq'
        ]);
        $partner = factory(Partner::class)->create(['name' => 'John Doe']);
        $bankAccount = factory(BankAccount::class)->create([
            'creator_id' => $user->id,
            'name' => 'QRIS'
        ]);

        $transaction = factory(Transaction::class)->create([
            'date' => '2025-08-08',
            'amount' => 100000,
            'in_out' => 1,
            'description' => 'Donasi untuk masjid',
            'category_id' => $category->id,
            'partner_id' => $partner->id,
            'bank_account_id' => $bankAccount->id,
        ]);

        $whatsappMessage = $transaction->toWhatsAppMessage();

        $this->assertIsString($whatsappMessage);
        $this->assertStringContainsString('*Jumat, 8 Agustus 2025*', $whatsappMessage);
        $this->assertStringContainsString('Infaq dari John Doe', $whatsappMessage);
        $this->assertStringContainsString('Rp 100.000', $whatsappMessage);
        $this->assertStringContainsString('(QRIS)', $whatsappMessage);
        $this->assertStringContainsString('Keterangan: Donasi untuk masjid', $whatsappMessage);
    }

    /** @test */
    public function transaction_whatsapp_format_handles_anonymous_partner()
    {
        $user = $this->loginAsUser();
        $category = factory(Category::class)->create([
            'creator_id' => $user->id,
            'name' => 'Infaq'
        ]);

        $transaction = factory(Transaction::class)->create([
            'date' => '2025-08-08',
            'amount' => 25000,
            'in_out' => 1,
            'category_id' => $category->id,
            'partner_id' => null,
        ]);

        $whatsappMessage = $transaction->toWhatsAppMessage();

        $this->assertStringContainsString('Infaq dari Hamba Allah', $whatsappMessage);
        $this->assertStringContainsString('Rp 25.000', $whatsappMessage);
    }

    /** @test */
    public function transaction_whatsapp_format_handles_cash_transactions()
    {
        $user = $this->loginAsUser();
        $category = factory(Category::class)->create([
            'creator_id' => $user->id,
            'name' => 'Kebersihan'
        ]);

        $transaction = factory(Transaction::class)->create([
            'date' => '2025-08-10',
            'amount' => 400000,
            'in_out' => 0,
            'category_id' => $category->id,
            'bank_account_id' => null,
        ]);

        $whatsappMessage = $transaction->toWhatsAppMessage();

        $this->assertStringContainsString('*Ahad, 10 Agustus 2025*', $whatsappMessage);
        $this->assertStringContainsString('Kebersihan dari Hamba Allah', $whatsappMessage);
        $this->assertStringContainsString('Rp 400.000', $whatsappMessage);
        $this->assertStringNotContainsString('(', $whatsappMessage); // No payment method for cash
    }

    /** @test */
    public function can_generate_whatsapp_report_for_period()
    {
        $user = $this->loginAsUser();

        // Create categories
        $infaqCategory = factory(Category::class)->create([
            'creator_id' => $user->id,
            'name' => 'Infaq'
        ]);
        $kebersihanCategory = factory(Category::class)->create([
            'creator_id' => $user->id,
            'name' => 'Kebersihan'
        ]);

        // Create bank account
        $qrisBankAccount = factory(BankAccount::class)->create([
            'creator_id' => $user->id,
            'name' => 'QRIS'
        ]);

        // Create partner
        $partner = factory(Partner::class)->create(['name' => 'Ahmad']);

        // Create income transactions
        factory(Transaction::class)->create([
            'date' => '2025-09-15',
            'amount' => 100000,
            'in_out' => 1,
            'category_id' => $infaqCategory->id,
            'partner_id' => $partner->id,
            'bank_account_id' => null, // Cash
        ]);

        factory(Transaction::class)->create([
            'date' => '2025-09-15',
            'amount' => 25000,
            'in_out' => 1,
            'category_id' => $infaqCategory->id,
            'partner_id' => null, // Anonymous
            'bank_account_id' => $qrisBankAccount->id,
        ]);

        // Create spending transaction
        factory(Transaction::class)->create([
            'date' => '2025-09-17',
            'amount' => 400000,
            'in_out' => 0,
            'category_id' => $kebersihanCategory->id,
            'partner_id' => null,
            'bank_account_id' => null,
        ]);

        $report = Transaction::generateWhatsAppReport(
            '2025-09-14',
            '2025-09-20',
            838500,
            'MUSHOLLA EL-FATIH',
            'DeKhirani Village'
        );

        $this->assertIsString($report);
        $this->assertStringContainsString('Assalamualaikum Warahmatullahi Wabarakatuh', $report);
        $this->assertStringContainsString('*LAPORAN KEUANGAN*', $report);
        $this->assertStringContainsString('*MUSHOLLA EL-FATIH*', $report);
        $this->assertStringContainsString('🕌 DeKhirani Village', $report);
        $this->assertStringContainsString('*🗓️ Periode: 14 - 20 September 2025*', $report);
        $this->assertStringContainsString('*Saldo Awal (per 13 September 2025):*', $report);
        $this->assertStringContainsString('*Rp 838.500*', $report);

        // Income section
        $this->assertStringContainsString('*🤲 PEMASUKAN*', $report);
        $this->assertStringContainsString('*Selasa, 15 September 2025*', $report);
        $this->assertStringContainsString('* Infaq dari Ahmad: Rp 100.000', $report);
        $this->assertStringContainsString('* Infaq dari Hamba Allah: Rp 25.000 (QRIS)', $report);
        $this->assertStringContainsString('*Total Pemasukan: Rp 125.000*', $report);

        // Spending section
        $this->assertStringContainsString('*📤 PENGELUARAN*', $report);
        $this->assertStringContainsString('*Kamis, 17 September 2025*', $report);
        $this->assertStringContainsString('* Kebersihan: Rp 400.000', $report);
        $this->assertStringContainsString('*Total Pengeluaran: Rp 400.000*', $report);

        // End balance
        $this->assertStringContainsString('*💰 SALDO AKHIR KAS (per 20 September 2025)*', $report);
        $this->assertStringContainsString('*Rp 563.500*', $report); // 838500 + 125000 - 400000

        // Footer
        $this->assertStringContainsString('Jazakumullahu Khairan Katsiran', $report);
        $this->assertStringContainsString('*DKM MUSHOLLA EL-FATIH*', $report);
    }

    /** @test */
    public function whatsapp_report_handles_empty_period()
    {
        $user = $this->loginAsUser();

        $report = Transaction::generateWhatsAppReport(
            '2025-09-14',
            '2025-09-20',
            500000,
            'MUSHOLLA TEST'
        );

        $this->assertIsString($report);
        $this->assertStringContainsString('*LAPORAN KEUANGAN*', $report);
        $this->assertStringContainsString('*MUSHOLLA TEST*', $report);
        $this->assertStringNotContainsString('*🤲 PEMASUKAN*', $report);
        $this->assertStringNotContainsString('*📤 PENGELUARAN*', $report);
        $this->assertStringContainsString('*Rp 500.000*', $report); // Same start and end balance
    }
}
