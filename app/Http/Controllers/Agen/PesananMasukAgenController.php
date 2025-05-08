<?php

namespace App\Http\Controllers\Agen;

use App\Http\Controllers\Controller;
use App\Models\OrderSale;
use App\Models\OrderDetailSales;
use App\Models\BarangDistributor;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;


class PesananMasukAgenController extends Controller
{
    public function index()
    {
        // Mengambil pesanan dengan mengurutkan berdasarkan ID terbesar
        $id_user_agen = session('id_user_agen');
        $pesananMasuks = OrderSale::where('id_user_agen', $id_user_agen)
            ->orderBy('id_order', 'desc')->paginate(10);


        // Mengonversi tanggal ke format Carbon
        foreach ($pesananMasuks as $pesananMasuk) {
            $pesananMasuk->tanggal = Carbon::parse($pesananMasuk->tanggal);
            // Mengambil nama user sales berdasarkan id_user_sales
            $namaSales = DB::table('user_sales')->where('id_user_sales', $pesananMasuk->id_user_sales)->first();
            $pesananMasuk->nama_sales = $namaSales ? $namaSales->nama_lengkap : 'Tidak Ditemukan';
        }
        return view('agen.transaksiAgen', compact('pesananMasuks'));
    }

    public function detailPesanMasuk($idPesanan)
    {
        Carbon::setLocale('id');
        // Ganti dengan ID order yang ingin dicari
        $orderDetailSales = OrderDetailSales::where('id_order', $idPesanan)->first();
        $orderDetailSalesItem = OrderDetailSales::where('id_order', $idPesanan)->get();
        $orderSales = OrderSale::where('id_order', $idPesanan)->first();
        $namaSales = DB::table('user_sales')->where('id_user_sales', $orderSales->id_user_sales)->first();

        $itemNota = [];
        $nama_rokok = [];

        foreach ($orderDetailSalesItem as $barangSales) {
            $product = DB::table('master_barang')->where('id_master_barang', $barangSales->id_master_barang)->first();
            $hargaSatuan = DB::table('tbl_barang_agen')->where('id_master_barang', $barangSales->id_master_barang)->first();
            if ($product) { // Cek apakah product ada dan memiliki properti nama_rokok
                $nama_rokok[] = $product->nama_rokok;
                $harga_satuan[] = $barangSales->harga_tetap_nota;
                $jumlah_item[] = $barangSales->jumlah_produk;
                $jumlah_harga[] = $barangSales->jumlah_harga_item;
            } else {
                $nama_rokok[] = null; // Jika tidak ditemukan
                $jumlah_item[] = null; // Jika tidak ditemukan
                $jumlah_harga[] = null; // Jika tidak ditemukan
                $harga_satuan[] = null; // Jika tidak ditemukan
            }

            $itemNota[] = [
                'nama_rokok' => end($nama_rokok),
                'harga_satuan' => end($harga_satuan),
                'jumlah_item' => end($jumlah_item),
                'jumlah_harga' => end($jumlah_harga),
            ];
        }


        $pesanMasukAgen = [
            'tanggal' => Carbon::parse($orderSales->tanggal)->translatedFormat('d F Y'),
            'id_order' => $orderSales->id_order,
            'nama_sales' => $namaSales->nama_lengkap,
            'no_telp' => $namaSales->no_telp,
            'total_item' => $orderSales->jumlah,
            'total_harga' => $orderSales->total,
            'item_nota' => $itemNota,
            'gambar' => $orderSales->bukti_transfer,
            'status' => $orderSales->status_pemesanan,
        ];


        // dd($pesanMasukAgen);
        return view('agen.detailPesanMasuk', compact('pesanMasukAgen'));
    }

    public function editStatus($id)
    {
        // Mengambil data pesanan berdasarkan ID
        $pesanMasukAgen = OrderSale::findOrFail($id);

        // Mengirim data pesanan ke view
        return view('agen.editStatusPesanan', compact('pesanMasukAgen'));
    }

    public function updateStatus(Request $request, $id)
    {
        // Validasi input dari form
        $request->validate([
            'status' => 'required|integer|in:0,1,2',
        ]);

        // Mengambil data pesanan berdasarkan ID
        $pesanMasukAgen = OrderSale::findOrFail($id);

        // Mengupdate status pesanan
        $pesanMasukAgen->status_pemesanan = $request->input('status');
        $pesanMasukAgen->save();

        // Redirect atau kembali dengan pesan sukses
        return redirect()->route('pesananMasuk')->with('success', 'Status pesanan berhasil diperbarui!');
    }

    public function pesananMasukAgenAPI(Request $request)
    {
        $id_user_agen = $request->user()->currentAccessToken()->user_id;
        // Mengambil pesanan dengan mengurutkan berdasarkan ID terbesar

        $pesananMasuks = OrderSale::with('userSales')
            ->where('id_user_agen', $id_user_agen)
            ->orderByDesc('id_order')
            ->paginate(10);

        // Transformasi data sebelum dikembalikan sebagai JSON
        $pesananMasuks->transform(function ($pesanan) {
            return [
                'id_order' => $pesanan->id_order,
                'tanggal' => Carbon::parse($pesanan->tanggal)->format('d F Y'),
                'nama_sales' => optional($pesanan->userSales)->nama_lengkap ?? 'Tidak Ditemukan',
                'total_harga' => $pesanan->total,
                'status_pemesanan' => $pesanan->status_pemesanan,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Data transaksi agen berhasil diambil.',
            'data' => $pesananMasuks
        ]);
    }

    public function detailPesananMasukAgenAPI($idPesanan)
    {
        Carbon::setLocale('id');

        // Mengambil data pesanan dan detailnya dengan eager loading
        $orderSales = OrderSale::with('userSales')->findOrFail($idPesanan);
        $orderDetailSales = OrderDetailSales::where('id_order', $idPesanan)->get();

        // Ambil data item pesanan secara batch untuk menghindari N+1 Query
        $idMasterBarang = $orderDetailSales->pluck('id_master_barang')->toArray();
        $products = DB::table('master_barang')->whereIn('id_master_barang', $idMasterBarang)->pluck('nama_rokok', 'id_master_barang');

        // Looping untuk membangun item pesanan
        $itemNota = $orderDetailSales->map(function ($barangSales) use ($products) {
            return [
                'nama_rokok' => $products[$barangSales->id_master_barang] ?? 'Tidak Ditemukan',
                'harga_satuan' => $barangSales->harga_tetap_nota,
                'jumlah_item' => $barangSales->jumlah_produk,
                'jumlah_harga' => $barangSales->jumlah_harga_item,
            ];
        });

        // Menyusun response
        $pesanMasukAgen = [
            'id_order' => $orderSales->id_order,
            'tanggal' => Carbon::parse($orderSales->tanggal)->translatedFormat('d F Y'),
            'nama_sales' => optional($orderSales->userSales)->nama_lengkap ?? 'Tidak Ditemukan',
            'no_telp' => optional($orderSales->userSales)->no_telp ?? 'Tidak Ada',
            'total_item' => $orderSales->jumlah,
            'total_harga' => $orderSales->total,
            'item_nota' => $itemNota,
            'gambar' => $orderSales->bukti_transfer,
            'status' => $orderSales->status_pemesanan,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Detail pesanan berhasil diambil.',
            'data' => $pesanMasukAgen
        ]);
    }
    public function updateStatusPesananAPI(Request $request, $id)
    {
        // Validasi input
        $validated = $request->validate([
            'status' => 'required|integer|in:0,1,2',
        ]);

        // Cari data pesanan, jika tidak ditemukan, otomatis akan mengembalikan error 404
        $pesanMasukAgen = OrderSale::findOrFail($id);

        // Update status pesanan
        $pesanMasukAgen->update(['status_pemesanan' => $validated['status']]);

        // Return JSON response
        return response()->json([
            'success' => true,
            'message' => 'Status pesanan berhasil diperbarui!',
            'data' => $pesanMasukAgen,
        ]);
    }
}
