@php
    $mode ??= 'mail';
    $isPDFPrint = request()->routeIs('filament.main.payrolls.details.show.export.pdf');
@endphp
<table
    style="width: 100%; border-collapse: collapse; margin: 0; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; min-height: 400px;">
    <thead>
        @if ($mode === 'mail')
            <tr style="background-color: #64748b; color: #ffffff;">
                <th style="padding: 12px; font-weight: 600; text-align: center; border: 1px solid #475569; font-size: 14px;"
                    colspan="4">
                    {{ Illuminate\Support\Str::headline($detail?->payroll?->period?->translatedFormat('\a\l d \d\e F \d\e\l Y') ?? 'Período') }}
                </th>
            </tr>
        @endif

        <tr style="background-color: #f1f5f9; color: #334155;">
            <th style="padding: 8px 12px; font-weight: 600; text-align: left; border: 1px solid #e2e8f0; font-size: 12px; text-transform: uppercase;"
                colspan="2">
                Colaborador (a)
            </th>
            <th style="padding: 8px 12px; font-weight: 500; text-align: left; border: 1px solid #e2e8f0; font-size: 14px;"
                colspan="2">
                {{ $detail?->name ?? 'Empleado' }}
            </th>
        </tr>
        @if ($mode === 'mail')
            <tr style="background-color: #f1f5f9; color: #334155;">
                <th style="padding: 8px 12px; font-weight: 600; text-align: left; border: 1px solid #e2e8f0; font-size: 12px; text-transform: uppercase;"
                    colspan="2">
                    Forma de Pago
                </th>
                <th style="padding: 8px 12px; font-weight: 500; text-align: left; border: 1px solid #e2e8f0; font-size: 14px;"
                    colspan="2">
                    {{ $detail?->payroll?->type?->getLabel() ?? 'Pago Mensual' }}
                </th>
            </tr>
        @endif

    </thead>
    <tbody>
        <tr>
            <td style="padding: 0; border: 1px solid #e2e8f0; vertical-align: top;" colspan="2">
                <!-- Ingresos Section -->
                <div
                    style="background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); padding: 4px; border-radius: 6px; margin: 8px;">
                    <div
                        style="background-color: #10b981; color: #ffffff; padding: 8px 12px; font-weight: 600; font-size: 13px; text-transform: uppercase; border-radius: 4px; margin-bottom: 8px; text-align: center;">
                        Ingresos
                    </div>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tbody>
                            <!-- Base Salary -->
                            <tr style="background-color: #ffffff; border-radius: 4px;">
                                <td
                                    style="padding: 8px 12px; font-weight: 500; border-bottom: 1px solid #d1fae5; width: 70%;">
                                    {{ match (true) {
                                        $detail?->payroll?->type?->isMonthly() => 'Pago Mensual',
                                        $detail?->payroll?->period?->day < 16 => 'Primera Quincena',
                                        default => 'Segunda Quincena',
                                    } }}
                                </td>
                                <td
                                    style="padding: 8px 12px; color: #10b981; font-weight: 600; text-align: right; border-bottom: 1px solid #d1fae5; width: 30%;">
                                    {{ Illuminate\Support\Number::dominicanCurrency($detail?->rawSalary ?? 0, in: 'USD') }}
                                </td>
                            </tr>
                            <!-- Income Adjustments -->
                            @if ($detail?->incomes && $detail->incomes->count() > 0)
                                @foreach ($detail->incomes as $incomeAlias => $incomeValue)
                                    <tr style="background-color: #ffffff; border-radius: 4px;">
                                        <td
                                            style="padding: 8px 12px; font-weight: 500; border-bottom: 1px solid #d1fae5; width: 70%;">
                                            {{ $detail->adjustmentNames->get($incomeAlias) ?? 'Ingreso' }}
                                        </td>
                                        <td
                                            style="padding: 8px 12px; color: #10b981; font-weight: 600; text-align: right; border-bottom: 1px solid #d1fae5; width: 30%;">
                                            {{ Illuminate\Support\Number::dominicanCurrency($incomeValue, in: 'USD') }}
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                            <!-- Fill empty rows if needed -->
                            @if ($detail?->deductions && $detail->deductions->count() > ($detail?->incomes?->count() ?? 0))
                                @for ($i = 0; $i < $detail->deductions->count() - ($detail?->incomes?->count() ?? 0) - 1; $i++)
                                    <tr style="background-color: #ffffff;">
                                        <td style="padding: 6px 12px; border-bottom: 1px solid #d1fae5;">&nbsp;</td>
                                        <td style="padding: 6px 12px; border-bottom: 1px solid #d1fae5;">&nbsp;</td>
                                    </tr>
                                @endfor
                            @endif
                        </tbody>
                    </table>
                </div>
            </td>
            <td style="padding: 0; border: 1px solid #e2e8f0; vertical-align: top;" colspan="2">
                <!-- Deducciones Section -->
                <div
                    style="background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); padding: 4px; border-radius: 6px; margin: 8px;">
                    <div
                        style="background-color: #ef4444; color: #ffffff; padding: 8px 12px; font-weight: 600; font-size: 13px; text-transform: uppercase; border-radius: 4px; margin-bottom: 8px; text-align: center;">
                        Deducciones
                    </div>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tbody>
                            @if ($detail?->deductions && $detail->deductions->count() > 0)
                                @foreach ($detail->deductions as $deductionAlias => $deductionValue)
                                    <tr style="background-color: #ffffff;">
                                        <td style="padding: 8px 12px; border-bottom: 1px solid #fee2e2; width: 70%;">
                                            {{ $detail->adjustmentNames->get($deductionAlias) ?? 'Deducción' }}
                                        </td>
                                        <td
                                            style="padding: 8px 12px; color: #ef4444; font-weight: 500; text-align: right; border-bottom: 1px solid #fee2e2; width: 30%;">
                                            {{ Illuminate\Support\Number::dominicanCurrency($deductionValue, in: 'USD') }}
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                            <!-- Fill empty rows if needed -->
                            @if ($detail?->deductions && $detail->deductions->count() < ($detail?->incomes?->count() ?? 0) + 1)
                                @for ($i = 0; $i < ($detail?->incomes?->count() ?? 0) - $detail->deductions->count() + 1; $i++)
                                    <tr style="background-color: #ffffff;">
                                        <td style="padding: 6px 12px; border-bottom: 1px solid #fee2e2;">&nbsp;</td>
                                        <td style="padding: 6px 12px; border-bottom: 1px solid #fee2e2;">&nbsp;</td>
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
            <td style="padding: 0; border: 1px solid #e2e8f0;" colspan="2">
                <div
                    style="margin: 8px; background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border-radius: 6px; padding: 6px;">
                    <!-- Income Totals -->
                    <div
                        style="background-color: #10b981; color: #ffffff; padding: 8px 12px; font-weight: 600; text-align: center; border-radius: 4px; margin-bottom: 6px; font-size: 13px;">
                        Total Ingresos Bruto
                    </div>
                    <div
                        style="background-color: #ffffff; color: #10b981; padding: 6px 12px; font-weight: 700; text-align: center; border-radius: 4px; font-size: 16px; border: 2px solid #10b981;">
                        {{ Illuminate\Support\Number::dominicanCurrency($detail?->incomeTotal ?? 0, in: 'USD') }}
                    </div>
                </div>
            </td>
            <td style="padding: 0; border: 1px solid #e2e8f0;" colspan="2">
                <div
                    style="margin: 8px; background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); border-radius: 6px; padding: 6px;">
                    <!-- Deduction Total -->
                    <div
                        style="background-color: #f59e0b; color: #ffffff; padding: 8px 12px; font-weight: 600; text-align: center; border-radius: 4px; margin-bottom: 6px; font-size: 13px;">
                        Total Deducciones
                    </div>
                    <div
                        style="background-color: #ffffff; color: #f59e0b; padding: 6px 12px; font-weight: 700; text-align: center; border-radius: 4px; font-size: 16px; border: 2px solid #f59e0b;">
                        {{ Illuminate\Support\Number::dominicanCurrency($detail?->deductionTotal ?? 0, in: 'USD') }}
                    </div>
                </div>
            </td>
        </tr>
        <!-- Net Salary - Most Important Section -->
        <tr>
            <td style="padding: 0; border: 1px solid #e2e8f0;" colspan="4">
                <div
                    style="margin: 8px; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-radius: 8px; padding: 12px; border: 3px solid #2563eb;">
                    <div style="text-align: center;">
                        <div
                            style="background: {{ $isPDFPrint ? '#2563eb' : 'linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%)' }}; color: #ffffff; padding: 12px 20px; font-weight: 700; text-align: center; border-radius: 8px; margin-bottom: 10px; font-size: 15px; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.3);">
                            Salario Neto a Recibir
                        </div>
                        <div
                            style="background-color: #ffffff; color: #2563eb; padding: 16px 24px; font-weight: 800; text-align: center; border-radius: 8px; font-size: 24px; border: 3px solid #2563eb; box-shadow: inset 0 2px 4px rgba(37, 99, 235, 0.1); text-shadow: 0 1px 2px rgba(37, 99, 235, 0.1);">
                            {{ Illuminate\Support\Number::dominicanCurrency($detail?->netSalary ?? 0, in: 'USD') }}
                        </div>
                        <div style="margin-top: 8px; color: #64748b; font-size: 12px; font-style: italic;">
                            Monto final después de deducciones
                        </div>
                    </div>
                </div>
            </td>
        </tr>
    </tbody>
</table>
