<?php

namespace App\Http\Controllers\Distributor;

use App\Http\Controllers\Controller;
use App\Models\OrderAgen;
use App\Models\OrderDetailAgen;
use App\Models\MasterBarang;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PesananMasukDistributorController extends Controller
{
    public function index()
    {
        // Mengambil semua pesanan dan mengonversi tanggal ke format Carbon
        $id_user_distributor = session('id_user_distributor');
        $pesananMasuks = OrderAgen::where('id_user_distributor', $id_user_distributor)
            ->orderBy('id_order', 'desc')->paginate(10);

        // Mengelompokkan pesanan berdasarkan bulan dan melakukan penotalan omset per bulan
        foreach ($pesananMasuks as $pesananMasuk) {
            $pesananMasuk->tanggal = Carbon::parse($pesananMasuk->tanggal);
            // Mengambil nama user sales berdasarkan id_user_agen
            $namaAgen = DB::table('user_agen')->where('id_user_agen', $pesananMasuk->id_user_agen)->first();
            $pesananMasuk->nama_agen = $namaAgen ? $namaAgen->nama_lengkap : 'Tidak Ditemukan';
        }

        // Mengirim data yang dikelompokkan dan total omset ke view

        return view('distributor.transaksi', compact('pesananMasuks'));
    }


    public function detailPesanMasuk($idPesanan)
    {
        Carbon::setLocale('id');
        // Ganti dengan ID order yang ingin dicari
        $orderDetailAgen = OrderDetailAgen::where('id_order', $idPesanan)->first();
        $orderDetailAgenItem = OrderDetailAgen::where('id_order', $idPesanan)->get();
        $orderAgen = OrderAgen::where('id_order', $idPesanan)->first();
        $namaAgen = DB::table('user_agen')->where('id_user_agen', $orderAgen->id_user_agen)->first();



        $itemNota = [];
        $nama_rokok = [];

        foreach ($orderDetailAgenItem as $barangAgen) {
            $product = DB::table('master_barang')->where('id_master_barang', $barangAgen->id_master_barang)->first();
            $hargaSatuan = DB::table('tbl_barang_disitributor')->where('id_master_barang', $barangAgen->id_master_barang)->first();
            if ($product) { // Cek apakah product ada dan memiliki properti nama_rokok
                $nama_rokok[] = $product->nama_rokok;
                $harga_satuan[] = $hargaSatuan->harga_distributor;
                $jumlah_item[] = $barangAgen->jumlah_produk;
                $jumlah_harga[] = $barangAgen->jumlah_harga_item;
            } else {
                $nama_rokok[] = null; // Jika tidak ditemukan
                $jumlah_item[] = null; // Jika tidak ditemukan
                $jumlah_harga[] = null; // Jika tidak ditemukan
                $harga_satuan[] = null; // Jika tidak ditemukan
            }

            $itemNota[] = [
                'nama_rokok' => end($nama_rokok), // Gunakan end() untuk mengambil elemen terakhir
                'harga_satuan' => end($harga_satuan),
                'jumlah_item' => end($jumlah_item),
                'jumlah_harga' => end($jumlah_harga),
            ];
        }


        $pesanMasukDistributor = [
            'tanggal' => Carbon::parse($orderAgen->tanggal)->translatedFormat('d F Y'),
            'id_order' => $orderAgen->id_order,
            'nama_agen' => $namaAgen->nama_lengkap,
            'no_telp' => $namaAgen->no_telp,
            'total_item' => $orderAgen->jumlah,
            'total_harga' => $orderAgen->total,
            'item_nota' => $itemNota,
            'gambar' => $orderAgen->bukti_transfer,
            'status' => $orderAgen->status_pemesanan,
        ];


        // dd($pesanMasukDistributor);
        return view('distributor.detail-transaksi', compact('pesanMasukDistributor'));
        // return response()->json(data: $pesanMasukDistributor);
    }

    public function editStatus($id)
    {
        // Mengambil data pesanan berdasarkan ID
        $pesanMasukDistributor = OrderAgen::findOrFail($id);

        // Mengirim data pesanan ke view
        return view('distributor.editStatusPesanan', compact('pesanMasukDistributor'));
    }

    public function updateStatus(Request $request, $id)
    {
        // Validasi input dari form
        $request->validate([
            'status' => 'required|integer|in:0,1,2',
        ]);

        // Mengambil data pesanan berdasarkan ID
        $pesanMasukDistributor = OrderAgen::findOrFail($id);

        // Mengupdate status pesanan
        $pesanMasukDistributor->status_pemesanan = $request->input('status');
        $pesanMasukDistributor->save();

        // Redirect atau kembali dengan pesan sukses
        return redirect()->route('pesananMasukDistributor')->with('success', 'Status pesanan berhasil diperbarui!');
    }

    public function pesananMasukDistributorAPI(Request $request)
    {
        $id_user_distributor = $request->user()->currentAccessToken()->user_id;
        $pesananMasuks = OrderAgen::where('id_user_distributor', $id_user_distributor)
            ->orderByDesc('id_order')
            ->paginate(10);

        // Ubah tiap item: format tanggal & ambil nama agen via query
        foreach ($pesananMasuks as $pesananMasuk) {
            $pesananMasuk->tanggal = Carbon::parse($pesananMasuk->tanggal);
            $namaAgen = DB::table('user_agen')
                ->where('id_user_agen', $pesananMasuk->id_user_agen)
                ->value('nama_lengkap');
            $pesananMasuk->nama_agen = $namaAgen ?: 'Tidak Ditemukan';
        }

        return response()->json(data: $pesananMasuks);
    }

    public function detailPesananMasukDistributorAPI($idPesanan)
    {
        Carbon::setLocale('id');

        // Fetch OrderAgen and related data in one query using eager loading
        $orderAgen = OrderAgen::with('userAgen')->where('id_order', $idPesanan)->firstOrFail();

        // Fetch order details with related product & price info
        $orderDetails = OrderDetailAgen::where('id_order', $idPesanan)
            ->with(['product:id_master_barang,nama_rokok', 'harga:id_master_barang,harga_distributor'])
            ->get();

        // If there's no order, return error response
        if (!$orderAgen) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        // Prepare items for the response
        $itemNota = $orderDetails->map(function ($detail) {
            return [
                'nama_rokok' => $detail->product->nama_rokok ?? 'Tidak Ditemukan',
                'harga_satuan' => $detail->harga->harga_distributor ?? 0,
                'jumlah_item' => $detail->jumlah_produk ?? 0,
                'jumlah_harga' => $detail->jumlah_harga_item ?? 0,
            ];
        });

        // Build final response
        $pesanMasukDistributor = [
            'tanggal' => Carbon::parse($orderAgen->tanggal)->translatedFormat('d F Y'),
            'id_order' => $orderAgen->id_order,
            'nama_agen' => $orderAgen->userAgen->nama_lengkap ?? 'Tidak Ditemukan',
            'no_telp' => $orderAgen->userAgen->no_telp ?? 'Tidak Ditemukan',
            'total_item' => $orderAgen->jumlah,
            'total_harga' => $orderAgen->total,
            'item_nota' => $itemNota,
            'gambar' => $orderAgen->bukti_transfer,
            'status' => $orderAgen->status_pemesanan,
        ];

        // Return JSON response
        return response()->json($pesanMasukDistributor);
    }

    public function updateStatusPesananMasukAPI(Request $request, $idPesanan)
    {
        // Validasi input
        $validated = $request->validate([
            'status' => 'required|integer|in:0,1,2',
        ]);

        // Cari pesanan berdasarkan ID
        $pesanan = OrderAgen::find($idPesanan);

        // Jika pesanan tidak ditemukan, kembalikan respons JSON
        if (!$pesanan) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak ditemukan.'
            ], 404);
        }

        // Update status pesanan
        $pesanan->update(['status_pemesanan' => $validated['status']]);

        // Kembalikan response JSON
        return response()->json([
            'success' => true,
            'message' => 'Status pesanan berhasil diperbarui.',
            'data' => [
                'id_order' => $pesanan->id_order,
                'status_pemesanan' => $pesanan->status_pemesanan,
            ]
        ]);
    }
}
