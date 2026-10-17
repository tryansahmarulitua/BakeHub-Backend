<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use App\Models\RecipeDetail;
use App\Services\UnitConversionService;
use App\Services\RecipeCostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecipeController extends Controller
{
    public function __construct(
        private UnitConversionService $converter,
        private RecipeCostService $costService
    ) {}

    public function index(Request $request)
    {
        $query = Recipe::with(['product', 'details.rawMaterial', 'details.unit'])
            ->orderByDesc('created_at');

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        return response()->json([
            'success' => true,
            'data'    => $query->paginate($request->get('per_page', 10)),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id'                  => 'required|exists:products,id',
            'name'                        => 'required|string',
            'output_quantity'             => 'required|numeric|min:0.01',
            'status'                      => 'in:aktif,nonaktif',
            'details'                     => 'required|array|min:1',
            'details.*.raw_material_id'   => 'required|exists:raw_materials,id',
            'details.*.unit_id'           => 'required|exists:units,id',
            'details.*.quantity'          => 'required|numeric|min:0.001',
        ]);

        return DB::transaction(function () use ($request) {
            $recipe = Recipe::create([
                'product_id'      => $request->product_id,
                'name'            => $request->name,
                'output_quantity' => $request->output_quantity,
                'biaya_kemasan'   => $request->get('biaya_kemasan', 0),
                'overhead_percent'=> $request->get('overhead_percent', 10),
                'status'          => $request->status ?? 'aktif',
            ]);

            foreach ($request->details as $d) {
                RecipeDetail::create([
                    'recipe_id'       => $recipe->id,
                    'raw_material_id' => $d['raw_material_id'],
                    'unit_id'         => $d['unit_id'],
                    'quantity'        => $d['quantity'],
                ]);
            }

            // Auto-calculate costs and update product harga_modal
            $this->costService->calculateAndUpdate($recipe->fresh());

            return response()->json([
                'success' => true,
                'message' => 'Resep berhasil dibuat',
                'data'    => $recipe->load(['product', 'details.rawMaterial', 'details.unit']),
            ], 201);
        });
    }

    public function show(Recipe $recipe)
    {
        return response()->json([
            'success' => true,
            'data'    => $recipe->load(['product', 'details.rawMaterial.unit', 'details.unit']),
        ]);
    }

    public function update(Request $request, Recipe $recipe)
    {
        $request->validate([
            'product_id'                  => 'required|exists:products,id',
            'name'                        => 'required|string',
            'output_quantity'             => 'required|numeric|min:0.01',
            'status'                      => 'in:aktif,nonaktif',
            'details'                     => 'required|array|min:1',
            'details.*.raw_material_id'   => 'required|exists:raw_materials,id',
            'details.*.unit_id'           => 'required|exists:units,id',
            'details.*.quantity'          => 'required|numeric|min:0.001',
        ]);

        return DB::transaction(function () use ($request, $recipe) {
            $recipe->update([
                'product_id'      => $request->product_id,
                'name'            => $request->name,
                'output_quantity' => $request->output_quantity,
                'biaya_kemasan'   => $request->get('biaya_kemasan', $recipe->biaya_kemasan),
                'overhead_percent'=> $request->get('overhead_percent', $recipe->overhead_percent),
                'status'          => $request->status ?? $recipe->status,
            ]);

            $recipe->details()->delete();
            foreach ($request->details as $d) {
                RecipeDetail::create([
                    'recipe_id'       => $recipe->id,
                    'raw_material_id' => $d['raw_material_id'],
                    'unit_id'         => $d['unit_id'],
                    'quantity'        => $d['quantity'],
                ]);
            }

            // Recalculate costs and update product harga_modal
            $this->costService->calculateAndUpdate($recipe->fresh());

            return response()->json([
                'success' => true,
                'message' => 'Resep berhasil diperbarui',
                'data'    => $recipe->load(['product', 'details.rawMaterial', 'details.unit']),
            ]);
        });
    }

    public function destroy(Recipe $recipe)
    {
        $recipe->delete();
        return response()->json(['success' => true, 'message' => 'Resep berhasil dihapus']);
    }

    /** Preview kebutuhan bahan untuk qty tertentu */
    public function calculate(Request $request, Recipe $recipe)
    {
        $request->validate(['quantity' => 'required|numeric|min:0.01']);

        $factor = $request->quantity / $recipe->output_quantity;
        $needs  = $recipe->details->map(function ($d) use ($factor) {
            $rawMaterial    = $d->rawMaterial;
            $quantityNeeded = round($detailQuantity = $d->quantity * $factor, 4);

            // Konversi kebutuhan bahan ke satuan stok bahan baku
            $neededInStockUnit = $this->converter->convert(
                $quantityNeeded,
                $d->unit_id,
                $rawMaterial->unit_id
            );

            return [
                'nama_bahan'                  => $rawMaterial->nama_bahan,
                'unit'                        => $d->unit->name,
                'quantity_needed'             => $quantityNeeded,
                'stok_tersedia'               => (float) $rawMaterial->stok,
                'stok_unit'                   => $rawMaterial->unit?->name,
                'quantity_needed_in_stock'    => $neededInStockUnit,
                'cukup'                       => (float) $rawMaterial->stok >= $neededInStockUnit,
            ];
        });

        return response()->json(['success' => true, 'data' => $needs]);
    }
}
