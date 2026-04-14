<?php

declare(strict_types=1);

namespace App\Support\Pages\Filament\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Alignment;

class SetupAction extends Action
{
    public function setUp(): void
    {
        parent::setUp();

        $this
            ->color(Color::Indigo)
            ->modalCancelAction(false)
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->modalCloseButton(false)
            ->extraAttributes([
                'class' => 'hidden'
            ])
            ->modalFooterActionsAlignment(Alignment::Center);
    }

    public function extraAttributes(array|Closure $attributes, bool $merge = true): static
    {
        return parent::extraAttributes($attributes, $merge);
    }
}
