<?php

use App\Models\AppSetting;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(SettingsService::class);
});

test('can store and retrieve settings', function () {
    $this->service->set('test.key', 'test value');

    $value = $this->service->get('test.key');

    expect($value)->toBe('test value');
});

test('can store and retrieve array settings', function () {
    $testArray = ['item1', 'item2', 'item3'];

    $this->service->set('test.array', $testArray);

    $retrieved = $this->service->getArray('test.array');

    expect($retrieved)->toBe($testArray);
});

test('can store and retrieve boolean settings', function () {
    $this->service->set('test.bool', true);

    expect($this->service->getBool('test.bool'))->toBeTrue();

    $this->service->set('test.bool', false);

    expect($this->service->getBool('test.bool'))->toBeFalse();
});

test('returns default value when setting does not exist', function () {
    $value = $this->service->get('nonexistent.key', 'default');

    expect($value)->toBe('default');
});

test('settings are cached for performance', function () {
    $this->service->set('cached.key', 'cached value');

    // First call - should hit database
    $value1 = $this->service->get('cached.key');

    // Delete from database
    AppSetting::where('key', 'cached.key')->delete();

    // Second call - should return cached value
    $value2 = $this->service->get('cached.key');

    expect($value1)->toBe('cached value')
        ->and($value2)->toBe('cached value');
});

test('cache is invalidated when setting is updated', function () {
    $this->service->set('update.key', 'original');

    expect($this->service->get('update.key'))->toBe('original');

    $this->service->set('update.key', 'updated');

    expect($this->service->get('update.key'))->toBe('updated');
});

test('tracks who updated the setting', function () {
    $user = User::factory()->create();

    $this->service->set('tracked.key', 'tracked value', $user->id);

    $setting = AppSetting::where('key', 'tracked.key')->first();

    expect($setting->updated_by)->toBe($user->id);
});

test('can check if setting exists', function () {
    $this->service->set('exists.key', 'value');

    expect($this->service->has('exists.key'))->toBeTrue()
        ->and($this->service->has('nonexistent.key'))->toBeFalse();
});

test('can delete settings', function () {
    $this->service->set('delete.key', 'value');

    expect($this->service->has('delete.key'))->toBeTrue();

    $this->service->delete('delete.key');

    expect($this->service->has('delete.key'))->toBeFalse();
});

test('getString returns string value', function () {
    $this->service->set('string.key', 'string value');

    $value = $this->service->getString('string.key');

    expect($value)->toBeString()
        ->and($value)->toBe('string value');
});

test('getInt returns integer value', function () {
    $this->service->set('int.key', 42);

    $value = $this->service->getInt('int.key');

    expect($value)->toBeInt()
        ->and($value)->toBe(42);
});
