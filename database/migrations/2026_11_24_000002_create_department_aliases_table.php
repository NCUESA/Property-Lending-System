<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 借用單位的同義詞：申請時單位是手填的（熱舞、熱舞社、酷電熱舞社…），
 * 統計時把各種寫法對應到同一個名稱。只影響統計，不改借用單上的原文。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('alias', 100)->unique()->comment('借用單上的寫法（去掉前後空白）');
            $table->string('canonical', 100)->index()->comment('統計時使用的名稱');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_aliases');
    }
};
