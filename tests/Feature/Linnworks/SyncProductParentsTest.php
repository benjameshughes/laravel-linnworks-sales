<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Product;
use App\Models\ProductParent;
use App\Models\LinnworksConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Actions\Linnworks\SyncProductParents;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $user = User::factory()->create();
    LinnworksConnection::factory()->create(['user_id' => $user->id, 'is_active' => true]);
    Cache::put('linnworks_session_token', 'test-token', now()->addHour());
    Cache::put('linnworks_session_token_server', 'https://eu-ext.linnworks.test/api/', now()->addHour());
});

/**
 * @param  array<int, array{id: string, sku: string, children: array<int, string>}>  $groups
 */
function fakeVariationGroups(array $groups, int $totalPages = 1): void
{
    Http::fake(function ($request) use ($groups, $totalPages) {
        if (str_contains($request->url(), 'SearchVariationGroups')) {
            return Http::response([
                'PageNumber' => 1,
                'EntriesPerPage' => 200,
                'TotalEntries' => count($groups),
                'TotalPages' => $totalPages,
                'Data' => array_map(fn (array $g): array => [
                    'pkVariationItemId' => $g['id'],
                    'VariationSKU' => $g['sku'],
                    'VariationTitle' => $g['sku'].' parent',
                ], $groups),
            ]);
        }

        if (str_contains($request->url(), 'GetVariationItems')) {
            $id = $request->data()['pkVariationItemId'] ?? null;
            $group = collect($groups)->firstWhere('id', $id);

            return Http::response(array_map(
                fn (string $id): array => ['pkStockItemId' => $id],
                $group['children'] ?? [],
            ));
        }

        return Http::response([]);
    });
}

describe('SyncProductParents', function () {
    it('creates a product parent for each variation group', function () {
        fakeVariationGroups([
            ['id' => 'v-1', 'sku' => '005', 'children' => ['si-005-001']],
            ['id' => 'v-2', 'sku' => '026', 'children' => ['si-026-004']],
        ]);

        $result = app(SyncProductParents::class)();

        expect(ProductParent::count())->toBe(2)
            ->and($result['groups'])->toBe(2)
            ->and(ProductParent::where('sku', '005')->first()->title)->toBe('005 parent');
    });

    it('links child products to their parent', function () {
        Product::factory()->create(['sku' => '005-001', 'linnworks_id' => 'si-005-001']);
        Product::factory()->create(['sku' => '005-002', 'linnworks_id' => 'si-005-002']);

        fakeVariationGroups([
            ['id' => 'v-1', 'sku' => '005', 'children' => ['si-005-001', 'si-005-002']],
        ]);

        $result = app(SyncProductParents::class)();

        $parent = ProductParent::where('sku', '005')->first();

        expect($result['linked'])->toBe(2)
            ->and($parent->products()->pluck('sku')->sort()->values()->all())->toBe(['005-001', '005-002']);
    });

    it('reports children that have no local product', function () {
        Product::factory()->create(['sku' => '005-001', 'linnworks_id' => 'si-005-001']);

        fakeVariationGroups([
            ['id' => 'v-1', 'sku' => '005', 'children' => ['si-005-001', 'si-005-999']],
        ]);

        $result = app(SyncProductParents::class)();

        expect($result['linked'])->toBe(1)
            ->and($result['unmatched'])->toBe(1);
    });

    it('is idempotent and does not duplicate parents', function () {
        Product::factory()->create(['sku' => '005-001', 'linnworks_id' => 'si-005-001']);

        fakeVariationGroups([
            ['id' => 'v-1', 'sku' => '005', 'children' => ['si-005-001']],
        ]);

        app(SyncProductParents::class)();
        app(SyncProductParents::class)();

        expect(ProductParent::count())->toBe(1);
    });

    it('walks every page rather than trusting the first', function () {
        fakeVariationGroups([
            ['id' => 'v-1', 'sku' => '005', 'children' => []],
        ], totalPages: 3);

        $result = app(SyncProductParents::class)();

        // three pages, same stub payload each time, all deduped onto one parent
        expect($result['groups'])->toBe(3)
            ->and(ProductParent::count())->toBe(1);

        $searches = collect(Http::recorded())
            ->filter(fn (array $pair): bool => str_contains($pair[0]->url(), 'SearchVariationGroups'));

        expect($searches)->toHaveCount(3);
    });

    it('skips malformed groups with no id or sku', function () {
        Http::fake(fn () => Http::response([
            'TotalPages' => 1,
            'Data' => [
                ['pkVariationItemId' => '', 'VariationSKU' => '005'],
                ['pkVariationItemId' => 'v-2', 'VariationSKU' => ''],
            ],
        ]));

        $result = app(SyncProductParents::class)();

        expect($result['groups'])->toBe(0)
            ->and(ProductParent::count())->toBe(0);
    });
});

describe('ProductParent relationships', function () {
    it('reaches order items through its products', function () {
        $parent = ProductParent::factory()->create(['sku' => '005']);
        $product = Product::factory()->create(['sku' => '005-001', 'product_parent_id' => $parent->id]);

        \App\Models\OrderItem::factory()->count(3)->create(['sku' => $product->sku]);

        expect($parent->orderItems()->count())->toBe(3);
    });

    it('exposes the parent from the product side', function () {
        $parent = ProductParent::factory()->create(['sku' => '005']);
        $product = Product::factory()->create(['product_parent_id' => $parent->id]);

        expect($product->parent->sku)->toBe('005');
    });
});
