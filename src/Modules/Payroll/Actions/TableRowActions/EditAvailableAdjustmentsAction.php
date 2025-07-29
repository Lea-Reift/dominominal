<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Actions\TableRowActions;

use App\Modules\Payroll\Models\Payroll;
use Filament\Tables\Actions\Action;
use Filament\Support\Enums\MaxWidth;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use App\Modules\Payroll\Models\PayrollDetail;

class EditAvailableAdjustmentsAction
{
    protected Action $action;

    public function __construct(
        protected Payroll $record,
    ) {
        $this->action = Action::make('edit_available_adjustments')
            ->disabled($this->record->type->isMonthly())
            ->hidden($this->record->type->isMonthly())
            ->label('Editar ajustes salariales')
            ->icon('heroicon-m-pencil-square')
            ->color('success')
            ->modalWidth(MaxWidth::Large)
            ->form([
                CheckboxList::make('available_salary_adjustments')
                    ->hiddenLabel()
                    ->options($this->record->salaryAdjustments->pluck('name', 'id'))
                    ->default(fn (PayrollDetail $record) => $record->salaryAdjustments->pluck('id')->toArray())
                    ->columns(2)
                    ->bulkToggleable()
                    ->disableOptionWhen(function (PayrollDetail $record, string $value) {
                        $allAdjustments = $this->record->salaryAdjustments->pluck('name', 'id');
                        $missingInComplementary = $allAdjustments->except($record->complementaryDetail->salaryAdjustments->pluck('id'))->keys();
                        return $missingInComplementary->contains($value);
                    })
            ])
            ->databaseTransaction()
            ->action(function (array $data, PayrollDetail $record, Action $action) {
                $record->salaryAdjustments()->sync($data['available_salary_adjustments']);
                return $action->sendSuccessNotification();
            })
            ->successNotification(
                Notification::make('edit_available_adjustments')
                    ->title('Datos guardados')
                    ->success()
            );
    }

    public static function make(Payroll $payroll): Action
    {
        return (new self($payroll))->getAction();
    }

    protected function getAction(): Action
    {
        return $this->action;
    }
}
