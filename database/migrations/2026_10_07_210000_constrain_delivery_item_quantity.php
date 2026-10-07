<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE delivery_task_items ADD CONSTRAINT delivery_items_positive_quantity CHECK (quantity > 0)');
    }

    public function down(): void
    {
        $keyword = DB::getDriverName() === 'pgsql' ? 'CONSTRAINT' : 'CHECK';
        DB::statement('ALTER TABLE delivery_task_items DROP '.$keyword.' delivery_items_positive_quantity');
    }
};
