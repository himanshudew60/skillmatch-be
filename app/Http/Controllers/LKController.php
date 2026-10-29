<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LKController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | 1. L&K - Employee List
    |--------------------------------------------------------------------------
    */

   public function employees(Request $request)
{
    $search = trim($request->input('search', ''));

    $query = DB::table('employees as e')
        ->leftJoin('departments as d', 'd.id', '=', 'e.department_id')
        ->leftJoin('designations as des', 'des.id', '=', 'e.designation_id')
        
        ->where('e.bit_delete', 0)
        ->where('e.status', 1)

        // Show employees who still have at least one skill to review.
        ->whereExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('employee_skills as es')
                ->whereColumn('es.employee_id', 'e.id')
                ->where('es.bit_delete', 0)

                // The skill must not have a latest Approved assessment.
                ->whereNotExists(function ($assessment) {
                    $assessment->select(DB::raw(1))
                        ->from('lk_skill_assessments as lsa')
                        ->whereColumn(
                            'lsa.employee_skill_id',
                            'es.id'
                        )
                        ->whereRaw('
                            lsa.id = (
                                SELECT MAX(lsa2.id)
                                FROM lk_skill_assessments AS lsa2
                                WHERE lsa2.employee_skill_id =
                                      es.id
                            )
                        ')
                        ->where('lsa.decision', 'Approved');
                });
        })

        ->select(
            'e.id',
            'e.emp_code',
            'e.emp_name',
            'e.emp_email',
            'e.department_id',
            'd.name as department_name',
            'e.designation_id',
            'des.name as designation_name',
            'e.location',
            'e.resource_status',
            'e.available_from'
        );

    // Employee search
    if ($search !== '') {
        $query->where(function ($q) use ($search) {
            $q->where('e.emp_name', 'like', "%{$search}%")
                ->orWhere('e.emp_code', 'like', "%{$search}%")
                ->orWhere('e.emp_email', 'like', "%{$search}%");
        });
    }

    $employees = $query
        ->orderBy('e.emp_name')
        ->get();

    return response()->json([
        'success' => true,
        'data' => $employees,
    ]);
}


    /*
    |--------------------------------------------------------------------------
    | 2. L&K - Employee Existing Skills
    |--------------------------------------------------------------------------
    */

    public function employeeSkills($employeeId)
    {
        $employee = DB::table('employees as e')
            ->leftJoin('departments as d', 'd.id', '=', 'e.department_id')
            ->leftJoin('designations as des', 'des.id', '=', 'e.designation_id')
            ->where('e.id', $employeeId)
            ->where('e.bit_delete', 0)
            ->select(
                'e.id',
                'e.emp_code',
                'e.emp_name',
                'e.emp_email',
                'd.name as department_name',
                'des.name as designation_name'
            )
            ->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found.'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Only skills already attached to this employee
        |--------------------------------------------------------------------------
        */

        $skills = DB::table('employee_skills as es')
    ->join('skills as s', 's.id', '=', 'es.skill_id')
    ->where('es.employee_id', $employeeId)
    ->where('es.bit_delete', 0)
    ->where('s.bit_delete', 0)
    ->select(
        'es.id as employee_skill_id',
        'es.employee_id',
        'es.skill_id',
        's.name as skill_name',
        's.category',
        'es.skill_type',
        'es.proficiency',
        'es.experience',
        'es.certification',
        'es.project_experience',
        'es.skill_validation_status'
    )
    ->orderBy('s.name')
    ->get();

        return response()->json([
            'success' => true,
            'employee' => $employee,
            'skills' => $skills
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | 3. L&K - Submit Assessment for Existing Skills
    |--------------------------------------------------------------------------
    */

    public function submitAssessment(Request $request)
{
    $validated = $request->validate([
        'employee_id' => 'required|integer|exists:employees,id',

        'skills' => 'required|array|min:1',

        'skills.*.employee_skill_id' =>
            'nullable|integer|exists:employee_skills,id',

        'skills.*.skill_id' =>
            'required|integer|exists:skills,id',

        'skills.*.lk_proficiency' =>
            'nullable|numeric|min:1|max:5',

        'skills.*.lk_score' =>
            'nullable|numeric|min:0|max:100',

        'skills.*.decision' =>
            'required|in:Approved,Upskill Required',

        'skills.*.feedback' =>
            'nullable|string|max:2000',
    ]);

    DB::beginTransaction();

    try {

        foreach ($validated['skills'] as $skill) {

            /*
            |--------------------------------------------------------------------------
            | Get employee's current skill
            |--------------------------------------------------------------------------
            */

            $employeeSkill = null;

            if (!empty($skill['employee_skill_id'])) {

                $employeeSkill = DB::table('employee_skills')
                    ->where('id', $skill['employee_skill_id'])
                    ->where('employee_id', $validated['employee_id'])
                    ->where('bit_delete', 0)
                    ->first();
            }

            /*
            |--------------------------------------------------------------------------
            | Read-only employee values
            |--------------------------------------------------------------------------
            */

            $employeeProficiency = $employeeSkill
                ? $employeeSkill->proficiency
                : null;

            $employeeScore =  null;

            /*
            |--------------------------------------------------------------------------
            | Save L&K assessment separately
            |--------------------------------------------------------------------------
            */

            $assessmentId = DB::table('lk_skill_assessments')
                ->insertGetId([
                    'employee_id' =>
                        $validated['employee_id'],

                    'employee_skill_id' =>
                        $employeeSkill?->id,

                    'skill_id' =>
                        $skill['skill_id'],

                    'employee_proficiency' =>
                        $employeeProficiency,

                    'employee_score' =>
                        $employeeScore,

                    'lk_proficiency' =>
                        $skill['lk_proficiency'] ?? null,

                    'lk_score' =>
                        $skill['lk_score'] ?? null,

                    'decision' =>
                        $skill['decision'],

                    'feedback' =>
                        $skill['feedback'] ?? null,

                    'assessed_by' =>
                        auth()->id(),

                    'assessed_at' =>
                        now(),

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);
                if ($skill['decision'] === 'Approved') {
    DB::table('employees')
        ->where('id', $validated['employee_id'])
        ->update([
            'status' => 2,
            'updated_at' => now(),
        ]);
} elseif ($skill['decision'] === 'Upskill Required') {
    DB::table('employees')
        ->where('id', $validated['employee_id'])
        ->update([
            'status' => 3,
            'updated_at' => now(),
        ]);
}
if ($skill['decision'] === 'Approved') {
    DB::table('skill_gap_requests')
        ->where('employee_id', $validated['employee_id'])
        ->where('skill_id', $skill['skill_id'])
        ->where('status', 'Assessment Submitted')
        ->update([
            'status' => 'Pending Resource Manager',
            'assessed_by' => auth()->id(),
            'assessed_at' => now(),
            'updated_at' => now(),
        ]);
}
            /*
            |--------------------------------------------------------------------------
            | If L&K says Upskill Required
            |--------------------------------------------------------------------------
            */
            

            if ($skill['decision'] === 'Upskill Required') {

                DB::table('skill_gap_requests')
                    ->insert([
                        'employee_id' =>
                            $validated['employee_id'],

                        'project_requirement_id' =>
                            null,

                        'skill_id' =>
                            $skill['skill_id'],

                        'current_proficiency' =>
                            $employeeProficiency,

                        'required_proficiency' =>
                            $skill['lk_proficiency'] ?? $employeeProficiency,

                        'resource_manager_feedback' =>
                            $skill['feedback'] ?? null,

                        'requested_by' =>
                            auth()->id(),

                        'status' =>
                            'Pending L&K',

                         'lk_remarks' => null,

        'assessed_by' => null,

        'assessed_at' => null,

        'created_at' => now(),

        'updated_at' => now(),
                    ]);
            }
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' =>
                'L&K assessment submitted successfully.'
        ]);

    } catch (\Throwable $e) {

        DB::rollBack();

        return response()->json([
            'success' => false,
            'message' =>
                'Failed to submit L&K assessment.',
            'error' =>
                $e->getMessage()
        ], 500);
    }
}
public function sendToUpskill(Request $request)
{
    $validated = $request->validate([
        'employee_id' => 'required|integer|exists:employees,id',
        'skills' => 'required|array|min:1',

        'skills.*.employee_skill_id' => 'nullable|integer',
        'skills.*.skill_id' => 'required|integer|exists:skills,id',
        'skills.*.required_proficiency' => 'required|numeric|min:1|max:5',
        'skills.*.feedback' => 'required|string|max:2000',
    ]);

    $employeeId = (int) $validated['employee_id'];
    $userId = auth()->id();

    if (!$userId) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated user.',
        ], 401);
    }

    // Prevent duplicate skill entries in the same request.
    $skillIds = array_map(
        fn ($skill) => (int) $skill['skill_id'],
        $validated['skills']
    );

    if (count($skillIds) !== count(array_unique($skillIds))) {
        return response()->json([
            'success' => false,
            'message' => 'A skill can only appear once per upskill submission.',
        ], 422);
    }

    $employee = DB::table('employees')
        ->where('id', $employeeId)
        ->where('bit_delete', 0)
        ->first();

    if (!$employee) {
        return response()->json([
            'success' => false,
            'message' => 'Employee not found or inactive.',
        ], 404);
    }

    try {
        DB::transaction(function () use (
            $validated,
            $employeeId,
            $userId
        ) {
            foreach ($validated['skills'] as $skill) {
                $skillId = (int) $skill['skill_id'];
                $feedback = trim($skill['feedback']);

                if ($feedback === '') {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'skills' => ['Feedback is required for every upskill skill.'],
                    ]);
                }

                $activeSkill = DB::table('skills')
                    ->where('id', $skillId)
                    ->where('bit_delete', 0)
                    ->exists();

                if (!$activeSkill) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'skills' => ["Skill ID {$skillId} is inactive or does not exist."],
                    ]);
                }

                // Find the employee's current active skill, if any.
                $employeeSkill = DB::table('employee_skills')
                    ->where('employee_id', $employeeId)
                    ->where('skill_id', $skillId)
                    ->where('bit_delete', 0)
                    ->first();

                // Validate that the supplied employee skill belongs to this employee.
                if (!empty($skill['employee_skill_id'])) {
                    if (
                        !$employeeSkill ||
                        (int) $employeeSkill->id !==
                        (int) $skill['employee_skill_id']
                    ) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'skills' => [
                                'The selected employee skill does not match the employee.',
                            ],
                        ]);
                    }
                }

                $currentProficiency = $employeeSkill->proficiency ?? null;
                $currentScore = $employeeSkill->assessment_score ?? null;

                // Save L&K's initial decision separately.
                DB::table('lk_skill_assessments')->insert([
                    'employee_id' => $employeeId,
                    'employee_skill_id' => $employeeSkill->id ?? null,
                    'skill_id' => $skillId,
                    'employee_proficiency' => $currentProficiency,
                    'employee_score' => $currentScore,
                    'lk_proficiency' => null,
                    'lk_score' => null,
                    'decision' => 'Upskill Required',
                    'feedback' => $feedback,
                    'assessed_by' => $userId,
                    'assessed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Create the upskill request.
                DB::table('skill_gap_requests')->insert([
                    'employee_id' => $employeeId,
                    'project_requirement_id' => null,
                    'skill_id' => $skillId,
                    'current_proficiency' => $currentProficiency,
                    'required_proficiency' => $skill['required_proficiency'],
                    'resource_manager_feedback' => null,
                    'requested_by' => $userId,
                    'status' => 'Pending L&K',
                    'lk_remarks' => $feedback,
                    'assessed_by' => null,
                    'assessed_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            // Update employee workflow status to L&K Upskill Required.
DB::table('employees')
    ->where('id', $employeeId)
    ->update([
        'status' => 3,
        'updated_at' => now(),
    ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Selected skills sent to upskill successfully.',
        ], 201);

    } catch (\Throwable $e) {
        report($e);

        return response()->json([
            'success' => false,
            'message' => 'Failed to send skills to upskill.',
        ], 500);
    }
}

    /*
    |--------------------------------------------------------------------------
    | 4. L&K - Upskill / Skill Gap Requests
    |--------------------------------------------------------------------------
    */

    public function upskillRequests(Request $request)
    {
        $query = DB::table('skill_gap_requests as sgr')
            ->join('employees as e', 'e.id', '=', 'sgr.employee_id')
            ->join('skills as s', 's.id', '=', 'sgr.skill_id')
            ->leftJoin(
                'project_requirements as pr',
                'pr.id',
                '=',
                'sgr.project_requirement_id'
            )
            ->leftJoin(
                'projects as p',
                'p.id',
                '=',
                'pr.project_id'
            )
            ->where('e.bit_delete', 0)
            ->where('sgr.status', 'Pending L&K')
            ->select(
                'sgr.id',
                'sgr.employee_id',
                'e.emp_code',
                'e.emp_name',
                'sgr.skill_id',
                's.name as skill_name',
                'sgr.project_requirement_id',
                'p.project_code',
                'p.project_name',
                'sgr.current_proficiency',
                'sgr.required_proficiency',
                'sgr.resource_manager_feedback',
                'sgr.status',
                'sgr.created_at'
            );

        if ($request->filled('employee_id')) {
            $query->where(
                'sgr.employee_id',
                $request->employee_id
            );
        }

        return response()->json([
            'success' => true,
            'data' => $query
                ->orderByDesc('sgr.created_at')
                ->get()
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | 5. L&K - Upskill Request Details
    |--------------------------------------------------------------------------
    */

    public function upskillRequest($id)
    {
        $requestData = DB::table('skill_gap_requests as sgr')
            ->join('employees as e', 'e.id', '=', 'sgr.employee_id')
            ->join('skills as s', 's.id', '=', 'sgr.skill_id')
            ->leftJoin(
                'project_requirements as pr',
                'pr.id',
                '=',
                'sgr.project_requirement_id'
            )
            ->leftJoin(
                'projects as p',
                'p.id',
                '=',
                'pr.project_id'
            )
            ->where('sgr.id', $id)
            ->select(
                'sgr.*',
                'e.emp_code',
                'e.emp_name',
                'e.emp_email',
                's.name as skill_name',
                's.category',
                'p.project_code',
                'p.project_name'
            )
            ->first();

        if (!$requestData) {
            return response()->json([
                'success' => false,
                'message' => 'Skill gap request not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $requestData
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | 6. L&K - Submit Upskill Assessment
    |--------------------------------------------------------------------------
    */

    public function submitUpskillAssessment(
        Request $request,
        $id
    ) {
        $validated = $request->validate([
            'proficiency' =>
                'required|numeric|min:1|max:5',

            'lk_remarks' =>
                'nullable|string|max:2000',

            'skill_type' =>
                'nullable|in:Primary,Secondary,Tertiary',
        ]);

        DB::beginTransaction();

        try {

            $gapRequest = DB::table('skill_gap_requests')
                ->where('id', $id)
                ->first();

            if (!$gapRequest) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Skill gap request not found.'
                ], 404);
            }

            if (!in_array($gapRequest->status, [
                'Pending L&K',
                'Training',
                'Assessment Submitted'
            ])) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' =>
                        'This skill gap request cannot be assessed in its current status.'
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | Check whether employee already has this skill
            |--------------------------------------------------------------------------
            */

            $employeeSkill = DB::table('employee_skills')
                ->where('employee_id', $gapRequest->employee_id)
                ->where('skill_id', $gapRequest->skill_id)
                ->first();

            if ($employeeSkill) {

                /*
                |--------------------------------------------------------------------------
                | Existing skill - update it
                |--------------------------------------------------------------------------
                */

                DB::table('employee_skills')
                    ->where('id', $employeeSkill->id)
                    ->update([
                        'proficiency' =>
                            $validated['proficiency'],


                        'skill_validation_status' =>
                            'Assessment',

                        'assessment_remarks' =>
                            $validated['lk_remarks'] ?? null,

                        'assessed_by' =>
                            auth()->id(),

                        'assessed_at' =>
                            now(),

                        'bit_delete' =>
                            0,

                        'updated_at' =>
                            now(),
                    ]);

            } else {

                /*
                |--------------------------------------------------------------------------
                | New skill - create employee skill
                |--------------------------------------------------------------------------
                */

                DB::table('employee_skills')
                    ->insert([
                        'employee_id' =>
                            $gapRequest->employee_id,

                        'skill_id' =>
                            $gapRequest->skill_id,

                        'skill_type' =>
                            $validated['skill_type'] ?? 'Secondary',

                        'proficiency' =>
                            $validated['proficiency'],

                        'experience' =>
                            0,

                    

                        'certification' =>
                            null,

                        'project_experience' =>
                            0,

                        'skill_validation_status' =>
                            'Assessment',

                        'assessment_remarks' =>
                            $validated['lk_remarks'] ?? null,

                        'assessed_by' =>
                            auth()->id(),

                        'assessed_at' =>
                            now(),

                        'bit_delete' =>
                            0,

                        'created_at' =>
                            now(),

                        'updated_at' =>
                            now(),
                    ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Send back to Resource Manager
            |--------------------------------------------------------------------------
            */

            DB::table('skill_gap_requests')
                ->where('id', $id)
                ->update([
                    'status' => 'Assessment Submitted',

                    'lk_remarks' =>
                        $validated['lk_remarks'] ?? null,

                    'assessed_by' =>
                        auth()->id(),

                    'assessed_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);
// Reset employee status to 1 (Draft).
DB::table('employees')
    ->where('id', $gapRequest->employee_id)
    ->update([
        'status' => 1,
        'updated_at' => now(),
    ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' =>
                    'Upskill assessment submitted. Sent to Resource Manager for re-verification.'
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' =>
                    'Failed to submit upskill assessment.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}