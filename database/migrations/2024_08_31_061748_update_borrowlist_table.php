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
        Schema::table('borrowlist', function (Blueprint $table) {
            //
            $table->string('sa_lending_person_name')->nullable()->change();
            $table->string('sa_lending_date')->nullable()->change();
            $table->string('sa_id_take')->default(0)->change();
            $table->string('sa_deposit_take')->default(0)->change();
            $table->string('sa_id_deposit_box_number')->default(-1)->change();
            $table->string('sa_return_person_name')->nullable()->change();
            $table->string('sa_returned_date')->nullable()->change();
            $table->string('sa_id_returned')->default(0)->change();
            $table->string('sa_deposit_returned')->default(0)->change();
            $table->string('sa_remark')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('=borrowlist', function (Blueprint $table) {
            //
        });
    }
};
