<?php

declare(strict_types=1);

namespace App\Modules\Company\Resources\Payrolls\Tables;

use App\Modules\Company\Resources\Payrolls\Pages\ViewPayroll;
use App\Modules\Payroll\Models\Payroll;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Grouping\Group;
use App\Modules\Payroll\Models\PayrollDetail;

class PayrollsTable
{
    public static function configure(Table $table): Table
    {
        $detailUrlCallback = fn (Payroll $record): string => ViewPayroll::getUrl(['company' => $record->company_id, 'record' => $record]);

        return $table
            ->recordTitleAttribute('period')
            ->recordUrl($detailUrlCallback)
            ->defaultGroup(
                Group::make('type')
                    ->titlePrefixedWithLabel(false)
                    ->getDescriptionFromRecordUsing(fn (Payroll $record): string => $record->type->getDescription()),
            )
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo'),
                TextColumn::make('period')
                    ->label('Periodo')
                    ->date(fn (Payroll $record) => $record->type->isMonthly() ? 'F-Y' : 'd-m-Y'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->successRedirectUrl($detailUrlCallback),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->modalHeading('Borrar Nómina')
                    ->before(function (Payroll $record) {
                        $record->salaryAdjustments()->sync([]);
                        $record->details->each(fn (PayrollDetail $detail) => $detail->salaryAdjustments()->sync([]));
                        $record->details()->delete();
                    }),
            ]);
    }
}
