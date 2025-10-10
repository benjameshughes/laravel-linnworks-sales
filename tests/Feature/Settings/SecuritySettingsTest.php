<?php

use App\Livewire\Settings\SecuritySettings;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->settings = app(SettingsService::class);
});

test('admin can view security settings', function () {
    $this->actingAs($this->admin)
        ->get(route('settings.security'))
        ->assertOk()
        ->assertSeeLivewire(SecuritySettings::class);
});

test('non-admin cannot view security settings', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get(route('settings.security'))
        ->assertForbidden();
});

test('can add allowed domain', function () {
    Livewire::actingAs($this->admin)
        ->test(SecuritySettings::class)
        ->set('newDomain', 'newcompany.com')
        ->call('addDomain')
        ->assertHasNoErrors();

    $domains = $this->settings->getArray('security.allowed_domains');

    expect($domains)->toContain('newcompany.com');
});

test('can remove allowed domain', function () {
    $this->settings->set('security.allowed_domains', ['company1.com', 'company2.com']);

    Livewire::actingAs($this->admin)
        ->test(SecuritySettings::class)
        ->call('removeDomain', 'company1.com');

    $domains = $this->settings->getArray('security.allowed_domains');

    expect($domains)->not->toContain('company1.com')
        ->and($domains)->toContain('company2.com');
});

test('domain validation rejects invalid domains', function () {
    Livewire::actingAs($this->admin)
        ->test(SecuritySettings::class)
        ->set('newDomain', 'not a valid domain')
        ->call('addDomain')
        ->assertHasErrors(['newDomain']);
});

test('can add individual allowed email', function () {
    Livewire::actingAs($this->admin)
        ->test(SecuritySettings::class)
        ->set('newEmail', 'contractor@example.com')
        ->call('addEmail')
        ->assertHasNoErrors();

    $emails = $this->settings->getArray('security.allowed_emails');

    expect($emails)->toContain('contractor@example.com');
});

test('can remove individual allowed email', function () {
    $this->settings->set('security.allowed_emails', ['email1@example.com', 'email2@example.com']);

    Livewire::actingAs($this->admin)
        ->test(SecuritySettings::class)
        ->call('removeEmail', 'email1@example.com');

    $emails = $this->settings->getArray('security.allowed_emails');

    expect($emails)->not->toContain('email1@example.com')
        ->and($emails)->toContain('email2@example.com');
});

test('email validation rejects invalid emails', function () {
    Livewire::actingAs($this->admin)
        ->test(SecuritySettings::class)
        ->set('newEmail', 'not-an-email')
        ->call('addEmail')
        ->assertHasErrors(['newEmail']);
});

test('domains are stored in lowercase', function () {
    Livewire::actingAs($this->admin)
        ->test(SecuritySettings::class)
        ->set('newDomain', 'UPPERCASE.COM')
        ->call('addDomain');

    $domains = $this->settings->getArray('security.allowed_domains');

    expect($domains)->toContain('uppercase.com')
        ->and($domains)->not->toContain('UPPERCASE.COM');
});

test('emails are stored in lowercase', function () {
    Livewire::actingAs($this->admin)
        ->test(SecuritySettings::class)
        ->set('newEmail', 'USER@EXAMPLE.COM')
        ->call('addEmail');

    $emails = $this->settings->getArray('security.allowed_emails');

    expect($emails)->toContain('user@example.com')
        ->and($emails)->not->toContain('USER@EXAMPLE.COM');
});

test('duplicate domains are not added', function () {
    $this->settings->set('security.allowed_domains', ['existing.com']);

    Livewire::actingAs($this->admin)
        ->test(SecuritySettings::class)
        ->set('newDomain', 'existing.com')
        ->call('addDomain');

    $domains = $this->settings->getArray('security.allowed_domains');

    expect($domains)->toHaveCount(1);
});

test('duplicate emails are not added', function () {
    $this->settings->set('security.allowed_emails', ['existing@example.com']);

    Livewire::actingAs($this->admin)
        ->test(SecuritySettings::class)
        ->set('newEmail', 'existing@example.com')
        ->call('addEmail');

    $emails = $this->settings->getArray('security.allowed_emails');

    expect($emails)->toHaveCount(1);
});
