<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\ReturnDetail;
use App\Models\ReturnTransaction;
use App\Models\Sale;
use App\Models\Waste;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request)
    {
        $query = ReturnTransaction::with(['creator', 'details'])
            ->orderByDesc('return_date')->orderByDesc('id');
        if ($request->return_type) $query->where('return_type', $request->return_type);

        return response()->json(['success' => true, 'data' => $query->paginate($request->get('per_page', 10))]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'return_type' => 'required|in:purchase_return,sales_return',
            'reference_id' => 'required|integer', 'return_date' => 'required|date',
            'reason' => 'required|string', 'notes' => 'nullable|string',
            'details' => 'required|array|min:1',
            'details.*.item_type' => 'required|in:raw_material,product',
            'details.*.item_id' => 'required|integer', 'details.*.quantity' => 'required|numeric|min:0.01',
            'details.*.condition' => 'required|in:good,damaged',
        ]);

        $source = $request->return_type === 'sales_return'
            ? Sale::with('details')->find($request->reference_id)
            : PurchaseOrder::with('details')->find($request->reference_id);
        $validStatuses = $request->return_type === 'sales_return' ? ['completed'] : ['approved', 'ordered', 'partially_received', 'received'];
        if (! $source || ! in_array($source->status, $validStatuses, true)) {
            throw ValidationException::withMessages(['reference_id' => 'Transaksi asal tidak ditemukan atau belum dapat diretur.']);
        }

        $key = $request->return_type === 'sales_return' ? 'product_id' : 'raw_material_id';
        $allowed = $source->details->groupBy($key)->map(fn ($items) => $items->sum('quantity'));
        $returned = ReturnTransaction::where('return_type', $request->return_type)
            ->where('reference_id', $source->id)->whereIn('status', ['pending', 'approved', 'completed'])
            ->with('details')->get()->flatMap->details->groupBy('item_id')
            ->map(fn ($items) => $items->sum('quantity'));
        foreach ($request->details as $detail) {
            if ($detail['item_type'] !== ($request->return_type === 'sales_return' ? 'product' : 'raw_material')) {
                throw ValidationException::withMessages(['details' => 'Jenis item tidak sesuai transaksi asal.']);
            }
            $limit = (float) ($allowed[$detail['item_id']] ?? 0) - (float) ($returned[$detail['item_id']] ?? 0);
            if ((float) $detail['quantity'] > $limit) {
                throw ValidationException::withMessages(['details' => "Quantity item {$detail['item_id']} melebihi sisa yang dapat diretur ({$limit})."]);
            }
        }

        return DB::transaction(function () use ($request) {
            $number = 'RTN-' . now()->format('Ymd') . '-' . str_pad(DB::table('return_transactions')->lockForUpdate()->count() + 1, 4, '0', STR_PAD_LEFT);
            $rtn = ReturnTransaction::create([
                'return_number' => $number, 'return_type' => $request->return_type,
                'reference_id' => $request->reference_id, 'return_date' => $request->return_date,
                'status' => 'pending', 'reason' => $request->reason, 'notes' => $request->notes,
                'created_by' => $request->user()->id,
            ]);
            foreach ($request->details as $d) $rtn->details()->create($d);
            return response()->json(['success' => true, 'message' => 'Return berhasil dibuat', 'data' => $rtn->load('details')], 201);
        });
    }

    public function show(ReturnTransaction $return)
    {
        return response()->json(['success' => true, 'data' => $return->load(['creator', 'details'])]);
    }

    public function approve(Request $request, ReturnTransaction $return)
    {
        if ($return->status !== 'pending') return response()->json(['success' => false, 'message' => 'Return tidak dapat diapprove'], 422);
        $return->update(['status' => 'approved']);
        return response()->json(['success' => true, 'message' => 'Return diapprove', 'data' => $return]);
    }

    public function reject(Request $request, ReturnTransaction $return)
    {
        if (! in_array($return->status, ['pending', 'approved'], true)) return response()->json(['success' => false, 'message' => 'Return tidak dapat ditolak'], 422);
        $return->update(['status' => 'rejected']);
        return response()->json(['success' => true, 'message' => 'Return ditolak', 'data' => $return]);
    }

    public function complete(Request $request, ReturnTransaction $return)
    {
        if ($return->status !== 'approved') return response()->json(['success' => false, 'message' => 'Return belum diapprove'], 422);

        try {
            return DB::transaction(function () use ($request, $return) {
                foreach ($return->details as $d) {
                    if ($return->return_type === 'sales_return' && $d->condition === 'damaged') {
                        $waste = Waste::create([
                            'waste_number' => 'WST-' . now()->format('Ymd') . '-' . str_pad(Waste::count() + 1, 4, '0', STR_PAD_LEFT),
                            'waste_date' => $return->return_date, 'item_type' => $d->item_type, 'item_id' => $d->item_id,
                            'quantity' => $d->quantity, 'reason' => 'damaged', 'estimated_loss' => 0,
                            'notes' => 'Barang rusak dari return ' . $return->return_number, 'created_by' => $request->user()->id,
                        ]);
                        continue;
                    }
                    $method = $return->return_type === 'purchase_return' ? 'stockOut' : 'stockIn';
                    $this->inventory->$method($d->item_type, $d->item_id, (float) $d->quantity,
                        $return->return_type === 'purchase_return' ? 'RETURN_OUT' : 'RETURN_IN',
                        'ReturnTransaction', $return->id, $request->user()->id, $return->return_date->format('Y-m-d'));
                }
                $return->update(['status' => 'completed']);
                return response()->json(['success' => true, 'message' => 'Return selesai, stok diperbarui', 'data' => $return->load('details')]);
            });
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
