<?php

use App\Models\User;
use Livewire\Livewire;
use App\Models\AppSetting;
use App\Livewire\Auth\Register;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function allowDomain(string $domain): void
{
    AppSetting::updateOrCreate(['key' => 'security.allowed_emails'], ['value' => []]);
    AppSetting::updateOrCreate(['key' => 'security.allowed_domains'], ['value' => [$domain]]);
}

it('renders the registration screen', function () {
    $this->get('/register')->assertOk();
});

it('resolves the business email rule through method injection', function () {
    allowDomain('caecusblinds.co.uk');

    Livewire::test(Register::class)
        ->set('name', 'Ben')
        ->set('email', 'someone@caecusblinds.co.uk')
        ->set('password', 'Sup3rSecret!Pass')
        ->set('password_confirmation', 'Sup3rSecret!Pass')
        ->call('register')
        ->assertHasNoErrors();

    expect(User::where('email', 'someone@caecusblinds.co.uk')->exists())->toBeTrue();
});

it('rejects an email outside the allowed domains', function () {
    allowDomain('caecusblinds.co.uk');

    Livewire::test(Register::class)
        ->set('name', 'Nobody')
        ->set('email', 'nobody@gmail.com')
        ->set('password', 'Sup3rSecret!Pass')
        ->set('password_confirmation', 'Sup3rSecret!Pass')
        ->call('register')
        ->assertHasErrors('email');

    expect(User::where('email', 'nobody@gmail.com')->exists())->toBeFalse();
});
