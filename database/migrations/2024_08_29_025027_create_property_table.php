<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('property', function (Blueprint $table) {
            $table->id();
            $table->string('ssid',8);

            $table->string('class',20);
            $table->string('name',30);
            $table->string('second_name',30);
            $table->integer('order_number');
            $table->integer('price');
            $table->string('department',10);
            $table->string('depositary',10);
            $table->string('belong_place',5);
            $table->time('get_day');
            $table->string('format',50);
            $table->string('remark',50);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property');
    }
};
