<?php

namespace App\Http\Controllers;

use App\Models\ReturnDetail;
use App\Models\ReturnTransaction;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReturnController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request)
    {
        $query = ReturnTransaction::with(['creator', 'details'])
            ->orderByDesc('return_date')->orderByDesc('id');

        if ($request->return_type) {
            $query->where('return_type', $request->return_type);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->paginate($request->get('per_page', 10)),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'return_type'              => 'required|in:purchase_return,sales_return',
            'reference_id'             => 'required|integer',
            'return_date'              => 'required|date',
            'reason'                   => 'required|string',
            'notes'                    => 'nullable|string',
            'details'                  => 'required|array|min:1',
            'details.*.item_type'      => 'required|in:raw_material,product',
            'details.*.item_id'        => 'required|integer',
            'details.*.quantity'       => 'required|numeric|min:0.01',
            'details.*.condition'      => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request) {
            $number = 'RTN-' . now()->format('Ymd') . '-' . str_pad(ReturnTransaction::count() + 1, 4, '0', STR_PAD_LEFT);

            $rtn = ReturnTransaction::create([
                'return_number' => $number,
                'return_type'   => $request->return_type,
                'reference_id'  => $request->reference_id,
                'return_date'   => $request->return_date,
                'status'        => 'pending',
                'reason'        => $request->reason,
                'notes'         => $request->notes,
                'created_by'    => $request->user()->id,
            ]);

            foreach ($request->details as $d) {
                ReturnDetail::create([
                    'return_transaction_id' => $rtn->id,
                    'item_type'             => $d['item_type'],
                    'item_id'               => $d['item_id'],
                    'quantity'              => $d['quantity'],
                    'condition'             => $d['condition'] ?? null,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Return berhasil dibuat',
                'data'    => $rtn->load('details'),
            ], 201);
        });
    }

    public function show(ReturnTransaction $return)
    {
        return response()->json([
            'success' => true,
            'data'    => $return->load(['creator', 'details']),
        ]);
    }

    public function approve(Request $request, ReturnTransaction $return)
    {
        if ($return->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Return tidak dapat diapprove'], 422);
        }
        $return->update(['status' => 'approved']);
        return response()->json(['success' => true, 'message' => 'Return diapprove', 'data' => $return]);
    }

    public function complete(Request $request, ReturnTransaction $return)
    {
        if ($return->status !== 'approved') {
            return response()->json(['success' => false, 'message' => 'Return belum diapprove'], 422);
        }

        return DB::transaction(function () use ($request, $return) {
            foreach ($return->details as $d) {
                // BR-018: produk retur masuk kembali jika kondisi baik
                if ($d->condition !== 'damaged') {
                    $txType = $return->return_type === 'purchase_return' ? 'RETURN_OUT' : 'RETURN_IN';
                    $method = $return->return_type === 'purchase_return' ? 'stockOut' : 'stockIn';

                    $this->inventory->$method(
                        $d->item_type,
                        $d->item_id,
                        (float) $d->quantity,
                        $txType,
                        'ReturnTransaction',
                        $return->id,
                        $request->user()->id,
                        $return->return_date->format('Y-m-d'),
                    );
                }
            }

            $return->update(['status' => 'completed']);

            return response()->json(['success' => true, 'message' => 'Return selesai, stok diperbarui', 'data' => $return]);
        });
    }
}
