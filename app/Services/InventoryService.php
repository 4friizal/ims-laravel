<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Models\MaterialUsage;
use App\Models\Menu;
use App\Models\RawMaterial;
use App\Models\StockBatch;
use App\Models\StockMovement;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Record incoming stock: create a FIFO batch and an "in" movement.
     */
    public function stockIn(
        RawMaterial $material,
        float $quantity,
        float $unitCost,
        ?CarbonInterface $receivedAt = null,
        ?string $note = null,
        ?string $reference = null,
    ): StockMovement {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Jumlah stok masuk harus lebih dari nol.');
        }

        $receivedAt ??= Carbon::now();

        return DB::transaction(function () use ($material, $quantity, $unitCost, $receivedAt, $note, $reference) {
            $batch = StockBatch::create([
                'raw_material_id' => $material->id,
                'quantity' => $quantity,
                'remaining_quantity' => $quantity,
                'unit_cost' => $unitCost,
                'received_at' => $receivedAt,
                'note' => $note,
            ]);

            return StockMovement::create([
                'raw_material_id' => $material->id,
                'stock_batch_id' => $batch->id,
                'type' => MovementType::In,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => round($quantity * $unitCost, 2),
                'reference' => $reference ?? 'Pembelian',
                'occurred_at' => $receivedAt,
            ]);
        });
    }

    /**
     * Record outgoing stock, consuming batches FIFO (oldest received first).
     *
     * @throws InsufficientStockException
     */
    public function stockOut(
        RawMaterial $material,
        float $quantity,
        ?string $reference = null,
        ?Menu $menu = null,
        ?CarbonInterface $occurredAt = null,
    ): StockMovement {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Jumlah stok keluar harus lebih dari nol.');
        }

        $occurredAt ??= Carbon::now();

        return DB::transaction(function () use ($material, $quantity, $reference, $menu, $occurredAt) {
            $batches = StockBatch::query()
                ->where('raw_material_id', $material->id)
                ->available()
                ->lockForUpdate()
                ->get();

            $available = (float) $batches->sum('remaining_quantity');
            if ($available < $quantity) {
                throw new InsufficientStockException(
                    "Stok {$material->name} tidak mencukupi. Tersedia: {$available}, dibutuhkan: {$quantity}."
                );
            }

            $movement = StockMovement::create([
                'raw_material_id' => $material->id,
                'menu_id' => $menu?->id,
                'type' => MovementType::Out,
                'quantity' => $quantity,
                'reference' => $reference ?? 'Pemakaian',
                'occurred_at' => $occurredAt,
                'total_cost' => 0,
            ]);

            $remainingToConsume = $quantity;
            $totalCost = 0.0;

            foreach ($batches as $batch) {
                if ($remainingToConsume <= 0) {
                    break;
                }

                $take = min($remainingToConsume, (float) $batch->remaining_quantity);
                $batch->decrement('remaining_quantity', $take);

                $movement->batches()->attach($batch->id, [
                    'quantity_consumed' => $take,
                    'unit_cost' => $batch->unit_cost,
                ]);

                $totalCost += $take * (float) $batch->unit_cost;
                $remainingToConsume -= $take;
            }

            $movement->update([
                'unit_cost' => $quantity > 0 ? round($totalCost / $quantity, 2) : 0,
                'total_cost' => round($totalCost, 2),
            ]);

            $this->recordUsage($material, $quantity, $occurredAt);

            return $movement;
        });
    }

    /**
     * Sell/produce a menu: deduct every recipe material via FIFO.
     * Validates all materials have enough stock before deducting anything.
     *
     * @return array<int, StockMovement>
     *
     * @throws InsufficientStockException
     */
    public function sellMenu(Menu $menu, float $portions = 1): array
    {
        if ($portions <= 0) {
            throw new \InvalidArgumentException('Jumlah porsi harus lebih dari nol.');
        }

        return DB::transaction(function () use ($menu, $portions) {
            $menu->loadMissing('materials');
            $reference = "Penjualan {$menu->name} ({$portions} porsi)";
            $movements = [];

            // Pre-check availability for all materials so the sale is all-or-nothing.
            foreach ($menu->materials as $material) {
                $needed = (float) $material->pivot->quantity * $portions;
                $available = (float) $material->batches()->available()->sum('remaining_quantity');
                if ($available < $needed) {
                    throw new InsufficientStockException(
                        "Stok {$material->name} tidak mencukupi untuk menjual {$menu->name}. Tersedia: {$available}, dibutuhkan: {$needed}."
                    );
                }
            }

            foreach ($menu->materials as $material) {
                $needed = (float) $material->pivot->quantity * $portions;
                $movements[] = $this->stockOut($material, $needed, $reference, $menu);
            }

            return $movements;
        });
    }

    /**
     * Upsert today's aggregated usage for a material (canonical AI training source).
     */
    private function recordUsage(RawMaterial $material, float $quantity, CarbonInterface $occurredAt): void
    {
        $date = $occurredAt->toDateString();

        $usage = MaterialUsage::firstOrNew([
            'raw_material_id' => $material->id,
            'usage_date' => $date,
        ]);

        $usage->quantity = (float) $usage->quantity + $quantity;
        $usage->save();
    }
}
