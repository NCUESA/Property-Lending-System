<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bulk 品項池：例如「進德 折疊椅」。池內每件器材仍有自己的財產編號，借用時只算數量、不指定編號。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_pools', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->foreignId('location_id')->comment('取用地點')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('auth_level')->default(0)->comment('0 = 訪客可借；> 0 需群組授權');
            $table->string('image_path')->nullable();
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->unique(['location_id', 'name']);
        });

        DB::statement('ALTER TABLE property_pools ADD CONSTRAINT property_pools_auth_level_check CHECK (auth_level >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('property_pools');
    }
};
