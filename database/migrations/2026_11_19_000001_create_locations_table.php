<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 50);
            $table->boolean('is_pickup_point')->default(false)->comment('借用人可選的取用地點');
            $table->foreignId('pickup_location_id')->nullable()
                ->constrained('locations')->nullOnDelete()
                ->comment('存放於此的器材從哪個取用地點借出；取用地點本身為 null');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
