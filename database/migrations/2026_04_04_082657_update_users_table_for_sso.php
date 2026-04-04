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
        Schema::table('users', function (Blueprint $table) {
            // Add provider_id for SSO identification
            if (!Schema::hasColumn('users', 'provider_id')) {
                $table->string('provider_id')->nullable()->unique()->after('id');
            }

            // Make password nullable since SSO users will not have a local password
            $table->string('password')->nullable()->change();

            // Ensure specific fields exist for the system logic
            if (!Schema::hasColumn('users', 'student_id')) {
                $table->string('student_id')->unique()->nullable()->after('provider_id');
            }
            if (!Schema::hasColumn('users', 'level')) {
                $table->string('level')->default('muggle')->after('name');
            }
            if (!Schema::hasColumn('users', 'status')) {
                $table->string('status')->default('u')->after('level');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'provider_id')) {
                $table->dropColumn('provider_id');
            }
            
            $table->string('password')->nullable(false)->change();
        });
    }
};
