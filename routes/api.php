<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;


// =========================================================
// Authentication
// =========================================================

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');


// =========================================================
// Protected Routes
// =========================================================

Route::middleware('jwt')->group(function () {

    // ---------------------------------------------
    // Authentication
    // ---------------------------------------------

    Route::get(
        '/me',
        [AuthController::class, 'me']
    );

    Route::post(
        '/logout',
        [AuthController::class, 'logout']
    );


    // ---------------------------------------------
    // Employee Dropdown Data
    // ---------------------------------------------

    Route::get(
        '/getDepartments',
        [EmployeeController::class, 'getDepartments']
    );

    Route::get(
        '/getDesignations',
        [EmployeeController::class, 'getDesignations']
    );

    Route::get(
        '/getSkills',
        [EmployeeController::class, 'getSkills']
    );


    // ---------------------------------------------
    // Employees
    // ---------------------------------------------

    Route::get(
        '/employees',
        [EmployeeController::class, 'index']
    );

    Route::post(
        '/employees',
        [EmployeeController::class, 'store']

    );
    Route::post('/employees/import', [EmployeeController::class, 'import']);

    Route::get('/employees/{id}', [EmployeeController::class, 'show']);

   Route::put('/employees/{id}', [EmployeeController::class, 'update']);

    Route::delete(
        '/employees/{id}',
        [EmployeeController::class, 'destroy']
    );
    Route::get('/users', [UserController::class, 'index']);
Route::get('/users/available-employees', [
    UserController::class, 'getAvailableEmployees'
]);
Route::get('/user-roles', [UserController::class, 'getRoles']);
Route::post('/users', [UserController::class, 'store']);

});