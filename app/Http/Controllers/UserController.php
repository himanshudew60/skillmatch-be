<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    // List users with employee, department and role names.
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
            ])
            ->orderBy('u.id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    // Employees available for user-account creation.
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
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $employees,
        ]);
    }

    // Fetch available roles.
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

    // Create a login account for an employee.
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|integer|exists:employees,id',
            'role_id' => 'required|integer|exists:roles,id',
            'user_id' => 'required|string|max:255|unique:users,user_id',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please correct the submitted information.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Only active employees and roles can be assigned.
        $employee = DB::table('employees')
            ->where('id', $request->employee_id)
            ->where('bit_delete', 0)
            ->first();

        $role = DB::table('roles')
            ->where('id', $request->role_id)
            ->where('bit_delete', 0)
            ->first();

        if (!$employee || !$role) {
            return response()->json([
                'success' => false,
                'message' => 'The selected employee or role is unavailable.',
            ], 422);
        }

        // Prevent duplicate accounts for the same employee.
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
            'data' => ['id' => $id],
        ], 201);
    }
}
