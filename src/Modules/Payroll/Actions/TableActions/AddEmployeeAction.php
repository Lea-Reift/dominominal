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

class AddEmployeeAction
{
    use HasEmployeeForm;

    protected Action $action;

    protected bool $showImportEmployeeTab = true;

    protected string $formTabsId = 'add-employees-form-tabs';

    protected Tab $addEmployeeTab;
    protected Tab $importEmployeeTab;
    protected Tabs $tabs;

    public function __construct(
        protected Payroll $record,
    ) {
        $this->addEmployeeTab = $this->addEmployeeTab();
        $this->importEmployeeTab = $this->importEmployeeTab();

        $this->tabs = Tabs::make()
            ->tabs([
                $this->importEmployeeTab,
                $this->addEmployeeTab,
            ])
            ->extraAttributes([
                'x-on:click' => '$wire.set("mountedTableActionsData.0.active_add_employee_form_tab", tab)'
            ]);

        $this->action = Action::make('add_employees')
            ->modalHeading('')
            ->label('Añadir empleados')
            ->slideOver()
            ->form([
                $this->tabs,
                Hidden::make('active_add_employee_form_tab')
                    ->afterStateUpdated(fn (string $state) => $this->setActiveTab($state)),
            ])
            ->databaseTransaction()
            ->action(Closure::fromCallable([$this, 'importEmployeeAction']))
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
        return session('active_add_employee_form_tab', '-import-employee-tab');
    }

    protected function setActiveTab(string $tab): void
    {
        session(['active_add_employee_form_tab' => $tab]);
    }

    protected function importEmployeeTab(): Tab
    {
        return Tab::make('import_employee')
            ->label('Importar Empleados')
            ->visible($this->record->company->employees->reject(fn (Employee $employee) => $this->record->employees->contains($employee))->isNotEmpty())
            ->disabled(fn () => $this->getActiveTab() !== '-import-employee-tab')
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

    protected function addEmployeeTab(): Tab
    {
        return Tab::make('add_employee')
            ->label('Crear Empleado')
            ->columns(2)
            ->disabled(fn () => $this->getActiveTab() !== '-add-employee-tab')
            ->schema(fn (Component $component) => $this->fields(enabled: ! $component->isDisabled(), nested: true));
    }

    protected function importEmployeeAction(array $data, Action $action): void
    {
        $isRegisteringEmployee = empty($data['employees']);
        $payrollDetails = Collection::make()
            ->unless(
                $isRegisteringEmployee,
                fn (Collection $payrollDetails) => $payrollDetails
                    ->union(Employee::query()->whereIn('id', $data['employees'])->with('salary')->get(['id'])
                        ->map(fn (Employee $employee) => new PayrollDetail([
                            'employee_id' => $employee->id,
                            'salary_id' => $employee->salary->id,
                        ])))
            )
            ->when(
                $isRegisteringEmployee,
                function (Collection $payrollDetails) use ($data) {
                    $employee = Employee::query()->create([
                        ...$data,
                        'company_id' => $this->record->company_id
                    ]);

                    $employee->salary()->create($data['salary']);
                    $payrollDetails->push(new PayrollDetail(['employee_id' => $employee->id, 'salary_id' => $employee->salary->id]));
                }
            );

        /** @var Collection<int, salaryAdjustment> $details */
        $details = $this->record->details()->saveMany($payrollDetails);

        $adjustmentsForDetails = $this->record->salaryAdjustments->mapWithKeys(fn (SalaryAdjustment $payrollAdjustment) => [
            $payrollAdjustment->id => ['custom_value' => null],
        ]);

        $details->each(fn (PayrollDetail $detail) => $detail->salaryAdjustments()->sync($adjustmentsForDetails));
        $action->sendSuccessNotification();
    }
}
