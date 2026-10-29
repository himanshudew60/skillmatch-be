<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        // Employees that have not been soft-deleted.
        $activeEmployees = DB::table('employees')
            ->where('bit_delete', 0);

        $totalEmployees = (clone $activeEmployees)->count();

        $availableEmployees = (clone $activeEmployees)
            ->where('resource_status', 0)
            ->count();

        $allocatedEmployees = (clone $activeEmployees)
            ->where('resource_status', 1)
            ->count();

        $partiallyAvailableEmployees = (clone $activeEmployees)
            ->where('resource_status', 2)
            ->count();

        // Count unique employees with at least one allocation.
        $allocatedEmployeesFromProjects = DB::table('project_allocations as pa')
            ->join('employees as e', 'e.id', '=', 'pa.employee_id')
            ->join('projects as p', 'p.id', '=', 'pa.project_id')
            ->where('e.bit_delete', 0)
            ->where('p.bit_delete', 0)
            ->distinct('pa.employee_id')
            ->count('pa.employee_id');

        // Active departments.
        $totalDepartments = DB::table('departments')
            ->where('bit_delete', 0)
            ->count();

        // Active projects.
        $totalProjects = DB::table('projects')
            ->where('bit_delete', 0)
            ->count();

        // Active skills in the skills directory.
        $totalSkills = DB::table('skills')
            ->where('bit_delete', 0)
            ->count();

        // Upskilling-required assessment records.
        $upskillAssessmentCount = DB::table('lk_skill_assessments as lsa')
            ->join('employees as e', 'e.id', '=', 'lsa.employee_id')
            ->where('e.bit_delete', 0)
            ->where('lsa.decision', 'Upskill Required')
            ->count();

        // Unique employees requiring upskilling.
        $employeesRequiringUpskill = DB::table('lk_skill_assessments as lsa')
            ->join('employees as e', 'e.id', '=', 'lsa.employee_id')
            ->where('e.bit_delete', 0)
            ->where('lsa.decision', 'Upskill Required')
            ->distinct()
            ->count('lsa.employee_id');

        // Assessment decisions summary.
        $assessmentSummary = DB::table('lk_skill_assessments')
            ->select('decision', DB::raw('COUNT(*) as total'))
            ->groupBy('decision')
            ->get()
            ->keyBy('decision');

        // Department employee counts and allocation status.
        $departmentAnalytics = DB::table('departments as d')
            ->leftJoin('employees as e', function ($join) {
                $join->on('e.department_id', '=', 'd.id')
                    ->where('e.bit_delete', '=', 0);
            })
            ->where('d.bit_delete', 0)
            ->select(
                'd.id as department_id',
                'd.name as department_name',
                DB::raw('COUNT(e.id) as total_employees'),
                DB::raw(
                    'SUM(CASE WHEN e.resource_status = 0 THEN 1 ELSE 0 END) as available_employees'
                ),
                DB::raw(
                    'SUM(CASE WHEN e.resource_status = 1 THEN 1 ELSE 0 END) as allocated_employees'
                ),
                DB::raw(
                    'SUM(CASE WHEN e.resource_status = 2 THEN 1 ELSE 0 END) as partially_available_employees'
                )
            )
            ->groupBy('d.id', 'd.name')
            ->orderBy('d.name')
            ->get();

        // Most recent L&K assessment activity.
        $recentAssessments = DB::table('lk_skill_assessments as lsa')
            ->join('employees as e', 'e.id', '=', 'lsa.employee_id')
            ->join('skills as s', 's.id', '=', 'lsa.skill_id')
            ->where('e.bit_delete', 0)
            ->where('s.bit_delete', 0)
            ->select(
                'lsa.id',
                'lsa.employee_id',
                'e.emp_name',
                'e.emp_code',
                's.name as skill_name',
                'lsa.employee_proficiency',
                'lsa.lk_proficiency',
                'lsa.decision',
                'lsa.assessed_at',
                'lsa.created_at'
            )
            ->orderByDesc('lsa.assessed_at')
            ->orderByDesc('lsa.created_at')
            ->limit(8)
            ->get();

        // Top skills most often marked for upskilling.
        $upskillSkills = DB::table('lk_skill_assessments as lsa')
            ->join('skills as s', 's.id', '=', 'lsa.skill_id')
            ->join('employees as e', 'e.id', '=', 'lsa.employee_id')
            ->where('e.bit_delete', 0)
            ->where('s.bit_delete', 0)
            ->where('lsa.decision', 'Upskill Required')
            ->select(
                's.id as skill_id',
                's.name as skill_name',
                DB::raw('COUNT(*) as assessment_count'),
                DB::raw('COUNT(DISTINCT lsa.employee_id) as employee_count')
            )
            ->groupBy('s.id', 's.name')
            ->orderByDesc('assessment_count')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_employees' => $totalEmployees,
                    'allocated_employees' => $allocatedEmployeesFromProjects,
                    'available_employees' => $availableEmployees,
                    'partially_available_employees' => $partiallyAvailableEmployees,
                    'resource_status_allocated' => $allocatedEmployees,
                    'total_departments' => $totalDepartments,
                    'total_projects' => $totalProjects,
                    'total_skills' => $totalSkills,
                    'employees_requiring_upskill' => $employeesRequiringUpskill,
                    'upskill_assessment_count' => $upskillAssessmentCount,
                    'pending_assessments' => (int) (
                        $assessmentSummary->get('Pending')->total ?? 0
                    ),
                    'approved_assessments' => (int) (
                        $assessmentSummary->get('Approved')->total ?? 0
                    ),
                ],
                'departments' => $departmentAnalytics,
                'recent_assessments' => $recentAssessments,
                'upskill_skills' => $upskillSkills,
            ],
        ]);
    }
}
