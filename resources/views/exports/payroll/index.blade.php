@php
    use Illuminate\Support\Number;
    $columnSpan = 6 + $incomes->count() + $deductions->count();
    $is_pdf_export ??= false;
@endphp

<style>
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }

    thead th {
        background-color: #ddd;
        border: 1px solid black;
        padding: 6px;
        text-align: center;
        vertical-align: middle;
    }

    tbody td {
        border: 1px solid black;
        padding: 6px;
        vertical-align: middle;
    }

    /* Columnas de texto alineadas a la izquierda */
    td:first-child,
    td:nth-child(2),
    th:first-child,
    th:nth-child(2) {
        text-align: left;
    }

    /* Columnas numéricas alineadas a la derecha */
    td:not(:first-child):not(:nth-child(2)) {
        text-align: right;
    }

    /* Encabezado superior (nombre empresa + fecha) centrado y en negrita */
    thead tr:nth-child(1) th,
    thead tr:nth-child(2) th,
    thead tr:nth-child(3) th {
        border: none;
        font-weight: bold;
        text-align: center;
        font-size: 14px;
        padding: 8px 0;
    }

    thead tr:nth-child(1) th {
        font-size: 16px;
    }

    /* Evitar bordes duplicados con colspan */
    thead tr:nth-child(-n+3) th[colspan] {
        border: none;
    }
</style>

<table>
    <thead>
        <tr>
            <th colspan="{{ $columnSpan }}"><b>{{ $company_name }}</b></th>
        </tr>
        <tr>
            <th colspan="{{ $columnSpan }}">Nómina Administrativa</th>
        </tr>
        <tr>
            <th colspan="{{ $columnSpan }}">Al {{ $date_string }}</th>
        </tr>
        <tr>
            <th>Colaboradores</th>
            <th>Cédula</th>
            <th>Sueldo Bruto</th>
            @foreach ($incomes as $income)
                <th>{{ $income->name }}</th>
            @endforeach
            <th>Total Ingresos</th>
            @foreach ($deductions as $deduction)
                <th>{{ $deduction->name }}</th>
            @endforeach
            <th>Total Descuentos</th>
            <th>Sueldo Neto</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($details as $detail)
            <tr>
                <td>{{ $detail->name }}</td>
                <td>{{ $detail->documentNumber }}</td>
                <td>{{ $is_pdf_export ? Number::currency($detail->rawSalary) : $detail->rawSalary }}</td>
                @foreach ($incomes->keys() as $income)
                    <td>{{ $is_pdf_export ? Number::currency($detail->incomes->get($income, 0)) : $detail->incomes->get($income, 0) }}
                    </td>
                @endforeach
                <td>{{ $is_pdf_export ? Number::currency($detail->incomeTotal) : $detail->incomeTotal }}</td>
                @foreach ($deductions->keys() as $deduction)
                    <td>{{ $is_pdf_export ? Number::currency($detail->deductions->get($deduction, 0)) : $detail->deductions->get($deduction, 0) }}
                    </td>
                @endforeach
                <td>{{ $is_pdf_export ? Number::currency($detail->deductionTotal) : $detail->deductionTotal }}</td>
                <td>{{ $is_pdf_export ? Number::currency($detail->netSalary) : $detail->netSalary }}</td>
            </tr>
        @endforeach
        @if ($is_pdf_export)
            <tr>
                <td colspan="2"><strong>Totales</strong></td>
                <td><strong>{{ $is_pdf_export ? Number::currency($totals->rawSalary) : $totals->rawSalary }}</strong>
                </td>

                @foreach ($incomes->keys() as $income)
                    <td><strong>{{ $is_pdf_export ? Number::currency($totals->incomes->get($income, 0)) : $totals->incomes->get($income, 0) }}</strong>
                    </td>
                @endforeach

                <td><strong>{{ $is_pdf_export ? Number::currency($totals->incomesTotal) : $totals->incomesTotal }}</strong>
                </td>

                @foreach ($deductions->keys() as $deduction)
                    <td><strong>{{ $is_pdf_export ? Number::currency($totals->deductions->get($deduction, 0)) : $totals->deductions->get($deduction, 0) }}</strong>
                    </td>
                @endforeach

                <td><strong>{{ $is_pdf_export ? Number::currency($totals->deductionsTotal) : $totals->deductionsTotal }}</strong>
                </td>
                <td><strong>{{ $is_pdf_export ? Number::currency($totals->netSalary) : $totals->netSalary }}</strong>
                </td>
            </tr>
        @endif
    </tbody>
</table>
