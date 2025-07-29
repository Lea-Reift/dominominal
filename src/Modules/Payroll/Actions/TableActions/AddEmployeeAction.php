<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Actions\TableActions;

use App\Concerns\HasEmployeeForm;
use Filament\Tables\Actions\Action;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\PayrollDetail;
use Illuminate\Support\Collection;
use App\Modules\Company\Models\Employee;
use Closure;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Component;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Hidden;
use App\Modules\Payroll\Models\SalaryAdjustment;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Set;
use Livewire\Component as Livewire;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use App\Enums\DocumentTypeEnum;
use Filament\Support\RawJs;
use Filament\Forms\Components\Repeater;
use Filament\Support\Enums\Alignment;
use App\Support\ValueObjects\FullName;
use Illuminate\Support\Number;
use InvalidArgumentException;
use App\Modules\Company\Models\Salary;

class AddEmployeeAction
{
    use HasEmployeeForm;

    protected Action $action;

    protected bool $showaddEmployeesTab = true;

    protected string $formTabsId = 'add-employees-form-tabs';

    protected Tab $addEmployeeTab;
    protected Tab $addEmployeesTab;
    protected Tab $importRawEmployeeTab;

    protected Tabs $tabs;

    public function __construct(
        protected Payroll $record,
    ) {
        $this->addEmployeeTab = $this->createEmployeeTab();
        $this->addEmployeesTab = $this->addEmployeesTab();
        $this->importRawEmployeeTab = $this->importRawEmployeeTab();

        $this->tabs = Tabs::make()
            ->tabs([
                $this->addEmployeesTab,
                $this->addEmployeeTab,
                $this->importRawEmployeeTab
            ])
            ->extraAttributes([
                'x-on:click' => '$wire.set("mountedTableActionsData.0.active_add_employee_form_tab", tab)'
            ]);

        $this->action = Action::make('add_employees')
            ->modalHeading('')
            ->label('Añadir empleados')
            ->slideOver()
            ->beforeFormFilled(fn () => session(['active_add_employee_form_tab' => '-add-employees-tab']))
            ->form([
                $this->tabs,
                Hidden::make('active_add_employee_form_tab')
                    ->afterStateUpdated(fn (string $state) => $this->setActiveTab($state)),
            ])
            ->databaseTransaction()
            ->action(fn (array $data, Action $action) => match ($this->getActiveTab()) {
                '-add-employees-tab' => $this->addEmployeesAction($data, $action),
                '-create-employee-tab' => $this->createEmployeeAction($data, $action),
                '-add-raw-employees-tab' => $this->importRawEmployeesAction($data, $action),
                default => throw new InvalidArgumentException('Accion o pestaña invalida'),
            })
            ->successNotification(
                Notification::make()
                    ->success()
                    ->title('Empleados agregados con éxito')
            );
    }

    public function getAction(): Action
    {
        return $this->action;
    }

    public static function make(Payroll $payroll): Action
    {
        return (new self($payroll))->getAction();
    }

    protected function getActiveTab(): string
    {
        return session('active_add_employee_form_tab', '-add-employees-tab');
    }

    protected function setActiveTab(string $tab): void
    {
        session(['active_add_employee_form_tab' => $tab]);
    }

    protected function addEmployeesTab(): Tab
    {
        return Tab::make('add_employees')
            ->label('Agregar Empleados Existentes')
            ->visible($this->record->company->employees->reject(fn (Employee $employee) => $this->record->employees->contains($employee))->isNotEmpty())
            ->disabled(fn () => $this->getActiveTab() !== '-add-employees-tab')
            ->schema([
                CheckboxList::make('employees')
                    ->hiddenLabel()
                    ->dehydrated(fn (Component $component) => $component->getContainer()->getParentComponent()->isDisabled())
                    ->disabled(fn (Component $component) => $component->getContainer()->getParentComponent()->isDisabled())
                    ->options(
                        fn () => $this->record->company->employees()
                            ->whereNotIn('id', $this->record->employees()->select('employees.id'))
                            ->get()
                            ->pluck('full_name', 'id')
                    )
                    ->bulkToggleable()
                    ->searchable()
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    protected function createEmployeeTab(): Tab
    {
        return Tab::make('create_employee')
            ->label('Crear Empleado')
            ->columns(2)
            ->disabled(fn () => $this->getActiveTab() !== '-create-employee-tab')
            ->schema(fn (Component $component) => $this->fields(enabled: ! $component->isDisabled(), nested: true));
    }

    protected function importRawEmployeeTab(): Tab
    {
        return Tab::make('import_raw_employee')
            ->label('Importar Empleados')
            ->disabled(fn () => $this->getActiveTab() !== '-import-raw-employee-tab')
            ->schema([
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
                    ->dehydrated(fn () => $this->getActiveTab() === '-import-raw-employee-tab')
                    ->schema([
                        $this->employeeRepeater(),
                    ]),
            ]);
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

        return Repeater::make('raw_employees')
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

        $set('raw_employees', $employees);

        $livewire->dispatch('backend-collapse');
    }

    protected function createEmployeeAction(array $data, Action $action): void
    {
        $employee = Employee::query()->create([
            ...$data,
            'company_id' => $this->record->company_id
        ]);

        $employee->salary()->create($data['salary']);

        $payrollDetail = new PayrollDetail(['employee_id' => $employee->id, 'salary_id' => $employee->salary->id, 'payroll_id' => $this->record->id]);
        $payrollDetail->save();

        $adjustmentForDetail = $this->record->salaryAdjustments->mapWithKeys(fn (SalaryAdjustment $payrollAdjustment) => [
            $payrollAdjustment->id => ['custom_value' => null],
        ]);

        $payrollDetail->salaryAdjustments()->sync($adjustmentForDetail);
        $action->sendSuccessNotification();
    }

    protected function addEmployeesAction(array $data, Action $action): void
    {
        $payrollDetails = Employee::query()->whereIn('id', $data['employees'])->with('salary')->get(['id'])
            ->map(fn (Employee $employee) => new PayrollDetail([
                'employee_id' => $employee->id,
                'salary_id' => $employee->salary->id,
            ]));

        /** @var Collection<int, salaryAdjustment> $details */
        $details = $this->record->details()->saveMany($payrollDetails);

        $adjustmentsForDetails = $this->record->salaryAdjustments->mapWithKeys(fn (SalaryAdjustment $payrollAdjustment) => [
            $payrollAdjustment->id => ['custom_value' => null],
        ]);

        $details->each(fn (PayrollDetail $detail) => $detail->salaryAdjustments()->sync($adjustmentsForDetails));
        $action->sendSuccessNotification();
    }

    protected function importRawEmployeesAction(array $data, Action $action): void
    {
        $employees = collect($data['raw_employees']);

        $payrollDetails = collect();

        $employees->each(function (array $data) use ($payrollDetails) {
            $salary = $data['salary'];
            unset($data['salary']);

            /** @var Employee */
            $employee = Employee::query()->firstOrCreate([
                ...$data,
                'company_id' => $this->record->company_id
            ]);

            /** @var Salary */
            $salary = $employee->salary()->firstOrCreate([
                'amount' => $salary,
            ]);

            $payrollDetails->push(new PayrollDetail(['employee_id' => $employee->id, 'salary_id' => $salary->id]));
        });

        /** @var Collection */
        $details = $this->record->details()->saveMany($payrollDetails);

        $adjustmentsForDetails = $this->record->salaryAdjustments->mapWithKeys(fn (SalaryAdjustment $payrollAdjustment) => [
            $payrollAdjustment->id => ['custom_value' => null],
        ]);

        $details->each(fn (PayrollDetail $detail) => $detail->salaryAdjustments()->sync($adjustmentsForDetails));
        $action->sendSuccessNotification();
    }
}
