<style>
    table,
    td,
    tr,
    th {
        border: solid black 1px
    }

    .container-table {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
    }

    .container-table td {
        vertical-align: top;
    }


    .inner-table {
        width: 100%;
        border-collapse: collapse;
    }

    .inner-table td:nth-child(1){
        width: 75%;
    }
    
    .inner-table td:nth-child(2){
        width: 25%;
    }

</style>


<table class="container-table">
    <thead>
        <tr style="background-color: #a6a6a6">
            <th colspan="4">{{ $detail->company->name }}</th>
        </tr>
        <tr style="background-color: #a6a6a6">
            <th colspan="4">Volante de pago {{ Str::headline($detail->payroll->period->translatedFormat('d \d\e F \d\e\l Y')) }}</th>
        </tr>
        <tr>
            <th colspan="2">Colaborador (a)</th>
            <th colspan="2">{{ $detail->name }}</th>
        </tr>
        <tr>
            <th colspan="2">Salario</th>
            <th colspan="2"></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td colspan="2">
                <table class="inner-table">
                    <thead>
                        <tr style="background-color: #a6a6a6">
                            <th colspan="2">Ingresos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td> 
                                {{
                                    match (true) {
                                        $detail->payroll->type->isMonthly() => "Pago Mensual",
                                        $detail->payroll->period->day < 16 => "Primera Quincena",
                                        default => "Segunda Quincena"
                                    }
                                }}
                            </td>
                            <td style="text-align: right;">{{ Illuminate\Support\Number::currency($detail->rawSalary, in: 'USD') }}</td>
                        </tr>
                        @foreach ($detail->incomes as $incomeName => $incomeValue)
                            <tr>
                                <td>{{ $detail->adjustmentNames->get($incomeName) }}</td>
                                <td style="text-align: right;">{{ Illuminate\Support\Number::currency($incomeValue, in: 'USD') }}</td>
                            </tr>
                        @endforeach
                        @if ($detail->deductions->count() > $detail->incomes->count())
                            @for ($i = 0; $i < ($detail->deductions->count() - $detail->incomes->count()) - 1; $i++)
                                <tr>
                                    <td>&nbsp;</td>
                                    <td style="text-align: right;">&nbsp;</td>
                                </tr>
                            @endfor
                        @endif
                    </tbody>
                </table>
            </td>
            <td colspan="2">
                <table class="inner-table">
                    <thead>
                        <tr style="background-color: #a6a6a6">
                            <th colspan="2">Deducciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($detail->deductions as $deductionName => $deductionValue)
                            <tr>
                                <td>{{ $detail->adjustmentNames->get($deductionName) }}</td>
                                <td style="text-align: right;">{{ Illuminate\Support\Number::currency($deductionValue, in: 'USD') }}</td>
                            </tr>
                        @endforeach
                        @if ($detail->deductions->count() < ($detail->incomes->count() + 1))
                            @for ($i = 0; $i < ($detail->incomes->count() - $detail->deductions->count()) + 1; $i++)
                                <tr>
                                    <td>&nbsp;</td>
                                    <td style="text-align: right;">&nbsp;</td>
                                </tr>
                            @endfor
                        @endif
                    </tbody>
                </table>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <table class="inner-table">
                    <tbody>
                        <tr style="background-color: #a9d08d">
                            <td>Total Ingresos Bruto&nbsp;</td>
                            <td style="text-align: right;">{{ Illuminate\Support\Number::currency($detail->incomeTotal, in: 'USD') }}</td>
                        </tr>
                        <tr style="background-color: #a6a6a6">

                            <td>Total Ingresos Neto</td>
                            <td style="text-align: right;">{{ Illuminate\Support\Number::currency($detail->netSalary, in: 'USD') }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>
            <td colspan="2">
                <table class="inner-table">
                    <tbody>
                        <tr style="background-color: #f3b084">
                            <td>Total Deducciones</td>
                            <td style="text-align: right;">{{ Illuminate\Support\Number::currency($detail->deductionTotal, in: 'USD') }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </tbody>
</table>
