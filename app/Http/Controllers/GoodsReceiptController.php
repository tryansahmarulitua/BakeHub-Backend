<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptDetail;
use App\Models\PurchaseOrder;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GoodsReceiptController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request)
    {
        $query = GoodsReceipt::with(['purchaseOrder.supplier', 'receiver', 'details.rawMaterial.unit'])
            ->orderByDesc('created_at');

        if ($request->purchase_order_id) {
            $query->where('purchase_order_id', $request->purchase_order_id);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->paginate($request->get('per_page', 10)),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'purchase_order_id'          => 'required|exists:purchase_orders,id',
            'tanggal_terima'             => 'required|date',
            'catatan'                    => 'nullable|string',
            'details'                    => 'required|array|min:1',
            'details.*.raw_material_id'  => 'required|exists:raw_materials,id',
            'details.*.quantity_ordered' => 'required|numeric|min:0',
            'details.*.quantity_received'=> 'required|numeric|min:0',
            'details.*.quantity_rejected'=> 'numeric|min:0',
            'details.*.expired_date'     => 'nullable|date',
        ]);

        $po = PurchaseOrder::findOrFail($request->purchase_order_id);
        if (!in_array($po->status, ['approved', 'ordered', 'partially_received'])) {
            return response()->json(['success' => false, 'message' => 'Status PO tidak memungkinkan penerimaan barang'], 422);
        }

        return DB::transaction(function () use ($request, $po) {
            $nomor = 'GR-' . now()->format('Ymd') . '-' . str_pad(GoodsReceipt::count() + 1, 4, '0', STR_PAD_LEFT);

            $gr = GoodsReceipt::create([
                'nomor_gr'          => $nomor,
                'purchase_order_id' => $po->id,
                'tanggal_terima'    => $request->tanggal_terima,
                'received_by'       => $request->user()->id,
                'status'            => 'draft',
                'catatan'           => $request->catatan,
            ]);

            foreach ($request->details as $d) {
                GoodsReceiptDetail::create([
                    'goods_receipt_id'  => $gr->id,
                    'raw_material_id'   => $d['raw_material_id'],
                    'quantity_ordered'  => $d['quantity_ordered'],
                    'quantity_received' => $d['quantity_received'],
                    'quantity_rejected' => $d['quantity_rejected'] ?? 0,
                    'expired_date'      => $d['expired_date'] ?? null,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Goods Receipt berhasil dibuat',
                'data'    => $gr->load(['purchaseOrder.supplier', 'receiver', 'details.rawMaterial.unit']),
            ], 201);
        });
    }

    public function show(GoodsReceipt $goodsReceipt)
    {
        return response()->json([
            'success' => true,
            'data'    => $goodsReceipt->load(['purchaseOrder.supplier', 'receiver', 'details.rawMaterial.unit']),
        ]);
    }

    public function complete(Request $request, GoodsReceipt $goodsReceipt)
    {
        if ($goodsReceipt->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'GR sudah selesai atau tidak dapat dikonfirmasi'], 422);
        }

        return DB::transaction(function () use ($request, $goodsReceipt) {
            // Stock in untuk setiap item yang diterima
            foreach ($goodsReceipt->details as $detail) {
                if ($detail->quantity_received > 0) {
                    $this->inventory->stockIn(
                        'raw_material',
                        $detail->raw_material_id,
                        (float) $detail->quantity_received,
                        'PURCHASE_IN',
                        'GoodsReceipt',
                        $goodsReceipt->id,
                        $request->user()->id,
                        $goodsReceipt->tanggal_terima->format('Y-m-d'),
                    );
                }
            }

            $goodsReceipt->update(['status' => 'completed']);

            // Update status PO
            $po = $goodsReceipt->purchaseOrder;
            $allDetails = $po->details;
            $totalOrdered  = $allDetails->sum('quantity');
            $totalReceived = $po->goodsReceipts()
                ->where('status', 'completed')
                ->with('details')
                ->get()
                ->flatMap->details
                ->sum('quantity_received');

            if ($totalReceived >= $totalOrdered) {
                $po->update(['status' => 'received']);
            } else {
                $po->update(['status' => 'partially_received']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Goods Receipt berhasil dikonfirmasi, stok telah diperbarui',
                'data'    => $goodsReceipt->load(['purchaseOrder.supplier', 'receiver', 'details.rawMaterial.unit']),
            ]);
        });
    }
}
