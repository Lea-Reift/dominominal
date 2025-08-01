@props(['detail', 'mode' => 'email'])

@if($mode === 'email')
    {{-- Modern email styling --}}
    <table style="width: 100%; border-collapse: collapse; font-family: system-ui, -apple-system, sans-serif; margin: 0; padding: 0;">
        {{-- Header Section --}}
        <tr>
            <td style="padding: 0; border: 1px solid #e2e8f0;" colspan="4">
                <div style="margin: 8px; background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: #ffffff; padding: 16px 24px; font-weight: 700; text-align: center; border-radius: 12px; font-size: 16px;">
                    📋 DETALLE DE NÓMINA - {{ $detail->payroll->period->format('F Y') }}
                </div>
            </td>
        </tr>

        {{-- Incomes Section --}}
        @if($detail->incomes && count($detail->incomes) > 0)
        <tr>
            <td style="padding: 0; border: 1px solid #e2e8f0;" colspan="4">
                <div style="margin: 8px; background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); border-radius: 8px; padding: 12px; border: 2px solid #10b981;">
                    <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; padding: 8px 16px; font-weight: 600; text-align: center; border-radius: 6px; margin-bottom: 8px; font-size: 13px;">
                        💰 INGRESOS
                    </div>
                    @foreach($detail->incomes as $income)
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 6px 12px; background-color: #ffffff; margin: 4px 0; border-radius: 4px; border: 1px solid #10b981;">
                        <span style="font-weight: 500; color: #064e3b;">{{ $income['name'] ?? 'Ingreso' }}</span>
                        <span style="font-weight: 700; color: #10b981;">{{ Illuminate\Support\Number::currency($income['amount'] ?? 0, in: 'USD') }}</span>
                    </div>
                    @endforeach
                </div>
            </td>
        </tr>
        @endif

        {{-- Deductions Section --}}
        @if($detail->deductions && count($detail->deductions) > 0)
        <tr>
            <td style="padding: 0; border: 1px solid #e2e8f0;" colspan="4">
                <div style="margin: 8px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 8px; padding: 12px; border: 2px solid #f59e0b;">
                    <div style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #ffffff; padding: 8px 16px; font-weight: 600; text-align: center; border-radius: 6px; margin-bottom: 8px; font-size: 13px;">
                        📉 DEDUCCIONES
                    </div>
                    @foreach($detail->deductions as $deduction)
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 6px 12px; background-color: #ffffff; margin: 4px 0; border-radius: 4px; border: 1px solid #f59e0b;">
                        <span style="font-weight: 500; color: #92400e;">{{ $deduction['name'] ?? 'Deducción' }}</span>
                        <span style="font-weight: 700; color: #f59e0b;">{{ Illuminate\Support\Number::currency($deduction['amount'] ?? 0, in: 'USD') }}</span>
                    </div>
                    @endforeach
                </div>
            </td>
        </tr>
        @endif

        {{-- Totals Section --}}
        <tr>
            <td style="padding: 0; border: 1px solid #e2e8f0;" colspan="2">
                <div style="margin: 8px; background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); padding: 12px; border-radius: 8px; border: 2px solid #10b981;">
                    <div style="text-align: center; font-weight: 600; color: #064e3b; margin-bottom: 4px;">Total Ingresos</div>
                    <div style="text-align: center; font-weight: 800; color: #10b981; font-size: 16px;">{{ Illuminate\Support\Number::currency($detail->totalIncomes ?? 0, in: 'USD') }}</div>
                </div>
            </td>
            <td style="padding: 0; border: 1px solid #e2e8f0;" colspan="2">
                <div style="margin: 8px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); padding: 12px; border-radius: 8px; border: 2px solid #f59e0b;">
                    <div style="text-align: center; font-weight: 600; color: #92400e; margin-bottom: 4px;">Total Deducciones</div>
                    <div style="text-align: center; font-weight: 800; color: #f59e0b; font-size: 16px;">{{ Illuminate\Support\Number::currency($detail->totalDeductions ?? 0, in: 'USD') }}</div>
                </div>
            </td>
        </tr>

        {{-- Net Salary - Most Important Section --}}
        <tr>
            <td style="padding: 0; border: 1px solid #e2e8f0;" colspan="4">
                <div style="margin: 8px; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-radius: 8px; padding: 12px; border: 3px solid #2563eb;">
                    <div style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: #ffffff; padding: 12px 20px; font-weight: 700; text-align: center; border-radius: 8px; margin-bottom: 10px; font-size: 15px; text-transform: uppercase;">
                        💰 Salario Neto a Recibir
                    </div>
                    <div style="background-color: #ffffff; color: #2563eb; padding: 16px 24px; font-weight: 800; text-align: center; border-radius: 8px; font-size: 24px; border: 3px solid #2563eb;">
                        {{ Illuminate\Support\Number::currency($detail->netSalary ?? 0, in: 'USD') }}
                    </div>
                </div>
            </td>
        </tr>
    </table>
@else
    {{-- PDF-like modal styling --}}
    <div class="bg-white p-6 rounded-lg">
        <div class="text-center mb-6">
            <h3 class="text-lg font-bold text-gray-900">DETALLE DE NÓMINA</h3>
            <p class="text-gray-600">{{ $detail->payroll->period->format('F Y') }}</p>
        </div>

        @if($detail->incomes && count($detail->incomes) > 0)
        <div class="mb-6">
            <h4 class="font-semibold text-green-700 mb-3">INGRESOS</h4>
            <div class="space-y-2">
                @foreach($detail->incomes as $income)
                <div class="flex justify-between py-1 border-b border-gray-200">
                    <span>{{ $income['name'] ?? 'Ingreso' }}</span>
                    <span class="font-medium">{{ Illuminate\Support\Number::currency($income['amount'] ?? 0, in: 'USD') }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        @if($detail->deductions && count($detail->deductions) > 0)
        <div class="mb-6">
            <h4 class="font-semibold text-yellow-700 mb-3">DEDUCCIONES</h4>
            <div class="space-y-2">
                @foreach($detail->deductions as $deduction)
                <div class="flex justify-between py-1 border-b border-gray-200">
                    <span>{{ $deduction['name'] ?? 'Deducción' }}</span>
                    <span class="font-medium">{{ Illuminate\Support\Number::currency($deduction['amount'] ?? 0, in: 'USD') }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <div class="grid grid-cols-2 gap-4 mb-6">
            <div class="text-center p-4 bg-green-50 rounded">
                <div class="text-sm text-green-700">Total Ingresos</div>
                <div class="text-lg font-bold text-green-800">{{ Illuminate\Support\Number::currency($detail->totalIncomes ?? 0, in: 'USD') }}</div>
            </div>
            <div class="text-center p-4 bg-yellow-50 rounded">
                <div class="text-sm text-yellow-700">Total Deducciones</div>
                <div class="text-lg font-bold text-yellow-800">{{ Illuminate\Support\Number::currency($detail->totalDeductions ?? 0, in: 'USD') }}</div>
            </div>
        </div>

        <div class="text-center p-6 bg-blue-50 rounded-lg border-2 border-blue-200">
            <div class="text-sm text-blue-700 mb-2">SALARIO NETO</div>
            <div class="text-2xl font-bold text-blue-800">{{ Illuminate\Support\Number::currency($detail->netSalary ?? 0, in: 'USD') }}</div>
        </div>
    </div>
@endif