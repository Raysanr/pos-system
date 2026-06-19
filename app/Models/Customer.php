<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'shop_id', 'pancake_id', 'name', 'phone', 'email',
        'gender', 'birthday', 'province', 'district', 'ward', 'address',
        'customer_level', 'is_new_customer', 'is_wholesale',
        'total_orders', 'total_spent', 'reward_points', 'referred_by',
        'tags', 'pancake_tags', 'utm_source',
        'first_order_at', 'last_order_at',
    ];

    protected $casts = [
        'birthday' => 'date',
        'is_new_customer' => 'boolean',
        'is_wholesale' => 'boolean',
        'tags' => 'array',
        'pancake_tags' => 'array',
        'utm_source' => 'array',
        'first_order_at' => 'datetime',
        'last_order_at' => 'datetime',
    ];

    public function shop(): BelongsTo { return $this->belongsTo(PancakeShop::class, 'shop_id'); }
    public function orders(): HasMany { return $this->hasMany(Order::class, 'customer_pancake_id', 'pancake_id'); }

    public function getAgeAttribute(): ?int
    {
        return $this->birthday ? $this->birthday->age : null;
    }

    public function getAgeGroupAttribute(): string
    {
        $age = $this->age;
        if (!$age) return 'Unknown';
        return match(true) {
            $age < 18 => 'Under 18',
            $age < 25 => '18-24',
            $age < 35 => '25-34',
            $age < 45 => '35-44',
            $age < 55 => '45-54',
            default   => '55+',
        };
    }
}
