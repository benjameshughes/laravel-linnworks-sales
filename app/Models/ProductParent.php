<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * A Linnworks variation group: the sellable parent that child SKUs belong to.
 *
 * @property int $id
 * @property string $linnworks_id
 * @property string $sku
 * @property string|null $title
 * @property array|null $metadata
 * @property \Carbon\Carbon|null $last_synced_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product> $products
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OrderItem> $orderItems
 */
final class ProductParent extends Model
{
    use HasFactory;

    protected $fillable = [
        'linnworks_id',
        'sku',
        'title',
        'metadata',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orderItems(): HasManyThrough
    {
        return $this->hasManyThrough(
            OrderItem::class,
            Product::class,
            'product_parent_id',
            'sku',
            'id',
            'sku',
        );
    }
}
