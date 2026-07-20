<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['raw_material_id', 'quantity', 'remaining_quantity', 'unit_cost', 'received_at', 'note'])]
class StockBatch extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'remaining_quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'received_at' => 'date',
        ];
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }

    /**
     * Batches that still have stock, ordered FIFO (oldest received first).
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('remaining_quantity', '>', 0)
            ->orderBy('received_at')
            ->orderBy('id');
    }
}
