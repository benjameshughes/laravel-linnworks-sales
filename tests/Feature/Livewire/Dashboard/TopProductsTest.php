<?php

declare(strict_types=1);

use App\Livewire\Dashboard\TopProducts;
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

describe('TopProducts Livewire Component', function () {
    it('renders successfully', function () {
        Livewire::test(TopProducts::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.dashboard.top-products');
    });

    it('initializes with default values', function () {
        Livewire::test(TopProducts::class)
            ->assertSet('period', '7')
            ->assertSet('channel', 'all')
            ->assertSet('status', 'all');
    });

    it('responds to filters-updated event', function () {
        Livewire::test(TopProducts::class)
            ->dispatch('filters-updated', period: '30', channel: 'Amazon', status: 'all')
            ->assertSet('period', '30')
            ->assertSet('channel', 'Amazon');
    });

    it('computes top products correctly', function () {
        Order::factory()
            ->withItems([
                ['sku' => 'ABC123', 'quantity' => 10],
                ['sku' => 'DEF456', 'quantity' => 5],
            ])
            ->create([
                'received_at' => now()->subDays(3),
            ]);

        Order::factory()
            ->withItems([
                ['sku' => 'ABC123', 'quantity' => 5],
                ['sku' => 'GHI789', 'quantity' => 3],
            ])
            ->create([
                'received_at' => now()->subDays(3),
            ]);

        $component = Livewire::test(TopProducts::class)
            ->set('period', 'custom')
            ->set('customFrom', now()->subDays(7)->format('Y-m-d'))
            ->set('customTo', now()->format('Y-m-d'));

        $topProducts = $component->get('topProducts');

        expect($topProducts)
            ->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->toHaveCount(3)
            ->and($topProducts[0]['sku'])
            ->toBe('ABC123')
            ->and($topProducts[0]['quantity'])
            ->toBe(15);
    });

    it('limits results to 10 products', function () {
        $items = collect(range(1, 15))->map(fn ($i) => [
            'sku' => "SKU{$i}",
            'quantity' => $i,
        ])->toArray();

        Order::factory()
            ->withItems($items)
            ->create([
                'received_at' => now()->subDays(3),
            ]);

        $component = Livewire::test(TopProducts::class)
            ->set('period', 'custom')
            ->set('customFrom', now()->subDays(7)->format('Y-m-d'))
            ->set('customTo', now()->format('Y-m-d'));

        $topProducts = $component->get('topProducts');

        expect($topProducts)->toHaveCount(10);
    });

    it('returns empty collection when no orders exist', function () {
        $component = Livewire::test(TopProducts::class);

        $topProducts = $component->get('topProducts');

        expect($topProducts)
            ->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->toHaveCount(0);
    });

    it('filters by channel', function () {
        Order::factory()
            ->withItems([
                ['sku' => 'ABC123', 'quantity' => 10],
            ])
            ->create([
                'received_at' => now()->subDays(3),
                'source' => 'amazon',
            ]);

        Order::factory()
            ->withItems([
                ['sku' => 'DEF456', 'quantity' => 20],
            ])
            ->create([
                'received_at' => now()->subDays(3),
                'source' => 'ebay',
            ]);

        $component = Livewire::test(TopProducts::class)
            ->set('period', 'custom')
            ->set('customFrom', now()->subDays(7)->format('Y-m-d'))
            ->set('customTo', now()->format('Y-m-d'))
            ->set('channel', 'amazon');

        $topProducts = $component->get('topProducts');

        expect($topProducts)->toHaveCount(1)
            ->and($topProducts[0]['sku'])->toBe('ABC123');
    });

    it('handles custom date range', function () {
        Order::factory()
            ->withItems([
                ['sku' => 'ABC123', 'quantity' => 10],
            ])
            ->create([
                'received_at' => Carbon::parse('2025-01-05'),
            ]);

        Order::factory()
            ->withItems([
                ['sku' => 'DEF456', 'quantity' => 20],
            ])
            ->create([
                'received_at' => Carbon::parse('2025-01-20'),
            ]);

        $component = Livewire::test(TopProducts::class)
            ->set('period', 'custom')
            ->set('customFrom', '2025-01-01')
            ->set('customTo', '2025-01-10');

        $topProducts = $component->get('topProducts');

        expect($topProducts)->toHaveCount(1)
            ->and($topProducts[0]['sku'])->toBe('ABC123');
    });

    it('sorts products by quantity descending', function () {
        Order::factory()
            ->withItems([
                ['sku' => 'LOW', 'quantity' => 5],
                ['sku' => 'HIGH', 'quantity' => 50],
                ['sku' => 'MEDIUM', 'quantity' => 20],
            ])
            ->create([
                'received_at' => now()->subDays(3),
            ]);

        $component = Livewire::test(TopProducts::class)
            ->set('period', 'custom')
            ->set('customFrom', now()->subDays(7)->format('Y-m-d'))
            ->set('customTo', now()->format('Y-m-d'));

        $topProducts = $component->get('topProducts');

        expect($topProducts[0]['sku'])->toBe('HIGH')
            ->and($topProducts[1]['sku'])->toBe('MEDIUM')
            ->and($topProducts[2]['sku'])->toBe('LOW');
    });
});
