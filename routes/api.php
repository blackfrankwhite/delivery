<?php

use App\Http\Controllers\Api\AddressSearchController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerAddressController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeliveryTaskController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\RideController;
use App\Http\Controllers\Api\StorageLocationController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->group(function () {

    Route::get('addresses/search', AddressSearchController::class)->middleware(['can:dispatch', 'throttle:30,1']);
    Route::get('ride-storage-locations', [StorageLocationController::class, 'rideOptions']);
    Route::apiResource('storage-locations', StorageLocationController::class)->parameters(['storage-locations' => 'storageLocation']);
    Route::get('me', [AuthController::class, 'me']);
    Route::get('dashboard', DashboardController::class);
    Route::get('drivers', [UserController::class, 'drivers']);
    Route::get('drivers/{user}', [UserController::class, 'driver']);
    Route::get('operators', [UserController::class, 'operators']);
    Route::apiResource('users', UserController::class);
    Route::apiResource('customers', CustomerController::class);
    Route::get('customers/{customer}/addresses', [CustomerAddressController::class, 'index']);
    Route::post('customers/{customer}/addresses', [CustomerAddressController::class, 'store']);
    Route::put('customers/{customer}/addresses/{address}', [CustomerAddressController::class, 'update']);
    Route::delete('customers/{customer}/addresses/{address}', [CustomerAddressController::class, 'destroy']);
    Route::apiResource('items', ItemController::class);
    Route::apiResource('delivery-tasks', DeliveryTaskController::class)->parameters(['delivery-tasks' => 'deliveryTask']);
    Route::post('delivery-tasks/{deliveryTask}/assign-driver', [DeliveryTaskController::class, 'assign']);
    Route::post('delivery-tasks/{deliveryTask}/status', [DeliveryTaskController::class, 'status']);
    Route::patch('delivery-tasks/{deliveryTask}/notes', [DeliveryTaskController::class, 'notes']);
    Route::apiResource('orders', DeliveryTaskController::class)->parameters(['orders' => 'deliveryTask']);
    Route::post('orders/{deliveryTask}/assign-driver', [DeliveryTaskController::class, 'assign']);
    Route::post('orders/{deliveryTask}/status', [DeliveryTaskController::class, 'status']);
    Route::patch('orders/{deliveryTask}/notes', [DeliveryTaskController::class, 'notes']);
    Route::apiResource('rides', RideController::class);
    Route::post('rides/{ride}/clients', [RideController::class, 'addClient']);
    Route::post('rides/{ride}/tasks', [RideController::class, 'addTasks']);
    Route::delete('rides/{ride}/tasks/{deliveryTask}', [RideController::class, 'removeTask']);
    Route::put('rides/{ride}/reorder', [RideController::class, 'reorder']);
    Route::post('rides/{ride}/plan', [RideController::class, 'plan'])->middleware('throttle:20,1');
    Route::post('rides/{ride}/status', [RideController::class, 'status']);
    Route::prefix('driver')->middleware('can:driver')->group(function () {
        Route::get('today', [DeliveryTaskController::class, 'today']);
        Route::get('rides', [RideController::class, 'index']);
        Route::get('orders/{deliveryTask}', [DeliveryTaskController::class, 'show']);
        Route::get('tasks/{deliveryTask}', [DeliveryTaskController::class, 'show']);
    });
});
