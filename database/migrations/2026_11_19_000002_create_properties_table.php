<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('ssid', 20)->unique()->comment('財產編號');
            $table->unsignedSmallInteger('sequence_no')->nullable()->comment('財產序');
            $table->string('category', 50)->comment('財產分類');
            $table->string('name', 100);
            $table->string('second_name', 100)->nullable();
            $table->string('spec')->nullable()->comment('規格');
            $table->unsignedInteger('price')->nullable();
            $table->string('department', 50)->nullable()->comment('使用單位');
            $table->string('custodian', 50)->nullable()->comment('保管人');
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->date('acquired_on')->nullable();
            $table->boolean('is_school_owned')->default(false)->comment('校方資產');
            $table->string('image_path')->nullable();
            $table->boolean('is_lendable')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->index(['location_id', 'is_lendable']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
