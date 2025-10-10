<?php

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->settings = app(SettingsService::class);

    // Set up test domains
    $this->settings->set('security.allowed_domains', ['testcompany.com', 'example.org']);
    $this->settings->set('security.allowed_emails', ['contractor@gmail.com']);
});

test('user can register with allowed domain email', function () {
    Livewire::test(\App\Livewire\Auth\Register::class)
        ->set('name', 'John Doe')
        ->set('email', 'john@testcompany.com')
        ->set('password', 'MySecure#Pass2024!')
        ->set('password_confirmation', 'MySecure#Pass2024!')
        ->call('register')
        ->assertHasNoErrors();

    expect(User::where('email', 'john@testcompany.com')->exists())->toBeTrue();
});

test('user cannot register with disallowed domain email', function () {
    Livewire::test(\App\Livewire\Auth\Register::class)
        ->set('name', 'John Doe')
        ->set('email', 'john@gmail.com')
        ->set('password', 'MySecure#Pass2024!')
        ->set('password_confirmation', 'MySecure#Pass2024!')
        ->call('register')
        ->assertHasErrors(['email']);

    expect(User::where('email', 'john@gmail.com')->exists())->toBeFalse();
});

test('user can register with individually allowed email', function () {
    Livewire::test(\App\Livewire\Auth\Register::class)
        ->set('name', 'Contractor')
        ->set('email', 'contractor@gmail.com')
        ->set('password', 'MySecure#Pass2024!')
        ->set('password_confirmation', 'MySecure#Pass2024!')
        ->call('register')
        ->assertHasNoErrors();

    expect(User::where('email', 'contractor@gmail.com')->exists())->toBeTrue();
});

test('email validation is case insensitive', function () {
    Livewire::test(\App\Livewire\Auth\Register::class)
        ->set('name', 'John Doe')
        ->set('email', 'JOHN@TESTCOMPANY.COM')
        ->set('password', 'MySecure#Pass2024!')
        ->set('password_confirmation', 'MySecure#Pass2024!')
        ->call('register')
        ->assertHasNoErrors();

    // The email should be normalized to lowercase when stored
    expect(User::where('email', 'john@testcompany.com')->exists())->toBeTrue();
});

test('registration fails when no domains are configured', function () {
    $this->settings->set('security.allowed_domains', []);
    $this->settings->set('security.allowed_emails', []);

    Livewire::test(\App\Livewire\Auth\Register::class)
        ->set('name', 'John Doe')
        ->set('email', 'john@anydomain.com')
        ->set('password', 'MySecure#Pass2024!')
        ->set('password_confirmation', 'MySecure#Pass2024!')
        ->call('register')
        ->assertHasErrors(['email']);
});
