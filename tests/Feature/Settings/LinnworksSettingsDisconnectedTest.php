<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;
use App\Models\LinnworksConnection;
use App\Livewire\Settings\LinnworksSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The settings screen is the only way back from a disconnected account, so it
 * must never eagerly resolve anything that needs a live Linnworks client.
 */
describe('linnworks settings while disconnected', function () {
    beforeEach(function (): void {
        $this->user = User::factory()->create();
        LinnworksConnection::factory()->create(['user_id' => $this->user->id, 'is_active' => false]);
    });

    it('renders with no active connection', function () {
        Livewire::actingAs($this->user)
            ->test(LinnworksSettings::class)
            ->assertOk();
    });

    it('survives a hydrate round trip', function () {
        Livewire::actingAs($this->user)
            ->test(LinnworksSettings::class)
            ->call('$refresh')
            ->assertOk();
    });

    it('can still open the connection form', function () {
        Livewire::actingAs($this->user)
            ->test(LinnworksSettings::class)
            ->call('showConnectionForm')
            ->assertOk()
            ->assertSet('showForm', true);
    });

    it('renders when there is no connection record at all', function () {
        LinnworksConnection::query()->delete();

        Livewire::actingAs($this->user)
            ->test(LinnworksSettings::class)
            ->call('$refresh')
            ->assertOk();
    });
});
