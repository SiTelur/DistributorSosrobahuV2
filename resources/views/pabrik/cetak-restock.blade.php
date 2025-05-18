<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Nota Restock</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            color: #1a1a1a;
            padding: 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
        }
        .header img {
            height: 80px;
        }
        .header .right {
            text-align: right;
        }
        .header .right .title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background-color: #1a202c;
            color: #ffffff;
            padding: 10px;
            font-size: 14px;
        }
        td {
            padding: 10px;
            border-bottom: 1px solid #ccc;
            text-align: center;
        }
        .total-row td {
            font-weight: bold;
            font-size: 16px;
        }
        .note {
            margin-top: 40px;
            font-size: 13px;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }
    </style>
</head>
<body>

<div class="header">
{{-- <img src="{{ public_path('assets/images/logo.png') }}" height="80" alt="Logo"> --}}
    <div class="right">
        <div class="title">RESTOCK PRODUK</div>
        <div>{{ $notaPabrik['tanggal'] }}</div>
        <div>{{ $notaPabrik['id_restock'] }}</div>
        <div><strong>Official CV. Santoso Jaya Tembakau</strong></div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>Nama Produk</th>
            <th>Jumlah (Karton)</th>
        </tr>
    </thead>
        <tbody>
        @php $totalJumlah = 0; @endphp
        @if(!empty($notaPabrik['item_nota']))
            @foreach ($notaPabrik['item_nota'] as $item)
                <tr>
                    <td style="padding: 10px; text-align: center;">{{ $item['nama_rokok'] }}</td>
                    <td style="padding: 10px; text-align: center;">{{ $item['jumlah_item'] }}</td>
                </tr>
                @php $totalJumlah += $item['jumlah_item']; @endphp
            @endforeach
        @else
            <tr>
                <td colspan="2" style="text-align: center; padding: 10px;">Data tidak tersedia</td>
            </tr>
        @endif

        {{-- Baris total --}}
        <tr class="total-row">
            <td style="text-align: right; font-weight: bold; padding: 10px;">Total Keseluruhan</td>
            <td style="font-weight: bold; padding: 10px;">{{ $totalJumlah }} Karton</td>
        </tr>
        </tbody>

</table>

<div class="note">
    Detail riwayat restock produk ini adalah dokumen yang dicetak secara otomatis oleh sistem berdasarkan data yang diinput oleh perusahaan.
</div>

</body>
</html>
