<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_tasks', function (Blueprint $table) {
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->softDeletes();
            $table->index(['date_from', 'date_to']);
        });
        DB::table('delivery_tasks')->update(['date_from' => DB::raw('delivery_date'), 'date_to' => DB::raw('delivery_date')]);
        Schema::table('delivery_tasks', function (Blueprint $table) {
            $table->date('date_from')->nullable(false)->change();
            $table->date('date_to')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_tasks', function (Blueprint $table) {
            $table->dropIndex(['date_from', 'date_to']);
            $table->dropColumn(['date_from', 'date_to', 'deleted_at']);
        });
    }
};
