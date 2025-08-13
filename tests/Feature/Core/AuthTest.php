<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Pages\Dashboard;
use Filament\Pages\Auth\Login;
use Livewire\Livewire;

use function Pest\Laravel\{actingAs, get, assertAuthenticated, assertGuest};

describe('Authentication', function () {
    test('guests are redirected to login', function () {
        assertGuest();
        get(route('filament.main.pages.dashboard'))
            ->assertRedirect(route('filament.main.auth.login'));
    });

    test('authenticated users can access dashboard', function () {
        /** @var User */
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertSuccessful();
    });

    test('user can login with valid credentials', function () {
        /** @var User */
        $user = User::factory()->create();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $user->email,
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        assertAuthenticated();
    });

    test('user cannot login with invalid credentials', function () {
        /** @var User */
        $user = User::factory()->create();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $user->email,
                'password' => 'wrong-password',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        assertGuest();
    });

    test('user can logout', function () {
        /** @var User */
        $user = User::factory()->create();

        actingAs($user)
            ->post(route('filament.main.auth.logout'));

        assertGuest();
    });
});
