<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SystemVersionWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Versión Actual', system_version())
        ];
    }

    public function getSectionContentComponent(): Component
    {
        #ToDo: Add Check Updates button
        return Section::make()
            ->description($this->getDescription())
            ->schema($this->getCachedStats())
            ->columns($this->getColumns())
            ->contained(false)
            ->gridContainer();
    }
}
