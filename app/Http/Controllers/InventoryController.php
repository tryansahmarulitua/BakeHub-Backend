<?php

namespace App\Http\Controllers;

use App\Exports\InventoryExport;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Services\InventoryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class InventoryController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function rawMaterials(Request $request)
    {
        $query = RawMaterial::with(['category', 'unit', 'supplier'])
            ->orderBy('nama_bahan');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->has('search')) {
            $query->where('nama_bahan', 'like', "%{$request->search}%")
                ->orWhere('kode_bahan', 'like', "%{$request->search}%");
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->get('per_page', 20)),
        ]);
    }

    public function products(Request $request)
    {
        $query = Product::with(['category', 'unit'])->orderBy('nama_produk');

        if ($request->has('search')) {
            $query->where('nama_produk', 'like', "%{$request->search}%")
                ->orWhere('kode_produk', 'like', "%{$request->search}%");
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->get('per_page', 20)),
        ]);
    }

    public function lowStock(Request $request)
    {
        $query = RawMaterial::with(['category', 'unit'])
            ->orderByRaw('(stok - stok_minimum) ASC');

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    public function expiring(Request $request)
    {
        $rawMaterials = RawMaterial::with(['category', 'unit', 'supplier'])
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'item_type' => 'Bahan Baku',
                    'kode' => $item->kode_bahan,
                    'kode_bahan' => $item->kode_bahan,
                    'nama' => $item->nama_bahan,
                    'nama_bahan' => $item->nama_bahan,
                    'stok' => $item->stok,
                    'unit' => $item->unit,
                    'tanggal_kedaluwarsa' => $item->tanggal_kedaluwarsa ? substr((string) $item->tanggal_kedaluwarsa, 0, 10) : null,
                    'category' => $item->category,
                    'supplier' => $item->supplier,
                ];
            });

        $products = Product::with(['category', 'unit'])
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'item_type' => 'Produk',
                    'kode' => $item->kode_produk,
                    'kode_bahan' => $item->kode_produk,
                    'nama' => $item->nama_produk,
                    'nama_bahan' => $item->nama_produk,
                    'stok' => $item->stok,
                    'unit' => $item->unit,
                    'tanggal_kedaluwarsa' => $item->tanggal_kedaluwarsa ? substr((string) $item->tanggal_kedaluwarsa, 0, 10) : null,
                    'category' => $item->category,
                    'supplier' => null,
                ];
            });

        $merged = $rawMaterials->concat($products)
            ->sortBy(function ($item) {
                return $item['tanggal_kedaluwarsa'] ?? '9999-12-31';
            })
            ->values();

        if ($request->has('search') && $request->search) {
            $search = strtolower($request->search);
            $merged = $merged->filter(function ($item) use ($search) {
                return str_contains(strtolower($item['nama']), $search) || str_contains(strtolower($item['kode']), $search);
            })->values();
        }

        return response()->json([
            'success' => true,
            'data' => $merged,
        ]);
    }

    public function transactions(Request $request)
    {
        $query = InventoryTransaction::with(['creator'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        if ($request->has('item_type')) {
            $query->where('item_type', $request->item_type);
        }
        if ($request->has('item_id')) {
            $query->where('item_id', $request->item_id);
        }
        if ($request->has('reference_type')) {
            $query->where('reference_type', $request->reference_type);
        }
        if ($request->has('from_date')) {
            $query->where('transaction_date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('transaction_date', '<=', $request->to_date);
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->get('per_page', 20)),
        ]);
    }

    public function adjustment(Request $request)
    {
        $request->validate([
            'item_type' => 'required|in:raw_material,product',
            'item_id' => 'required|integer',
            'quantity' => 'required|numeric|min:0.01',
            'type' => 'required|in:increase,decrease',
            'reason' => 'required|string',
            'date' => 'required|date',
        ]);

        return DB::transaction(function () use ($request) {
            $txType = $request->type === 'increase' ? 'ADJUSTMENT_IN' : 'ADJUSTMENT_OUT';

            if ($request->type === 'increase') {
                $tx = $this->inventory->stockIn(
                    $request->item_type,
                    $request->item_id,
                    (float) $request->quantity,
                    $txType,
                    'Adjustment',
                    0,
                    $request->user()->id,
                    $request->date,
                );
            } else {
                $tx = $this->inventory->stockOut(
                    $request->item_type,
                    $request->item_id,
                    (float) $request->quantity,
                    $txType,
                    'Adjustment',
                    0,
                    $request->user()->id,
                    $request->date,
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Penyesuaian stok berhasil',
                'data' => $tx,
            ]);
        });
    }

    public function stockCard(Request $request)
    {
        $request->validate([
            'item_type' => 'nullable|in:raw_material,product',
            'item_id' => 'nullable|integer',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
        ]);

        $query = InventoryTransaction::query()
            ->orderBy('transaction_date')
            ->orderBy('id');

        if ($request->filled('item_type')) {
            $query->where('item_type', $request->item_type);
        }
        if ($request->filled('item_id')) {
            $query->where('item_id', $request->item_id);
        }
        if ($request->from_date) {
            $query->where('transaction_date', '>=', $request->from_date);
        }
        if ($request->to_date) {
            $query->where('transaction_date', '<=', $request->to_date);
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->get('per_page', 50)),
        ]);
    }

    public function exportTransactions(Request $request)
    {
        $query = InventoryTransaction::with(['creator'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        if ($request->has('item_type')) {
            $query->where('item_type', $request->item_type);
        }
        if ($request->has('item_id')) {
            $query->where('item_id', $request->item_id);
        }
        if ($request->has('reference_type')) {
            $query->where('reference_type', $request->reference_type);
        }
        if ($request->has('from_date')) {
            $query->where('transaction_date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('transaction_date', '<=', $request->to_date);
        }

        $data = $query->get()->map(fn ($t) => [
            'Tanggal' => $t->transaction_date,
            'Tipe Item' => $t->item_type === 'raw_material' ? 'Bahan Baku' : 'Produk',
            'Item ID' => $t->item_id,
            'Tipe Transaksi' => $t->transaction_type,
            'Kuantitas' => $t->quantity,
            'Saldo' => $t->balance_after,
            'Referensi' => $t->reference_type,
            'Ref ID' => $t->reference_id,
            'Catatan' => $t->notes,
            'Dibuat Oleh' => $t->creator?->name,
        ]);

        $format = $request->get('format', 'xlsx');

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.transactions', ['data' => $data]);

            return $pdf->download('laporan-transaksi-'.now()->format('Y-m-d').'.pdf');
        }

        return Excel::download(new InventoryExport($data, 'Transaksi Inventori'), 'laporan-transaksi-'.now()->format('Y-m-d').'.xlsx');
    }

    public function exportStockCard(Request $request)
    {
        $request->validate([
            'item_type' => 'nullable|in:raw_material,product',
            'item_id' => 'nullable|integer',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
        ]);

        $query = InventoryTransaction::query()
            ->orderBy('transaction_date')
            ->orderBy('id');

        if ($request->filled('item_type')) {
            $query->where('item_type', $request->item_type);
        }
        if ($request->filled('item_id')) {
            $query->where('item_id', $request->item_id);
        }
        if ($request->from_date) {
            $query->where('transaction_date', '>=', $request->from_date);
        }
        if ($request->to_date) {
            $query->where('transaction_date', '<=', $request->to_date);
        }

        $data = $query->get()->map(fn ($t) => [
            'Tanggal' => $t->transaction_date,
            'Tipe Transaksi' => $t->transaction_type,
            'Masuk' => in_array($t->transaction_type, ['PURCHASE', 'PRODUCTION', 'RETURN_IN', 'ADJUSTMENT_IN']) ? $t->quantity : 0,
            'Keluar' => in_array($t->transaction_type, ['PRODUCTION', 'SALE', 'WASTE', 'RETURN_OUT', 'ADJUSTMENT_OUT']) ? $t->quantity : 0,
            'Saldo' => $t->balance_after,
            'Referensi' => $t->reference_type.' #'.$t->reference_id,
            'Catatan' => $t->notes,
        ]);

        $format = $request->get('format', 'xlsx');

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.stock-card', ['data' => $data, 'item_type' => $request->item_type, 'item_id' => $request->item_id]);

            return $pdf->download('kartu-stok-'.now()->format('Y-m-d').'.pdf');
        }

        return Excel::download(new InventoryExport($data, 'Kartu Stok'), 'kartu-stok-'.now()->format('Y-m-d').'.xlsx');
    }

    public function exportLowStock(Request $request)
    {
        $data = RawMaterial::with(['category', 'unit'])
            ->orderByRaw('(stok - stok_minimum) ASC')
            ->get()
            ->map(fn ($r) => [
                'Kode' => $r->kode_bahan,
                'Nama' => $r->nama_bahan,
                'Kategori' => $r->category?->nama_kategori,
                'Stok Saat Ini' => $r->stok,
                'Stok Minimum' => $r->stok_minimum,
                'Selisih' => $r->stok - $r->stok_minimum,
                'Satuan' => $r->unit?->nama_satuan,
                'Supplier' => $r->supplier?->nama_supplier,
            ]);

        $format = $request->get('format', 'xlsx');

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.low-stock', ['data' => $data]);

            return $pdf->download('laporan-stok-rendah-'.now()->format('Y-m-d').'.pdf');
        }

        return Excel::download(new InventoryExport($data, 'Stok Rendah'), 'laporan-stok-rendah-'.now()->format('Y-m-d').'.xlsx');
    }

    public function exportExpiring(Request $request)
    {
        $rawMaterials = RawMaterial::with(['category', 'unit', 'supplier'])
            ->get()
            ->map(function ($item) {
                return [
                    'Tipe' => 'Bahan Baku',
                    'Kode' => $item->kode_bahan,
                    'Nama' => $item->nama_bahan,
                    'Stok' => $item->stok,
                    'Satuan' => $item->unit?->nama_satuan,
                    'Tanggal Kedaluwarsa' => $item->tanggal_kedaluwarsa ? substr((string) $item->tanggal_kedaluwarsa, 0, 10) : '-',
                    'Kategori' => $item->category?->nama_kategori,
                    'Supplier' => $item->supplier?->nama_supplier,
                ];
            });

        $products = Product::with(['category', 'unit'])
            ->get()
            ->map(function ($item) {
                return [
                    'Tipe' => 'Produk',
                    'Kode' => $item->kode_produk,
                    'Nama' => $item->nama_produk,
                    'Stok' => $item->stok,
                    'Satuan' => $item->unit?->nama_satuan,
                    'Tanggal Kedaluwarsa' => $item->tanggal_kedaluwarsa ? substr((string) $item->tanggal_kedaluwarsa, 0, 10) : '-',
                    'Kategori' => $item->category?->nama_kategori,
                    'Supplier' => '-',
                ];
            });

        $data = $rawMaterials->concat($products)
            ->sortBy(fn ($item) => $item['Tanggal Kedaluwarsa'] === '-' ? '9999-12-31' : $item['Tanggal Kedaluwarsa'])
            ->values();

        if ($request->has('search') && $request->search) {
            $search = strtolower($request->search);
            $data = $data->filter(fn ($item) => str_contains(strtolower($item['Nama']), $search) || str_contains(strtolower($item['Kode']), $search))->values();
        }

        $format = $request->get('format', 'xlsx');

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.expiring', ['data' => $data]);

            return $pdf->download('laporan-kedaluwarsa-'.now()->format('Y-m-d').'.pdf');
        }

        return Excel::download(new InventoryExport($data, 'Item Kedaluwarsa'), 'laporan-kedaluwarsa-'.now()->format('Y-m-d').'.xlsx');
    }
}
