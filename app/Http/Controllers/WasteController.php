<?php

namespace App\Http\Controllers;

use App\Models\Waste;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WasteController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request)
    {
        $query = Waste::with('creator')->orderByDesc('waste_date')->orderByDesc('id');

        if ($request->item_type) {
            $query->where('item_type', $request->item_type);
        }
        if ($request->from_date) {
            $query->where('waste_date', '>=', $request->from_date);
        }
        if ($request->to_date) {
            $query->where('waste_date', '<=', $request->to_date);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->paginate($request->get('per_page', 10)),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'item_type'      => 'required|in:raw_material,product',
            'item_id'        => 'required|integer',
            'waste_date'     => 'required|date',
            'quantity'       => 'required|numeric|min:0.01',
            'reason'         => 'required|in:expired,damaged,burned,failed_production,unsold,spill,other',
            'estimated_loss' => 'required|numeric|min:0',
            'notes'          => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request) {
            $number = 'WST-' . now()->format('Ymd') . '-' . str_pad(Waste::count() + 1, 4, '0', STR_PAD_LEFT);

            $waste = Waste::create([
                'waste_number'   => $number,
                'waste_date'     => $request->waste_date,
                'item_type'      => $request->item_type,
                'item_id'        => $request->item_id,
                'quantity'       => $request->quantity,
                'reason'         => $request->reason,
                'estimated_loss' => $request->estimated_loss,
                'notes'          => $request->notes,
                'created_by'     => $request->user()->id,
            ]);

            // BR-013: setiap waste harus punya alasan — sudah divalidasi
            // Stock out (WASTE_OUT)
            $this->inventory->stockOut(
                $request->item_type,
                $request->item_id,
                (float) $request->quantity,
                'WASTE_OUT',
                'Waste',
                $waste->id,
                $request->user()->id,
                $request->waste_date,
            );

            return response()->json([
                'success' => true,
                'message' => 'Waste berhasil dicatat, stok diperbarui',
                'data'    => $waste,
            ], 201);
        });
    }

    public function show(Waste $waste)
    {
        return response()->json([
            'success' => true,
            'data'    => $waste->load('creator'),
        ]);
    }
}
