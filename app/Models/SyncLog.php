<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncLog extends Model
{
    protected $fillable = [
        'shop_id', 'type', 'status', 'records_fetched',
        'records_upserted', 'error_message', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function shop(): BelongsTo { return $this->belongsTo(PancakeShop::class, 'shop_id'); }
}
