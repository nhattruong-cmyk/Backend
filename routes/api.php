<?php

use App\Http\Controllers\RoleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DepartmentController;


Route::apiResource('roles', RoleController::class);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    //user
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    
    //department
    Route::get('departments', [DepartmentController::class, 'index']);
    Route::post('departments', [DepartmentController::class, 'store']);
    Route::post('departments/{department_id}/add-user', [DepartmentController::class, 'addUserToDepartment']);
    Route::delete('departments/{department_id}/remove-user/{user_id}', [DepartmentController::class, 'removeUserFromDepartment']);
    Route::put('departments/{department_id}', [DepartmentController::class, 'update']);
    Route::delete('departments/{department_id}', [DepartmentController::class, 'destroy']);
});




Route::get('/', function () {
    return 'API';
});