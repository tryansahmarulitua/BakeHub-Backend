<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Produksi</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #333; line-height: 1.4; margin: 0; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #ea580c; padding-bottom: 10px; margin-bottom: 15px; }
        .header h1 { margin: 0; font-size: 18px; color: #ea580c; text-transform: uppercase; }
        .header h2 { margin: 4px 0; font-size: 14px; color: #1e293b; }
        .header p { margin: 2px 0; font-size: 10px; color: #64748b; }
        .summary-box { width: 100%; margin-bottom: 15px; border-collapse: collapse; }
        .summary-box td { background: #f8fafc; border: 1px solid #e2e8f0; padding: 8px 12px; }
        .summary-title { font-size: 9px; color: #64748b; text-transform: uppercase; }
        .summary-value { font-size: 13px; font-weight: bold; color: #0f172a; margin-top: 2px; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.data th, table.data td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        table.data th { background-color: #f1f5f9; color: #334155; font-size: 10px; text-transform: uppercase; }
        table.data tr:nth-child(even) { background-color: #f8fafc; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .footer { font-size: 9px; color: #94a3b8; text-align: right; margin-top: 15px; border-top: 1px solid #e2e8f0; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>BakeHub Bakery ERP</h1>
        <h2>Laporan Produksi</h2>
        <p>Periode: {{ $fromDate }} s/d {{ $toDate }} | Dicetak: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <table class="summary-box">
        <tr>
            <td>
                <div class="summary-title">Total Batch / Order</div>
                <div class="summary-value">{{ number_format($summary['total_orders']) }} Order</div>
            </td>
            <td>
                <div class="summary-title">Total Qty Diproduksi</div>
                <div class="summary-value" style="color: #2563eb;">{{ number_format($summary['total_qty']) }} Pcs</div>
            </td>
        </tr>
    </table>

    <h3 style="font-size: 12px; margin-bottom: 6px;">Daftar Transaksi Produksi Selesai</h3>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 25px;" class="text-center">No</th>
                <th>Kode Produksi</th>
                <th>Tanggal</th>
                <th>Produk</th>
                <th>Resep</th>
                <th class="text-right">Qty</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($productions as $idx => $p)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ $p->production_code }}</td>
                    <td>{{ \Carbon\Carbon::parse($p->production_date)->format('d/m/Y') }}</td>
                    <td>{{ $p->product?->nama_produk ?? '-' }}</td>
                    <td>{{ $p->recipe?->name ?? '-' }}</td>
                    <td class="text-right">{{ number_format($p->quantity) }}</td>
                    <td class="text-center">{{ ucfirst($p->status) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center">Tidak ada produksi selesai pada periode ini</td></tr>
            @endforelse
        </tbody>
    </table>

    @if(count($summary['by_product']) > 0)
    <h3 style="font-size: 12px; margin-bottom: 6px; margin-top: 15px;">Ringkasan Hasil Per Produk</h3>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 25px;" class="text-center">No</th>
                <th>Produk</th>
                <th class="text-right">Total Qty</th>
            </tr>
        </thead>
        <tbody>
            @foreach($summary['by_product'] as $idx => $bp)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ $bp['product'] }}</td>
                    <td class="text-right">{{ number_format($bp['total_qty']) }} Pcs</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="footer">
        BakeHub ERP - Dokumen ini dicetak otomatis dari sistem komputer.
    </div>
</body>
</html>
