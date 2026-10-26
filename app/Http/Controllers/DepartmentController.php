<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    /**
     * Get all active departments.
     */
    public function index()
    {
        $departments = DB::table('departments')
            ->where('bit_delete', 0)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $departments
        ]);
    }

    /**
     * Create department.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')->where(function ($query) {
                    return $query->where('bit_delete', 0);
                }),
            ],
            'description' => [
                'nullable',
                'string'
            ],
        ]);

        $id = DB::table('departments')->insertGetId([
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
            'bit_delete' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Department created successfully.',
            'data' => DB::table('departments')->where('id', $id)->first()
        ], 201);
    }

    /**
     * Get one department.
     */
    public function show($id)
    {
        $department = DB::table('departments')
            ->where('id', $id)
            ->where('bit_delete', 0)
            ->first();

        if (!$department) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $department
        ]);
    }

    /**
     * Update department.
     */
    public function update(Request $request, $id)
    {
        $department = DB::table('departments')
            ->where('id', $id)
            ->where('bit_delete', 0)
            ->first();

        if (!$department) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found.'
            ], 404);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')
                    ->ignore($id)
                    ->where(function ($query) {
                        return $query->where('bit_delete', 0);
                    }),
            ],
            'description' => [
                'nullable',
                'string'
            ],
        ]);

        DB::table('departments')
            ->where('id', $id)
            ->update([
                'name' => trim($validated['name']),
                'description' => $validated['description'] ?? null,
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Department updated successfully.',
            'data' => DB::table('departments')->where('id', $id)->first()
        ]);
    }

    /**
     * Soft delete department.
     */
    public function destroy($id)
    {
        $department = DB::table('departments')
            ->where('id', $id)
            ->where('bit_delete', 0)
            ->first();

        if (!$department) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found.'
            ], 404);
        }

        DB::table('departments')
            ->where('id', $id)
            ->update([
                'bit_delete' => 1,
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Department deleted successfully.'
        ]);
    }
}
