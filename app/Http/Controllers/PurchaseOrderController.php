<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = PurchaseOrder::with(['supplier', 'creator'])
            ->orderByDesc('created_at');

        if ($request->search) {
            $query->where('nomor_po', 'like', "%{$request->search}%");
        }
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
            'supplier_id'            => 'required|exists:suppliers,id',
            'tanggal_po'             => 'required|date',
            'catatan'                => 'nullable|string',
            'diskon'                 => 'numeric|min:0',
            'pajak'                  => 'numeric|min:0',
            'details'                => 'required|array|min:1',
            'details.*.raw_material_id' => 'required|exists:raw_materials,id',
            'details.*.quantity'     => 'required|numeric|min:0.01',
            'details.*.unit_price'   => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($request) {
            $nomor = 'PO-' . now()->format('Ymd') . '-' . str_pad(PurchaseOrder::count() + 1, 4, '0', STR_PAD_LEFT);

            $subtotal = collect($request->details)
                ->sum(fn($d) => $d['quantity'] * $d['unit_price']);

            $diskon  = $request->diskon ?? 0;
            $pajak   = $request->pajak  ?? 0;
            $total   = $subtotal - $diskon + $pajak;

            $po = PurchaseOrder::create([
                'nomor_po'    => $nomor,
                'supplier_id' => $request->supplier_id,
                'tanggal_po'  => $request->tanggal_po,
                'status'      => 'draft',
                'subtotal'    => $subtotal,
                'diskon'      => $diskon,
                'pajak'       => $pajak,
                'total'       => $total,
                'catatan'     => $request->catatan,
                'created_by'  => $request->user()->id,
            ]);

            foreach ($request->details as $d) {
                PurchaseOrderDetail::create([
                    'purchase_order_id' => $po->id,
                    'raw_material_id'   => $d['raw_material_id'],
                    'quantity'          => $d['quantity'],
                    'unit_price'        => $d['unit_price'],
                    'subtotal'          => $d['quantity'] * $d['unit_price'],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Purchase Order berhasil dibuat',
                'data'    => $po->load(['supplier', 'details.rawMaterial']),
            ], 201);
        });
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        return response()->json([
            'success' => true,
            'data'    => $purchaseOrder->load(['supplier', 'creator', 'details.rawMaterial.unit']),
        ]);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        if (!in_array($purchaseOrder->status, ['draft'])) {
            return response()->json(['success' => false, 'message' => 'Hanya PO berstatus draft yang dapat diedit'], 422);
        }

        $request->validate([
            'supplier_id'               => 'required|exists:suppliers,id',
            'tanggal_po'                => 'required|date',
            'catatan'                   => 'nullable|string',
            'diskon'                    => 'numeric|min:0',
            'pajak'                     => 'numeric|min:0',
            'details'                   => 'required|array|min:1',
            'details.*.raw_material_id' => 'required|exists:raw_materials,id',
            'details.*.quantity'        => 'required|numeric|min:0.01',
            'details.*.unit_price'      => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($request, $purchaseOrder) {
            $subtotal = collect($request->details)
                ->sum(fn($d) => $d['quantity'] * $d['unit_price']);
            $diskon  = $request->diskon ?? 0;
            $pajak   = $request->pajak  ?? 0;

            $purchaseOrder->update([
                'supplier_id' => $request->supplier_id,
                'tanggal_po'  => $request->tanggal_po,
                'subtotal'    => $subtotal,
                'diskon'      => $diskon,
                'pajak'       => $pajak,
                'total'       => $subtotal - $diskon + $pajak,
                'catatan'     => $request->catatan,
            ]);

            $purchaseOrder->details()->delete();
            foreach ($request->details as $d) {
                PurchaseOrderDetail::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'raw_material_id'   => $d['raw_material_id'],
                    'quantity'          => $d['quantity'],
                    'unit_price'        => $d['unit_price'],
                    'subtotal'          => $d['quantity'] * $d['unit_price'],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Purchase Order berhasil diperbarui',
                'data'    => $purchaseOrder->load(['supplier', 'details.rawMaterial']),
            ]);
        });
    }

    public function submit(Request $request, PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'Hanya PO berstatus draft yang dapat disubmit'], 422);
        }
        $purchaseOrder->update(['status' => 'submitted']);
        return response()->json(['success' => true, 'message' => 'PO berhasil disubmit', 'data' => $purchaseOrder]);
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'submitted') {
            return response()->json(['success' => false, 'message' => 'Hanya PO berstatus submitted yang dapat diapprove'], 422);
        }
        $purchaseOrder->update(['status' => 'approved']);
        return response()->json(['success' => true, 'message' => 'PO berhasil diapprove', 'data' => $purchaseOrder]);
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder)
    {
        if (in_array($purchaseOrder->status, ['received', 'cancelled'])) {
            return response()->json(['success' => false, 'message' => 'PO tidak dapat dibatalkan'], 422);
        }
        $purchaseOrder->update(['status' => 'cancelled']);
        return response()->json(['success' => true, 'message' => 'PO berhasil dibatalkan', 'data' => $purchaseOrder]);
    }
}
