@extends('distributor.default')

@section('content')
    <div class="w-full max-w-6xl mx-auto bg-white rounded-lg shadow-lg overflow-hidden my-20">
        <div class="flex items-center justify-between p-6 border-b">
            <div class="flex-1 text-center">
                <h1 class="text-2xl font-bold text-black">Pesanan Masuk dari Agen</h1>
            </div>
        </div>

        <div class="flex flex-col md:flex-row p-4 space-y-4 md:space-y-0 md:space-x-4">

            <!-- Tabel Pesanan Masuk -->
            <div class="w-full overflow-x-auto bg-white rounded-lg shadow border">
                <table class="w-full border-separate border-spacing-0 text-sm text-black">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="p-2 text-left">Tanggal</th>
                            <th class="p-2 text-left">Nama Agen</th>
                            <th class="p-2 text-left">Jumlah</th>
                            <th class="p-2 text-left">Total Harga</th>
                            <th class="p-2 text-left">Status Pesanan</th>
                            <th class="p-2 text-left">Detail Pesanan</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white text-sm">
                        @if ($pesananMasuks->isEmpty())
                            <tr>
                                <td colspan="10" class="p-2">
                                    <p class="text-center text-red-500">Belum terdapat pesanan masuk dari Agen.</p>
                                </td>
                            </tr>
                        @else
                            @foreach ($pesananMasuks as $pesananMasuk)
                                <tr class="border-b border-gray-200">
                                    <td class="p-2">
                                        @if ($pesananMasuk->tanggal)
                                            {{ \Carbon\Carbon::parse($pesananMasuk->tanggal)->format('d/m/Y') }}
                                        @else
                                            Tidak ada tanggal
                                        @endif
                                    </td>
                                    <td class="p-2">{{ $pesananMasuk->nama_agen }}</td>
                                    <td class="p-2">{{ $pesananMasuk->jumlah }} Karton</td>
                                    <td class="p-2">Rp. {{ number_format($pesananMasuk->total, 0, ',', '.') }}</td>
                                    <td
                                        class="p-2 {{ $pesananMasuk->status_pemesanan == 1 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $pesananMasuk->status_pemesanan == 1 ? 'Selesai' : ($pesananMasuk->status_pemesanan == 2 ? 'Ditolak' : 'Diproses') }}
                                    </td>
                                    <td class="p-2">
                                        @if (empty($pesananMasuk->bukti_transfer))
                                            <p class="text-gray-600">Menunggu pembayaran</p>
                                        @elseif ($pesananMasuk->status_pemesanan == 0)
                                            <button
                                                onclick="window.location.href='{{ route('detailPesanMasukDistributor', $pesananMasuk->id_order) }}'"
                                                class="bg-orange-600 text-white font-bold py-1 px-3 rounded hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-green-500 text-xs">
                                                Edit
                                            </button>
                                        @else
                                            <button
                                                onclick="window.location.href='{{ route('detailPesanMasukDistributor', $pesananMasuk->id_order) }}'"
                                                class="bg-green-600 text-white font-bold py-1 px-3 rounded hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 text-xs">
                                                Lihat
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                    </tbody>
                    @endif
                </table>
            </div>
        </div>

        @if ($pesananMasuks->total() > 10)
            <div class="flex flex-col items-center my-6">
                <span class="text-sm text-gray-700">
                    Menampilkan <span class="font-semibold text-gray-900">{{ $pesananMasuks->firstItem() }}</span>
                    sampai
                    <span class="font-semibold text-gray-900">{{ $pesananMasuks->lastItem() }}</span> dari
                    <span class="font-semibold text-gray-900">{{ $pesananMasuks->total() }}</span>
                    transaksi
                </span>
                <div class="inline-flex mt-2">
                    <button {{ $pesananMasuks->onFirstPage() ? 'disabled' : '' }}
                        class="flex items-center justify-center px-3 h-8 text-sm font-medium text-white bg-gray-800 rounded-l hover:bg-gray-900"
                        {{ $pesananMasuks->previousPageUrl() ? 'onclick=window.location.href=\'' . $pesananMasuks->previousPageUrl() . '\'' : '' }}>
                        Sebelumnya
                    </button>
                    <button {{ !$pesananMasuks->hasMorePages() ? 'disabled' : '' }}
                        class="flex items-center justify-center px-3 h-8 text-sm font-medium text-white bg-gray-800 rounded-r hover:bg-gray-900"
                        {{ $pesananMasuks->nextPageUrl() ? 'onclick=window.location.href=\'' . $pesananMasuks->nextPageUrl() . '\'' : '' }}>
                        Selanjutnya
                    </button>
                </div>
            </div>
        @endif
    </div>
@endsection
