<?php

use App\Rules\BusinessEmailDomain;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->settings = app(SettingsService::class);
    $this->settings->set('security.allowed_domains', ['testcompany.com', 'example.org']);
    $this->settings->set('security.allowed_emails', ['special@gmail.com']);
});

test('allows email from allowed domain', function () {
    $rule = new BusinessEmailDomain($this->settings);

    $fails = false;
    $rule->validate('email', 'user@testcompany.com', function () use (&$fails) {
        $fails = true;
    });

    expect($fails)->toBeFalse();
});

test('rejects email from disallowed domain', function () {
    $rule = new BusinessEmailDomain($this->settings);

    $fails = false;
    $rule->validate('email', 'user@disallowed.com', function () use (&$fails) {
        $fails = true;
    });

    expect($fails)->toBeTrue();
});

test('allows individually whitelisted email', function () {
    $rule = new BusinessEmailDomain($this->settings);

    $fails = false;
    $rule->validate('email', 'special@gmail.com', function () use (&$fails) {
        $fails = true;
    });

    expect($fails)->toBeFalse();
});

test('validation is case insensitive for domains', function () {
    $rule = new BusinessEmailDomain($this->settings);

    $fails = false;
    $rule->validate('email', 'USER@TESTCOMPANY.COM', function () use (&$fails) {
        $fails = true;
    });

    expect($fails)->toBeFalse();
});

test('validation is case insensitive for whitelisted emails', function () {
    $rule = new BusinessEmailDomain($this->settings);

    $fails = false;
    $rule->validate('email', 'SPECIAL@GMAIL.COM', function () use (&$fails) {
        $fails = true;
    });

    expect($fails)->toBeFalse();
});

test('rejects invalid email format', function () {
    $rule = new BusinessEmailDomain($this->settings);

    $fails = false;
    $failMessage = '';
    $rule->validate('email', 'not-an-email', function ($message) use (&$fails, &$failMessage) {
        $fails = true;
        $failMessage = $message;
    });

    expect($fails)->toBeTrue()
        ->and($failMessage)->toContain('valid email');
});

test('extracts domain correctly', function () {
    $rule = new BusinessEmailDomain($this->settings);

    // Test with subdomain
    $fails = false;
    $this->settings->set('security.allowed_domains', ['company.co.uk']);

    $rule->validate('email', 'user@company.co.uk', function () use (&$fails) {
        $fails = true;
    });

    expect($fails)->toBeFalse();
});
