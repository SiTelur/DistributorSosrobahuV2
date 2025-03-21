<?php

namespace App\Http\Controllers\Distributor;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\OrderDistributor;
use App\Models\OrderDetailDistributor;
use App\Models\MasterBarang;
use App\Models\UserPabrik;


class OrderDistributorController extends Controller
{
    public function dashboardData()
    {
        // Mengambil semua pesanan yang statusnya selesai
        $completedOrders = OrderDistributor::where('status_pemesanan', 1)->get();

        // Mengambil detail pesanan
        $orderDetails = OrderDetailDistributor::whereIn('id_order', $completedOrders->pluck('id_order'))->get();

        // Menghitung total stok (konversi dari karton ke slop, 1 karton = 10 slop)
        $slopPerKarton = 10;
        $totalStockKarton = $orderDetails->sum('jumlah_produk'); // Karton
        $totalStockSlop = $totalStockKarton * $slopPerKarton;

        // Pesanan masuk (yang sudah berhasil)
        $incomingCompletedOrders = DB::table('order_detail_agen')
            ->join('order_agen', 'order_agen.id_order', '=', 'order_detail_agen.id_order')
            ->where('order_agen.status_pemesanan', 1)
            ->sum('order_detail_agen.jumlah_produk'); // Slop

        // Hitung stok yang disesuaikan (dikurangi pesanan masuk yang sudah berhasil)
        $finalStockSlop = $totalStockSlop - $incomingCompletedOrders;

        // Produk terlaris dari pesanan sales yang statusnya 1
        $topProduct = DB::table('order_detail_agen')
            ->join('order_agen', 'order_agen.id_order', '=', 'order_detail_agen.id_order')
            ->where('order_agen.status_pemesanan', 1) // Status pesanan sales yang selesai
            ->select('order_detail_agen.id_master_barang', DB::raw('SUM(order_detail_agen.jumlah_produk) as total_jumlah'))
            ->groupBy('order_detail_agen.id_master_barang')
            ->orderBy('total_jumlah', 'desc')
            ->first();

        $topProductName = $topProduct ? DB::table('master_barang')
            ->where('id_master_barang', $topProduct->id_master_barang)
            ->value('nama_rokok') : 'Tidak ada data';

        // Total pendapatan dari pesanan sales yang statusnya 1
        $totalPendapatan = DB::table('order_distributor')
            ->where('status_pemesanan', 1)
            ->sum('total');

        // Mengambil jumlah sales dari tabel user_sales
        $totalAgen = DB::table('user_agen')->count();

        // Mengirim data ke view dashboard
        return view('distributor.dashboard', [
            'finalStockSlop' => $finalStockSlop,
            'totalPendapatan' => $totalPendapatan,
            'topProductName' => $topProductName,
            'totalAgen' => $totalAgen,
        ]);
    }

    public function index()
    {
        $id_user_distributor = session('id_user_distributor');
        // Mengambil pesanan dengan mengurutkan berdasarkan ID terbesar
        $orderDistributors = OrderDistributor::where('id_user_distributor', $id_user_distributor)
            ->orderBy('id_order', 'desc')
            ->paginate(10);

        // Mengonversi tanggal ke format Carbon
        foreach ($orderDistributors as $orderDistributor) {
            $orderDistributor->tanggal = Carbon::parse($orderDistributor->tanggal);
        }

        // Mengirim data pesanan ke view
        return view('distributor.riwayatDistributor', compact('orderDistributors'));

        // Menampilkan data menggunakan json
        // return response()->json($orderDistributors);
    }

    // Menampilkan detail pada order Distributor
    public function detail(Request $request)
    {
        $selectedProductIds = $request->input('products', []); // Mengambil ID produk yang dipilih dari request
        $namaRokokList = [];

        // Ambil informasi pabrik (asumsikan hanya ada satu pabrik)
        $getPabrik = UserPabrik::first(); // Mengambil data pabrik pertama

        // Pastikan pabrik ditemukan
        if (!$getPabrik) {
            return back()->withErrors(['message' => 'Pabrik tidak ditemukan.']);
        }

        // Ambil informasi pabrik
        $infoPabrik = [
            'nama_pabrik' => $getPabrik->nama_lengkap,
            'no_rek' => $getPabrik->no_rek,
            'nama_bank' => $getPabrik->nama_bank,
        ];

        // Loop through each selected product ID
        foreach ($selectedProductIds as $barangPabrik) {

            // Convert the ID to an integer
            $namaProdukint = intval($barangPabrik);

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
        $orders = MasterBarang::whereIn('id_master_barang', $selectedProductIds)->get();

        // Menghitung total harga
        $totalAmount = $orders->sum(function ($order) {
            return $order->harga_karton_pabrik * $order->jumlah; // Menghitung total harga untuk semua barang
        });


        // Mengambil harga per produk
        $prices = $orders->pluck('harga_karton_pabrik', 'id_master_barang')->toArray();

        return view('distributor.detailpesan', compact('orders', 'totalAmount', 'prices', 'namaRokokList', 'infoPabrik'));
    }

    //Menyimpan Order Distributor
    public function store(Request $request)
    {
        // Handle file upload
        if ($request->hasFile('payment_proof')) {
            $path = $request->file('payment_proof')->store('bukti_transfer', 'public');
        }

        //ambil data semua buat data untuk tabel order lalu generate id order terbaru lalu jalankan foreach

        // Calculate total price
        $totalAmount = 0;
        $id_user_distributor = session('id_user_distributor');
        // Memasukkan data kedalan tabel Order DIstributor
        $orders = [
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
        OrderDistributor::insert($orders);
        $id_order = OrderDistributor::latest('id_order')->first()->id_order;
        $orders = [];
        foreach ($request->input('quantities') as $productId => $quantity) {
            $totalAmount = 0;
            $product = DB::table('master_barang')->where('id_master_barang', $productId)->first();
            $totalAmount += $product->harga_karton_pabrik * $quantity;


            $orders[] = [
                'id_order' => $id_order,
                'id_user_pabrik' => 1,
                'id_user_distributor' => $id_user_distributor,
                'id_master_barang' => $productId,
                'jumlah_produk' => $quantity,
                'jumlah_harga_item' => $totalAmount,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Memasukan data Ke dalam tabel Order Detail Sales
        OrderDetailDistributor::insert($orders);

        // Redirect or return a response
        return redirect()->route('riwayatDistributor')->with('success', 'Pesanan berhasil dikirim!');
    }

    // Menampilkan Nota pada riwayat Distributor
    public function notaDistributor($idNota)
    {
        Carbon::setLocale('id');
        // Ganti dengan ID order yang ingin dicari
        $orderDetailDistributor = OrderDetailDistributor::where('id_order', $idNota)->first();
        $orderDetailDistributorItem = OrderDetailDistributor::where('id_order', $idNota)->get();
        $orderDistributor = OrderDistributor::where('id_order', $idNota)->first();
        $namaPabrik = DB::table('user_pabrik')->where('id_user_pabrik', $orderDetailDistributor->id_user_pabrik)->first();
        $namaDistributor = DB::table('user_distributor')->where('id_user_distributor', $orderDistributor->id_user_distributor)->first();

        $itemNota = [];
        $nama_rokok = [];

        foreach ($orderDetailDistributorItem as $barangDistributor) {
            // Mengambil data dari tabel master_barang, termasuk harga_karton_pabrik
            $product = DB::table('master_barang')->where('id_master_barang', $barangDistributor->id_master_barang)->first();

            if ($product) { // Cek apakah product ada dan memiliki properti nama_rokok
                $nama_rokok[] = $product->nama_rokok;
                $harga_satuan[] = $product->harga_karton_pabrik; // Menggunakan harga_karton_pabrik dari master_barang
                $jumlah_item[] = $barangDistributor->jumlah_produk;
                $jumlah_harga[] = $barangDistributor->jumlah_harga_item;
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

        $notaDistributor = [
            'tanggal' => Carbon::parse($orderDistributor->tanggal)->translatedFormat('d F Y'),
            'id_order' => $orderDistributor->id_order,
            'nama_pabrik' => $namaPabrik->nama_lengkap,
            'no_pabrik' => $namaPabrik->no_telp,
            'nama_distributor' => $namaDistributor->nama_lengkap,
            'no_telp' => $namaDistributor->no_telp,
            'total_item' => $orderDistributor->jumlah,
            'total_harga' => $orderDistributor->total,
            'item_nota' => $itemNota
        ];

        // Menampilkan Hasil nota format view
        return view('distributor.nota', compact('notaDistributor'));

        //Menampilkan hasil nota format json
        // return response()->json($notaDistributor);
    }

    public function storeOrderAPI(Request $request)
    {
        try {
            // Validate request
            $validated = $request->validate([
                'total_items' => 'required|integer|min:1',
                'total_amount' => 'required|numeric|min:1',
                'quantities' => 'required|array', // Ensure quantities is an array
                'quantities.*.id_master_barang' => 'required|integer|exists:master_barang,id_master_barang',
                'quantities.*.quantity' => 'required|integer|min:1',
                'payment_proof' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
            ]);

            // Handle file upload
            $path = $request->hasFile('payment_proof')
                ? $request->file('payment_proof')->store('bukti_transfer', 'public')
                : null;

            // Get distributor ID from token
            $id_user_distributor = $request->user()->currentAccessToken()->user_id;

            // Start transaction to ensure data consistency
            DB::beginTransaction();

            // Create order and get its ID
            $order = OrderDistributor::create([
                'id_user_distributor' => $id_user_distributor,
                'jumlah' => $request->total_items,
                'total' => $request->total_amount,
                'tanggal' => now(),
                'bukti_transfer' => $path,
                'status_pemesanan' => 0, // 0 = Pending
            ]);

            // Prepare order details for bulk insert
            $orderDetails = [];
            foreach ($request->input('quantities') as $productId => $quantity) {
                $product = DB::table('master_barang')->where('id_master_barang', $productId)->first();
                if (!$product) {
                    throw new \Exception("Produk dengan ID $productId tidak ditemukan.");
                }

                $orderDetails[] = [
                    'id_order' => $order->id_order,
                    'id_user_pabrik' => 1,
                    'id_user_distributor' => $id_user_distributor,
                    'id_master_barang' => $productId,
                    'jumlah_produk' => $quantity,
                    'jumlah_harga_item' => $product->harga_karton_pabrik * $quantity,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Bulk insert order details
            OrderDetailDistributor::insert($orderDetails);

            // Commit transaction
            DB::commit();

            // Return JSON response
            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil dikirim!',
                'order' => $order,
                'order_details' => $orderDetails,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memproses pesanan.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function listRiwayatOrderAPI(Request $request)
    {
        $id_user_distributor = $request->user()->currentAccessToken()->user_id;

        // Ambil data pesanan dengan format tanggal yang sudah dikonversi
        $orderDistributors = OrderDistributor::where('id_user_distributor', $id_user_distributor)
            ->orderByDesc('id_order')
            ->paginate(10)
            ->through(fn($order) => [
                'id_order' => $order->id_order,
                'jumlah' => $order->jumlah,
                'total' => $order->total,
                'tanggal' => Carbon::parse($order->tanggal)->translatedFormat('d F Y'),
                'status_pemesanan' => $order->status_pemesanan,
                'bukti_transfer' => $order->bukti_transfer,
            ]);

        // Mengembalikan data sebagai JSON response
        return response()->json([
            'success' => true,
            'message' => 'Riwayat pesanan berhasil diambil.',
            'data' => $orderDistributors
        ]);
    }
}
