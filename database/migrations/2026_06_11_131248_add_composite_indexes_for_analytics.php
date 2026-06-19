<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['shop_id', 'ordered_at'], 'orders_shop_date');
            $table->index(['shop_id', 'is_rts', 'ordered_at'], 'orders_shop_rts_date');
            $table->index(['shop_id', 'province', 'ordered_at'], 'orders_shop_province_date');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index(['shop_id', 'total_spent'], 'customers_shop_spent');
            $table->index(['shop_id', 'province'], 'customers_shop_province');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_shop_date');
            $table->dropIndex('orders_shop_rts_date');
            $table->dropIndex('orders_shop_province_date');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_shop_spent');
            $table->dropIndex('customers_shop_province');
        });
    }
};
