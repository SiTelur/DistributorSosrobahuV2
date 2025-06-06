<?php

namespace App\Http\Controllers;

use App\Models\OrderAgen;
use App\Models\OrderDetailAgen;
use App\Models\BarangAgen;
use App\Models\MasterBarang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BarangAgenController extends Controller
{

    public function stockbarang()
    {
        // Ambil id_user_agen dari session
        $id_user_agen = session('id_user_agen');

        // Ambil semua barang agen yang sesuai dengan id_user_agen
        $barangAgens = BarangAgen::where('id_user_agen', $id_user_agen)->get();

        // Siapkan array untuk menyimpan data
        $namaRokokList = [];
        $gambarRokokList = [];
        $totalProdukList = [];

        // Loop untuk setiap barang agen
        foreach ($barangAgens as $barangAgen) {
            $idMasterBarang = $barangAgen->id_master_barang;

            // Ambil data dari master_barang berdasarkan id_master_barang
            $orderValue = DB::table('master_barang')->where('id_master_barang', $idMasterBarang)->first();

            // Hitung total jumlah produk berdasarkan id_master_barang dan status_pemesanan dari order_detail_agen
            $totalProduk = DB::table('order_detail_agen')
                ->join('order_agen', 'order_detail_agen.id_order', '=', 'order_agen.id_order')
                ->where('order_detail_agen.id_master_barang', $idMasterBarang)
                ->where('order_detail_agen.id_user_agen', $id_user_agen)
                ->where('order_agen.status_pemesanan', 1)
                ->sum('order_detail_agen.jumlah_produk');

            // Hitung total produk terjual berdasarkan id_master_barang dan status_pemesanan dari order_detail_sales
            $totalProdukTerjual = DB::table('order_detail_sales')
                ->join('order_sales', 'order_detail_sales.id_order', '=', 'order_sales.id_order')
                ->where('order_detail_sales.id_master_barang', $idMasterBarang)
                ->where('order_detail_sales.id_user_agen', $id_user_agen)
                ->where('order_sales.status_pemesanan', 1)
                ->sum('order_detail_sales.jumlah_produk');

            $isianSlop = $orderValue->stok_slop;

            if ($orderValue) {
                $namaRokokList[] = $orderValue->nama_rokok;
                $gambarRokokList[] = $orderValue->gambar;
                $totalProdukList[] = ($totalProduk * $isianSlop) - $totalProdukTerjual;
            } else {
                $namaRokokList[] = null;
                $gambarRokokList[] = null;
                $totalProdukList[] = 0;
            }
        }
        // Menghitung total keseluruhan produk
        $totalProdukKeseluruhan = array_sum($totalProdukList);

        // Mengambil semua pesanan yang statusnya selesai sesuai id_user_agen
        $completedOrders = OrderAgen::where('status_pemesanan', 1)
            ->where('id_user_agen', $id_user_agen)
            ->get();

        // Mengambil detail pesanan yang sesuai id_user_agen
        $orderDetails = OrderDetailAgen::whereIn('id_order', $completedOrders->pluck('id_order'))->get();

        // Menghitung total stok (konversi dari karton ke slop, 1 karton = 10 slop)
        $slopPerKarton = 10;
        $totalStockKarton = $orderDetails->sum('jumlah_produk'); // Karton
        $totalStockSlop = array_sum($totalProdukList);

        // Pesanan masuk (yang sudah berhasil) sesuai id_user_agen
        $incomingCompletedOrders = DB::table('order_detail_sales')
            ->join('order_sales', 'order_sales.id_order', '=', 'order_detail_sales.id_order')
            ->where('order_sales.status_pemesanan', 1)
            ->where('order_detail_sales.id_user_agen', $id_user_agen)
            ->sum('order_detail_sales.jumlah_produk'); // Slop

        // Hitung stok yang disesuaikan (dikurangi pesanan masuk yang sudah berhasil)
        $finalStockSlop = $totalStockSlop;



        // Produk terlaris dari pesanan sales yang statusnya 1 dan sesuai id_user_agen
        $topProduct = DB::table('order_detail_sales')
            ->join('order_sales', 'order_sales.id_order', '=', 'order_detail_sales.id_order')
            ->where('order_sales.id_user_agen', $id_user_agen) // Menambahkan alias tabel
            ->where('order_sales.status_pemesanan', 1) // Status pesanan sales yang selesai
            ->select('order_detail_sales.id_master_barang', DB::raw('SUM(order_detail_sales.jumlah_produk) as total_jumlah'))
            ->groupBy('order_detail_sales.id_master_barang')
            ->orderBy('total_jumlah', 'desc')
            ->first();


        $topProductName = $topProduct ? DB::table('master_barang')
            ->where('id_master_barang', $topProduct->id_master_barang)
            ->value('nama_rokok') : 'Tidak ada data';

        // Total pendapatan dari pesanan sales yang statusnya 1 sesuai id_user_agen
        $totalPendapatan = DB::table('order_sales')
            ->where('id_user_agen', $id_user_agen)->get()
            ->where('status_pemesanan', 1)
            ->sum('total');

        // Mengambil jumlah sales dari tabel user_sales
        $totalSales = DB::table('user_sales')
            ->where('id_user_agen', $id_user_agen)->get()
            ->count();

        // Kirim data ke view
        return view('agen.dashboard-agen', [
            'barangAgens' => $barangAgens,
            'namaRokokList' => $namaRokokList,
            'gambarRokokList' => $gambarRokokList,
            'totalProdukList' => $totalProdukList,
            'finalStockSlop' => $finalStockSlop,
            'totalPendapatan' => $totalPendapatan,
            'topProductName' => $topProductName,
            'totalSales' => $totalSales,
        ]);
    }


    //Menampilkan semua barang pada order sales
    public function index()
    {
        $id_user_agen = session('id_user_agen');
        $barangAgens = BarangAgen::where('id_user_agen', $id_user_agen)->get();
        $namaRokokList = [];
        $gambarRokokList = [];

        // Loop through each BarangAgen item
        foreach ($barangAgens as $barangAgen) {
            // Get the id_master_barang for the current BarangAgen item
            $namaProduk = $barangAgen->id_master_barang;

            // Query the master_barang table for the corresponding record
            $orderValue = DB::table('master_barang')->where('id_master_barang', $namaProduk)->first();

            // Store the nama_rokok in the array
            if ($orderValue) {
                $namaRokokList[] = $orderValue->nama_rokok;
                $gambarRokokList[] = $orderValue->gambar;
            } else {
                $namaRokokList[] = null; // If no matching record is found
                $gambarRokokList[] = null;
            }
        }

        return view('sales.pesan_barang', compact('barangAgens', 'namaRokokList', 'gambarRokokList'));
    }


    public function create()
    {
        return view('barang_agen.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_master_barang' => 'required|integer',
            'id_user_agen' => 'required|integer',
            'harga_agen' => 'required|integer',
            'stok_karton' => 'required|integer',
        ]);

        BarangAgen::create($request->all());

        return redirect()->route('barang_agen.index')->with('success', 'Barang Agen berhasil ditambahkan.');
    }

    public function show($id)
    {
        $barangAgen = BarangAgen::findOrFail($id);
        return view('barang_agen.show', compact('barangAgen'));
    }

    public function edit($id)
    {
        $barangAgen = BarangAgen::findOrFail($id);
        return view('barang_agen.edit', compact('barangAgen'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'id_master_barang' => 'required|integer',
            'id_user_agen' => 'required|integer',
            'harga_agen' => 'required|integer',
            'stok_karton' => 'required|integer',
        ]);

        $barangAgen = BarangAgen::findOrFail($id);
        $barangAgen->update($request->all());

        return redirect()->route('barang_agen.index')->with('success', 'Barang Agen berhasil diperbarui.');
    }



    public function destroy($id)
    {
        BarangAgen::findOrFail($id)->delete();
        return redirect()->route('barang_agen.index')->with('success', 'Barang Agen berhasil dihapus.');
    }

    public function stockbarangAPI(Request $request)
    {
        // Ambil ID distributor dari token akses
        $id_user_distributor = $request->user()->currentAccessToken()->user_id;

        // Ambil semua barang yang dimiliki oleh agen
        $barangAgens = BarangAgen::where('id_user_agen', $id_user_distributor)->get();

        // Siapkan array untuk menyimpan data stok
        $stokData = [];

        $namaLengkapAgen = DB::table('user_agen')
            ->where('id_user_agen', $id_user_distributor)
            ->value('nama_lengkap');

        foreach ($barangAgens as $barangAgen) {
            $idMasterBarang = $barangAgen->id_master_barang;

            // Ambil data produk dari `master_barang`
            $product = DB::table('master_barang')->where('id_master_barang', $idMasterBarang)->first();
            if (!$product) {
                continue; // Lewati jika barang tidak ditemukan
            }

            // Hitung total produk berdasarkan pesanan agen yang sudah selesai
            $totalProduk = DB::table('order_detail_agen')
                ->join('order_agen', 'order_detail_agen.id_order', '=', 'order_agen.id_order')
                ->where([
                    ['order_detail_agen.id_master_barang', $idMasterBarang],
                    ['order_detail_agen.id_user_agen', $id_user_distributor],
                    ['order_agen.status_pemesanan', 1]
                ])
                ->sum('order_detail_agen.jumlah_produk');

            // Hitung total produk yang telah terjual
            $totalProdukTerjual = DB::table('order_detail_sales')
                ->join('order_sales', 'order_detail_sales.id_order', '=', 'order_sales.id_order')
                ->where([
                    ['order_detail_sales.id_master_barang', $idMasterBarang],
                    ['order_detail_sales.id_user_agen', $id_user_distributor],
                    ['order_sales.status_pemesanan', 1]
                ])
                ->sum('order_detail_sales.jumlah_produk');

            // Hitung stok akhir dalam satuan slop
            $stokAkhir = ($totalProduk * $product->stok_slop) - $totalProdukTerjual;

            // Tambahkan data ke array stok
            $stokData[] = [
                'nama_rokok' => $product->nama_rokok,
                'gambar' => $product->gambar,
                'stok' => $stokAkhir
            ];
        }

        // Menghitung total stok keseluruhan
        $totalStokKeseluruhan = array_sum(array_column($stokData, 'stok'));

        // Produk terlaris berdasarkan pesanan sales yang sudah selesai
        $topProduct = DB::table('order_detail_sales')
            ->join('order_sales', 'order_sales.id_order', '=', 'order_detail_sales.id_order')
            ->where([
                ['order_sales.id_user_agen', $id_user_distributor],
                ['order_sales.status_pemesanan', 1]
            ])
            ->select('order_detail_sales.id_master_barang', DB::raw('SUM(order_detail_sales.jumlah_produk) as total_jumlah'))
            ->groupBy('order_detail_sales.id_master_barang')
            ->orderByDesc('total_jumlah')
            ->first();

        // Ambil nama produk terlaris
        $topProductName = $topProduct ? DB::table('master_barang')
            ->where('id_master_barang', $topProduct->id_master_barang)
            ->value('nama_rokok') : 'Tidak ada data';

        // Total pendapatan dari order sales yang selesai
        $totalPendapatan = DB::table('order_sales')
            ->where([
                ['id_user_agen', $id_user_distributor],
                ['status_pemesanan', 1]
            ])
            ->sum('total');

        // Total sales (jumlah sales yang dimiliki agen)
        $totalSales = DB::table('user_sales')
            ->where('id_user_agen', $id_user_distributor)
            ->count();

        // Kembalikan response dalam format JSON
        return response()->json([
            'success' => true,
            'stok_barang' => $stokData,
            'total_stok_keseluruhan' => $totalStokKeseluruhan,
            'top_product' => $topProductName,
            'total_pendapatan' => $totalPendapatan,
            'total_sales' => $totalSales,
            'nama_agen' => $namaLengkapAgen,    
        ]);
    }
}