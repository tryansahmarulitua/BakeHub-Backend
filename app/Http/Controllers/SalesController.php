<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request)
    {
        $query = Sale::with(['customer', 'creator'])
            ->orderByDesc('sale_date')
            ->orderByDesc('id');

        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->from_date) {
            $query->where('sale_date', '>=', $request->from_date);
        }
        if ($request->to_date) {
            $query->where('sale_date', '<=', $request->to_date);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->paginate($request->get('per_page', 10)),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id'              => 'nullable|exists:customers,id',
            'sale_date'                => 'required|date',
            'discount'                 => 'numeric|min:0',
            'tax'                      => 'numeric|min:0',
            'payment_method'           => 'required|in:cash,transfer,qris,e_wallet',
            'payment_amount'           => 'required|numeric|min:0',
            'notes'                    => 'nullable|string',
            'details'                  => 'required|array|min:1',
            'details.*.product_id'     => 'required|exists:products,id',
            'details.*.quantity'       => 'required|numeric|min:0.01',
            'details.*.unit_price'     => 'required|numeric|min:0',
            'details.*.discount'       => 'numeric|min:0',
        ]);

        return DB::transaction(function () use ($request) {
            $invoice = 'INV-' . now()->format('Ymd') . '-' . str_pad(Sale::count() + 1, 4, '0', STR_PAD_LEFT);

            // Calculate totals
            $subtotal = collect($request->details)->sum(function ($d) {
                return ($d['quantity'] * $d['unit_price']) - ($d['discount'] ?? 0);
            });
            $discount = $request->discount ?? 0;
            $tax      = $request->tax ?? 0;
            $total    = $subtotal - $discount + $tax;
            $payment  = $request->payment_amount;
            $change   = max(0, $payment - $total);

            // BR-008: Cek stok sebelum proses
            foreach ($request->details as $d) {
                $product = Product::find($d['product_id']);
                if (!$product || (float) $product->stok < (float) $d['quantity']) {
                    return response()->json([
                        'success' => false,
                        'message' => "Stok produk {$product?->nama_produk} tidak mencukupi. Tersedia: " . ($product?->stok ?? 0),
                    ], 422);
                }
            }

            $sale = Sale::create([
                'invoice_number'  => $invoice,
                'customer_id'     => $request->customer_id,
                'sale_date'       => $request->sale_date,
                'subtotal'        => $subtotal,
                'discount'        => $discount,
                'tax'             => $tax,
                'total'           => $total,
                'payment_amount'  => $payment,
                'change_amount'   => $change,
                'payment_method'  => $request->payment_method,
                'payment_status'  => 'paid',
                'status'          => 'completed',
                'notes'           => $request->notes,
                'created_by'      => $request->user()->id,
            ]);

            // BR-007: Kurangi stok produk, BR-010: catat transaksi
            foreach ($request->details as $d) {
                $itemSubtotal = ($d['quantity'] * $d['unit_price']) - ($d['discount'] ?? 0);

                SaleDetail::create([
                    'sale_id'    => $sale->id,
                    'product_id' => $d['product_id'],
                    'quantity'   => $d['quantity'],
                    'unit_price' => $d['unit_price'],
                    'discount'   => $d['discount'] ?? 0,
                    'subtotal'   => $itemSubtotal,
                ]);

                $this->inventory->stockOut(
                    'product',
                    $d['product_id'],
                    (float) $d['quantity'],
                    'SALE_OUT',
                    'Sale',
                    $sale->id,
                    $request->user()->id,
                    $request->sale_date,
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Penjualan berhasil dicatat',
                'data'    => $sale->load(['customer', 'details.product']),
            ], 201);
        });
    }

    public function show(Sale $sale)
    {
        return response()->json([
            'success' => true,
            'data'    => $sale->load(['customer', 'creator', 'details.product.unit']),
        ]);
    }

    public function cancel(Request $request, Sale $sale)
    {
        if ($sale->status === 'cancelled') {
            return response()->json(['success' => false, 'message' => 'Penjualan sudah dibatalkan'], 422);
        }

        // BR-011: Cancellation via reversal (optional stock return logic here)
        $sale->update(['status' => 'cancelled']);

        return response()->json(['success' => true, 'message' => 'Penjualan dibatalkan', 'data' => $sale]);
    }
}
