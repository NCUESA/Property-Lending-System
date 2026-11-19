<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 使用者群組與借用授權。
 *
 * 群組可以包含其他群組（user_group_subgroups）：子群組的成員也算是父群組的成員，
 * 因此會繼承父群組的所有授權。例如「全體幹部」包含「資訊部」，
 * 授權給全體幹部的器材，資訊部成員也能借。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('user_group_members', function (Blueprint $table) {
            $table->foreignId('user_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['user_group_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('user_group_subgroups', function (Blueprint $table) {
            $table->foreignId('parent_group_id')->constrained('user_groups')->cascadeOnDelete();
            $table->foreignId('child_group_id')->constrained('user_groups')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['parent_group_id', 'child_group_id']);
            $table->index('child_group_id');
        });

        Schema::create('user_group_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_group_id')->constrained()->cascadeOnDelete();
            // 四擇一：單一器材、主要類別、次要類別、bulk 品項池
            $table->foreignId('property_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('property_category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('property_subcategory_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('property_pool_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE user_group_subgroups ADD CONSTRAINT user_group_subgroups_not_self CHECK (parent_group_id <> child_group_id)');
        DB::statement('ALTER TABLE user_group_grants ADD CONSTRAINT user_group_grants_one_target CHECK (num_nonnulls(property_id, property_category_id, property_subcategory_id, property_pool_id) = 1)');
        DB::statement('CREATE UNIQUE INDEX user_group_grants_unique ON user_group_grants (user_group_id, property_id, property_category_id, property_subcategory_id, property_pool_id) NULLS NOT DISTINCT');

        // 防止群組互相包含形成循環：加入 parent → child 時，child 不能是 parent 本身或 parent 的祖先
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION user_group_subgroups_prevent_cycle() RETURNS trigger AS $$
            BEGIN
                IF EXISTS (
                    WITH RECURSIVE ancestors(id) AS (
                        SELECT NEW.parent_group_id
                        UNION
                        SELECT s.parent_group_id
                        FROM user_group_subgroups s
                        JOIN ancestors a ON s.child_group_id = a.id
                    )
                    SELECT 1 FROM ancestors WHERE id = NEW.child_group_id
                ) THEN
                    RAISE EXCEPTION '群組 % 已經是群組 % 的上層，不能再被包含（會形成循環）',
                        NEW.child_group_id, NEW.parent_group_id
                        USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER user_group_subgroups_prevent_cycle
                BEFORE INSERT OR UPDATE ON user_group_subgroups
                FOR EACH ROW EXECUTE FUNCTION user_group_subgroups_prevent_cycle();
            SQL);

        // 使用者實際所屬的所有群組（直接加入的群組 + 這些群組的所有上層群組）
        DB::statement(<<<'SQL'
            CREATE VIEW user_effective_groups AS
            WITH RECURSIVE memberships(user_id, user_group_id) AS (
                SELECT user_id, user_group_id FROM user_group_members
                UNION
                SELECT m.user_id, s.parent_group_id
                FROM memberships m
                JOIN user_group_subgroups s ON s.child_group_id = m.user_group_id
            )
            SELECT user_id, user_group_id FROM memberships
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS user_effective_groups');
        Schema::dropIfExists('user_group_grants');
        Schema::dropIfExists('user_group_subgroups');
        DB::statement('DROP FUNCTION IF EXISTS user_group_subgroups_prevent_cycle()');
        Schema::dropIfExists('user_group_members');
        Schema::dropIfExists('user_groups');
    }
};
