<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('driver')->index();
            $t->string('phone')->nullable();
            $t->boolean('active')->default(true);
        });
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('phone');
            $t->string('email')->nullable();
            $t->text('notes')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('customer_addresses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->string('label');
            $t->text('address');
            $t->string('city')->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            foreach (['entrance', 'floor', 'apartment'] as $f) {
                $t->string($f)->nullable();
            }
            $t->text('instructions')->nullable();
            $t->boolean('is_default')->default(false);
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('items', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('sku')->nullable()->unique();
            $t->text('description')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('rides', function (Blueprint $t) {
            $t->id();
            $t->foreignId('driver_id')->constrained('users')->restrictOnDelete();
            $t->date('date')->index();
            $t->string('status')->default('planned')->index();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('delivery_reference_sequences', function (Blueprint $t) {
            $t->date('date')->primary();
            $t->unsignedInteger('value')->default(0);
        });
        Schema::create('delivery_tasks', function (Blueprint $t) {
            $t->id();
            $t->string('reference_number')->unique();
            $t->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('customer_address_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('driver_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignId('ride_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->string('recipient_name');
            $t->string('recipient_phone');
            $t->text('address');
            $t->string('city')->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            foreach (['entrance', 'floor', 'apartment'] as $f) {
                $t->string($f)->nullable();
            }
            $t->date('delivery_date')->index();
            $t->time('time_from')->nullable();
            $t->time('time_to')->nullable();
            $t->string('status')->default('pending')->index();
            $t->string('priority')->default('normal')->index();
            $t->text('notes')->nullable();
            $t->text('driver_notes')->nullable();
            $t->unsignedInteger('sequence')->nullable();
            foreach (['started_at', 'arrived_at', 'completed_at', 'cancelled_at'] as $f) {
                $t->timestamp($f)->nullable();
            }
            $t->timestamps();
            $t->index(['driver_id', 'delivery_date']);
            $t->index(['ride_id', 'sequence']);
        });
        Schema::create('delivery_task_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('delivery_task_id')->constrained()->cascadeOnDelete();
            $t->foreignId('item_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('quantity');
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['delivery_task_id', 'item_id']);
        });
        Schema::create('delivery_task_status_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('delivery_task_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('from_status')->nullable();
            $t->string('to_status');
            $t->text('notes')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['delivery_task_status_history', 'delivery_task_items', 'delivery_tasks', 'delivery_reference_sequences', 'rides', 'items', 'customer_addresses', 'customers'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'phone', 'active']));
    }
};
