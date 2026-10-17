<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Models\ProductionDetail;
use App\Services\ProductionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductionController extends Controller
{
    public function __construct(private ProductionService $service) {}

    public function index(Request $request)
    {
        $query = Production::with(['product', 'recipe', 'creator'])
            ->orderByDesc('production_date')
            ->orderByDesc('id');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->paginate($request->get('per_page', 10)),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id'      => 'required|exists:products,id',
            'recipe_id'       => 'required|exists:recipes,id',
            'quantity'        => 'required|numeric|min:0.01',
            'production_date' => 'required|date',
            'notes'           => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request) {
            $nomor = 'PROD-' . now()->format('Ymd') . '-' . str_pad(Production::count() + 1, 4, '0', STR_PAD_LEFT);

            $production = Production::create([
                'nomor_produksi'  => $nomor,
                'product_id'      => $request->product_id,
                'recipe_id'       => $request->recipe_id,
                'quantity'        => $request->quantity,
                'production_date' => $request->production_date,
                'status'          => 'draft',
                'notes'           => $request->notes,
                'created_by'      => $request->user()->id,
            ]);

            // Pre-calculate needed materials and save to production_details
            $materials = $this->service->calculateMaterials($production->recipe, (float) $request->quantity);
            foreach ($materials as $m) {
                ProductionDetail::create([
                    'production_id'   => $production->id,
                    'raw_material_id' => $m['raw_material_id'],
                    'unit_id'         => $m['unit_id'],
                    'quantity_needed' => $m['quantity_needed'],
                    'quantity_used'   => 0,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Produksi berhasil dibuat',
                'data'    => $production->load(['product', 'recipe', 'details.rawMaterial.unit']),
            ], 201);
        });
    }

    public function show(Production $production)
    {
        return response()->json([
            'success' => true,
            'data'    => $production->load(['product', 'recipe.details', 'creator', 'details.rawMaterial.unit']),
        ]);
    }

    public function start(Request $request, Production $production)
    {
        if ($production->status !== 'draft' && $production->status !== 'planned') {
            return response()->json(['success' => false, 'message' => 'Produksi tidak dapat dimulai'], 422);
        }
        $production->update(['status' => 'processing']);
        return response()->json(['success' => true, 'message' => 'Produksi dimulai', 'data' => $production]);
    }

    public function complete(Request $request, Production $production)
    {
        try {
            $completed = $this->service->complete($production, $request->user()->id);
            return response()->json([
                'success' => true,
                'message' => 'Produksi selesai. Stok bahan berkurang, stok produk bertambah.',
                'data'    => $completed,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function cancel(Request $request, Production $production)
    {
        if (in_array($production->status, ['completed', 'cancelled'])) {
            return response()->json(['success' => false, 'message' => 'Produksi tidak dapat dibatalkan'], 422);
        }
        $production->update(['status' => 'cancelled']);
        return response()->json(['success' => true, 'message' => 'Produksi dibatalkan', 'data' => $production]);
    }
}
