<?php

declare(strict_types=1);

use App\Models\{User, Setting};
use App\Support\Pages\Settings;
use Livewire\Livewire;

use function Pest\Laravel\{get};

describe('Settings Management', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        Setting::factory()->setupCompleted(true)->create();
    });

    test('settings page renders successfully', function () {
        Livewire::actingAs($this->user)
            ->test(Settings::class)
            ->assertSuccessful();
    });

    test('settings page requires authentication', function () {
        get(route('filament.main.pages.settings'))
            ->assertRedirect(route('filament.main.auth.login'));
    });

    test('can view settings form', function () {
        Livewire::actingAs($this->user)
            ->test(Settings::class)
            ->assertSuccessful();
    });

    test('can update email settings', function () {
        Livewire::actingAs($this->user)
            ->test(Settings::class)
            ->set('emailFormData.username', 'test@example.com')
            ->call('submitEmailSettings')
            ->assertHasNoErrors();
    });

    test('can set email form data', function () {
        Livewire::actingAs($this->user)
            ->test(Settings::class)
            ->set('emailFormData.username', 'test@example.com')
            ->assertSet('emailFormData.username', 'test@example.com');
    });
});
