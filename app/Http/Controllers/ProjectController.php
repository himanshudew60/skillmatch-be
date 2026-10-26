<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    /**
     * List projects
     */
    public function index()
    {
        $projects = DB::table('projects as p')
            ->leftJoin('departments as d', 'd.id', '=', 'p.department_id')
            ->leftJoin('employees as e', 'e.id', '=', 'p.project_manager_id')
            ->where('p.bit_delete', 0)
            ->select(
                'p.id',
                'p.project_code',
                'p.project_name',
                'p.description',
                'p.client_name',
                'p.department_id',
                'd.name as department_name',
                'p.project_manager_id',
                'e.emp_name as project_manager_name',
                'p.start_date',
                'p.end_date',
                'p.status',
                'p.created_at',
                'p.updated_at'
            )
            ->orderByDesc('p.id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $projects
        ]);
    }

    /**
     * Create project
     */
    public function store(Request $request)
    {
        $request->validate([
            'project_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'client_name' => 'nullable|string|max:255',
            'department_id' => 'nullable|integer',
            'project_manager_id' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|string|max:50',
        ]);

        DB::beginTransaction();

        try {

            $lastProject = DB::table('projects')
                ->orderByDesc('id')
                ->first();

            $nextNumber = $lastProject
                ? $lastProject->id + 1
                : 1;

            $projectCode = 'PRJ' . str_pad(
                $nextNumber,
                4,
                '0',
                STR_PAD_LEFT
            );

            $projectId = DB::table('projects')->insertGetId([
                'project_code' => $projectCode,
                'project_name' => $request->project_name,
                'description' => $request->description,
                'client_name' => $request->client_name,
                'department_id' => $request->department_id,
                'project_manager_id' => $request->project_manager_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'status' => $request->status ?? 'Draft',
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Project created successfully.',
                'data' => [
                    'id' => $projectId,
                    'project_code' => $projectCode
                ]
            ], 201);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create project.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show project
     */
    public function show($id)
    {
        $project = DB::table('projects as p')
            ->leftJoin('departments as d', 'd.id', '=', 'p.department_id')
            ->leftJoin('employees as e', 'e.id', '=', 'p.project_manager_id')
            ->where('p.id', $id)
            ->where('p.bit_delete', 0)
            ->select(
                'p.id',
                'p.project_code',
                'p.project_name',
                'p.description',
                'p.client_name',
                'p.department_id',
                'd.name as department_name',
                'p.project_manager_id',
                'e.emp_name as project_manager_name',
                'p.start_date',
                'p.end_date',
                'p.status',
                'p.created_at',
                'p.updated_at'
            )
            ->first();

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $project
        ]);
    }

    /**
     * Update project
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'project_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'client_name' => 'nullable|string|max:255',
            'department_id' => 'nullable|integer',
            'project_manager_id' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|string|max:50',
        ]);

        $project = DB::table('projects')
            ->where('id', $id)
            ->where('bit_delete', 0)
            ->first();

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found.'
            ], 404);
        }

        DB::table('projects')
            ->where('id', $id)
            ->update([
                'project_name' => $request->project_name,
                'description' => $request->description,
                'client_name' => $request->client_name,
                'department_id' => $request->department_id,
                'project_manager_id' => $request->project_manager_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'status' => $request->status ?? $project->status,
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Project updated successfully.'
        ]);
    }

    /**
     * Delete project
     */
    public function destroy($id)
    {
        $project = DB::table('projects')
            ->where('id', $id)
            ->where('bit_delete', 0)
            ->first();

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found.'
            ], 404);
        }

        DB::beginTransaction();

        try {

            DB::table('projects')
                ->where('id', $id)
                ->update([
                    'bit_delete' => 1,
                    'updated_at' => now()
                ]);

            // Also soft delete project requirements
            DB::table('project_requirements')
                ->where('project_id', $id)
                ->update([
                    'bit_delete' => 1,
                    'updated_at' => now()
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Project deleted successfully.'
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete project.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}