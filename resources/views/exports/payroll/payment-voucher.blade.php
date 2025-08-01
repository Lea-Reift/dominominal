<style>
    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        color: #1e293b;
        line-height: 1.6;
        font-size: 12px;
        margin: 0;
        padding: 10px;
    }

    .container-table {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        overflow: hidden;
    }

    .container-table td {
        vertical-align: top;
        border: 1px solid #e2e8f0;
        padding: 0;
    }

    .container-table th {
        border: 1px solid #e2e8f0;
        padding: 8px 12px;
        font-weight: 600;
        text-align: left;
    }

    .header-company {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        color: #ffffff;
        font-size: 16px;
        font-weight: 700;
        text-align: center;
        padding: 12px;
    }

    .header-date {
        background-color: #64748b;
        color: #ffffff;
        font-size: 12px;
        font-weight: 600;
        text-align: left;
        padding: 8px 12px;
    }

    .header-info {
        background-color: #f1f5f9;
        color: #334155;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        padding: 6px 12px;
    }

    .header-value {
        background-color: #f1f5f9;
        color: #334155;
        font-size: 12px;
        font-weight: 500;
        padding: 6px 12px;
    }

    .inner-table {
        width: 100%;
        border-collapse: collapse;
        margin: 0;
    }

    .inner-table td {
        padding: 6px 8px;
        border-bottom: 1px solid #e2e8f0;
        font-size: 11px;
    }

    .inner-table th {
        padding: 6px 8px;
        font-weight: 600;
        font-size: 11px;
        text-transform: uppercase;
        text-align: center;
        color: #ffffff;
    }

    .incomes-section {
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        padding: 4px;
        border-radius: 6px;
        margin: 6px;
    }

    .incomes-header {
        background-color: #10b981;
        color: #ffffff;
        border-radius: 4px;
        margin-bottom: 6px;
    }

    .incomes-row {
        background-color: #ffffff;
        color: #064e3b;
    }

    .incomes-value {
        color: #10b981;
        font-weight: 600;
        text-align: right;
    }

    .deductions-section {
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        padding: 4px;
        border-radius: 6px;
        margin: 6px;
    }

    .deductions-header {
        background-color: #ef4444;
        color: #ffffff;
        border-radius: 4px;
        margin-bottom: 6px;
    }

    .deductions-row {
        background-color: #ffffff;
        color: #7f1d1d;
    }

    .deductions-value {
        color: #ef4444;
        font-weight: 500;
        text-align: right;
    }

    .totals-income {
        background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
        border-radius: 6px;
        padding: 4px;
        margin: 6px;
    }

    .totals-income-header {
        background-color: #10b981;
        color: #ffffff;
        text-align: center;
        border-radius: 4px;
        margin-bottom: 4px;
        font-size: 11px;
        padding: 6px 8px;
    }

    .totals-income-value {
        background-color: #ffffff;
        color: #10b981;
        font-weight: 700;
        text-align: center;
        border-radius: 4px;
        font-size: 14px;
        border: 2px solid #10b981;
        padding: 6px 8px;
    }

    .totals-deduction {
        background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
        border-radius: 6px;
        padding: 4px;
        margin: 6px;
    }

    .totals-deduction-header {
        background-color: #f59e0b;
        color: #ffffff;
        text-align: center;
        border-radius: 4px;
        margin-bottom: 4px;
        font-size: 11px;
        padding: 6px 8px;
    }

    .totals-deduction-value {
        background-color: #ffffff;
        color: #f59e0b;
        font-weight: 700;
        text-align: center;
        border-radius: 4px;
        font-size: 14px;
        border: 2px solid #f59e0b;
        padding: 6px 8px;
    }

    .net-salary {
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        border-radius: 8px;
        padding: 8px;
        margin: 6px;
        border: 3px solid #2563eb;
        text-align: center;
    }

    .net-salary-header {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        color: #ffffff;
        padding: 8px 12px;
        font-weight: 700;
        text-align: center;
        border-radius: 6px;
        margin-bottom: 6px;
        font-size: 12px;
        text-transform: uppercase;
    }

    .net-salary-value {
        background-color: #ffffff;
        color: #2563eb;
        padding: 10px 16px;
        font-weight: 800;
        text-align: center;
        border-radius: 6px;
        font-size: 18px;
        border: 3px solid #2563eb;
    }

    .net-salary-subtitle {
        margin-top: 6px;
        color: #64748b;
        font-size: 10px;
        font-style: italic;
    }
</style>

<table class="container-table">
    <thead>
        <tr>
            <th colspan="4" class="header-company">
                🏢 {{ $detail->company->name }}
            </th>
        </tr>
        <tr>
            <th colspan="4" class="header-date">
                📋 {{ Str::headline($detail->payroll->period->translatedFormat('d \d\e F \d\e\l Y')) }}
            </th>
        </tr>
        <tr>
            <th colspan="2" class="header-info">👤 Colaborador (a)</th>
            <th colspan="2" class="header-value">{{ $detail->name }}</th>
        </tr>
        <tr>
            <th colspan="2" class="header-info">🕰️ Tipo de Pago</th>
            <th colspan="2" class="header-value">
                {{
                    match (true) {
                        $detail->payroll->type->isMonthly() => "Pago Mensual",
                        $detail->payroll->period->day < 16 => "Primera Quincena",
                        default => "Segunda Quincena"
                    }
                }}
            </th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td colspan="2">
                <!-- Ingresos Section -->
                <div class="incomes-section">
                    <table class="inner-table">
                        <thead>
                            <tr>
                                <th colspan="2" class="incomes-header">💰 Ingresos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Base Salary -->
                            <tr class="incomes-row">
                                <td>
                                    {{
                                        match (true) {
                                            $detail->payroll->type->isMonthly() => "Pago Mensual",
                                            $detail->payroll->period->day < 16 => "Primera Quincena",
                                            default => "Segunda Quincena"
                                        }
                                    }}
                                </td>
                                <td class="incomes-value">{{ Illuminate\Support\Number::dominicanCurrency($detail->rawSalary, in: 'USD') }}</td>
                            </tr>
                            <!-- Income Adjustments -->
                            @foreach ($detail->incomes as $incomeName => $incomeValue)
                                <tr class="incomes-row">
                                    <td>{{ $detail->adjustmentNames->get($incomeName) }}</td>
                                    <td class="incomes-value">{{ Illuminate\Support\Number::dominicanCurrency($incomeValue, in: 'USD') }}</td>
                                </tr>
                            @endforeach
                            <!-- Fill empty rows if needed -->
                            @if ($detail->deductions->count() > $detail->incomes->count())
                                @for ($i = 0; $i < ($detail->deductions->count() - $detail->incomes->count()) - 1; $i++)
                                    <tr class="incomes-row">
                                        <td>&nbsp;</td>
                                        <td>&nbsp;</td>
                                    </tr>
                                @endfor
                            @endif
                        </tbody>
                    </table>
                </div>
            </td>
            <td colspan="2">
                <!-- Deducciones Section -->
                <div class="deductions-section">
                    <table class="inner-table">
                        <thead>
                            <tr>
                                <th colspan="2" class="deductions-header">📉 Deducciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($detail->deductions as $deductionName => $deductionValue)
                                <tr class="deductions-row">
                                    <td>{{ $detail->adjustmentNames->get($deductionName) }}</td>
                                    <td class="deductions-value">{{ Illuminate\Support\Number::dominicanCurrency($deductionValue, in: 'USD') }}</td>
                                </tr>
                            @endforeach
                            <!-- Fill empty rows if needed -->
                            @if ($detail->deductions->count() < ($detail->incomes->count() + 1))
                                @for ($i = 0; $i < ($detail->incomes->count() - $detail->deductions->count()) + 1; $i++)
                                    <tr class="deductions-row">
                                        <td>&nbsp;</td>
                                        <td>&nbsp;</td>
                                    </tr>
                                @endfor
                            @endif
                        </tbody>
                    </table>
                </div>
            </td>
        </tr>
        <!-- Totals Section -->
        <tr>
            <td colspan="2">
                <div class="totals-income">
                    <div class="totals-income-header">💵 Total Ingresos Bruto</div>
                    <div class="totals-income-value">{{ Illuminate\Support\Number::dominicanCurrency($detail->incomeTotal, in: 'USD') }}</div>
                </div>
            </td>
            <td colspan="2">
                <div class="totals-deduction">
                    <div class="totals-deduction-header">📊 Total Deducciones</div>
                    <div class="totals-deduction-value">{{ Illuminate\Support\Number::dominicanCurrency($detail->deductionTotal, in: 'USD') }}</div>
                </div>
            </td>
        </tr>
        <!-- Net Salary Section -->
        <tr>
            <td colspan="4">
                <div class="net-salary">
                    <div class="net-salary-header">💰 Salario Neto a Recibir</div>
                    <div class="net-salary-value">{{ Illuminate\Support\Number::dominicanCurrency($detail->netSalary, in: 'USD') }}</div>
                    <div class="net-salary-subtitle">Monto final después de deducciones</div>
                </div>
            </td>
        </tr>
    </tbody>
</table>
