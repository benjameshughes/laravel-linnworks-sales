<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

test('password requires at least 12 characters', function () {
    $validator = Validator::make(
        ['password' => 'Short1!'],
        ['password' => ['required', Password::defaults()]]
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('password'))->toBeTrue();
});

test('password requires mixed case letters', function () {
    $validator = Validator::make(
        ['password' => 'alllowercase123!'],
        ['password' => ['required', Password::defaults()]]
    );

    expect($validator->fails())->toBeTrue();
});

test('password requires numbers', function () {
    $validator = Validator::make(
        ['password' => 'NoNumbersHere!'],
        ['password' => ['required', Password::defaults()]]
    );

    expect($validator->fails())->toBeTrue();
});

test('password requires symbols', function () {
    $validator = Validator::make(
        ['password' => 'NoSymbolsHere123'],
        ['password' => ['required', Password::defaults()]]
    );

    expect($validator->fails())->toBeTrue();
});

test('password rejects commonly compromised passwords', function () {
    $validator = Validator::make(
        ['password' => 'Password123!'],  // Common compromised password
        ['password' => ['required', Password::defaults()]]
    );

    // May fail if the haveibeenpwned API is unavailable
    expect($validator->fails())->toBeTrue();
})->skip('Skipped because it requires external API');

test('strong password passes validation', function () {
    $validator = Validator::make(
        ['password' => 'MySecure#Pass2024!'],
        ['password' => ['required', Password::defaults()]]
    );

    expect($validator->passes())->toBeTrue();
});

test('password is hashed when creating user', function () {
    $plainPassword = 'MySecure#Pass2024!';

    $user = User::factory()->create([
        'password' => $plainPassword,
    ]);

    expect($user->password)->not->toBe($plainPassword)
        ->and(Hash::check($plainPassword, $user->password))->toBeTrue();
});
