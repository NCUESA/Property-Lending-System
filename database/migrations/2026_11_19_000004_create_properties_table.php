<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->char('ssid', 8)->unique()->comment('財產編號：地點(1) 主類(3) 次類(1) 流水號(3)');
            $table->foreignId('property_subcategory_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sequence_no')->nullable()->comment('財產序');
            $table->string('name', 100);
            $table->string('second_name', 100)->nullable();
            $table->string('spec')->nullable()->comment('規格');
            $table->unsignedInteger('price')->nullable();
            $table->string('department', 50)->nullable()->comment('使用單位');
            $table->string('custodian', 50)->nullable()->comment('保管人');
            $table->foreignId('location_id')->comment('目前存放地點')->constrained()->restrictOnDelete();
            $table->foreignId('property_pool_id')->nullable()
                ->comment('屬於 bulk 品項池時不可單獨挑選，權限以品項池為準')
                ->constrained()->nullOnDelete();
            $table->date('acquired_on')->nullable();
            $table->boolean('is_school_owned')->default(false)->comment('校方資產');
            $table->unsignedSmallInteger('auth_level')->default(0)->comment('0 = 訪客可借；> 0 需群組授權');
            $table->string('image_path')->nullable();
            $table->boolean('is_lendable')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->index(['location_id', 'is_lendable']);
            $table->index('property_pool_id');
        });

        DB::statement("ALTER TABLE properties ADD CONSTRAINT properties_ssid_format_check CHECK (ssid ~ '^[0-9A-Z][0-9]{3}[0-9A-Z][0-9]{3}$')");
        DB::statement('ALTER TABLE properties ADD CONSTRAINT properties_auth_level_check CHECK (auth_level >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
