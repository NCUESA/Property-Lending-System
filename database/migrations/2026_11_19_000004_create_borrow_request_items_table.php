<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrow_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrow_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->foreignId('returned_by')->nullable()
                ->comment('部分歸還時每件可能由不同人經手')
                ->constrained('users')->restrictOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();

            $table->unique(['borrow_request_id', 'property_id']);
            $table->index('property_id');
        });

        DB::statement("ALTER TABLE borrow_request_items ADD CONSTRAINT borrow_request_items_status_check CHECK (status IN ('pending', 'lent', 'cancelled', 'returned'))");

        // 一件器材同時只能有一筆進行中的借用
        DB::statement("CREATE UNIQUE INDEX one_active_borrow_per_property ON borrow_request_items (property_id) WHERE status IN ('pending', 'lent')");
    }

    public function down(): void
    {
        Schema::dropIfExists('borrow_request_items');
    }
};
