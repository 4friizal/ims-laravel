<?php

namespace App\Models;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'raw_material_id',
    'menu_id',
    'stock_batch_id',
    'type',
    'quantity',
    'unit_cost',
    'total_cost',
    'reference',
    'occurred_at',
])]
class StockMovement extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * Batches this movement drew from (FIFO breakdown, only for OUT movements).
     */
    public function batches(): BelongsToMany
    {
        return $this->belongsToMany(StockBatch::class, 'stock_movement_batch')
            ->withPivot('quantity_consumed', 'unit_cost')
            ->withTimestamps();
    }
}
