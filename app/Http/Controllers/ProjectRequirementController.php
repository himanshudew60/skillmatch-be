<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProjectRequirementController extends Controller
{
    /**
     * List project requirements
     */
    public function index(Request $request)
    {
        $query = DB::table('project_requirements as pr')
            ->join('projects as p', 'p.id', '=', 'pr.project_id')

            ->leftJoin('skills as ps', 'ps.id', '=', 'pr.primary_skill_id')
            ->leftJoin('skills as ss', 'ss.id', '=', 'pr.secondary_skill_id')
            ->leftJoin('skills as ts', 'ts.id', '=', 'pr.tertiary_skill_id')

            ->where('pr.bit_delete', 0)
            ->where('p.bit_delete', 0)

            ->select(
                'pr.id',
                'pr.project_id',
                'p.project_code',
                'p.project_name',

                'pr.required_resources',

                'pr.primary_skill_id',
                'ps.name as primary_skill_name',
                'pr.primary_proficiency',

                'pr.secondary_skill_id',
                'ss.name as secondary_skill_name',
                'pr.secondary_proficiency',

                'pr.tertiary_skill_id',
                'ts.name as tertiary_skill_name',
                'pr.tertiary_proficiency',

                'pr.requirement_description',

                'pr.created_at',
                'pr.updated_at'
            );

        if ($request->filled('project_id')) {
            $query->where(
                'pr.project_id',
                $request->project_id
            );
        }

        $requirements = $query
            ->orderByDesc('pr.id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $requirements
        ]);
    }


    /**
     * Create project requirement
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'project_id' => [
                'required',
                'integer',
                'exists:projects,id'
            ],

            'required_resources' => [
                'required',
                'integer',
                'min:1'
            ],

            'primary_skill_id' => [
                'nullable',
                'integer',
                'exists:skills,id'
            ],

            'primary_proficiency' => [
                'nullable',
                'numeric',
                'min:1',
                'max:5'
            ],

            'secondary_skill_id' => [
                'nullable',
                'integer',
                'exists:skills,id'
            ],

            'secondary_proficiency' => [
                'nullable',
                'numeric',
                'min:1',
                'max:5'
            ],

            'tertiary_skill_id' => [
                'nullable',
                'integer',
                'exists:skills,id'
            ],

            'tertiary_proficiency' => [
                'nullable',
                'numeric',
                'min:1',
                'max:5'
            ],

            'requirement_description' => [
                'nullable',
                'string'
            ]
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        /*
        |--------------------------------------------------------------------------
        | Check project
        |--------------------------------------------------------------------------
        */

        $project = DB::table('projects')
            ->where('id', $data['project_id'])
            ->where('bit_delete', 0)
            ->first();

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found.'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | At least one skill is required
        |--------------------------------------------------------------------------
        */

        if (
            empty($data['primary_skill_id']) &&
            empty($data['secondary_skill_id']) &&
            empty($data['tertiary_skill_id'])
        ) {
            return response()->json([
                'success' => false,
                'message' => 'At least one skill is required.'
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Proficiency is required when skill is selected
        |--------------------------------------------------------------------------
        */

        if (
            !empty($data['primary_skill_id']) &&
            empty($data['primary_proficiency'])
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Primary skill proficiency is required.'
            ], 422);
        }

        if (
            !empty($data['secondary_skill_id']) &&
            empty($data['secondary_proficiency'])
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Secondary skill proficiency is required.'
            ], 422);
        }

        if (
            !empty($data['tertiary_skill_id']) &&
            empty($data['tertiary_proficiency'])
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Tertiary skill proficiency is required.'
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent same skill being selected twice
        |--------------------------------------------------------------------------
        */

        $skillIds = array_filter([
            $data['primary_skill_id'] ?? null,
            $data['secondary_skill_id'] ?? null,
            $data['tertiary_skill_id'] ?? null
        ]);

        if (count($skillIds) !== count(array_unique($skillIds))) {
            return response()->json([
                'success' => false,
                'message' => 'The same skill cannot be selected more than once.'
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent duplicate requirement for same project
        |--------------------------------------------------------------------------
        */

        $existing = DB::table('project_requirements')
            ->where('project_id', $data['project_id'])
            ->where('bit_delete', 0)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'A requirement already exists for this project.'
            ], 422);
        }

        $id = DB::table('project_requirements')->insertGetId([

            'project_id' => $data['project_id'],

            'required_resources' =>
                $data['required_resources'],

            'primary_skill_id' =>
                $data['primary_skill_id'] ?? null,

            'primary_proficiency' =>
                $data['primary_proficiency'] ?? null,

            'secondary_skill_id' =>
                $data['secondary_skill_id'] ?? null,

            'secondary_proficiency' =>
                $data['secondary_proficiency'] ?? null,

            'tertiary_skill_id' =>
                $data['tertiary_skill_id'] ?? null,

            'tertiary_proficiency' =>
                $data['tertiary_proficiency'] ?? null,

            'requirement_description' =>
                $data['requirement_description'] ?? null,

            'bit_delete' => 0,

            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Project requirement created successfully.',
            'data' => [
                'id' => $id
            ]
        ], 201);
    }


    /**
     * Show requirement
     */
    public function show($id)
    {
        $requirement = DB::table('project_requirements as pr')

            ->join('projects as p', 'p.id', '=', 'pr.project_id')

            ->leftJoin('skills as ps', 'ps.id', '=', 'pr.primary_skill_id')
            ->leftJoin('skills as ss', 'ss.id', '=', 'pr.secondary_skill_id')
            ->leftJoin('skills as ts', 'ts.id', '=', 'pr.tertiary_skill_id')

            ->where('pr.id', $id)
            ->where('pr.bit_delete', 0)

            ->select(
                'pr.id',
                'pr.project_id',
                'p.project_code',
                'p.project_name',

                'pr.required_resources',

                'pr.primary_skill_id',
                'ps.name as primary_skill_name',
                'pr.primary_proficiency',

                'pr.secondary_skill_id',
                'ss.name as secondary_skill_name',
                'pr.secondary_proficiency',

                'pr.tertiary_skill_id',
                'ts.name as tertiary_skill_name',
                'pr.tertiary_proficiency',

                'pr.requirement_description',

                'pr.created_at',
                'pr.updated_at'
            )
            ->first();

        if (!$requirement) {
            return response()->json([
                'success' => false,
                'message' => 'Project requirement not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $requirement
        ]);
    }


    /**
     * Update requirement
     */
    public function update(Request $request, $id)
    {
        $requirement = DB::table('project_requirements')
            ->where('id', $id)
            ->where('bit_delete', 0)
            ->first();

        if (!$requirement) {
            return response()->json([
                'success' => false,
                'message' => 'Project requirement not found.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [

            'project_id' => [
                'required',
                'integer',
                'exists:projects,id'
            ],

            'required_resources' => [
                'required',
                'integer',
                'min:1'
            ],

            'primary_skill_id' => [
                'nullable',
                'integer',
                'exists:skills,id'
            ],

            'primary_proficiency' => [
                'nullable',
                'numeric',
                'min:1',
                'max:5'
            ],

            'secondary_skill_id' => [
                'nullable',
                'integer',
                'exists:skills,id'
            ],

            'secondary_proficiency' => [
                'nullable',
                'numeric',
                'min:1',
                'max:5'
            ],

            'tertiary_skill_id' => [
                'nullable',
                'integer',
                'exists:skills,id'
            ],

            'tertiary_proficiency' => [
                'nullable',
                'numeric',
                'min:1',
                'max:5'
            ],

            'requirement_description' => [
                'nullable',
                'string'
            ]
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        /*
        |--------------------------------------------------------------------------
        | At least one skill
        |--------------------------------------------------------------------------
        */

        if (
            empty($data['primary_skill_id']) &&
            empty($data['secondary_skill_id']) &&
            empty($data['tertiary_skill_id'])
        ) {
            return response()->json([
                'success' => false,
                'message' => 'At least one skill is required.'
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Validate proficiency
        |--------------------------------------------------------------------------
        */

        if (
            !empty($data['primary_skill_id']) &&
            empty($data['primary_proficiency'])
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Primary skill proficiency is required.'
            ], 422);
        }

        if (
            !empty($data['secondary_skill_id']) &&
            empty($data['secondary_proficiency'])
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Secondary skill proficiency is required.'
            ], 422);
        }

        if (
            !empty($data['tertiary_skill_id']) &&
            empty($data['tertiary_proficiency'])
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Tertiary skill proficiency is required.'
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent duplicate skills
        |--------------------------------------------------------------------------
        */

        $skillIds = array_filter([
            $data['primary_skill_id'] ?? null,
            $data['secondary_skill_id'] ?? null,
            $data['tertiary_skill_id'] ?? null
        ]);

        if (count($skillIds) !== count(array_unique($skillIds))) {
            return response()->json([
                'success' => false,
                'message' => 'The same skill cannot be selected more than once.'
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent another requirement for same project
        |--------------------------------------------------------------------------
        */

        $existing = DB::table('project_requirements')
            ->where('project_id', $data['project_id'])
            ->where('bit_delete', 0)
            ->where('id', '!=', $id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'A requirement already exists for this project.'
            ], 422);
        }

        DB::table('project_requirements')
            ->where('id', $id)
            ->update([

                'project_id' =>
                    $data['project_id'],

                'required_resources' =>
                    $data['required_resources'],

                'primary_skill_id' =>
                    $data['primary_skill_id'] ?? null,

                'primary_proficiency' =>
                    $data['primary_proficiency'] ?? null,

                'secondary_skill_id' =>
                    $data['secondary_skill_id'] ?? null,

                'secondary_proficiency' =>
                    $data['secondary_proficiency'] ?? null,

                'tertiary_skill_id' =>
                    $data['tertiary_skill_id'] ?? null,

                'tertiary_proficiency' =>
                    $data['tertiary_proficiency'] ?? null,

                'requirement_description' =>
                    $data['requirement_description'] ?? null,

                'updated_at' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Project requirement updated successfully.'
        ]);
    }


    /**
     * Delete requirement
     */
    public function destroy($id)
    {
        $requirement = DB::table('project_requirements')
            ->where('id', $id)
            ->where('bit_delete', 0)
            ->first();

        if (!$requirement) {
            return response()->json([
                'success' => false,
                'message' => 'Project requirement not found.'
            ], 404);
        }

        DB::table('project_requirements')
            ->where('id', $id)
            ->update([
                'bit_delete' => 1,
                'updated_at' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Project requirement deleted successfully.'
        ]);
    }
}