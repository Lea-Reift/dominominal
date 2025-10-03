<?php

declare(strict_types=1);

namespace App\Modules\Company\Resources\Companies\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use App\Concerns\HasEmployeeForm;
use App\Tables\Columns\DocumentColumn;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Wizard\Step;
use App\Modules\Payroll\Models\SalaryAdjustment;
use App\Support\ValueObjects\ManualVoucherDisplay;
use Filament\Forms\Components\ViewField;
use Illuminate\Support\Facades\Mail;
use App\Mail\ManualPayrollVoucher;
use Filament\Support\Icons\Heroicon;
use Filament\Notifications\Notification;

class EmployeesRelationManager extends RelationManager
{
    use HasEmployeeForm;
    protected static string $relationship = 'employees';

    protected static ?string $title = 'Empleados';

    protected static ?string $modelLabel = 'empleado';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components($this->fields(enabled: true));
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Empleado'),
                DocumentColumn::make('document_type')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->limit(25)
                    ->tooltip(fn (TextColumn $column) => strlen($state = strval($column->getState())) <= $column->getCharacterLimit() ? null : $state),
            ])
            ->headerActions([
                CreateAction::make(),
                Action::make('customVoucher')
                    ->label('Comprobante Manual')
                    ->icon(Heroicon::DocumentText)
                    ->modalHeading('Generar Comprobante de Pago Manual')
                    ->modalWidth('4xl')
                    ->steps([
                        Step::make('Datos del Comprobante')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nombre del Empleado')
                                    ->required(),
                                TextInput::make('document_number')
                                    ->label('Número de Documento'),
                                TextInput::make('salary')
                                    ->label('Salario Base')
                                    ->required()
                                    ->numeric()
                                    ->prefix('$'),
                                DatePicker::make('period')
                                    ->label('Período')
                                    ->required()
                                    ->default(now()),
                                Repeater::make('adjustments')
                                    ->label('Ajustes Salariales')
                                    ->schema([
                                        Select::make('adjustment_id')
                                            ->label('Ajuste')
                                            ->required()
                                            ->searchable()
                                            ->options(
                                                SalaryAdjustment::query()
                                                    ->orderBy('type')
                                                    ->orderBy('name')
                                                    ->get()
                                                    ->pluck('name', 'id')
                                            ),
                                        TextInput::make('value')
                                            ->label('Valor')
                                            ->required()
                                            ->numeric()
                                            ->prefix('$'),
                                    ])
                                    ->columns(2)
                                    ->defaultItems(0)
                                    ->addActionLabel('Agregar Ajuste'),
                            ]),
                        Step::make('Vista Previa y Envío')
                            ->schema(function ($get, RelationManager $livewire) {
                                $adjustments = collect($get('adjustments') ?? [])
                                    ->filter(fn ($adjustment) => isset($adjustment['adjustment_id']) && isset($adjustment['value']))
                                    ->map(function ($adjustment) {
                                        $salaryAdjustment = SalaryAdjustment::find($adjustment['adjustment_id']);

                                        return [
                                            'name' => $salaryAdjustment->name,
                                            'alias' => $salaryAdjustment->parser_alias,
                                            'value' => (float) $adjustment['value'],
                                            'type' => $salaryAdjustment->type->getKey(),
                                        ];
                                    })
                                    ->toArray();

                                $voucher = new ManualVoucherDisplay(
                                    name: $get('name') ?? '',
                                    documentNumber: $get('document_number') ?? '',
                                    salary: (float) ($get('salary') ?? 0),
                                    period: \Carbon\Carbon::parse($get('period') ?? now())->translatedFormat('d \d\e F \d\e\l Y'),
                                    adjustments: $adjustments,
                                    companyName: $livewire->getOwnerRecord()->name
                                );

                                cache()->put('manual_voucher_send', $voucher, now()->addMinutes(10));

                                return [
                                    ViewField::make('voucher_preview')
                                        ->view('components.manual-payment-voucher-table', [
                                            'detail' => $voucher,
                                            'mode' => 'preview'
                                        ]),
                                    TextInput::make('email')
                                        ->label('Correo Electrónico')
                                        ->email()
                                        ->required()
                                        ->helperText('El comprobante será enviado a este correo'),
                                ];
                            }),
                    ])
                    ->action(function (array $data) {
                        $voucher = cache()->get('manual_voucher_send');

                        if (! $voucher) {
                            Notification::make()
                                ->title('Error')
                                ->danger()
                                ->body('La vista previa ha expirado. Por favor, genere el comprobante nuevamente.')
                                ->send();

                            return;
                        }

                        defer(
                            fn () =>
                            Mail::to($data['email'])->send(new ManualPayrollVoucher($voucher))
                        );

                        throw new Halt();

                        cache()->forget('manual_voucher_send');


                        Notification::make()
                            ->title('Comprobante enviado')
                            ->success()
                            ->body('El comprobante ha sido enviado a ' . $data['email'])
                            ->send();
                    })
                    ->modalSubmitActionLabel('Enviar por Correo'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
