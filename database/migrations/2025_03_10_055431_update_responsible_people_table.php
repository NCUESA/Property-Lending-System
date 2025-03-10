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
        Schema::table('responsible_person', function (Blueprint $table) {
            // Adjust responsible_person columns
            $table->string('stu_id',10);
            $table->enum('auth_level',['admin','normal','muggle'])->default('muggle');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('responsible_person', function (Blueprint $table) {
            //
        });
    }
};
