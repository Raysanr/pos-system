<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'shop_id', 'pancake_id', 'order_code', 'customer_pancake_id',
        'customer_name', 'customer_phone', 'status', 'payment_method', 'payment_status',
        'total_price', 'shipping_fee', 'discount', 'cod_amount',
        'courier', 'tracking_code', 'shipping_status',
        'province', 'district', 'ward', 'shipping_address',
        'channel', 'warehouse', 'is_rts', 'is_returned', 'is_cancelled', 'is_wholesale',
        'extra_note', 'return_reason', 'customer_age', 'health_condition',
        'items', 'utm_data', 'ordered_at', 'delivered_at',
    ];

    protected $casts = [
        'is_rts' => 'boolean',
        'is_returned' => 'boolean',
        'is_cancelled' => 'boolean',
        'is_wholesale' => 'boolean',
        'items' => 'array',
        'utm_data' => 'array',
        'ordered_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function shop(): BelongsTo { return $this->belongsTo(PancakeShop::class, 'shop_id'); }
}
