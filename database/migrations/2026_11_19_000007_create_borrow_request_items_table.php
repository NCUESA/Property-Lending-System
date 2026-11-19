<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 借用明細有兩種：
 * - 指定器材：property_id，quantity 固定為 1
 * - bulk：property_pool_id + quantity，可部分歸還（returned_quantity）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrow_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrow_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('property_pool_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedSmallInteger('returned_quantity')->default(0);
            $table->string('status', 20)->default('pending');
            $table->foreignId('returned_by')->nullable()
                ->comment('最後一次歸還的經手人；部分歸還時每件可能不同人')
                ->constrained('users')->restrictOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();

            $table->unique(['borrow_request_id', 'property_id']);
            $table->unique(['borrow_request_id', 'property_pool_id']);
            $table->index('property_id');
            $table->index('property_pool_id');
        });

        DB::statement("ALTER TABLE borrow_request_items ADD CONSTRAINT borrow_request_items_status_check CHECK (status IN ('pending', 'lent', 'cancelled', 'returned'))");
        DB::statement(<<<'SQL'
            ALTER TABLE borrow_request_items ADD CONSTRAINT borrow_request_items_kind_check CHECK (
                (property_id IS NOT NULL AND property_pool_id IS NULL AND quantity = 1)
                OR (property_id IS NULL AND property_pool_id IS NOT NULL AND quantity >= 1)
            )
            SQL);
        DB::statement('ALTER TABLE borrow_request_items ADD CONSTRAINT borrow_request_items_returned_quantity_check CHECK (returned_quantity <= quantity)');

        // 指定器材：一件器材同時只能有一筆進行中的借用
        DB::statement("CREATE UNIQUE INDEX one_active_borrow_per_property ON borrow_request_items (property_id) WHERE status IN ('pending', 'lent')");

        // bulk：進行中的未歸還數量不能超過品項池內可借的器材數。
        // 先鎖住品項池那一列，讓同時送出的申請排隊檢查。
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION borrow_request_items_check_pool_capacity() RETURNS trigger AS $$
            DECLARE
                capacity integer;
                in_use integer;
                requested integer;
            BEGIN
                IF NEW.property_pool_id IS NULL OR NEW.status NOT IN ('pending', 'lent') THEN
                    RETURN NEW;
                END IF;

                PERFORM 1 FROM property_pools WHERE id = NEW.property_pool_id FOR UPDATE;

                SELECT count(*) INTO capacity
                FROM properties
                WHERE property_pool_id = NEW.property_pool_id AND is_lendable;

                SELECT coalesce(sum(quantity - returned_quantity), 0) INTO in_use
                FROM borrow_request_items
                WHERE property_pool_id = NEW.property_pool_id
                  AND status IN ('pending', 'lent')
                  AND id <> NEW.id;

                requested := NEW.quantity - NEW.returned_quantity;

                IF in_use + requested > capacity THEN
                    RAISE EXCEPTION '品項池 % 數量不足：需要 %，剩餘 %',
                        NEW.property_pool_id, requested, capacity - in_use
                        USING ERRCODE = 'check_violation';
                END IF;

                RETURN NEW;
            END
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER borrow_request_items_check_pool_capacity
                BEFORE INSERT OR UPDATE ON borrow_request_items
                FOR EACH ROW EXECUTE FUNCTION borrow_request_items_check_pool_capacity();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('borrow_request_items');
        DB::statement('DROP FUNCTION IF EXISTS borrow_request_items_check_pool_capacity()');
    }
};
