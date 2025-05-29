<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderSale extends Model
{
    use HasFactory;

    protected $table = 'order_sales';
    protected $primaryKey = 'id_order';
    public $timestamps = false;

    protected $fillable = [
        'id_user_sales',
        'id_user_agen',
        'jumlah',
        'total',
        'tanggal',
        'bukti_transfer',
        'status_pemesanan',
    ];

    public function userSales()
    {
        return $this->belongsTo(UserSales::class, 'id_user_sales', 'id_user_sales');
    }

    public function detailSales()
    {
        return $this->hasMany(OrderDetailSales::class, 'id_order', 'id_order');
    }

    public function agen()
    {
        return $this->belongsTo(UserAgen::class, 'id_user_agen');
    }

    public function sales()
    {
        return $this->belongsTo(UserSales::class, 'id_user_sales');
    }
}
