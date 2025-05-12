<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\BarangAgen;
use App\Models\DaftarToko;
use App\Models\OrderSale;
use Illuminate\Http\Request;
use App\Models\OrderDetailSales;
use App\Models\KunjunganToko;
use App\Models\UserAgen;
use App\Models\UserSales;
use Carbon\Carbon;
use App\Models\MasterBarang;
use Illuminate\Validation\ValidationException;

class OrderSaleController extends Controller
{

    public function dashboard()
    {
        // Ambil id_user_sales dari session
        $id_user_sales = session('id_user_sales');

        // Mengambil data total harga dari semua pemesanan yang berstatus 'selesai' dan sesuai dengan id_user_sales
        $orderSales = OrderSale::where('status_pemesanan', 1)
            ->where('id_user_sales', $id_user_sales)
            ->get();
        $totalPrice = $orderSales->sum('total');

        // Mengambil jumlah toko berdasarkan id_user_sales
        $jumlahToko = DaftarToko::where('id_user_sales', $id_user_sales)->count();

        // Mengambil produk terlaris untuk id_user_sales
        $topProduct = OrderDetailSales::select('id_master_barang', DB::raw('SUM(jumlah_produk) as total_quantity'))
            ->whereHas('orderSale', function ($query) use ($id_user_sales) {
                $query->where('status_pemesanan', 1)
                    ->where('id_user_sales', $id_user_sales);
            })
            ->groupBy('id_master_barang')
            ->orderBy('total_quantity', 'desc')
            ->first();

        // Jika ada produk terlaris
        $topProductName = $topProduct ? DB::table('master_barang')->where('id_master_barang', $topProduct->id_master_barang)->value('nama_rokok') : 'Tidak ada data';

        // Menghitung total stok (dalam pcs) berdasarkan pesanan yang berstatus 'selesai' dan sesuai id_user_sales
        $totalStok = OrderDetailSales::whereHas('orderSale', function ($query) use ($id_user_sales) {
            $query->where('status_pemesanan', 1)
                ->where('id_user_sales', $id_user_sales);
        })->sum(DB::raw('jumlah_produk * 10')); // Mengonversi slop ke pcs

        // Menghitung total penjualan (sisa produk) sesuai dengan id_user_sales
        $totalPenjualan = KunjunganToko::where('id_user_sales', $id_user_sales)->sum('sisa_produk'); // Total produk yang terjual
        $totalStok -= $totalPenjualan; // Mengurangi stok berdasarkan produk yang terjual

        // Mengirimkan variabel ke view
        return view('sales.dashboard', compact('totalPrice', 'jumlahToko', 'topProductName', 'totalStok'));
    }


    /**
     * Function untuk Menampilkan semua Order dari database
     */
    public function index()
    {
        // Ambil id_user_sales dari session
        $id_user_sales = session('id_user_sales');

        // Mengambil pesanan yang sesuai dengan id_user_sales dan mengurutkan berdasarkan ID terbesar
        $orderSales = OrderSale::where('id_user_sales', $id_user_sales)
            ->orderBy('id_order', 'desc')
            ->paginate(10);

        // Mengonversi tanggal ke format Carbon
        foreach ($orderSales as $orderSale) {
            $orderSale->tanggal = Carbon::parse($orderSale->tanggal);
        }

        // Mengirim data pesanan ke view
        return view('sales.riwayatOrder', compact('orderSales'));
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('order_sales.create');
    }

    /**
     * Function untuk input ke database
     */
    public function store(Request $request)
    {

        // Validate the request
        // $validatedData = $request->validate([
        //     'payment-proof' => 'required|file|mimes:jpeg,png,pdf|max:2048',
        //     'quantities' => 'required|array',
        //     'quantities.*' => 'required|integer|min:1',
        // ]);

        // Handle file upload
        if ($request->hasFile('payment_proof')) {
            $path = $request->file('payment_proof')->store('bukti_transfer', 'public');
        }

        //ambil data semua buat data untuk tabel order lalu generate id order terbaru lalu jalankan foreach

        // Calculate total price
        $totalAmount = 0;
        $id_user_sales = session('id_user_sales');
        $id_user_agen = session('id_user_agen');
        // Memasukkan data kedalan tabel Order Sales
        $orders = [
            'id_user_sales' => $id_user_sales,
            'id_user_agen' => $id_user_agen,
            'jumlah' => $request->total_items,
            'total' => $request->total_amount,
            'tanggal' => now(),
            'bukti_transfer' => $path ?? '',
            'status_pemesanan' => 0, // Assuming 0 means "Pending"
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Memasukan data Ke dalam tabel Detail Order Sales
        OrderSale::insert($orders);
        $id_order = OrderSale::latest('id_order')->first()->id_order;
        $orders = [];
        foreach ($request->input('quantities') as $productId => $quantity) {
            $totalAmount = 0;
            $product = DB::table('tbl_barang_agen')
                ->where('id_master_barang', $productId)
                ->where('id_user_agen', $id_user_agen)
                ->first();
            $totalAmount += $product->harga_agen * $quantity;



            $orders[] = [
                'id_order' => $id_order,
                'id_user_agen' => $id_user_agen,
                'id_user_sales' => $id_user_sales,
                'id_master_barang' => $productId,
                'id_barang_agen' => $product->id_barang_agen,
                'jumlah_produk' => $quantity,
                'jumlah_harga_item' => $totalAmount,
                'harga_tetap_nota' => $product->harga_agen,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Memasukan data Ke dalam tabel Order Detail Sales
        OrderDetailSales::insert($orders);

        // Redirect or return a response
        return redirect()->route('riwayatOrder')->with('success', 'Pesanan berhasil dikirim!');
    }



    /**
     * Menampilkan Order Berdasarkan id pada database
     */
    public function show($id)
    {
        $orderSale = OrderSale::find($id);
        return view('order_sales.show', compact('orderSale'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $orderSale = OrderSale::find($id);
        return view('sales.riwayat', compact('orderSale'));
    }

    // Menampilkan nota Berdasarkan id order
    public function showNota($id_order)
    {
        $orderSale = OrderSale::findOrFail($id_order);

        // Logika untuk menampilkan nota pesanan
        return view('order_sales.nota', compact('orderSale'));
    }

    public function showBayar($id_nota)
    {
        // Ganti dengan ID order yang ingin dicari
        $orderDetailSales = OrderDetailSales::where('id_order', $id_nota)->first();
        $orderDetailSalesItem = OrderDetailSales::where('id_order', $id_nota)->get();
        $orderSale = OrderSale::where('id_order', $id_nota)->first();
        $namaAgen = DB::table('user_agen')->where('id_user_agen', $orderDetailSales->id_user_agen)->first();
        $namaSales = DB::table('user_sales')->where('id_user_sales', $orderSale->id_user_sales)->first();

        foreach ($orderDetailSalesItem as $barangAgen) {
            $product = DB::table('master_barang')->where('id_master_barang', $barangAgen->id_master_barang)->first();
            $hargaSatuan = DB::table('tbl_barang_agen')
                ->where('id_user_agen', $barangAgen->id_user_agen)
                ->where('id_barang_agen', $barangAgen->id_barang_agen)
                ->first();

            $itemNota[] = [
                'nama_rokok' => $product ? $product->nama_rokok : null,
                'harga_satuan' => $hargaSatuan ? $hargaSatuan->harga_agen : null,
                'jumlah_item' => $barangAgen->jumlah_produk,
                'jumlah_harga' => $barangAgen->jumlah_harga_item,
            ];
        }

        $formattedDate = Carbon::parse($orderSale->tanggal)->locale('id')->translatedFormat('j F Y');

        $notaSales = [
            'tanggal' => $formattedDate,
            'id_order' => $orderSale->id_order,
            'nama_agen' => $namaAgen->nama_lengkap,
            'nama_bank' => $namaAgen->nama_bank,
            'no_rek' => $namaAgen->no_rek,
            'nama_sales' => $namaSales->nama_lengkap,
            'no_telp' => $namaSales->no_telp,
            'total_item' => $orderSale->jumlah,
            'total_harga' => $orderSale->total,
            'item_nota' => $itemNota
        ];

        return view('sales.bayar', compact('notaSales', 'id_nota'));
    }


    /**
     * Function untuk Mengupdate ke database 
     */
    public function update(Request $request, $id_nota)
    {

        $editNota = OrderSale::find($id_nota);
        if (!$editNota) {
            return response()->json(['message' => 'Data not found'], 404);
        } else {
            if ($request->hasFile('bukti_transfer')) {
                $gambarPath = $request->file('bukti_transfer')->store('images', 'public');
                $editNota->bukti_transfer = $gambarPath;
            }
            $editNota->save();
        }


        return redirect()->route('riwayatOrder',)
            ->with('success', 'Kunjungan toko berhasil diperbarui.');
    }

    /**
     * Function untuk Menghapus atau delete ke database
     */
    public function destroy($id)
    {
        $orderSale = OrderSale::find($id);
        $orderSale->delete();

        return redirect()->route('order_sales.index')
            ->with('success', 'Order Sale deleted successfully.');
    }


    public function detail(Request $request)
    {
        $selectedProductIds = $request->input('products', []); // Mengambil ID produk yang dipilih dari request
        $namaRokokList = [];
        // Ambil ID agen dari session
        $idAgen = session('id_user_agen'); // Mengambil ID agen dari session
        $getAgen = UserAgen::where('id_user_agen', $idAgen)->first();

        // Pastikan agen ditemukan
        if (!$getAgen) {
            return back()->withErrors(['message' => 'Agen tidak ditemukan.']);
        }

        // Ambil informasi agen
        $namaAgen = [
            'nama_agen' => $getAgen->nama_lengkap,
            'no_rek' => $getAgen->no_rek,
            'nama_bank' => $getAgen->nama_bank,
        ];

        // Loop through each selected product ID
        foreach ($selectedProductIds as $barangAgen) {

            // Convert the ID to an integer
            $namaProdukint = intval($barangAgen);

            // Query the master_barang table for the corresponding record
            $orderValue = DB::table('master_barang')->where('id_master_barang', $namaProdukint)->limit(1)->first();

            // Store the nama_rokok in the array
            if ($orderValue) {
                $namaRokokList[] = $orderValue->nama_rokok;
            } else {
                $namaRokokList[] = null; // If no matching record is found
            }
        }



        // Ambil detail pesanan berdasarkan ID produk yang dipilih
        $orders = BarangAgen::where('id_user_agen', $idAgen) // Filter berdasarkan id_user_agen
            ->whereIn('id_master_barang', $selectedProductIds) // Filter berdasarkan id_barang_agen
            ->get();

        // Menghitung total harga
        $totalAmount = $orders->sum(function ($order) {
            return $order->harga_agen * $order->jumlah; // Menghitung total harga untuk semua barang
        });


        // Mengambil harga per produk
        $prices = $orders->pluck('harga_agen', 'id_master_barang')->toArray();
        // return response()->json($orders);
        return view('sales.detail_pesanan', compact('orders', 'totalAmount', 'prices', 'namaRokokList', 'namaAgen'));
    }




    public function submit(Request $request)
    {
        $request->validate([
            'products' => 'required|array', // Validasi ID produk yang dipilih
            'payment-proof' => 'required|file|mimes:jpeg,png,pdf|max:2048' // Validasi file bukti pembayaran
        ]);

        // Proses upload bukti pembayaran
        $filePath = $request->file('payment-proof')->store('public/payment_proofs');

        // Simpan data pesanan ke database (contoh sederhana, sesuaikan dengan struktur data dan kebutuhan aplikasi)
        // Misalnya, Anda perlu membuat model Order dan menyimpan data pesanan ke dalamnya

        // Redirect dengan pesan sukses
        return redirect()->route('detail')->with('success', 'Pesanan Anda telah diproses. Terima kasih!');
    }


    public function notaSales($idNota)
    {
        // Ganti dengan ID order yang ingin dicari
        $orderDetailSales = OrderDetailSales::where('id_order', $idNota)->first();
        $orderDetailSalesItem = OrderDetailSales::where('id_order', $idNota)->get();
        $orderSale = OrderSale::where('id_order', $idNota)->first();
        $namaAgen = DB::table('user_agen')->where('id_user_agen', $orderDetailSales->id_user_agen)->first();
        $namaSales = DB::table('user_sales')->where('id_user_sales', $orderSale->id_user_sales)->first();


        $itemNota = [];
        $nama_rokok = [];

        foreach ($orderDetailSalesItem as $barangAgen) {
            $product = DB::table('master_barang')->where('id_master_barang', $barangAgen->id_master_barang)->first();
            $hargaSatuan = DB::table('tbl_barang_agen')->where('id_master_barang', $barangAgen->id_master_barang)->first();
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

        $formattedDate = Carbon::parse($orderSale->tanggal)->locale('id')->translatedFormat('j F Y');

        $notaSales = [
            'tanggal' => $formattedDate,
            'id_order' => $orderSale->id_order,
            'nama_agen' => $namaAgen->nama_lengkap,
            'no_agen' => $namaAgen->no_telp,
            'nama_sales' => $namaSales->nama_lengkap,
            'no_telp' => $namaSales->no_telp,
            'total_item' => $orderSale->jumlah,
            'total_harga' => $orderSale->total,
            'item_nota' => $itemNota
        ];



        return view('sales.nota', compact('notaSales'));
    }


    public function dashboardSalesAPI(Request $request)
    {
        $id_user_sales = $request->user()->currentAccessToken()->user_id;
        $totalPrice = intval(OrderSale::where('status_pemesanan', 1)
            ->where('id_user_sales', $id_user_sales)
            ->sum('total'));

        // Menghitung jumlah toko berdasarkan id_user_sales
        $jumlahToko = DaftarToko::where('id_user_sales', $id_user_sales)->count();

        // Mengambil produk terlaris untuk id_user_sales
        $topProduct = OrderDetailSales::select('id_master_barang', DB::raw('SUM(jumlah_produk) as total_quantity'))
            ->whereHas('orderSale', function ($query) use ($id_user_sales) {
                $query->where('status_pemesanan', 1)
                    ->where('id_user_sales', $id_user_sales);
            })
            ->groupBy('id_master_barang')
            ->orderByDesc('total_quantity')
            ->first();

        $namaLengkapSales = DB::table('user_sales')
            ->where('id_user_sales', $id_user_sales)
            ->value('nama_lengkap');

        // Mengambil nama produk terlaris
        $topProductName = $topProduct
            ? DB::table('master_barang')
            ->where('id_master_barang', $topProduct->id_master_barang)
            ->value('nama_rokok')
            : 'Tidak ada data';

        // Menghitung total stok (dalam pcs)
        $totalStok = OrderDetailSales::whereHas('orderSale', function ($query) use ($id_user_sales) {
            $query->where('status_pemesanan', 1)
                ->where('id_user_sales', $id_user_sales);
        })->sum(DB::raw('jumlah_produk * 10')); // Mengonversi slop ke pcs

        // Menghitung total penjualan (sisa produk)
        $totalPenjualan = KunjunganToko::where('id_user_sales', $id_user_sales)->sum('sisa_produk');

        // Mengurangi stok berdasarkan produk yang terjual
        $totalStok -= $totalPenjualan;

        // Mengembalikan response dalam format JSON
        return response()->json([
            'total_price' => $totalPrice,
            'jumlah_toko' => $jumlahToko,
            'top_product' => $topProductName,
            'total_stok' => $totalStok,
            'nama_sales' => $namaLengkapSales,
        ]);
    }

    public function getListBarangOrderAPI(Request $request)
    {
        // Get user ID from current access token
        $id_user_sales = $request->user()->currentAccessToken()->user_id;

        $id_user_agen = UserSales::where('id_user_sales', $id_user_sales)->value('id_user_agen');
        // Retrieve BarangAgen with related master_barang data in a single query
        $barangAgens = BarangAgen::where('id_user_agen', $id_user_agen)
            ->with('masterBarang:id_master_barang,nama_rokok,gambar') // Eager load only needed fields
            ->get();


        // Transform data into a structured response
        $data = $barangAgens->map(function ($item) {
            return [
                'id_barang_agen'    => $item->id_barang_agen,
                'id_master_barang'  => $item->id_master_barang,
                'nama_rokok'        => optional($item->masterBarang)->nama_rokok,
                'gambar'            => optional($item->masterBarang)->gambar,
                'harga'             => $item->harga_agen,
                'stok' => $item->stok_karton
            ];
        });

        // Return JSON response
        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function storeOrderAPI(Request $request)
    {
        try {
            // 1. Validasi input
            $validated = $request->validate([
                'total_items'                  => 'required|integer|min:1',
                'total_amount'                 => 'required|numeric|min:1',
                'quantities'                   => 'required|array',
                'quantities.*.id_barang_agen'  => 'required|integer|exists:tbl_barang_agen,id_barang_agen',
                'quantities.*.quantity'        => 'required|integer|min:1',
                'payment_proof'                => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            ]);

            // 2. Simpan file jika ada
            $path = $request->hasFile('payment_proof')
                ? $request->file('payment_proof')->store('bukti_transfer', 'public')
                : null;

            // 3. Ambil user IDs
            $id_user_sales = $request->user()
                ->currentAccessToken()
                ->user_id;
            $id_user_agen = DB::table('user_sales')
                ->where('id_user_sales', $id_user_sales)
                ->value('id_user_agen');
            if (! $id_user_agen) {
                return response()->json(['error' => 'Agen tidak ditemukan'], 404);
            }

            // 4. Buat order + detail dalam satu transaksi
            return DB::transaction(function () use ($validated, $id_user_sales, $id_user_agen, $path) {
                // a) Create OrderSale
                $order = OrderSale::create([
                    'id_user_sales'    => $id_user_sales,
                    'id_user_agen'     => $id_user_agen,
                    'jumlah'           => $validated['total_items'],
                    'total'            => $validated['total_amount'],
                    'tanggal'          => now(),
                    'bukti_transfer'   => $path,
                    'status_pemesanan' => 0, // pending
                ]);

                // b) Siapkan array bulk-insert
                $orderDetails = [];
                foreach ($validated['quantities'] as $item) {
                    $idBarangAgen = $item['id_barang_agen'];
                    $qty          = $item['quantity'];

                    // Ambil record tbl_barang_agen
                    $prod = DB::table('tbl_barang_agen')
                        ->where('id_barang_agen', $idBarangAgen)
                        ->first();

                    // Dari sana kita bisa ambil id_master_barang & harga_agen
                    $idMasterBarang = $prod->id_master_barang;
                    $hargaAgen      = $prod->harga_agen;

                    $orderDetails[] = [
                        'id_order'          => $order->id_order,
                        'id_user_agen'      => $id_user_agen,
                        'id_user_sales'     => $id_user_sales,
                        'id_master_barang'  => $idMasterBarang,
                        'id_barang_agen'    => $idBarangAgen,
                        'jumlah_produk'     => $qty,
                        'jumlah_harga_item' => $hargaAgen * $qty,
                        'harga_tetap_nota'  => $hargaAgen,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ];
                }

                // c) Bulk insert detail
                OrderDetailSales::insert($orderDetails);

                // d) Response sukses
                return response()->json([
                    'message'        => 'Pesanan berhasil dikirim!',
                    'order_id'       => $order->id_order,
                    'order_details'  => $orderDetails,
                ], 201);
            });
        } catch (\Exception $e) {

            return response()->json([
                'error' => $e->getMessage()
            ], $e instanceof ValidationException ? 422 : 500);
        }
    }


    public function riwayatOrderAPI(Request $request)
    {
        // 1. Ambil id_user_sales & cari id_user_agen yang terkait
        $id_user_sales = $request->user()
            ->currentAccessToken()
            ->user_id;
        $id_user_agen = UserSales::where('id_user_sales', $id_user_sales)
            ->value('id_user_agen');

        // 2. Ambil orders & paginate
        $orders = OrderSale::where('id_user_sales', $id_user_sales)
            ->orderByDesc('id_order')
            ->paginate(10);

        // 3. Transform setiap order
        $orders->getCollection()->transform(function ($order) use ($id_user_agen) {
            // a) ambil detail per order
            $detail = DB::table('order_detail_sales')
                ->where('id_order', $order->id_order)
                ->get();

            // b) ambil data produk master
            $masterData = MasterBarang::whereIn(
                'id_master_barang',
                $detail->pluck('id_master_barang')->toArray()
            )
                ->get()
                ->keyBy('id_master_barang');

            // c) ambil harga agen untuk tiap produk
            $hargaAgenData = DB::table('tbl_barang_agen')
                ->where('id_user_agen', $id_user_agen)
                ->whereIn('id_master_barang', $detail->pluck('id_master_barang')->toArray())
                ->get()
                ->keyBy('id_master_barang');

            // d) gabungkan semuanya
            $detailProduk = $detail->map(function ($d) use ($masterData, $hargaAgenData) {
                $brg      = $masterData[$d->id_master_barang]    ?? null;
                $hargaAgen = $hargaAgenData[$d->id_master_barang]->harga_agen ?? null;
                if (! $brg) return null;

                return [
                    'id_master_barang' => $brg->id_master_barang,
                    'nama_rokok'       => $brg->nama_rokok,
                    'harga_agen'       => $hargaAgen,
                    'quantity'         => $d->jumlah_produk,
                ];
            })
                ->filter()  // buang null
                ->values(); // reindex

            // e) bentuk response per order
            return [
                'id_order'         => $order->id_order,
                'jumlah'           => $order->jumlah,
                'total'            => $order->total,
                'tanggal'          => Carbon::parse($order->tanggal)
                    ->translatedFormat('d F Y'),
                'status_pemesanan' => $order->status_pemesanan,
                'bukti_transfer'   => $order->bukti_transfer,
                'detail_produk'    => $detailProduk,
            ];
        });

        // 4. kembalikan pagination + data
        return response()->json($orders);
    }
}
