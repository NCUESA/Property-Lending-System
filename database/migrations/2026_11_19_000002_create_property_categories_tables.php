<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 編碼規則清冊。財產編號 8 碼 = 地點(1) + 主要類別(3) + 次要類別(1) + 流水號(3)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_categories', function (Blueprint $table) {
            $table->id();
            $table->char('code', 3)->unique()->comment('財產編號第 2-4 碼');
            $table->string('name', 50);
            $table->timestamps();
        });

        Schema::create('property_subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_category_id')->constrained()->restrictOnDelete();
            $table->char('code', 1)->comment('財產編號第 5 碼，0-9 再 A-Z');
            $table->string('name', 50);
            $table->timestamps();

            $table->unique(['property_category_id', 'code']);
        });

        DB::statement("ALTER TABLE property_categories ADD CONSTRAINT property_categories_code_check CHECK (code ~ '^[0-9]{3}$')");
        DB::statement("ALTER TABLE property_subcategories ADD CONSTRAINT property_subcategories_code_check CHECK (code ~ '^[0-9A-Z]$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('property_subcategories');
        Schema::dropIfExists('property_categories');
    }
};
