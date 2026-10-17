<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Inventory</title>
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
        <h2>Laporan Inventory & Nilai Stok</h2>
        <p>Dicetak pada: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <table class="summary-box">
        <tr>
            <td>
                <div class="summary-title">Total Nilai Stok</div>
                <div class="summary-value" style="color: #4f46e5;">Rp {{ number_format($totalValue, 0, ',', '.') }}</div>
            </td>
            <td>
                <div class="summary-title">Item Stok Minimum</div>
                <div class="summary-value" style="color: #d97706;">{{ count($lowStock) }} Item</div>
            </td>
            <td>
                <div class="summary-title">Item Mendekati Expired</div>
                <div class="summary-value" style="color: #dc2626;">{{ count($expiring) }} Item</div>
            </td>
        </tr>
    </table>

    <h3 style="font-size: 12px; margin-bottom: 6px;">Daftar Stok Bahan Baku</h3>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 25px;" class="text-center">No</th>
                <th>Kode</th>
                <th>Nama Bahan</th>
                <th>Kategori</th>
                <th class="text-right">Stok</th>
                <th>Satuan</th>
                <th class="text-right">Harga Beli (Rp)</th>
                <th class="text-right">Nilai Stok (Rp)</th>
                <th class="text-center">Tgl Expired</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rawMaterials as $idx => $r)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ $r->kode_bahan }}</td>
                    <td>{{ $r->nama_bahan }}</td>
                    <td>{{ $r->category?->name ?? '-' }}</td>
                    <td class="text-right">{{ number_format($r->stok) }}</td>
                    <td>{{ $r->unit?->name ?? '-' }}</td>
                    <td class="text-right">{{ number_format($r->harga_beli, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($r->nilai_stok, 0, ',', '.') }}</td>
                    <td class="text-center">{{ $r->tanggal_kedaluwarsa ? \Carbon\Carbon::parse($r->tanggal_kedaluwarsa)->format('d/m/Y') : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center">Tidak ada data bahan baku</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        BakeHub ERP - Dokumen ini dicetak otomatis dari sistem komputer.
    </div>
</body>
</html>
