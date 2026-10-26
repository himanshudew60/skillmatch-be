<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SkillController extends Controller
{
    /**
     * Get all active skills.
     */
    public function index()
    {
        $skills = DB::table('skills')
            ->where('bit_delete', 0)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $skills
        ]);
    }

    /**
     * Create skill.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('skills', 'name')->where(function ($query) {
                    return $query->where('bit_delete', 0);
                }),
            ],
            'category' => [
                'required',
                'string',
                Rule::in([
                    'Programming',
                    'Backend',
                    'Frontend',
                    'Database',
                    'Cloud',
                    'DevOps'
                ]),
            ],
        ]);

        $id = DB::table('skills')->insertGetId([
            'name' => trim($validated['name']),
            'category' => $validated['category'],
            'bit_delete' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Skill created successfully.',
            'data' => DB::table('skills')->where('id', $id)->first()
        ], 201);
    }

    /**
     * Get one skill.
     */
    public function show($id)
    {
        $skill = DB::table('skills')
            ->where('id', $id)
            ->where('bit_delete', 0)
            ->first();

        if (!$skill) {
            return response()->json([
                'success' => false,
                'message' => 'Skill not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $skill
        ]);
    }

    /**
     * Update skill.
     */
    public function update(Request $request, $id)
    {
        $skill = DB::table('skills')
            ->where('id', $id)
            ->where('bit_delete', 0)
            ->first();

        if (!$skill) {
            return response()->json([
                'success' => false,
                'message' => 'Skill not found.'
            ], 404);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('skills', 'name')
                    ->ignore($id)
                    ->where(function ($query) {
                        return $query->where('bit_delete', 0);
                    }),
            ],
            'category' => [
                'required',
                'string',
                Rule::in([
                    'Programming',
                    'Backend',
                    'Frontend',
                    'Database',
                    'Cloud',
                    'DevOps'
                ]),
            ],
        ]);

        DB::table('skills')
            ->where('id', $id)
            ->update([
                'name' => trim($validated['name']),
                'category' => $validated['category'],
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Skill updated successfully.',
            'data' => DB::table('skills')->where('id', $id)->first()
        ]);
    }

    /**
     * Soft delete skill.
     */
    public function destroy($id)
    {
        $skill = DB::table('skills')
            ->where('id', $id)
            ->where('bit_delete', 0)
            ->first();

        if (!$skill) {
            return response()->json([
                'success' => false,
                'message' => 'Skill not found.'
            ], 404);
        }

        DB::table('skills')
            ->where('id', $id)
            ->update([
                'bit_delete' => 1,
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Skill deleted successfully.'
        ]);
    }
}
