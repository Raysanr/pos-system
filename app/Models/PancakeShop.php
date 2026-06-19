<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PancakeShop extends Model
{
    protected $fillable = [
        'user_id', 'shop_id', 'api_key', 'shop_name',
        'is_active', 'last_synced_at', 'sync_interval_hours',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function customers(): HasMany { return $this->hasMany(Customer::class, 'shop_id'); }
    public function orders(): HasMany { return $this->hasMany(Order::class, 'shop_id'); }
    public function syncLogs(): HasMany { return $this->hasMany(SyncLog::class, 'shop_id'); }
}
