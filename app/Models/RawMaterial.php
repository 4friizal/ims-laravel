<?php

namespace App\Models;

use App\Enums\MaterialUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['category_id', 'name', 'unit', 'minimum_stock'])]
class RawMaterial extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'unit' => MaterialUnit::class,
            'minimum_stock' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(MaterialUsage::class);
    }

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'menu_materials')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    /**
     * Current stock = sum of remaining quantity across all batches.
     */
    public function currentStock(): float
    {
        return (float) $this->batches()->sum('remaining_quantity');
    }

    public function isLowStock(): bool
    {
        return $this->currentStock() <= (float) $this->minimum_stock;
    }
}
