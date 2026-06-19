<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_geocache', function (Blueprint $table) {
            $table->id();
            $table->string('name_key')->unique(); // "level:name:province" lowercase
            $table->string('display_name');
            $table->string('level');             // province | city | barangay
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_geocache');
    }
};
