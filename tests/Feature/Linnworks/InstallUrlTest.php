<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Linnworks\Auth\AuthenticationService;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['linnworks.application_id' => 'app-id-1234']);
});

describe('install url', function () {
    it('points at the install page a user can actually load', function () {
        $url = app(AuthenticationService::class)->generateInstallUrl('app-id-1234', 'user_1');

        expect($url)->toStartWith('https://apps.linnworks.net/Authorization/Authorize/app-id-1234');
    });

    it('never leaks the application secret into the query string', function () {
        config(['linnworks.application_secret' => 'super-secret']);

        $url = app(AuthenticationService::class)->generateInstallUrl('app-id-1234', 'user_1');

        expect($url)->not->toContain('super-secret')
            ->and($url)->not->toContain('ApplicationSecret');
    });

    it('carries the tracking value the callback parses the user id out of', function () {
        $url = app(AuthenticationService::class)->generateInstallUrl('app-id-1234', 'user_42');

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        expect($query['Tracking'])->toBe('user_42');
    });

    it('does not point at the server to server token endpoint', function () {
        $url = app(AuthenticationService::class)->generateInstallUrl('app-id-1234', 'user_1');

        expect($url)->not->toContain('AuthorizeByApplication');
    });
});

describe('install url endpoint', function () {
    it('hands a signed in user a url tracking their own id', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('linnworks.install.url'))
            ->assertOk()
            ->assertJsonPath('tracking', "user_{$user->id}")
            ->assertJsonPath('user_id', $user->id)
            ->assertJsonPath('install_url', fn (string $url): bool => str_contains($url, 'apps.linnworks.net'));
    });

    it('refuses guests', function () {
        $this->getJson(route('linnworks.install.url'))->assertUnauthorized();
    });

    it('fails loudly when no application id is configured', function () {
        config(['linnworks.application_id' => null]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('linnworks.install.url'))
            ->assertStatus(500);
    });
});
