<div wire:init="getIncomeDashboardEntries">
    @if ($isLoading)
        <div class="loading-state text-center w-100">
            <img src="{{ asset('images/spinner.gif') }}" alt="Data loading spinner">
        </div>
    @else
        @forelse ($incomeDashboardEntries as $trYear => $incomeDashboardEntriesPerYear)
            <div class="row align-items-end">
                <div class="col-sm-6">
                    <div class="h3">
                        {{ __('donor.all') }}
                        {{ optional($book)->name }}
                        {{ get_months()[$month] ?? '' }}
                        {{ $trYear }}
                    </div>
                </div>
                <div class="col-sm-6 text-right mb-2">
                    <span class="text-muted">({{ __('report.in_thousand') }} {{ config('money.currency_text') }})</span>
                </div>
            </div>

            <div class="card table-responsive-sm">
                <table class="table-sm table-striped table-bordered small">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 3em">{{ __('app.table_no') }}</th>
                            <th style="width: 20em">{{ __('partner.partner_type_donor') }}</th>
                            @if (isset(get_months()[$month]))
                            @else
                                @foreach (get_months() as $monthNumber => $monthName)
                                    <th class="text-center" style="width: 5em">{{ Carbon\Carbon::parse($trYear.'-'.$monthNumber.'-01')->isoFormat('MMM') }}</th>
                                @endforeach
                            @endif
                            <th class="text-center">{{ __('app.total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $no = 1;
                        @endphp
                        @forelse ($availablePartners[$trYear] as $partner)
                            <tr>
                                <td class="text-center">{{ $no++ }}</td>
                                <td>
                                    {{ link_to_route('donors.show', $partner->name, $partner->id) }}
                                    <a href="https://wa.me/{{ str_replace([' ', '+', '(', ')'], '', $partner->phone) }}">
                                        <i class="fe fe-phone-outgoing float-right"></i>
                                    </a>
                                </td>
                                @if (isset(get_months()[$month]))
                                @else
                                    @foreach (get_months() as $monthNumber => $monthName)
                                        @php
                                            $incomeEntry = $incomeDashboardEntriesPerYear->filter(function ($income) use ($trYear, $monthNumber, $partner) {
                                                return $income->tr_year_month == $trYear.'-'.$monthNumber && $income->partner_id == $partner->id;
                                            })->first();
                                        @endphp
                                        <td class="text-right">{{ $incomeEntry ? format_number($incomeEntry->total_amount / 1000) : '' }}</td>
                                    @endforeach
                                @endif

                                @php
                                    $incomeTotal = $incomeDashboardEntriesPerYear->filter(function ($income) use ($partner) {
                                        return $income->partner_id == $partner->id;
                                    })->sum('total_amount');
                                @endphp
                                <td class="text-right">{{ format_number($incomeTotal / 1000) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="15">Tidak ada transaksi donatur tahun {{ $trYear }}.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="strong">
                            <td colspan="2" class="text-right">{{ __('app.total') }}</td>
                            @if (isset(get_months()[$month]))
                            @else
                                @foreach (get_months() as $monthNumber => $monthName)
                                    @php
                                        $monthTotal = $incomeDashboardEntriesPerYear->filter(function ($income) use ($trYear, $monthNumber) {
                                            return $income->tr_year_month == $trYear.'-'.$monthNumber;
                                        })->sum('total_amount');
                                    @endphp
                                    <td class="text-right">{{ format_number($monthTotal / 1000) }}</td>
                                @endforeach
                            @endif
                            <td class="text-right">{{ format_number($incomeDashboardEntriesPerYear->sum('total_amount') / 1000) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @empty
            <div>
                {{ __('transaction.not_found') }}
                {{ optional($book)->name }}
                {{ get_months()[$month] ?? '' }}
                {{ $year != '0000' ? $year : '' }}
            </div>
        @endforelse
    @endif
</div>
