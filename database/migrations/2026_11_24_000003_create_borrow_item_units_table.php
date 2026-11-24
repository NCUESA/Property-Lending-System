<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 大量借用品項實際借出的是哪幾件：出借時掃到的器材記在這裡，歸還時逐件結清。
 * 沒掃條碼、只填數量的部分不會有紀錄（數量仍以 borrow_request_items 為準）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrow_item_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrow_request_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['borrow_request_item_id', 'property_id']);
            $table->index('property_id');
        });

        // 同一件器材同時只能在一筆借用裡
        DB::statement('CREATE UNIQUE INDEX one_open_unit_per_property ON borrow_item_units (property_id) WHERE returned_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('borrow_item_units');
    }
};
