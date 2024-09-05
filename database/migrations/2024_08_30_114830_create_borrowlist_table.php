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
        Schema::create('borrowlist', function (Blueprint $table) {
            $table->id();

            // Input from the form
            $table->boolean('understand');
            $table->string('borrow_place',5);
            $table->string('borrow_department',10);
            $table->string('borrow_person_name',10);
            $table->string('phone',12);
            $table->string('email',20);
            $table->date('borrow_date');
            $table->date('returned_date');

            // Other require columns
            $table->string('sa_lending_person_name');
            $table->date('sa_lending_date');
            $table->boolean('sa_id_take');
            $table->boolean('sa_deposit_take');
            $table->integer('sa_id_deposit_box_number');
            $table->string('sa_return_person_name');
            $table->date('sa_returned_date');
            $table->boolean('sa_id_returned');
            $table->boolean('sa_deposit_returned');
            $table->string('sa_remark');


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('borrowlist');
    }
};
