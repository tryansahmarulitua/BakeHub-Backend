<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Penjualan</title>
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
        .badge { padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .badge-success { background: #dcfce7; color: #15803d; }
        .badge-danger { background: #fee2e2; color: #b91c1c; }
        .footer { font-size: 9px; color: #94a3b8; text-align: right; margin-top: 15px; border-top: 1px solid #e2e8f0; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>BakeHub Bakery ERP</h1>
        <h2>Laporan Penjualan</h2>
        <p>Periode: {{ $fromDate }} s/d {{ $toDate }} | Dicetak: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <table class="summary-box">
        <tr>
            <td>
                <div class="summary-title">Total Transaksi</div>
                <div class="summary-value">{{ number_format($summary['total_transactions']) }}</div>
            </td>
            <td>
                <div class="summary-title">Total Revenue</div>
                <div class="summary-value" style="color: #16a34a;">Rp {{ number_format($summary['total_revenue'], 0, ',', '.') }}</div>
            </td>
            <td>
                <div class="summary-title">Total Item Terjual</div>
                <div class="summary-value">{{ number_format($summary['total_items']) }}</div>
            </td>
        </tr>
    </table>

    <h3 style="font-size: 12px; margin-bottom: 6px;">Daftar Transaksi Penjualan</h3>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 25px;" class="text-center">No</th>
                <th>No Invoice</th>
                <th>Tanggal</th>
                <th>Customer</th>
                <th>Pembayaran</th>
                <th class="text-right">Total (Rp)</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($sales as $idx => $s)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ $s->invoice_number }}</td>
                    <td>{{ \Carbon\Carbon::parse($s->sale_date)->format('d/m/Y') }}</td>
                    <td>{{ $s->customer?->nama ?? 'Umum' }}</td>
                    <td style="text-transform: capitalize;">{{ str_replace('_', ' ', $s->payment_method) }}</td>
                    <td class="text-right">{{ number_format($s->total, 0, ',', '.') }}</td>
                    <td class="text-center">
                        <span class="badge {{ $s->status === 'completed' ? 'badge-success' : 'badge-danger' }}">{{ $s->status }}</span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center">Tidak ada transaksi pada periode ini</td></tr>
            @endforelse
        </tbody>
    </table>

    @if(count($topProducts) > 0)
    <h3 style="font-size: 12px; margin-bottom: 6px; margin-top: 15px;">Top 10 Produk Terlaris</h3>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 25px;" class="text-center">No</th>
                <th>Produk</th>
                <th class="text-right">Qty Terjual</th>
                <th class="text-right">Total Revenue (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($topProducts as $idx => $p)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ $p->product?->nama_produk ?? '-' }}</td>
                    <td class="text-right">{{ number_format($p->total_qty) }}</td>
                    <td class="text-right">{{ number_format($p->revenue, 0, ',', '.') }}</td>
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
