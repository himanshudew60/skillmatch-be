<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DesignationController extends Controller
{
    /**
     * Get all active designations.
     */
    public function index()
    {
        $designations = DB::table('designations')
            ->where('bit_delete', 0)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $designations
        ]);
    }

    /**
     * Create designation.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('designations', 'name')->where(function ($query) {
                    return $query->where('bit_delete', 0);
                }),
            ],
            'description' => [
                'nullable',
                'string'
            ],
        ]);

        $id = DB::table('designations')->insertGetId([
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
            'bit_delete' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Designation created successfully.',
            'data' => DB::table('designations')->where('id', $id)->first()
        ], 201);
    }

    /**
     * Get one designation.
     */
    public function show($id)
    {
        $designation = DB::table('designations')
            ->where('id', $id)
            ->where('bit_delete', 0)
            ->first();

        if (!$designation) {
            return response()->json([
                'success' => false,
                'message' => 'Designation not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $designation
        ]);
    }

    /**
     * Update designation.
     */
    public function update(Request $request, $id)
    {
        $designation = DB::table('designations')
            ->where('id', $id)
            ->where('bit_delete', 0)
            ->first();

        if (!$designation) {
            return response()->json([
                'success' => false,
                'message' => 'Designation not found.'
            ], 404);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('designations', 'name')
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

        DB::table('designations')
            ->where('id', $id)
            ->update([
                'name' => trim($validated['name']),
                'description' => $validated['description'] ?? null,
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Designation updated successfully.',
            'data' => DB::table('designations')->where('id', $id)->first()
        ]);
    }

    /**
     * Soft delete designation.
     */
    public function destroy($id)
    {
        $designation = DB::table('designations')
            ->where('id', $id)
            ->where('bit_delete', 0)
            ->first();

        if (!$designation) {
            return response()->json([
                'success' => false,
                'message' => 'Designation not found.'
            ], 404);
        }

        DB::table('designations')
            ->where('id', $id)
            ->update([
                'bit_delete' => 1,
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Designation deleted successfully.'
        ]);
    }
}
