<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Actions\TableActions;

use App\Enums\DocumentTypeEnum;
use App\Modules\Payroll\Models\Payroll;
use App\Support\ValueObjects\FullName;
use Closure;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;
use Filament\Support\RawJs;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Number;
use Livewire\Component as Livewire;
use App\Modules\Payroll\Models\PayrollDetail;
use App\Modules\Company\Models\Employee;
use App\Modules\Payroll\Models\SalaryAdjustment;

class ImportRawEmployeeAction
{
    protected Action $action;

    public function __construct(
        protected Payroll $payroll,
    ) {
        $this->action = Action::make('import_employees')
            ->label('Importar empleados')
            ->form($this->modalForm())
            ->databaseTransaction()
            ->action(Closure::fromCallable([$this, 'modalAction']))
            ->successNotification(
                Notification::make()
                    ->success()
                    ->title('Empleados agregados con éxito')
            )
            ->slideOver();
    }

    public static function make(Payroll $payroll): Action
    {
        return (new self($payroll))->getAction();
    }

    protected function modalForm(): array
    {
        return [
            Section::make([])
                ->heading('Importar por texto')
                ->schema([
                    Textarea::make('employees_text')
                        ->helperText(
                            'Los registros deben tener el siguiente formato, separados por (;), tabulaciones (\t) o comas (,): NOMBRES;APELLIDOS;CEDULA;CARGO;SALARIO'
                        )
                        ->dehydrated(false)
                        ->hiddenLabel(true)
                        ->live()
                        ->afterStateUpdated(Closure::fromCallable([$this, 'parseEmployeeRows']))
                        ->rows(10)
                ])
                ->extraAttributes([
                    'x-on:backend-collapse.window' => 'isCollapsed = true',
                ])
                ->collapsible(),
            Section::make([])
                ->heading('Generar empleados')
                ->schema([
                    $this->employeeRepeater(),
                ]),
        ];
    }

    public function getAction(): Action
    {
        return $this->action;
    }

    public function employeeRepeater(): Repeater
    {
        $fields = collect([
            TextInput::make('name')
                ->label('Nombres')
                ->required()
                ->maxLength(255),
            TextInput::make('surname')
                ->label('Apellidos')
                ->required()
                ->maxLength(255),
            Select::make('document_type')
                ->label('Tipo de documento')
                ->options(DocumentTypeEnum::class)
                ->required(true)
                ->native(false)
                ->placeholder(null),
            TextInput::make('document_number')
                ->label('Número de documento')
                ->required(true)
                ->maxLength(255),
            TextInput::make('job_title')
                ->label('Cargo')
                ->maxLength(255),
            TextInput::make('salary')
                ->mask(RawJs::make('$money($input)'))
                ->stripCharacters(',')
                ->numeric()
                ->required()
                ->inputMode('decimal')
                ->minValue(0)
                ->label('Salario'),
        ]);

        return Repeater::make('employees')
            ->hiddenLabel()
            ->addActionLabel('Agregar empleado')
            ->columns(2)
            ->schema($fields->toArray())
            ->live()
            ->addActionAlignment(Alignment::End)
            ->itemLabel(fn (array $state): string => empty($state['name']) ? 'Nuevo Empleado' : collect($state)->filter()->only(['name', 'surname'])->join(' '))
            ->collapsed()
            ->reorderable(false)
            ->defaultItems(0);
    }

    protected function parseEmployeeRows(?string $state, Set $set, Textarea $component, Livewire $livewire): void
    {
        if ($state === null) {
            return;
        }

        $separator = match (true) {
            str_contains($state, "\t") => "\t",
            str_contains($state, ';') => ';',
            default => ','
        };

        $keys = ['name', 'document_number', 'job_title', 'salary'];

        try {
            $employees = str($state)->explode("\n")
                ->map(fn (string $line) => array_combine($keys, str_getcsv($line, $separator)))
                ->map(fn (array $employee) => [
                    ...$employee,
                    ...FullName::fromFullNameString($employee['name'])->toArray(),
                    'salary' => Number::format(str($employee['salary'])->trim()->remove(',')->toFloat(), 2),
                    'document_type' => DocumentTypeEnum::IDENTIFICATION->value,
                ])
                ->toArray();
        } catch (\Throwable) {
            Notification::make('raw_import_failure')
                ->title('No se pudieron generar los registros')
                ->body('El formato del texto enviado es incorrecto.')
                ->danger()
                ->send();

            return;
        }

        $component->state(null);

        $set('employees', $employees);

        $livewire->dispatch('backend-collapse');
    }

    protected function modalAction(array $data, Action $action): void
    {
        $employees = collect($data['employees']);

        $payrollDetails = collect();

        $employees->each(function (array $data) use ($payrollDetails) {
            $salary = $data['salary'];
            unset($data['salary']);

            $employee = Employee::query()->firstOrCreate([
                ...$data,
                'company_id' => $this->payroll->company_id
            ]);

            $salary = $employee->salary()->create([
                'amount' => $salary,
            ]);

            $payrollDetails->push(new PayrollDetail(['employee_id' => $employee->id, 'salary_id' => $salary->id]));
        });

        $details = $this->payroll->details()->saveMany($payrollDetails);

        $adjustmentsForDetails = $this->payroll->salaryAdjustments->mapWithKeys(fn (SalaryAdjustment $payrollAdjustment) => [
            $payrollAdjustment->id => ['custom_value' => null],
        ]);

        $details->each(fn (PayrollDetail $detail) => $detail->salaryAdjustments()->sync($adjustmentsForDetails));
        $action->sendSuccessNotification();
    }
}
