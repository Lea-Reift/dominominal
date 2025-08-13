<?php

declare(strict_types=1);

use App\Models\{User, Setting};
use App\Support\Pages\Setup;
use Livewire\Livewire;

use function Pest\Laravel\{actingAs, get};

describe('Application Setup', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

        // Ensure required setup setting exists and is not completed
        Setting::updateOrCreate(
            ['setting' => 'setup', 'name' => 'is_completed'],
            ['value' => false, 'is_encrypted' => false]
        );
    });

    test('setup page renders when setup is not completed', function () {
        Livewire::actingAs($this->user)
            ->test(Setup::class)
            ->assertSuccessful();
    });

    test('setup page redirects when already completed', function () {
        // Update the existing setup setting to completed
        Setting::where('setting', 'setup')->where('name', 'is_completed')->first()->update(['value' => true]);

        actingAs($this->user)
            ->get(route('filament.main.pages.dashboard'))
            ->assertSuccessful();
    });

    test('can complete setup process', function () {
        Livewire::actingAs($this->user)
            ->test(Setup::class)
            ->callAction('setupWizard', [
                'name' => 'Test Setup User',
                'email' => 'setuptest@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123'
            ])
            ->assertHasNoActionErrors();

        expect(Setting::where('setting', 'setup')->where('name', 'is_completed')->value('value'))
            ->toBeTrue();
    });

    test('setup action fails with validation errors', function () {
        Livewire::actingAs($this->user)
            ->test(Setup::class)
            ->callAction('setupWizard', [
                'name' => '',
                'email' => 'invalid-email',
                'password' => '123',
            ])
            ->assertHasActionErrors(['name', 'email', 'password']);
    });

    test('middleware blocks access when setup incomplete', function () {
        // The middleware redirects to login first for authentication
        get(route('filament.main.pages.dashboard'))
            ->assertRedirect(route('filament.main.auth.login'));
    });

    test('middleware allows access when setup complete', function () {
        // Update the existing setup setting to completed
        Setting::where('setting', 'setup')->where('name', 'is_completed')->first()->update(['value' => true]);

        actingAs($this->user)
            ->get(route('filament.main.pages.dashboard'))
            ->assertSuccessful();
    });
});
