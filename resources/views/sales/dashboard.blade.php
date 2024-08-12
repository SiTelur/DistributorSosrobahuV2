@extends('sales.default')

@section('content')
    <div class="p-4 md:p-6 lg:p-8 my-16">
        <div class="text-center mb-8 bg-gray-800 text-white py-4 rounded-lg shadow-md">
            <h1 class="text-3xl md:text-4xl font-bold">Dashboard Sales</h1>
            <p class="text-lg md:text-xl mt-1">Gambaran Kinerja Penjualan Sales</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-6">
            <!-- Jumlah Toko -->
            <div class="bg-white shadow-lg rounded-lg p-6">
                <div class="flex items-center mb-4">
                    <i class="fas fa-store text-green-500 text-3xl md:text-4xl mr-3"></i>
                    <h2 class="text-xl md:text-2xl font-semibold text-gray-800">5</h2>
                </div>
                <p class="text-gray-600">Jumlah Toko</p>
            </div>

            <!-- Total Stock -->
            <div class="bg-white shadow-lg rounded-lg p-6">
                <div class="flex items-center mb-4">
                    <i class="fas fa-boxes text-blue-500 text-3xl md:text-4xl mr-3"></i>
                    <h2 class="text-xl md:text-2xl font-semibold text-gray-800">53</h2>
                </div>
                <p class="text-gray-600"> Total Stok (Slop)</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-6">
            <!-- Produk Paling Sering Dipesan -->
            <div class="col-span-1 md:col-span-2 lg:col-span-3 mt-6">
                <h2 class="text-xl md:text-2xl font-semibold text-gray-800 mb-4">Produk Paling Sering Dipesan</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3 gap-6">
                    <div class="bg-white shadow-lg rounded-lg p-6">
                        <div class="flex items-center mb-4">
                            <i class="fas fa-box text-red-500 text-3xl md:text-4xl mr-3"></i>
                            <h3 class="text-lg md:text-xl font-semibold text-gray-900">Sosrobahu Mangga Alpukat</h3>
                        </div>
                        <p class="text-gray-600">50 kali dipesan</p>
                    </div>
                    <div class="bg-white shadow-lg rounded-lg p-6">
                        <div class="flex items-center mb-4">
                            <i class="fas fa-box text-red-500 text-3xl md:text-4xl mr-3"></i>
                            <h3 class="text-lg md:text-xl font-semibold text-gray-900">Sosrobahu Kopi Hitam</h3>
                        </div>
                        <p class="text-gray-600">45 kali dipesan</p>
                    </div>
                    <div class="bg-white shadow-lg rounded-lg p-6">
                        <div class="flex items-center mb-4">
                            <i class="fas fa-box text-red-500 text-3xl md:text-4xl mr-3"></i>
                            <h3 class="text-lg md:text-xl font-semibold text-gray-900">Sosrobahu D&H</h3>
                        </div>
                        <p class="text-gray-600">40 kali dipesan</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
