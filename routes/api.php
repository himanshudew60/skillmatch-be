<?php

use App\Http\Controllers\AIController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectAllocationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LKController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectRequirementController;
use App\Http\Controllers\ResourceManagerController;
use App\Http\Controllers\SkillController;
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
  Route::get('/dashboard', [DashboardController::class, 'index']);
    // ---------------------------------------------
    // Authentication
    // ---------------------------------------------
Route::get('/me',[AuthController::class, 'me']);
Route::post('/logout',[AuthController::class, 'logout']);


    // ---------------------------------------------
    // Employee Dropdown Data
    // ---------------------------------------------
Route::get('/getDepartments',[EmployeeController::class, 'getDepartments']);
Route::get('/getDesignations',[EmployeeController::class, 'getDesignations']);
Route::get('/getSkills',[EmployeeController::class, 'getSkills']);


    // ---------------------------------------------
    // Employees
    // ---------------------------------------------
// employees
Route::get('/employees',[EmployeeController::class, 'index']);
Route::post('/employees',[EmployeeController::class, 'store']);
Route::post('/employees/import', [EmployeeController::class, 'import']);
Route::get('/employees/{id}', [EmployeeController::class, 'show']);
Route::put('/employees/{id}', [EmployeeController::class, 'update']);
Route::delete('/employees/{id}',[EmployeeController::class, 'destroy']);

// users
Route::get('/users', [UserController::class, 'index']);
Route::post('/users', [UserController::class, 'store']);
Route::get('/users/available-employees', [UserController::class, 'getAvailableEmployees']);
Route::get('/user-roles', [UserController::class, 'getRoles']);
Route::get('/users/{id}', [UserController::class, 'show']);
Route::put('/users/{id}', [UserController::class, 'update']);
Route::delete('/users/{id}', [UserController::class, 'destroy']);

// Projects
Route::get('/projects', [ProjectController::class, 'index']);
Route::post('/projects', [ProjectController::class, 'store']);
Route::get('/projects/{id}', [ProjectController::class, 'show']);
Route::put('/projects/{id}', [ProjectController::class, 'update']);
Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);

// Project Requirements
Route::get('/project-requirements', [ProjectRequirementController::class, 'index']);
Route::post('/project-requirements', [ProjectRequirementController::class, 'store']);
Route::get('/project-requirements/{id}', [ProjectRequirementController::class, 'show']);
Route::put('/project-requirements/{id}', [ProjectRequirementController::class, 'update']);
Route::delete('/project-requirements/{id}', [ProjectRequirementController::class, 'destroy']);

// =========================
// Skills
// =========================

Route::get('/skills', [SkillController::class, 'index']);
Route::post('/skills', [SkillController::class, 'store']);
Route::get('/skills/{id}', [SkillController::class, 'show']);
Route::put('/skills/{id}', [SkillController::class, 'update']);
Route::delete('/skills/{id}', [SkillController::class, 'destroy']);


// =========================
// Departments
// =========================

Route::get('/departments', [DepartmentController::class, 'index']);
Route::post('/departments', [DepartmentController::class, 'store']);
Route::get('/departments/{id}', [DepartmentController::class, 'show']);
Route::put('/departments/{id}', [DepartmentController::class, 'update']);
Route::delete('/departments/{id}', [DepartmentController::class, 'destroy']);


// =========================
// Designations
// =========================

Route::get('/designations', [DesignationController::class, 'index']);
Route::post('/designations', [DesignationController::class, 'store']);
Route::get('/designations/{id}', [DesignationController::class, 'show']);
Route::put('/designations/{id}', [DesignationController::class, 'update']);
Route::delete('/designations/{id}', [DesignationController::class, 'destroy']);


// L&K


Route::get('/lk/employees', [LKController::class, 'employees']);

Route::get('/lk/employees/{employeeId}/skills', [
    LKController::class,
    'employeeSkills'
]);

Route::post('/lk/skill-assessments', [
    LKController::class,
    'submitAssessment'
]);

Route::get('/lk/upskill-requests', [
    LKController::class,
    'upskillRequests'
]);

// ADD THIS POST ROUTE
Route::post('/lk/upskill-requests', [
    LKController::class,
    'sendToUpskill'
]);

Route::get('/lk/upskill-requests/{id}', [
    LKController::class,
    'upskillRequest'
]);

Route::post('/lk/upskill-requests/{id}/assessment', [
    LKController::class,
    'submitUpskillAssessment'
]);

// resource manger
Route::post(
    '/resource-manager/upskill-requests',
    [ResourceManagerController::class, 'sendUpskillRequest']
);
Route::put(
    '/employees/{id}/status',
    [ResourceManagerController::class, 'updateEmployeeStatus']
);
Route::get(
        '/resource-manager/requirements/{id}/recommendations',
        [ResourceManagerController::class, 'recommendations']
    );

    // Route::post(
    //     '/resource-manager/requirements/{id}/ai-recommendations',
    //     [ResourceManagerController::class, 'aiRecommendations']
    // );
    Route::post(
        '/resource-manager/reviedByManager/{id}',
        [ResourceManagerController::class, 'reviedByManager']
    );

Route::get(
    '/resource-manager/ai-recommendations/{id}',
    [ResourceManagerController::class, 'aiRecommendations']
);

    
    // =============================================
    // Project Allocations
    // =============================================

Route::get(
    '/project-allocations',
    [ProjectAllocationController::class, 'index']
);

Route::get(
    '/project-allocations/eligible-employees',
    [ProjectAllocationController::class, 'eligibleEmployees']
);

Route::get(
    '/projects/{projectId}/allocations',
    [ProjectAllocationController::class, 'projectAllocations']
);

Route::post(
    '/project-allocations',
    [ProjectAllocationController::class, 'store']
);

Route::get(
    '/project-allocations/{id}',
    [ProjectAllocationController::class, 'show']
);

Route::put(
    '/project-allocations/{id}',
    [ProjectAllocationController::class, 'update']
);

Route::delete(
    '/project-allocations/{id}',
    [ProjectAllocationController::class, 'destroy']
);
Route::put(
    '/projects/{projectId}/allocations/bulk-update',
    [ProjectAllocationController::class, 'bulkUpdate']
);

Route::post(
    '/projects/{projectId}/allocations/bulk-release',
    [ProjectAllocationController::class, 'bulkRelease']
);
Route::post(
    '/project-allocations/bulk',
    [ProjectAllocationController::class, 'storeBulk']
);


// ai COntorller
Route::get('/ai/dash', [AIController::class, 'dash']);
Route::post('/ai/chat', [AIController::class, 'chat']);
});