<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Nota Pesanan</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            padding: 20px;
            position: relative;
        }
        .container {
            max-width: 700px;
            margin: auto;
            padding: 20px;
            border-radius: 10px;
            position: relative;
            z-index: 1;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        .header img {
            height: 80px;
        }
        .right {
            text-align: right;
        }
        .right .title {
            font-size: 20px;
            font-weight: bold;
        }
        .section-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .info {
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background-color: #1a202c;
            color: #fff;
            padding: 8px;
            text-align: left;
        }
        td {
            padding: 10px;
            border-bottom: 1px solid #ccc;
        }
        .total {
            text-align: right;
            font-weight: bold;
            font-size: 16px;
            margin-top: 10px;
        }
        .note {
            margin-top: 30px;
            font-size: 13px;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }
        .watermark {
            position: absolute;
            top: 35%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.1;
            z-index: 0;
        }
        .watermark img {
            width: 400px;
        }
    </style>
</head>
<body>
    <div class="watermark">
        {{-- <img src="{{ public_path('assets/images/lunas.png') }}" alt="LUNAS"> --}}
    </div>

    <div class="container">
        <div class="header">
            {{-- <img src="{{ public_path('assets/images/logo.png') }}" alt="Logo"> --}}
            <div class="right">
                <div class="title">NOTA PESANAN</div>
                <div>{{ $notaSales['tanggal'] }}</div>
                <div>INVO/SOSRO/00{{ $notaSales['id_order'] }}</div>
                <div><strong>Official CV. Santoso Jaya Tembakau</strong></div>
            </div>
        </div>

        <div class="info">
            <div class="section-title">Pesanan Kepada:</div>
            <div>{{ $notaSales['nama_sales'] }}</div>
            <div>{{ $notaSales['no_telp'] }}</div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Nama Produk</th>
                    <th>Harga Satuan</th>
                    <th>Jumlah</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @php $total = 0; @endphp
                @foreach ($notaSales['item_nota'] as $item)
                    <tr>
                        <td>{{ $item['nama_rokok'] }}</td>
                        <td>Rp {{ number_format($item['harga_satuan'], 0, ',', '.') }}</td>
                        <td>{{ $item['jumlah_item'] }}</td>
                        <td>Rp {{ number_format($item['jumlah_harga'], 0, ',', '.') }}</td>
                    </tr>
                    @php $total += $item['jumlah_harga']; @endphp
                @endforeach
            </tbody>
        </table>

        <div class="total">
            Total Keseluruhan: Rp {{ number_format($total, 0, ',', '.') }}
        </div>

        <div class="note">
            Terima kasih atas kepercayaan Anda kepada kami, <strong>Sales Resmi Sosrobahu</strong>.<br>
            Untuk bantuan atau informasi lebih lanjut, silakan hubungi Admin Official <strong>CV. Santoso Jaya Tembakau</strong> melalui WhatsApp di {{ $notaSales['no_pabrik'] }}.
        </div>
    </div>
</body>
</html>
