<?php

declare(strict_types=1);

namespace App\Forms\Components;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;

class PhoneRepeater extends Repeater
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Teléfonos')
            ->addActionLabel('Añadir teléfono')
            ->columnSpanFull()
            ->grid([
                'sm' => 2,
                'md' => 3,
                'xl' => 4,
            ])
            ->schema([
                TextInput::make('type')
                    ->label('Tipo')
                    ->required()
                    ->maxLength(255),
                TextInput::make('number')
                    ->label('Número')
                    ->mask('+1 (999) 999-9999')
                    ->required(),
            ]);
    }
}
