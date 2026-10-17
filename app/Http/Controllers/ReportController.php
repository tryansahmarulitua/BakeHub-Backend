<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransaction;
use App\Models\Production;
use App\Models\RawMaterial;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Waste;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ReportExport;

class ReportController extends Controller
{
    public function dashboard(Request $request)
    {
        $month = $request->get('month', now()->format('Y-m'));
        [$year, $mon] = explode('-', $month);

        $totalSales = Sale::where('status', 'completed')
            ->whereYear('sale_date', $year)->whereMonth('sale_date', $mon)
            ->sum('total');

        $totalProductions = Production::where('status', 'completed')
            ->whereYear('production_date', $year)->whereMonth('production_date', $mon)
            ->sum('quantity');

        $totalWasteValue = Waste::whereYear('waste_date', $year)->whereMonth('waste_date', $mon)
            ->sum('estimated_loss');

        $inventoryValue = RawMaterial::selectRaw('SUM(stok * harga_beli) as value')->value('value') ?? 0;

        $lowStockCount  = RawMaterial::whereColumn('stok', '<=', 'stok_minimum')->where('stok_minimum', '>', 0)->count();
        $expiringCount  = RawMaterial::whereNotNull('tanggal_kedaluwarsa')
            ->where('tanggal_kedaluwarsa', '<=', now()->addDays(14)->format('Y-m-d'))
            ->where('stok', '>', 0)->count();

        // Top 5 produk terlaris bulan ini
        $topProducts = SaleDetail::select('product_id', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(subtotal) as total_revenue'))
            ->whereHas('sale', fn($q) => $q->where('status', 'completed')
                ->whereYear('sale_date', $year)->whereMonth('sale_date', $mon))
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->with('product:id,nama_produk,kode_produk')
            ->limit(5)
            ->get();

        // Sales chart — per-hari bulan ini
        $salesChart = Sale::where('status', 'completed')
            ->whereYear('sale_date', $year)->whereMonth('sale_date', $mon)
            ->selectRaw('DATE(sale_date) as date, SUM(total) as total')
            ->groupBy('date')->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => compact(
                'totalSales', 'totalProductions', 'totalWasteValue',
                'inventoryValue', 'lowStockCount', 'expiringCount',
                'topProducts', 'salesChart',
            ),
        ]);
    }

    public function sales(Request $request)
    {
        $from = $request->get('from_date', now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->get('to_date', now()->format('Y-m-d'));

        $sales = Sale::with(['customer', 'details.product'])
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$from, $to])
            ->orderBy('sale_date')
            ->get();

        $summary = [
            'total_transactions' => $sales->count(),
            'total_revenue'      => $sales->sum('total'),
            'total_items'        => $sales->flatMap->details->sum('quantity'),
        ];

        $byPayment = $sales->groupBy('payment_method')->map(fn($g) => [
            'count'   => $g->count(),
            'revenue' => $g->sum('total'),
        ]);

        $topProducts = SaleDetail::select('product_id', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(subtotal) as revenue'))
            ->whereHas('sale', fn($q) => $q->where('status', 'completed')->whereBetween('sale_date', [$from, $to]))
            ->groupBy('product_id')->orderByDesc('total_qty')
            ->with('product:id,nama_produk')->limit(10)->get();

        return response()->json([
            'success' => true,
            'data'    => compact('summary', 'byPayment', 'topProducts'),
        ]);
    }

    public function inventory(Request $request)
    {
        $rawMaterials = RawMaterial::with(['category', 'unit', 'supplier'])
            ->selectRaw('*, (stok * harga_beli) as nilai_stok')
            ->orderBy('nama_bahan')
            ->get();

        $totalValue = $rawMaterials->sum('nilai_stok');
        $lowStock   = $rawMaterials->filter(fn($r) => $r->stok <= $r->stok_minimum && $r->stok_minimum > 0)->values();
        $expiring   = $rawMaterials->filter(fn($r) => $r->tanggal_kedaluwarsa && now()->diffInDays($r->tanggal_kedaluwarsa, false) <= 14)->values();

        return response()->json([
            'success' => true,
            'data'    => compact('rawMaterials', 'totalValue', 'lowStock', 'expiring'),
        ]);
    }

    public function production(Request $request)
    {
        $from = $request->get('from_date', now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->get('to_date', now()->format('Y-m-d'));

        $productions = Production::with(['product', 'recipe'])
            ->where('status', 'completed')
            ->whereBetween('production_date', [$from, $to])
            ->orderBy('production_date')
            ->get();

        $summary = [
            'total_orders'  => $productions->count(),
            'total_qty'     => $productions->sum('quantity'),
            'by_product'    => $productions->groupBy('product_id')->map(fn($g) => [
                'product'   => $g->first()->product->nama_produk,
                'total_qty' => $g->sum('quantity'),
            ])->values(),
        ];

        return response()->json(['success' => true, 'data' => compact('productions', 'summary')]);
    }

    public function waste(Request $request)
    {
        $from = $request->get('from_date', now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->get('to_date', now()->format('Y-m-d'));

        $wastes = Waste::with('creator')
            ->whereBetween('waste_date', [$from, $to])
            ->orderBy('waste_date')
            ->get();

        $summary = [
            'total_records'        => $wastes->count(),
            'total_estimated_loss' => $wastes->sum('estimated_loss'),
            'by_reason'            => $wastes->groupBy('reason')->map(fn($g) => [
                'count' => $g->count(),
                'loss'  => $g->sum('estimated_loss'),
            ]),
            'by_item_type' => $wastes->groupBy('item_type')->map(fn($g) => [
                'count' => $g->count(),
                'loss'  => $g->sum('estimated_loss'),
            ]),
        ];

        return response()->json(['success' => true, 'data' => compact('wastes', 'summary')]);
    }

    public function exportSalesPdf(Request $request)
    {
        $from = $request->get('from_date', now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->get('to_date', now()->format('Y-m-d'));

        $sales = Sale::with(['customer', 'details.product'])
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$from, $to])
            ->orderBy('sale_date')
            ->get();

        $summary = [
            'total_transactions' => $sales->count(),
            'total_revenue'      => $sales->sum('total'),
            'total_items'        => $sales->flatMap->details->sum('quantity'),
        ];

        $topProducts = SaleDetail::select('product_id', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(subtotal) as revenue'))
            ->whereHas('sale', fn($q) => $q->where('status', 'completed')->whereBetween('sale_date', [$from, $to]))
            ->groupBy('product_id')->orderByDesc('total_qty')
            ->with('product:id,nama_produk')->limit(10)->get();

        $fromDate = $from;
        $toDate = $to;
        $pdf = Pdf::loadView('reports.sales', compact('sales', 'summary', 'topProducts', 'fromDate', 'toDate'));
        return $pdf->download("laporan-penjualan-{$from}-{$to}.pdf");
    }

    public function exportSalesExcel(Request $request)
    {
        $from = $request->get('from_date', now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->get('to_date', now()->format('Y-m-d'));

        $sales = Sale::with(['customer'])
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$from, $to])
            ->orderBy('sale_date')
            ->get();

        $data = $sales->map(fn($s, $i) => [
            $i + 1,
            $s->invoice_number,
            $s->sale_date,
            $s->customer?->nama ?? 'Umum',
            str_replace('_', ' ', $s->payment_method),
            $s->total,
            $s->status,
        ])->toArray();

        $headings = ['No', 'No Invoice', 'Tanggal', 'Customer', 'Pembayaran', 'Total (Rp)', 'Status'];

        return Excel::download(new ReportExport($data, $headings, 'Penjualan'), "laporan-penjualan-{$from}-{$to}.xlsx");
    }

    public function exportInventoryPdf()
    {
        $rawMaterials = RawMaterial::with(['category', 'unit', 'supplier'])
            ->selectRaw('*, (stok * harga_beli) as nilai_stok')
            ->orderBy('nama_bahan')
            ->get();

        $totalValue = $rawMaterials->sum('nilai_stok');
        $lowStock   = $rawMaterials->filter(fn($r) => $r->stok <= $r->stok_minimum && $r->stok_minimum > 0)->values();
        $expiring   = $rawMaterials->filter(fn($r) => $r->tanggal_kedaluwarsa && now()->diffInDays($r->tanggal_kedaluwarsa, false) <= 14)->values();

        $pdf = Pdf::loadView('reports.inventory', compact('rawMaterials', 'totalValue', 'lowStock', 'expiring'));
        return $pdf->download('laporan-inventory-' . now()->format('Y-m-d') . '.pdf');
    }

    public function exportInventoryExcel()
    {
        $rawMaterials = RawMaterial::with(['category', 'unit'])
            ->selectRaw('*, (stok * harga_beli) as nilai_stok')
            ->orderBy('nama_bahan')
            ->get();

        $data = $rawMaterials->map(fn($r, $i) => [
            $i + 1,
            $r->kode_bahan,
            $r->nama_bahan,
            $r->category?->name ?? '-',
            $r->stok,
            $r->unit?->name ?? '-',
            $r->harga_beli,
            $r->nilai_stok,
            $r->tanggal_kedaluwarsa,
        ])->toArray();

        $headings = ['No', 'Kode', 'Nama Bahan', 'Kategori', 'Stok', 'Satuan', 'Harga Beli (Rp)', 'Nilai Stok (Rp)', 'Tgl Expired'];

        return Excel::download(new ReportExport($data, $headings, 'Inventory'), 'laporan-inventory-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function exportProductionPdf(Request $request)
    {
        $from = $request->get('from_date', now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->get('to_date', now()->format('Y-m-d'));

        $productions = Production::with(['product', 'recipe'])
            ->where('status', 'completed')
            ->whereBetween('production_date', [$from, $to])
            ->orderBy('production_date')
            ->get();

        $summary = [
            'total_orders'  => $productions->count(),
            'total_qty'     => $productions->sum('quantity'),
            'by_product'    => $productions->groupBy('product_id')->map(fn($g) => [
                'product'   => $g->first()->product->nama_produk,
                'total_qty' => $g->sum('quantity'),
            ])->values()->toArray(),
        ];

        $fromDate = $from;
        $toDate = $to;
        $pdf = Pdf::loadView('reports.production', compact('productions', 'summary', 'fromDate', 'toDate'));
        return $pdf->download("laporan-produksi-{$from}-{$to}.pdf");
    }

    public function exportProductionExcel(Request $request)
    {
        $from = $request->get('from_date', now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->get('to_date', now()->format('Y-m-d'));

        $productions = Production::with(['product', 'recipe'])
            ->where('status', 'completed')
            ->whereBetween('production_date', [$from, $to])
            ->orderBy('production_date')
            ->get();

        $data = $productions->map(fn($p, $i) => [
            $i + 1,
            $p->production_code,
            $p->production_date,
            $p->product?->nama_produk ?? '-',
            $p->recipe?->name ?? '-',
            $p->quantity,
            $p->status,
        ])->toArray();

        $headings = ['No', 'Kode Produksi', 'Tanggal', 'Produk', 'Resep', 'Qty', 'Status'];

        return Excel::download(new ReportExport($data, $headings, 'Produksi'), "laporan-produksi-{$from}-{$to}.xlsx");
    }

    public function exportWastePdf(Request $request)
    {
        $from = $request->get('from_date', now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->get('to_date', now()->format('Y-m-d'));

        $wastes = Waste::with('creator')
            ->whereBetween('waste_date', [$from, $to])
            ->orderBy('waste_date')
            ->get();

        $summary = [
            'total_records'        => $wastes->count(),
            'total_estimated_loss' => $wastes->sum('estimated_loss'),
            'by_reason'            => $wastes->groupBy('reason')->map(fn($g) => ['count' => $g->count(), 'loss' => $g->sum('estimated_loss')])->toArray(),
            'by_item_type'         => $wastes->groupBy('item_type')->map(fn($g) => ['count' => $g->count(), 'loss' => $g->sum('estimated_loss')])->toArray(),
        ];

        $fromDate = $from;
        $toDate = $to;
        $pdf = Pdf::loadView('reports.waste', compact('wastes', 'summary', 'fromDate', 'toDate'));
        return $pdf->download("laporan-waste-{$from}-{$to}.pdf");
    }

    public function exportWasteExcel(Request $request)
    {
        $from = $request->get('from_date', now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->get('to_date', now()->format('Y-m-d'));

        $wastes = Waste::whereBetween('waste_date', [$from, $to])->orderBy('waste_date')->get();

        $data = $wastes->map(fn($w, $i) => [
            $i + 1,
            $w->waste_code,
            $w->waste_date,
            $w->item_type === 'raw_material' ? 'Bahan Baku' : 'Produk',
            $w->item_code,
            $w->quantity,
            str_replace('_', ' ', $w->reason),
            $w->estimated_loss,
        ])->toArray();

        $headings = ['No', 'Kode Waste', 'Tanggal', 'Tipe Item', 'Kode Item', 'Qty', 'Alasan', 'Kerugian (Rp)'];

        return Excel::download(new ReportExport($data, $headings, 'Waste'), "laporan-waste-{$from}-{$to}.xlsx");
    }
}
