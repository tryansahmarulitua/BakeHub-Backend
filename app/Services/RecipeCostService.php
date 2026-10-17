<?php

namespace App\Services;

use App\Models\Recipe;
use App\Models\Product;

class RecipeCostService
{
    public function __construct(private UnitConversionService $converter) {}

    /**
     * Calculate and update recipe costs:
     * - biaya_bahan: sum of (raw_material unit cost * quantity in stock unit)
     * - biaya_overhead: biaya_bahan * (overhead_percent / 100)
     * - total_biaya_produksi: biaya_bahan + biaya_kemasan + biaya_overhead
     * - harga_modal_per_unit: total_biaya_produksi / output_quantity
     * Then update product's harga_modal.
     */
    public function calculateAndUpdate(Recipe $recipe): Recipe
    {
        $biayaBahan = 0;

        foreach ($recipe->details as $detail) {
            $rawMaterial = $detail->rawMaterial;

            // Convert detail quantity (recipe unit) to stock unit
            $quantityInStockUnit = $this->converter->convert(
                (float) $detail->quantity,
                $detail->unit_id,
                $rawMaterial->unit_id
            );

            // Cost = quantity in stock unit * unit price (harga_beli)
            $biayaBahan += $quantityInStockUnit * (float) $rawMaterial->harga_beli;
        }

        $overheadPercent = (float) $recipe->overhead_percent;
        $biayaOverhead   = $biayaBahan * ($overheadPercent / 100);
        $biayaKemasan    = (float) $recipe->biaya_kemasan;

        $totalBiayaProduksi = $biayaBahan + $biayaKemasan + $biayaOverhead;
        $hargaModalPerUnit  = $recipe->output_quantity > 0
            ? $totalBiayaProduksi / (float) $recipe->output_quantity
            : 0;

        $recipe->update([
            'biaya_bahan'          => $biayaBahan,
            'biaya_overhead'       => $biayaOverhead,
            'total_biaya_produksi' => $totalBiayaProduksi,
            'harga_modal_per_unit' => $hargaModalPerUnit,
        ]);

        // Update product's harga_modal
        if ($recipe->product) {
            $recipe->product->update(['harga_modal' => $hargaModalPerUnit]);
        }

        return $recipe->fresh();
    }
}
