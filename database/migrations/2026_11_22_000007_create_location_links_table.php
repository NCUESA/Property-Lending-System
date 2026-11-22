<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 地點互連改成有向圖。
 *
 * 原本是 locations.pickup_location_id：一個存放地點只能掛在一個取用點底下（樹）。
 * 改成 location_links 之後，同一個倉庫可以同時由多個取用點借出，
 * 借用頁直接讀這張表，不必把關係寫死在程式或 seeder 裡。
 *
 * 只看直接相連的一層：進德→307、307→某小倉 不代表進德借得到某小倉，
 * 否則新增一條邊會意外放寬其他地點的範圍。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pickup_location_id')->comment('在這裡取件')
                ->constrained('locations')->cascadeOnDelete();
            $table->foreignId('storage_location_id')->comment('可借出這裡的器材')
                ->constrained('locations')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['pickup_location_id', 'storage_location_id']);
        });

        // 自己借自己是隱含的，不需要存成一條邊
        DB::statement('
            ALTER TABLE location_links
            ADD CONSTRAINT location_links_not_self
            CHECK (pickup_location_id <> storage_location_id)
        ');

        // 既有的樹搬成邊
        DB::statement('
            INSERT INTO location_links (pickup_location_id, storage_location_id, created_at, updated_at)
            SELECT pickup_location_id, id, now(), now()
            FROM locations
            WHERE pickup_location_id IS NOT NULL
        ');

        Schema::table('locations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pickup_location_id');
            $table->boolean('is_active')->default(true)->comment('停用後不出現在借用頁');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->foreignId('pickup_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->dropColumn('is_active');
        });

        // 一個存放點只能有一個取用點，多出來的邊會在這裡被丟掉
        DB::statement('
            UPDATE locations SET pickup_location_id = l.pickup_location_id
            FROM (
                SELECT DISTINCT ON (storage_location_id) storage_location_id, pickup_location_id
                FROM location_links ORDER BY storage_location_id, id
            ) l
            WHERE locations.id = l.storage_location_id
        ');

        Schema::dropIfExists('location_links');
    }
};
