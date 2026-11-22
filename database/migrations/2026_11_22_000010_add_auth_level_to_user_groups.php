<?php

use App\Support\AuthLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 群組的借用權限等級。
 *
 * 原本 auth_level 只有「0 或非 0」兩種意義，數字本身不被比較。
 * 加上群組等級之後才真的成為分級制：群組等級 >= 器材等級 才借得到。
 *
 * 既有群組一律給 MAX，維持原本「有授權就借得到」的行為，不讓現有設定突然失效。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_groups', function (Blueprint $table) {
            $table->unsignedSmallInteger('auth_level')->default(1)->comment('群組可借的最高器材等級');
        });

        DB::table('user_groups')->update(['auth_level' => AuthLevel::MAX]);

        DB::statement('ALTER TABLE user_groups ADD CONSTRAINT user_groups_auth_level_range CHECK (auth_level BETWEEN 0 AND '.AuthLevel::MAX.')');
        DB::statement('ALTER TABLE properties ADD CONSTRAINT properties_auth_level_range CHECK (auth_level BETWEEN 0 AND '.AuthLevel::MAX.')');
        DB::statement('ALTER TABLE property_pools ADD CONSTRAINT property_pools_auth_level_range CHECK (auth_level BETWEEN 0 AND '.AuthLevel::MAX.')');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE properties DROP CONSTRAINT IF EXISTS properties_auth_level_range');
        DB::statement('ALTER TABLE property_pools DROP CONSTRAINT IF EXISTS property_pools_auth_level_range');

        Schema::table('user_groups', function (Blueprint $table) {
            $table->dropColumn('auth_level');
        });
    }
};
