<?php

namespace App\Services;

use App\Models\InventoryTransaction;
use App\Models\RawMaterial;
use App\Models\Product;
use Illuminate\Support\Str;

class InventoryService
{
    public function stockIn(
        string $itemType,
        int $itemId,
        float $quantity,
        string $transactionType,
        string $referenceType,
        int $referenceId,
        int $userId,
        string $date
    ): InventoryTransaction {
        $item = $itemType === 'raw_material'
            ? RawMaterial::findOrFail($itemId)
            : Product::findOrFail($itemId);

        $before = (float) $item->stok;
        $after  = $before + $quantity;

        $item->update(['stok' => $after]);

        return InventoryTransaction::create([
            'transaction_number' => 'TRX-' . strtoupper(Str::random(8)),
            'item_type'          => $itemType,
            'item_id'            => $itemId,
            'transaction_type'   => $transactionType,
            'quantity'           => $quantity,
            'balance_before'     => $before,
            'balance_after'      => $after,
            'reference_type'     => $referenceType,
            'reference_id'       => $referenceId,
            'transaction_date'   => $date,
            'created_by'         => $userId,
        ]);
    }

    public function stockOut(
        string $itemType,
        int $itemId,
        float $quantity,
        string $transactionType,
        string $referenceType,
        int $referenceId,
        int $userId,
        string $date
    ): InventoryTransaction {
        $item = $itemType === 'raw_material'
            ? RawMaterial::findOrFail($itemId)
            : Product::findOrFail($itemId);

        $before = (float) $item->stok;

        // BR-003: stok tidak boleh negatif
        if ($before < $quantity) {
            throw new \RuntimeException("Stok tidak mencukupi. Stok saat ini: {$before}");
        }

        $after = $before - $quantity;
        $item->update(['stok' => $after]);

        return InventoryTransaction::create([
            'transaction_number' => 'TRX-' . strtoupper(Str::random(8)),
            'item_type'          => $itemType,
            'item_id'            => $itemId,
            'transaction_type'   => $transactionType,
            'quantity'           => $quantity,
            'balance_before'     => $before,
            'balance_after'      => $after,
            'reference_type'     => $referenceType,
            'reference_id'       => $referenceId,
            'transaction_date'   => $date,
            'created_by'         => $userId,
        ]);
    }
}
