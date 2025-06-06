<?php

namespace App\Http\Controllers\Agen;

use App\Http\Controllers\Controller;
use App\Models\BarangDistributor;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\OrderAgen;
use App\Models\MasterBarang;
use App\Models\OrderDetailAgen;
use App\Models\UserDistributor;
use Barryvdh\DomPDF\Facade\Pdf;  // alias “PDF”

class OrderAgenController extends Controller
{
    public function dashboardData()
    {
        // Mengambil semua pesanan yang statusnya selesai
        $completedOrders = OrderAgen::where('status_pemesanan', 1)->get();

        // Mengambil detail pesanan
        $orderDetails = OrderDetailAgen::whereIn('id_order', $completedOrders->pluck('id_order'))->get();

        // Menghitung total stok (konversi dari karton ke slop, 1 karton = 10 slop)
        $slopPerKarton = 10;
        $totalStockKarton = $orderDetails->sum('jumlah_produk'); // Karton
        $totalStockSlop = $totalStockKarton * $slopPerKarton;

        // Pesanan masuk (yang sudah berhasil)
        $incomingCompletedOrders = DB::table('order_detail_sales')
            ->join('order_sales', 'order_sales.id_order', '=', 'order_detail_sales.id_order')
            ->where('order_sales.status_pemesanan', 1)
            ->sum('order_detail_sales.jumlah_produk'); // Slop

        // Hitung stok yang disesuaikan (dikurangi pesanan masuk yang sudah berhasil)
        $finalStockSlop = $totalStockSlop - $incomingCompletedOrders;

        // Produk terlaris dari pesanan sales yang statusnya 1
        $topProduct = DB::table('order_detail_sales')
            ->join('order_sales', 'order_sales.id_order', '=', 'order_detail_sales.id_order')
            ->where('order_sales.status_pemesanan', 1) // Status pesanan sales yang selesai
            ->select('order_detail_sales.id_master_barang', DB::raw('SUM(order_detail_sales.jumlah_produk) as total_jumlah'))
            ->groupBy('order_detail_sales.id_master_barang')
            ->orderBy('total_jumlah', 'desc')
            ->first();

        $topProductName = $topProduct ? DB::table('master_barang')
            ->where('id_master_barang', $topProduct->id_master_barang)
            ->value('nama_rokok') : 'Tidak ada data';

        // Total pendapatan dari pesanan sales yang statusnya 1
        $totalPendapatan = DB::table('order_sales')
            ->where('status_pemesanan', 1)
            ->sum('total');

        // Mengambil jumlah sales dari tabel user_sales
        $totalSales = DB::table('user_sales')->count();

        // Mengirim data ke view dashboard
        return view('agen.dashboard-agen', [
            'finalStockSlop' => $finalStockSlop,
            'totalPendapatan' => $totalPendapatan,
            'topProductName' => $topProductName,
            'totalSales' => $totalSales,
        ]);
    }

    // Riwayat  Agen
    public function index()
    {
        $id_user_agen = session('id_user_agen');
        // Mengambil pesanan dengan mengurutkan berdasarkan ID terbesar
        $orderAgens = OrderAgen::where('id_user_agen', $id_user_agen)
            ->orderBy('id_order', 'desc')
            ->paginate(10);

        // Mengonversi tanggal ke format Carbon
        foreach ($orderAgens as $orderAgen) {
            $orderAgen->tanggal = Carbon::parse($orderAgen->tanggal);
        }

        // Mengirim data pesanan ke view
        return view('agen.riwayatAgen', compact('orderAgens'));
    }

    //  memanipulasi jumlah barang Dihalaman Order Detail
    public function detail(Request $request)
    {
        $selectedProductIds = $request->input('products', []); // Mengambil ID produk yang dipilih dari request
        $namaRokokList = [];

        // Ambil ID distributor dari session
        $idDistributor = session('id_user_distributor'); // Mengambil ID distributor dari session
        $getDistributor = UserDistributor::where('id_user_distributor', $idDistributor)->first();

        // Pastikan distributor ditemukan
        if (!$getDistributor) {
            return back()->withErrors(['message' => 'Distributor tidak ditemukan.']);
        }

        // Ambil informasi distributor
        $namaDistributor = [
            'nama_distributor' => $getDistributor->nama_lengkap,
            'no_rek' => $getDistributor->no_rek,
            'nama_bank' => $getDistributor->nama_bank,
        ];


        // Loop through each selected product ID
        foreach ($selectedProductIds as $barangDistributor) {

            // Convert the ID to an integer
            $namaProdukint = intval($barangDistributor);

            // Query the master_barang table for the corresponding record
            $orderValue = DB::table('master_barang')->where('id_master_barang', $namaProdukint)->first();

            // Store the nama_rokok in the array
            if ($orderValue) {
                $namaRokokList[] = $orderValue->nama_rokok;
            } else {
                $namaRokokList[] = null; // If no matching record is found
            }
        }

        // Ambil detail pesanan berdasarkan ID produk yang dipilih

        $orders = BarangDistributor::where('id_user_distributor', $idDistributor) // Filter berdasarkan id_user_agen
            ->whereIn('id_master_barang', $selectedProductIds) // Filter berdasarkan id_barang_agen
            ->get();

        // Menghitung total harga
        $totalAmount = $orders->sum(function ($order) {
            return $order->harga_distributor * $order->jumlah; // Menghitung total harga untuk semua barang
        });


        // Mengambil harga per produk
        $prices = $orders->pluck('harga_distributor', 'id_master_barang')->toArray();

        return view('agen.detailPesanan', compact('orders', 'totalAmount', 'prices', 'namaRokokList', 'namaDistributor'));
    }

    //Menyimpan Order Agen
    public function store(Request $request)
    {
        // Handle file upload
        if ($request->hasFile('payment_proof')) {
            $path = $request->file('payment_proof')->store('bukti_transfer', 'public');
        }

        //ambil data semua buat data untuk tabel order lalu generate id order terbaru lalu jalankan foreach

        // Calculate total price
        $totalAmount = 0;
        $id_user_agen = session('id_user_agen');
        $id_user_distributor = session('id_user_distributor');
        // Memasukkan data kedalan tabel Order Sales
        $orders = [
            'id_user_agen' => $id_user_agen,
            'id_user_distributor' => $id_user_distributor,
            'jumlah' => $request->total_items,
            'total' => $request->total_amount,
            'tanggal' => now(),
            'bukti_transfer' => $path ?? '',
            'status_pemesanan' => 0, // Assuming 0 means "Pending"
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Memasukan data Ke dalam tabel Detail Order Sales
        OrderAgen::insert($orders);
        $id_order = OrderAgen::latest('id_order')->first()->id_order;
        $orders = [];
        foreach ($request->input('quantities') as $productId => $quantity) {
            $totalAmount = 0;
            $product = DB::table('tbl_barang_disitributor')->where('id_master_barang', $productId)->first();
            $totalAmount += $product->harga_distributor * $quantity;


            $orders[] = [
                'id_order' => $id_order,
                'id_user_distributor' => $id_user_distributor,
                'id_user_agen' => $id_user_agen,
                'id_master_barang' => $productId,
                'id_barang_distributor' => $product->id_barang_distributor,
                'jumlah_produk' => $quantity,
                'jumlah_harga_item' => $totalAmount,
                'harga_tetap_nota' => $product->harga_distributor,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Memasukan data Ke dalam tabel Order Detail Sales
        OrderDetailAgen::insert($orders);

        // Redirect or return a response
        return redirect()->route('riwayatAgen')->with('success', 'Pesanan berhasil dikirim!');
    }

    public function notaAgen($idNota)
    {
        Carbon::setLocale('id');
        // Ganti dengan ID order yang ingin dicari
        $orderDetailAgen = OrderDetailAgen::where('id_order', $idNota)->first();
        $orderDetailAgenItem = OrderDetailAgen::where('id_order', $idNota)->get();
        $orderAgen = OrderAgen::where('id_order', $idNota)->first();
        $namaDistributor = DB::table('user_distributor')->where('id_user_distributor', $orderDetailAgen->id_user_distributor)->first();
        $namaAgen = DB::table('user_agen')->where('id_user_agen', $orderAgen->id_user_agen)->first();



        $itemNota = [];
        $nama_rokok = [];

        foreach ($orderDetailAgenItem as $barangAgen) {
            $product = DB::table('master_barang')->where('id_master_barang', $barangAgen->id_master_barang)->first();
            $hargaSatuan = DB::table('tbl_barang_disitributor')->where('id_master_barang', $barangAgen->id_master_barang)->first();
            if ($product) { // Cek apakah product ada dan memiliki properti nama_rokok
                $nama_rokok[] = $product->nama_rokok;
                $harga_satuan[] = $barangAgen->harga_tetap_nota;
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


        $notaAgen = [
            'tanggal' => Carbon::parse($orderAgen->tanggal)->translatedFormat('d F Y'),
            'id_order' => $orderAgen->id_order,
            'nama_distributor' => $namaDistributor->nama_lengkap,
            'no_distributor' => $namaDistributor->no_telp,
            'nama_agen' => $namaAgen->nama_lengkap,
            'no_telp' => $namaAgen->no_telp,
            'total_item' => $orderAgen->jumlah,
            'total_harga' => $orderAgen->total,
            'item_nota' => $itemNota
        ];



        return view('agen.nota', compact('notaAgen'));
    }

    public function storeOrder(Request $request)
    {
        try {
            // Validate input
            $validated = $request->validate([
                'total_items' => 'required|integer|min:1',
                'total_amount' => 'required|numeric|min:1',
                'quantities' => 'required|array',
                'quantities.*.id_barang_distributor' => 'required|integer|exists:tbl_barang_disitributor,id_barang_distributor',
                'quantities.*.quantity' => 'required|integer|min:1',
                'payment_proof' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            ]);

            // Handle file upload if exists
            $path = $request->hasFile('payment_proof')
                ? $request->file('payment_proof')->store('bukti_transfer', 'public')
                : null;

            // Retrieve user data
            $id_user_agen = $request->user()->currentAccessToken()->user_id;
            $id_user_distributor = DB::table('user_agen')
                ->where('id_user_agen', $id_user_agen)
                ->value('id_user_distributor');

            if (!$id_user_distributor) {
                return response()->json(['error' => 'Distributor not found'], 404);
            }

            // Create Order
            $order = OrderAgen::create([
                'id_user_agen' => $id_user_agen,
                'id_user_distributor' => $id_user_distributor,
                'jumlah' => $validated['total_items'],
                'total' => $validated['total_amount'],
                'tanggal' => now(),
                'bukti_transfer' => $path,
                'status_pemesanan' => 0, // 0 = Pending
            ]);

            $orderDetails = [];
            foreach ($validated['quantities'] as $item) {
                $product = DB::table('tbl_barang_disitributor')
                    ->where('id_barang_distributor', $item['id_barang_distributor'])
                    ->first();

                // jika struktur tabel order_detail_agen kamu butuh kolom id_master_barang
                $orderDetails[] = [
                    'id_order'           => $order->id_order,
                    'id_user_distributor' => $id_user_distributor,
                    'id_user_agen'       => $id_user_agen,

                    // <<< ini dia: pakai id_master_barang, bukan id_barang_distributor
                    'id_master_barang'   => $product->id_master_barang,
                    'id_barang_distributor' => $product->id_barang_distributor,
                    'jumlah_produk'      => $item['quantity'],
                    'jumlah_harga_item'  => $product->harga_distributor * $item['quantity'],
                    'harga_tetap_nota'   => $product->harga_distributor,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ];
            }

            // Bulk insert order details
            OrderDetailAgen::insert($orderDetails);

            return response()->json([
                'message' => 'Pesanan berhasil dikirim!',
                'id_order' => $order->id_order,
                'order_details' => $orderDetails
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function riwayatOrderAPI(Request $request)
    {
        $id_user_agen = $request->user()
            ->currentAccessToken()
            ->user_id;

        // 1. Ambil orders & paginate
        $orders = OrderAgen::where('id_user_agen', $id_user_agen)
            ->orderByDesc('id_order')
            ->paginate(10);

        // 2. Transform setiap item di collection
        $orders->getCollection()->transform(function ($order) {
            // a) Ambil detail per order dari tabel order_detail_agen
            $detail = DB::table('order_detail_agen')
                ->where('id_order', $order->id_order)
                ->get();

            // b) Ambil data master_barang sesuai id_master_barang
            $masterData = MasterBarang::whereIn(
                'id_master_barang',
                $detail->pluck('id_master_barang')->toArray()
            )
                ->get()
                ->keyBy('id_master_barang');

            // c) Gabungkan detail + info produk
            $detailProduk = $detail->map(function ($d) use ($masterData) {
                $brg = $masterData[$d->id_master_barang] ?? null;
                if (! $brg) return null;

                return [
                    'id_master_barang'    => $brg->id_master_barang,
                    'nama_rokok'         => $brg->nama_rokok,         // sesuaikan nama kolom di MasterBarang
                    'harga_karton_pabrik'        => $brg->harga_karton_pabrik,       // sesuaikan kolom harga
                    'quantity'            => $d->jumlah_produk,        // sesuaikan kolom di detail
                ];
            })
                ->filter()   // buang null
                ->values(); // reindex

            // d) Bentuk response untuk tiap order
            return [
                'id_order'          => $order->id_order,
                'jumlah'            => $order->jumlah,
                'total'             => $order->total,
                'tanggal'           => Carbon::parse($order->tanggal)
                    ->translatedFormat('d F Y'),
                'status_pemesanan'  => $order->status_pemesanan,
                'bukti_transfer'    => $order->bukti_transfer,
                'detail_produk'     => $detailProduk,
            ];
        });

        // 3. Kembalikan JSON dengan struktur yang sama seperti distributor
        return response()->json($orders);
    }

    public function notaAgenPdf($idNota)
    {
        Carbon::setLocale('id');

        // Ambil header + semua relasi (detail, masterBarang, distributor, agen)
        $order = OrderAgen::with([
            'detailAgen.masterBarang',
            'distributor',
            'agen',
        ])->where('id_order', $idNota)
            ->firstOrFail();

        // Map detail jadi array
        $itemNota = $order->detailAgen->map(function ($d) {
            return [
                'nama_rokok'   => optional($d->masterBarang)->nama_rokok,
                'harga_satuan' => $d->harga_tetap_nota,
                'jumlah_item'  => $d->jumlah_produk,
                'jumlah_harga' => $d->jumlah_harga_item,
            ];
        })->toArray();

        // Susun payload
        $notaAgen = [
            'tanggal'           => Carbon::parse($order->tanggal)
                ->translatedFormat('d F Y'),
            'id_order'          => $order->id_order,
            'nama_distributor'  => $order->distributor->nama_lengkap,
            'no_distributor'    => $order->distributor->no_telp,
            'nama_agen'         => $order->agen->nama_lengkap,
            'no_telp'           => $order->agen->no_telp,
            'total_item'        => $order->jumlah,
            'total_harga'       => $order->total,
            'item_nota'         => $itemNota,
        ];


        $pdf = Pdf::loadView('agen.nota-cetak', compact('notaAgen'))
            ->setPaper('a4', 'portrait');

        // Keluarkan sebagai download
        return $pdf->download("nota-agen-{$idNota}.pdf");
    }
}
