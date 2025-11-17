<?php

declare(strict_types=1);

use App\Livewire\Dashboard\TopChannels;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2025-01-15 14:30:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('TopChannels Livewire Component', function () {
    it('renders successfully', function () {
        Livewire::test(TopChannels::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.dashboard.top-channels');
    });

    it('initializes with default values', function () {
        Livewire::test(TopChannels::class)
            ->assertSet('period', '7')
            ->assertSet('channel', 'all')
            ->assertSet('status', 'all');
    });

    it('responds to filters-updated event', function () {
        Livewire::test(TopChannels::class)
            ->dispatch('filters-updated', period: '30', channel: 'Amazon', status: 'all')
            ->assertSet('period', '30')
            ->assertSet('channel', 'Amazon');
    });

    it('computes top channels correctly', function () {
        Order::factory()->count(3)->create([
            'created_at' => now()->subDays(3),
            'source' => 'Amazon',
            'total_charge' => 100.00,
        ]);

        Order::factory()->count(2)->create([
            'created_at' => now()->subDays(3),
            'source' => 'eBay',
            'total_charge' => 150.00,
        ]);

        $component = Livewire::test(TopChannels::class);

        $topChannels = $component->get('topChannels');

        expect($topChannels)
            ->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->toHaveCount(2)
            ->and($topChannels[0]['source'])
            ->toBe('Amazon')
            ->and($topChannels[0]['revenue'])
            ->toBe(300.00);
    });

    it('limits results to 6 channels', function () {
        foreach (['Amazon', 'eBay', 'Website', 'Etsy', 'Facebook', 'Instagram', 'TikTok'] as $channel) {
            Order::factory()->create([
                'created_at' => now()->subDays(3),
                'source' => $channel,
                'total_charge' => 100.00,
            ]);
        }

        $component = Livewire::test(TopChannels::class);

        $topChannels = $component->get('topChannels');

        expect($topChannels)->toHaveCount(6);
    });

    it('returns empty collection when no orders exist', function () {
        $component = Livewire::test(TopChannels::class);

        $topChannels = $component->get('topChannels');

        expect($topChannels)
            ->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->toHaveCount(0);
    });

    it('handles custom date range', function () {
        Order::factory()->create([
            'created_at' => Carbon::parse('2025-01-05'),
            'source' => 'Amazon',
            'total_charge' => 100.00,
        ]);

        Order::factory()->create([
            'created_at' => Carbon::parse('2025-01-20'),
            'source' => 'eBay',
            'total_charge' => 200.00,
        ]);

        $component = Livewire::test(TopChannels::class)
            ->set('period', 'custom')
            ->set('customFrom', '2025-01-01')
            ->set('customTo', '2025-01-10');

        $topChannels = $component->get('topChannels');

        expect($topChannels)->toHaveCount(1)
            ->and($topChannels[0]['source'])->toBe('Amazon');
    });

    it('sorts channels by revenue descending', function () {
        Order::factory()->create([
            'created_at' => now()->subDays(3),
            'source' => 'Amazon',
            'total_charge' => 500.00,
        ]);

        Order::factory()->create([
            'created_at' => now()->subDays(3),
            'source' => 'eBay',
            'total_charge' => 800.00,
        ]);

        Order::factory()->create([
            'created_at' => now()->subDays(3),
            'source' => 'Website',
            'total_charge' => 200.00,
        ]);

        $component = Livewire::test(TopChannels::class);

        $topChannels = $component->get('topChannels');

        expect($topChannels[0]['source'])->toBe('eBay')
            ->and($topChannels[1]['source'])->toBe('Amazon')
            ->and($topChannels[2]['source'])->toBe('Website');
    });
});
