<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * List all active users.
     */
    public function index()
    {
        $users = DB::table('users as u')
            ->leftJoin('employees as e', 'u.employee_id', '=', 'e.id')
            ->leftJoin('departments as d', 'e.department_id', '=', 'd.id')
            ->leftJoin('roles as r', 'u.role_id', '=', 'r.id')
            ->where('u.bit_delete', 0)
            ->select([
                'u.id',
                'u.user_id',
                'u.employee_id',
                'u.role_id',
                'e.emp_code',
                'e.emp_name',
                'e.emp_email',
                'd.name as department_name',
                'r.name as role_name',
                'u.created_at',
                'u.updated_at',
            ])
            ->orderByDesc('u.id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }


    /**
     * Get available employees for creating a user account.
     */
    public function getAvailableEmployees(Request $request)
    {
        $search = trim($request->query('search', ''));

        $employees = DB::table('employees as e')
            ->leftJoin('departments as d', 'e.department_id', '=', 'd.id')
            ->where('e.bit_delete', 0)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('users as u')
                    ->whereColumn('u.employee_id', 'e.id')
                    ->where('u.bit_delete', 0);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('e.emp_name', 'like', "%{$search}%")
                        ->orWhere('e.emp_code', 'like', "%{$search}%")
                        ->orWhere('e.emp_email', 'like', "%{$search}%");
                });
            })
            ->select([
                'e.id',
                'e.emp_code',
                'e.emp_name',
                'e.emp_email',
                'd.name as department_name',
            ])
            ->orderBy('e.emp_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $employees,
        ]);
    }


    /**
     * Get all active roles.
     */
    public function getRoles()
    {
        $roles = DB::table('roles')
            ->where('bit_delete', 0)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }


    /**
     * Create a new user account.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|integer',
            'role_id' => 'required|integer',
            'user_id' => 'required|string|max:255',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please correct the submitted information.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check employee
        $employee = DB::table('employees')
            ->where('id', $request->employee_id)
            ->where('bit_delete', 0)
            ->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Selected employee is unavailable.',
            ], 422);
        }

        // Check role
        $role = DB::table('roles')
            ->where('id', $request->role_id)
            ->where('bit_delete', 0)
            ->first();

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Selected role is unavailable.',
            ], 422);
        }

        // Check duplicate user ID among active users
        $userIdExists = DB::table('users')
            ->where('user_id', $request->user_id)
            ->where('bit_delete', 0)
            ->exists();

        if ($userIdExists) {
            return response()->json([
                'success' => false,
                'message' => 'This User ID is already in use.',
            ], 422);
        }

        // Check employee already has an active account
        $alreadyAssigned = DB::table('users')
            ->where('employee_id', $request->employee_id)
            ->where('bit_delete', 0)
            ->exists();

        if ($alreadyAssigned) {
            return response()->json([
                'success' => false,
                'message' => 'This employee already has a user account.',
            ], 422);
        }

        $id = DB::table('users')->insertGetId([
            'employee_id' => $request->employee_id,
            'user_id' => $request->user_id,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id,
            'bit_delete' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User account created successfully.',
            'data' => [
                'id' => $id,
            ],
        ], 201);
    }


    /**
     * Show one user.
     */
    public function show($id)
    {
        $user = DB::table('users as u')
            ->leftJoin('employees as e', 'u.employee_id', '=', 'e.id')
            ->leftJoin('departments as d', 'e.department_id', '=', 'd.id')
            ->leftJoin('roles as r', 'u.role_id', '=', 'r.id')
            ->where('u.id', $id)
            ->where('u.bit_delete', 0)
            ->select([
                'u.id',
                'u.user_id',
                'u.employee_id',
                'u.role_id',
                'e.emp_code',
                'e.emp_name',
                'e.emp_email',
                'd.name as department_name',
                'r.name as role_name',
                'u.created_at',
                'u.updated_at',
            ])
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }


    /**
     * Update an existing user.
     *
     * Employee can be changed.
     * Role can be changed.
     * User ID can be changed.
     * Password is optional.
     */
    public function update(Request $request, $id)
    {
        $user = DB::table('users')
            ->where('id', $id)
            ->where('bit_delete', 0)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|integer',
            'role_id' => 'required|integer',
            'user_id' => 'required|string|max:255',
            'password' => 'nullable|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please correct the submitted information.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check employee
        $employee = DB::table('employees')
            ->where('id', $request->employee_id)
            ->where('bit_delete', 0)
            ->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Selected employee is unavailable.',
            ], 422);
        }

        // Check role
        $role = DB::table('roles')
            ->where('id', $request->role_id)
            ->where('bit_delete', 0)
            ->first();

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Selected role is unavailable.',
            ], 422);
        }

        // Check duplicate User ID excluding current user
        $userIdExists = DB::table('users')
            ->where('user_id', $request->user_id)
            ->where('id', '!=', $id)
            ->where('bit_delete', 0)
            ->exists();

        if ($userIdExists) {
            return response()->json([
                'success' => false,
                'message' => 'This User ID is already in use.',
            ], 422);
        }

        // Check whether another active user already belongs to
        // the selected employee.
        $employeeAlreadyAssigned = DB::table('users')
            ->where('employee_id', $request->employee_id)
            ->where('id', '!=', $id)
            ->where('bit_delete', 0)
            ->exists();

        if ($employeeAlreadyAssigned) {
            return response()->json([
                'success' => false,
                'message' => 'This employee already has another user account.',
            ], 422);
        }

        $updateData = [
            'employee_id' => $request->employee_id,
            'user_id' => $request->user_id,
            'role_id' => $request->role_id,
            'updated_at' => now(),
        ];

        // Only update password if a new password was provided.
        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        DB::table('users')
            ->where('id', $id)
            ->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'User account updated successfully.',
        ]);
    }


    /**
     * Soft delete a user.
     */
    public function destroy($id)
    {
        $user = DB::table('users')
            ->where('id', $id)
            ->where('bit_delete', 0)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        DB::table('users')
            ->where('id', $id)
            ->update([
                'bit_delete' => 1,
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'User account deleted successfully.',
        ]);
    }
}