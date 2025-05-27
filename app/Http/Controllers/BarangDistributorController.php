<?php

namespace App\Http\Controllers;

use App\Models\BarangDistributor;
use Illuminate\Http\Request;
use App\Models\OrderDetailDistributor;
use App\Models\OrderDistributor;
use Illuminate\Support\Facades\DB;
use App\Models\OrderAgen;
use App\Models\UserAgen;
use App\Models\UserSales;
use Carbon\Carbon;

class BarangDistributorController extends Controller
{
    public function index()
    {
        $id_user_distributor = session('id_user_distributor');
        $barangDistributors = BarangDistributor::where('id_user_distributor', $id_user_distributor)->get();;
        $namaRokokList = [];
        $gambarRokokList = [];

        // Loop through each BarangDistributor item
        foreach ($barangDistributors as $barangDistributor) {
            // Get the id_master_barang for the current BarangDistributor item
            $namaProduk = $barangDistributor->id_master_barang;

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



        // menampilkan hasil dalam format view
        return view('agen.pesanBarang', compact('barangDistributors', 'namaRokokList', 'gambarRokokList'));

        // Menampilkan hasil dalam format json
        // return response()->json([$barangDistributors,$namaRokokList,$gambarRokokList]);
    }

    public function stockbarang()
    {
        $id_user_distributor = session('id_user_distributor');
        // Ambil semua barang agen
        $barangDistributors = BarangDistributor::where('id_user_distributor', $id_user_distributor)->get();
        // Mengambil semua tahun dari tabel pesanan agen berdasarkan tanggal pesanan
        $availableYears = DB::table('order_agen')
            ->select(DB::raw('YEAR(tanggal) as year'))
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');
        $pesananMasuks = OrderAgen::orderBy('id_order', 'desc')
            ->where('id_user_distributor', $id_user_distributor)
            ->where('order_agen.status_pemesanan', 1)
            ->get();

        // Mengelompokkan pesanan berdasarkan bulan dan melakukan penotalan omset per bulan
        $pesananPerBulan = $pesananMasuks->groupBy(function ($item) {
            // Mengelompokkan berdasarkan bulan dan tahun (misalnya, "2024-10")
            return Carbon::parse($item->tanggal)->format('Y-m');
        })->map(function ($group) {
            // Menambahkan total omset untuk setiap kelompok bulan
            return [
                'pesanan' => $group,
                'total_omset' => $group->sum('total'),
                'total_karton'  => $group->sum('jumlah'),
            ];
        });


        // Siapkan array untuk menyimpan data
        $namaRokokList = [];
        $gambarRokokList = [];
        $totalProdukList = [];

        // Loop untuk setiap barang agen
        foreach ($barangDistributors as $barangDistributor) {
            $idMasterBarang = $barangDistributor->id_master_barang;
            $idUserDistributor = $barangDistributor->id_user_distributor;

            // Ambil data dari master_barang berdasarkan id_master_barang
            $orderValue = DB::table('master_barang')->where('id_master_barang', $idMasterBarang)->first();

            // Hitung total jumlah produk berdasarkan id_master_barang, id_user_distributor, dan status_pemesanan dari order_detail_distributor
            $totalProduk = DB::table('order_detail_distributor')
                ->join('order_distributor', 'order_detail_distributor.id_order', '=', 'order_distributor.id_order')
                ->where('order_detail_distributor.id_master_barang', $idMasterBarang)
                ->where('order_detail_distributor.id_user_distributor', $idUserDistributor)
                ->where('order_distributor.status_pemesanan', 1)
                ->sum('order_detail_distributor.jumlah_produk');

            // Hitung total produk terjual berdasarkan id_master_barang, id_user_distributor, dan status_pemesanan dari order_detail_agen
            $totalProdukTerjual = DB::table('order_detail_agen')
                ->join('order_agen', 'order_detail_agen.id_order', '=', 'order_agen.id_order')
                ->where('order_detail_agen.id_master_barang', $idMasterBarang)
                ->where('order_detail_agen.id_user_distributor', $idUserDistributor)
                ->where('order_agen.status_pemesanan', 1)
                ->sum('order_detail_agen.jumlah_produk');

            // Simpan data ke dalam array
            if ($orderValue) {
                $namaRokokList[] = $orderValue->nama_rokok;
                $gambarRokokList[] = $orderValue->gambar;
                $totalProdukList[] = $totalProduk - $totalProdukTerjual;
            } else {
                $namaRokokList[] = null;
                $gambarRokokList[] = null;
                $totalProdukList[] = 0;
            }
        }


        // Mengambil semua pesanan yang statusnya selesai
        $completedOrders = OrderDistributor::where('status_pemesanan', 1)
            ->where('id_user_distributor', $id_user_distributor)
            ->get();

        // Mengambil detail pesanan
        $orderDetails = OrderDetailDistributor::whereIn('id_order', $completedOrders->pluck('id_order'))->get();

        $totalStockKarton = $orderDetails->sum('jumlah_produk'); // Karton

        // Pesanan masuk (yang sudah berhasil)
        $incomingCompletedOrders = DB::table('order_detail_agen')
            ->join('order_agen', 'order_agen.id_order', '=', 'order_detail_agen.id_order')
            ->where('order_agen.status_pemesanan', 1)
            ->where('order_detail_agen.id_user_distributor', $id_user_distributor)
            ->sum('order_detail_agen.jumlah_produk');

        // Hitung stok yang disesuaikan (dikurangi pesanan masuk yang sudah berhasil)
        $finalStockKarton = $totalStockKarton -= $incomingCompletedOrders;


        // Produk terlaris dari pesanan sales yang statusnya 1
        $topProduct = DB::table('order_detail_agen')
            ->join('order_agen', 'order_agen.id_order', '=', 'order_detail_agen.id_order')
            ->where('order_agen.id_user_distributor', $id_user_distributor)
            ->where('order_agen.status_pemesanan', 1) // Status pesanan sales yang selesai
            ->select('order_detail_agen.id_master_barang', DB::raw('SUM(order_detail_agen.jumlah_produk) as total_jumlah'))
            ->groupBy('order_detail_agen.id_master_barang')
            ->orderBy('total_jumlah', 'desc')
            ->first();

        $topProductName = $topProduct ? DB::table('master_barang')
            ->where('id_master_barang', $topProduct->id_master_barang)
            ->value('nama_rokok') : 'Tidak ada data';

        // Total pendapatan dari pesanan agen yang statusnya 1
        $totalPendapatan = DB::table('order_agen')
            ->where('id_user_distributor', $id_user_distributor)->get()
            ->where('status_pemesanan', 1)
            ->sum('total');

        // Mengambil jumlah sales dari tabel user_agen
        $totalAgen = DB::table('user_agen')
            ->where('id_user_distributor', $id_user_distributor)->get()
            ->count();

        // Kirim data ke view


        return view('distributor.dashboard', [
            'barangDistributors' => $barangDistributors,
            'namaRokokList' => $namaRokokList,
            'gambarRokokList' => $gambarRokokList,
            'totalProdukList' => $totalProdukList,
            'finalStockKarton' => $finalStockKarton,
            'totalPendapatan' => $totalPendapatan,
            'topProductName' => $topProductName,
            'totalAgen' => $totalAgen,
            'pesananPerBulan' => $pesananPerBulan,
            'availableYears' => $availableYears

        ]);

        // return response()->json([
        //     'barangDistributors' => $barangDistributors,
        //     'namaRokokList' => $namaRokokList,
        //     'gambarRokokList' => $gambarRokokList,
        //     'totalProdukList' => $totalProdukList,
        //     'finalStockKarton' => $finalStockKarton,
        //     'totalPendapatan' => $totalPendapatan,
        //     'topProductName' => $topProductName,
        //     'totalAgen' => $totalAgen,
        //     'pesananPerBulan' => $pesananPerBulan,
        //     'availableYears' => $availableYears

        // ]);
    }

    public function stockbarangAPI(Request $request)
    {
        $id_user_distributor = $request->user()->currentAccessToken()->user_id;
        $barangDistributors = BarangDistributor::where('id_user_distributor', $id_user_distributor)
            ->with('masterBarang:id_master_barang,nama_rokok,gambar')
            ->get();
        $namaDistributor = DB::table('user_distributor')
            ->where('id_user_distributor', $id_user_distributor)
            ->value('nama_lengkap');


        // Mengambil semua tahun dari tabel pesanan agen berdasarkan tanggal pesanan
        $availableYears = OrderAgen::where('id_user_distributor', $id_user_distributor)
            ->selectRaw('YEAR(tanggal) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');

        // Mengambil pesanan masuk dengan status_pemesanan = 1
        $pesananMasuks = OrderAgen::where('id_user_distributor', $id_user_distributor)
            ->where('status_pemesanan', 1)
            ->orderByDesc('id_order')
            ->get();

        // Mengelompokkan pesanan berdasarkan bulan dan total omset per bulan
        $raw = OrderAgen::where('id_user_distributor', $id_user_distributor)
            ->where('status_pemesanan', 1)
            ->get()
            ->groupBy(fn($item) => Carbon::parse($item->tanggal)->format('Y-m'))
            ->map(fn($group) => [
                'pesanan'      => $group->values(),         // koleksi Eloquent, nanti di‐serialize otomatis
                'total_omset'  => $group->sum('total'),
                'total_karton' => $group->sum('jumlah'),
            ])
            ->toArray();   // jadi PHP array keyed by "YYYY-MM"

        // 2. Cast ke object agar JSON-nya {} bukan []
        $pesananPerBulan = (object) $raw;

        // Mengambil total stok dan produk terjual dalam satu query per kategori
        $orderDetails = DB::table('order_detail_distributor')
            ->join('order_distributor', 'order_detail_distributor.id_order', '=', 'order_distributor.id_order')
            ->where('order_distributor.status_pemesanan', 1)
            ->where('order_detail_distributor.id_user_distributor', $id_user_distributor)
            ->select('id_master_barang', DB::raw('SUM(jumlah_produk) as total_stok'))
            ->groupBy('id_master_barang')
            ->get();

        $soldProducts = DB::table('order_detail_agen')
            ->join('order_agen', 'order_detail_agen.id_order', '=', 'order_agen.id_order')
            ->where('order_agen.status_pemesanan', 1)
            ->where('order_detail_agen.id_user_distributor', $id_user_distributor)
            ->select('id_master_barang', DB::raw('SUM(jumlah_produk) as total_terjual'))
            ->groupBy('id_master_barang')
            ->get()
            ->keyBy('id_master_barang');

        // Menyusun data produk
        $produkData = $barangDistributors->map(function ($barang) use ($orderDetails, $soldProducts) {
            $stok = $orderDetails->firstWhere('id_master_barang', $barang->id_master_barang)?->total_stok ?? 0;
            $terjual = $soldProducts[$barang->id_master_barang]->total_terjual ?? 0;
            return [
                'nama_rokok' => $barang->masterBarang->nama_rokok ?? null,
                'gambar' => $barang->masterBarang->gambar ?? null,
                'total_produk' => $stok - $terjual
            ];
        });

        // Total stok akhir
        $totalStockKarton = $orderDetails->sum('total_stok');
        $incomingCompletedOrders = $soldProducts->sum('total_terjual');
        $finalStockKarton = $totalStockKarton - $incomingCompletedOrders;

        // Produk terlaris dari pesanan sales
        $topProduct = DB::table('order_detail_agen')
            ->join('order_agen', 'order_agen.id_order', '=', 'order_detail_agen.id_order')
            ->where('order_agen.status_pemesanan', 1)
            ->where('order_agen.id_user_distributor', $id_user_distributor)
            ->select('order_detail_agen.id_master_barang', DB::raw('SUM(order_detail_agen.jumlah_produk) as total_jumlah'))
            ->groupBy('order_detail_agen.id_master_barang')
            ->orderByDesc('total_jumlah')
            ->first();

        $topProductName = $topProduct ? DB::table('master_barang')
            ->where('id_master_barang', $topProduct->id_master_barang)
            ->value('nama_rokok') : 'Tidak ada data';

        // Total pendapatan dari pesanan agen
        $totalPendapatan = intval(OrderAgen::where('id_user_distributor', $id_user_distributor)
            ->where('status_pemesanan', 1)
            ->sum('total'));

        // Mengambil jumlah sales dari user_agen
        $totalAgen = UserAgen::where('id_user_distributor', $id_user_distributor)->count();


        $agenIds = UserAgen::where('id_user_distributor', $id_user_distributor)->pluck('id_user_distributor');

        // // 2. Get user IDs under those agents
        // $userIds = UserAgen::whereIn('id_user_agen', $agenIds)->pluck('id_user_agen');

        // // 3. Count distinct users from those IDs who have made sales
        // $userSalesCount = UserSales::whereIn('id_user', $userIds)
        //     ->distinct('id_user')
        //     ->count('id_user');


        // Mengembalikan response JSON
        return response()->json([
            'produkData' => $produkData,
            'finalStockKarton' => $finalStockKarton,
            'totalPendapatan' => $totalPendapatan,
            'topProductName' => $topProductName,
            'totalAgen' => $totalAgen,
            'pesananPerBulan' => $pesananPerBulan,
            'availableYears' => $availableYears,
            'nama_distributor'   => $namaDistributor,
            'totalSales' => $agenIds
        ]);
    }

    public function listBarangDistributorAgenAPI(Request $request)
    {
        // 1) Ambil id agen dari token
        $idUserAgen = $request->user()
            ->currentAccessToken()
            ->user_id;

        // 2) Dapatkan id distributor dari tabel user_agen
        $idUserDistributor = DB::table('user_agen')
            ->where('id_user_agen', $idUserAgen)
            ->value('id_user_distributor');

        if (! $idUserDistributor) {
            return response()->json([
                'error' => 'Distributor untuk agen ini tidak ditemukan'
            ], 404);
        }

        // 3) Ambil semua barang milik distributor itu, join ke master_barang untuk nama & harga pabrik
        $barangDistributor = DB::table('tbl_barang_disitributor as bd')
            ->join('master_barang as mb', 'bd.id_master_barang', '=', 'mb.id_master_barang')
            ->where('bd.id_user_distributor', $idUserDistributor)
            ->select([
                'bd.id_barang_distributor',
                'mb.nama_rokok',
                'mb.gambar',
                'bd.harga_distributor',
                'bd.stok_karton'
            ])
            ->get();

        // 4) Ambil info pabrik (anggap hanya ada satu)
        $distributor = DB::table('user_distributor')
            ->select('nama_lengkap', 'nama_bank', 'no_rek')
            ->where('id_user_distributor', $idUserDistributor)
            ->first();

        // 5) Kembalikan response yang sama bentuknya dengan contoh distributor
        return response()->json([
            'barangDistributor' => $barangDistributor,
            'distributor'            => [
                'nama_lengkap' => $distributor->nama_lengkap,
                'nama_bank'    => $distributor->nama_bank,
                'no_rek'       => $distributor->no_rek,
            ],
        ], 200);
    }
}
