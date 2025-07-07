@php
    $columnSpan = 6 + $incomes->count() + $deductions->count();    
@endphp

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
                <td>{{ $detail->document_number }}</td>
                <td>{{ $detail->rawSalary }}</td>
                @foreach ($incomes->keys() as $income)
                    <td>{{ $detail->incomes->get($income, 0) }}</td>
                @endforeach
                <td>{{ $detail->incomeTotal }}</td>
                @foreach ($deductions->keys() as $deduction)
                    <td>{{ $detail->deductions->get($deduction, 0) }}</td>
                @endforeach
                <td>{{ $detail->deductionTotal }}</td>
                <td>{{ $detail->netSalary }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
