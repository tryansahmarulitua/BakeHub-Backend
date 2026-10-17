<?php

namespace App\Services;

use App\Models\Production;
use App\Models\ProductionDetail;
use App\Models\RawMaterial;
use App\Models\Recipe;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProductionService
{
    public function __construct(private InventoryService $inventory, private UnitConversionService $converter) {}

    /**
     * Calculate how much of each material is needed for a given quantity.
     * Returns array of ['raw_material_id', 'quantity_needed', 'unit_id', 'raw_material', 'quantity_needed_in_stock_unit'].
     */
    public function calculateMaterials(Recipe $recipe, float $quantity): array
    {
        // Scale factor: quantity / recipe output
        $factor = $quantity / $recipe->output_quantity;

        return $recipe->details->map(function ($detail) use ($factor) {
            $quantityNeeded = round($detail->quantity * $factor, 4);
            $rawMaterial    = $detail->rawMaterial;

            // Convert quantity from recipe unit to stock unit
            $quantityInStockUnit = $this->converter->convert(
                $quantityNeeded,
                $detail->unit_id,          // unit in recipe (e.g., Gram)
                $rawMaterial->unit_id      // unit in stock (e.g., Kilogram)
            );

            return [
                'raw_material_id'         => $detail->raw_material_id,
                'unit_id'                 => $detail->unit_id,
                'quantity_needed'         => $quantityNeeded,
                'raw_material'            => $rawMaterial,
                'quantity_needed_in_stock_unit' => $quantityInStockUnit,
            ];
        })->toArray();
    }

    /**
     * Check if stock is sufficient for all materials.
     * Returns ['ok' => bool, 'shortages' => array].
     */
    public function checkStock(array $materials): array
    {
        $shortages = [];

        foreach ($materials as $m) {
            $mat = RawMaterial::with('unit')->find($m['raw_material_id']);
            $needed = $m['quantity_needed_in_stock_unit'] ?? $m['quantity_needed'];
            if (!$mat || (float) $mat->stok < (float) $needed) {
                $shortages[] = [
                    'nama_bahan'      => $mat?->nama_bahan ?? 'Unknown',
                    'stok_tersedia'   => (float) ($mat?->stok ?? 0),
                    'stok_unit'       => $mat?->unit?->symbol ?? $mat?->unit?->name ?? '',
                    'quantity_needed' => (float) $needed,
                    'recipe_quantity' => (float) $m['quantity_needed'],
                ];
            }
        }

        return ['ok' => empty($shortages), 'shortages' => $shortages];
    }

    /**
     * Check expired materials — BR-009: expired material cannot be used.
     * Returns ['ok' => bool, 'expired' => array].
     */
    public function checkExpiry(array $materials): array
    {
        $expired = [];
        $today   = Carbon::today();

        foreach ($materials as $m) {
            $mat = RawMaterial::find($m['raw_material_id']);
            if ($mat && $mat->tanggal_kedaluwarsa && Carbon::parse($mat->tanggal_kedaluwarsa)->lte($today)) {
                $expired[] = [
                    'nama_bahan'           => $mat->nama_bahan,
                    'tanggal_kedaluwarsa'  => $mat->tanggal_kedaluwarsa->format('Y-m-d'),
                ];
            }
        }

        return ['ok' => empty($expired), 'expired' => $expired];
    }

    /**
     * Complete a production order:
     * 1. Validate stock
     * 2. Check expiry (BR-009)
     * 3. Stock out raw materials (FEFO-aware via single-item logic)
     * 4. Stock in finished product
     * 5. Mark production completed
     */
    public function complete(Production $production, int $userId): Production
    {
        return DB::transaction(function () use ($production, $userId) {
            if ($production->status !== 'processing') {
                throw new \RuntimeException('Produksi harus berstatus processing sebelum diselesaikan.');
            }

            $materials = $this->calculateMaterials($production->recipe, (float) $production->quantity);

            // BR-015: cek stok cukup
            $stockCheck = $this->checkStock($materials);
            if (!$stockCheck['ok']) {
                $msgs = collect($stockCheck['shortages'])->map(
                    fn($s) => "{$s['nama_bahan']} (butuh {$s['quantity_needed']} {$s['stok_unit']}, stok ada {$s['stok_tersedia']} {$s['stok_unit']})"
                )->implode(', ');
                throw new \RuntimeException("Stok tidak mencukupi untuk: {$msgs}");
            }

            // BR-009: cek expired
            $expiryCheck = $this->checkExpiry($materials);
            if (!$expiryCheck['ok']) {
                $names = collect($expiryCheck['expired'])->pluck('nama_bahan')->implode(', ');
                throw new \RuntimeException("Bahan sudah kedaluwarsa: {$names}");
            }

            $date = $production->production_date->format('Y-m-d');

            // BR-005: kurangi stok bahan baku, BR-010: catat transaksi
            foreach ($materials as $m) {
                $detail = $production->details()->where('raw_material_id', $m['raw_material_id'])->first();
                // Gunakan quantity yang sudah dikonversi ke satuan stok (misal: gram -> kg)
                $usedInStockUnit = $m['quantity_needed_in_stock_unit'] ?? $m['quantity_needed'];

                $this->inventory->stockOut(
                    'raw_material',
                    $m['raw_material_id'],
                    (float) $usedInStockUnit,
                    'PRODUCTION_OUT',
                    'Production',
                    $production->id,
                    $userId,
                    $date,
                );

                // Update quantity_used (simpan dalam satuan resep)
                if ($detail) {
                    $detail->update(['quantity_used' => $m['quantity_needed']]);
                }
            }

            // BR-006: tambah stok produk jadi
            $this->inventory->stockIn(
                'product',
                $production->product_id,
                (float) $production->quantity,
                'PRODUCTION_IN',
                'Production',
                $production->id,
                $userId,
                $date,
            );

            $production->update(['status' => 'completed']);

            return $production->fresh(['product', 'recipe', 'details.rawMaterial']);
        });
    }
}
