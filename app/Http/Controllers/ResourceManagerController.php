<?php

namespace App\Http\Controllers;

use App\Services\EmployeeRecommendationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Validator;

class ResourceManagerController extends Controller
{
    private function getEligibleEmployees()
    {
        $employees = DB::table('employees as e')
            ->leftJoin('departments as d', 'd.id', '=', 'e.department_id')
            ->leftJoin('designations as des', 'des.id', '=', 'e.designation_id')
            ->where('e.bit_delete', 0)
            
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('employee_skills as es')
                    ->whereColumn('es.employee_id', 'e.id')
                    ->where('es.bit_delete', 0);
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('employee_skills as es')
                    ->whereColumn('es.employee_id', 'e.id')
                    ->where('es.bit_delete', 0)
                    ->whereNotExists(function ($assessment) {
                        $assessment->select(DB::raw(1))
                            ->from('lk_skill_assessments as lsa')
                            ->whereColumn('lsa.employee_skill_id', 'es.id')
                            ->whereRaw(
                                'lsa.id = (
                                    SELECT MAX(lsa2.id)
                                    FROM lk_skill_assessments AS lsa2
                                    WHERE lsa2.employee_skill_id = es.id
                                )'
                            )
                            ->whereRaw("LOWER(TRIM(lsa.decision)) = 'approved'");
                    });
            })
            ->select(
                'e.id as employee_id',
                'e.emp_code',
                'e.emp_name',
                'e.emp_email',
                'd.name as department_name',
                'des.name as designation_name',
                'e.location',
                'e.resource_status',
                'e.available_from'
            )
            ->orderBy('e.emp_name')
            ->get();

        foreach ($employees as $employee) {
            $employee->skills = DB::table('employee_skills as es')
                ->join('skills as s', 's.id', '=', 'es.skill_id')
                ->where('es.employee_id', $employee->employee_id)
                ->where('es.bit_delete', 0)
                ->where('s.bit_delete', 0)
                ->select(
                    'es.id as employee_skill_id',
                    'es.skill_id',
                    's.name as skill_name',
                    's.category',
                    'es.skill_type',
                    'es.proficiency as employee_proficiency',
                    'es.experience',
                    'es.certification',
                    'es.project_experience',
                    'es.skill_validation_status'
                )
                ->orderByRaw(
                    "FIELD(es.skill_type, 'Primary', 'Secondary', 'Tertiary')"
                )
                ->get();

            foreach ($employee->skills as $skill) {
                $skill->lk_assessment = DB::table('lk_skill_assessments')
                    ->where('employee_skill_id', $skill->employee_skill_id)
                    ->orderByDesc('id')
                    ->select(
                        'id as assessment_id',
                        'employee_proficiency',
                        'lk_proficiency',
                        'lk_score',
                        'decision',
                        'feedback',
                        'assessed_by',
                        'assessed_at'
                    )
                    ->first();
            }
        }

        return $employees;
    }
    
public function sendUpskillRequest(Request $request)
{
    $validator = Validator::make($request->all(), [
        'employee_id' => 'required|integer|exists:employees,id',
        'project_requirement_id' => 'required|integer|exists:project_requirements,id',
        'skills' => 'required|array|min:1',
        'skills.*.skill_id' => 'required|integer|distinct|exists:skills,id',
        'skills.*.required_proficiency' => 'required|numeric|min:0|max:5',
        'skills.*.feedback' => 'nullable|string|max:5000',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 422);
    }

    $validated = $validator->validated();

    try {
        $result = DB::transaction(function () use ($validated, $request) {
            $employeeId = $validated['employee_id'];
            $requirementId = $validated['project_requirement_id'];

            // Ensure the employee and project requirement are active.
            $employee = DB::table('employees')
                ->where('id', $employeeId)
                ->where('bit_delete', 0)
                ->first();

            if (!$employee) {
                throw new \RuntimeException(
                    'Employee not found or inactive.'
                );
            }

            $requirement = DB::table('project_requirements')
                ->where('id', $requirementId)
                ->where('bit_delete', 0)
                ->first();

            if (!$requirement) {
                throw new \RuntimeException(
                    'Project requirement not found or inactive.'
                );
            }

            // Read the authenticated user ID for requested_by.
            $requestedBy = $request->user()?->id;

            if (!$requestedBy) {
                abort(401, 'Unauthenticated.');
            }

            // Build the set of skills allowed by this project requirement.
            $requiredSkillIds = [];

            foreach ([
                'primary_skill_id',
                'secondary_skill_id',
                'tertiary_skill_id',
            ] as $column) {
                if (!empty($requirement->{$column})) {
                    $requiredSkillIds[] = (int) $requirement->{$column};
                }
            }

            $requiredSkillIds = array_values(
                array_unique($requiredSkillIds)
            );

            if (empty($requiredSkillIds)) {
                throw new \RuntimeException(
                    'This project requirement has no configured skills.'
                );
            }

            $requestIds = [];

            foreach ($validated['skills'] as $skillRequest) {
                $skillId = (int) $skillRequest['skill_id'];

                // Only accept skills defined for this requirement.
                if (!in_array($skillId, $requiredSkillIds, true)) {
                    throw new \RuntimeException(
                        "Skill ID {$skillId} is not part of this project requirement."
                    );
                }

                // Find the employee's active skill record, if one exists.
                $employeeSkill = DB::table('employee_skills')
                    ->where('employee_id', $employeeId)
                    ->where('skill_id', $skillId)
                    ->where('bit_delete', 0)
                    ->orderByDesc('id')
                    ->first();

                $currentProficiency = $employeeSkill
                    ? $employeeSkill->proficiency
                    : null;

                // Do not create a duplicate active request for the same
                // employee, project requirement, and skill.
                $duplicate = DB::table('skill_gap_requests')
                    ->where('employee_id', $employeeId)
                    ->where('project_requirement_id', $requirementId)
                    ->where('skill_id', $skillId)
                    ->whereNotIn('status', [
                        'Approved',
                        'Rejected',
                        'Cancelled',
                    ])
                    ->exists();

                if ($duplicate) {
                    throw new \RuntimeException(
                        "An active upskill request already exists for skill ID {$skillId}."
                    );
                }

                $requestIds[] = DB::table('skill_gap_requests')
                    ->insertGetId([
                        'employee_id' => $employeeId,
                        'project_requirement_id' => $requirementId,
                        'skill_id' => $skillId,
                        'current_proficiency' => $currentProficiency,
                        'required_proficiency' =>
                            $skillRequest['required_proficiency'],
                        'resource_manager_feedback' =>
                            $skillRequest['feedback'] ?? null,
                        'requested_by' => $requestedBy,
                        'status' => 'Pending L&K',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            return $requestIds;
        });

        return response()->json([
            'success' => true,
            'message' => 'Upskill requests submitted successfully.',
            'request_ids' => $result,
        ], 201);

    } catch (\Throwable $e) {
        if ($e instanceof \Illuminate\Http\Exceptions\HttpResponseException) {
            throw $e;
        }

        Log::error('Resource Manager upskill request failed', [
            'employee_id' => $validated['employee_id'] ?? null,
            'project_requirement_id' =>
                $validated['project_requirement_id'] ?? null,
            'error' => $e->getMessage(),
        ]);

        $statusCode = $e instanceof \RuntimeException ? 422 : 500;

        return response()->json([
            'success' => false,
            'message' => $e->getMessage() === ''
                ? 'Unable to submit upskill requests.'
                : $e->getMessage(),
        ], $statusCode);
    }
}


    public function recommendations($id)
    {
        $employees = $this->getEligibleEmployees();

        return response()->json([
            'success' => true,
            'total_employees' => $employees->count(),
            'employees' => $employees,
        ]);
    }

    public function aiRecommendations(
    $id,
    EmployeeRecommendationService $aiService
) {
    try {
        // $id is project_requirements.id
        $project = DB::table('project_requirements as pr')
            ->join('projects as p', 'p.id', '=', 'pr.project_id')
            ->where('pr.id', $id)
            ->where('pr.bit_delete', 0)
            ->where('p.bit_delete', 0)
            ->select(
                'pr.id as requirement_id',
                'p.id as project_id',
                'p.project_name',
                'p.start_date',
                'p.end_date',
                'pr.required_resources',
                'pr.primary_skill_id',
                'pr.primary_proficiency',
                'pr.secondary_skill_id',
                'pr.secondary_proficiency',
                'pr.tertiary_skill_id',
                'pr.tertiary_proficiency',
                'pr.requirement_description'
            )
            ->first();

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => 'Project requirement not found.',
            ], 404);
        }

        /*
         * Build normalized project skill requirements.
         * Skill names come from the skills table.
         */
        $skillRequirements = [
            [
                'skill_type' => 'Primary',
                'skill_id' => $project->primary_skill_id,
                'required_proficiency' => $project->primary_proficiency,
            ],
            [
                'skill_type' => 'Secondary',
                'skill_id' => $project->secondary_skill_id,
                'required_proficiency' => $project->secondary_proficiency,
            ],
            [
                'skill_type' => 'Tertiary',
                'skill_id' => $project->tertiary_skill_id,
                'required_proficiency' => $project->tertiary_proficiency,
            ],
        ];

        $requiredSkills = [];

        foreach ($skillRequirements as $requirement) {
            // Ignore skill slots that are not configured.
            if (empty($requirement['skill_id'])) {
                continue;
            }

            $skill = DB::table('skills')
                ->where('id', $requirement['skill_id'])
                ->where('bit_delete', 0)
                ->select('id', 'name', 'category')
                ->first();

            if (!$skill) {
                continue;
            }

            $requiredSkills[] = [
                'skill_id' => (int) $skill->id,
                'skill_name' => $skill->name,
                'category' => $skill->category,
                'skill_type' => $requirement['skill_type'],
                'required_proficiency' =>
                    $requirement['required_proficiency'] !== null
                        ? (float) $requirement['required_proficiency']
                        : null,
            ];
        }

        // Return a project object with normalized skill requirements.
        $projectData = [
            'requirement_id' => (int) $project->requirement_id,
            'project_id' => (int) $project->project_id,
            'project_name' => $project->project_name,
            'start_date' => $project->start_date,
            'end_date' => $project->end_date,
            'required_resources' => (int) $project->required_resources,
            'requirement_description' =>
                $project->requirement_description,
            'required_skills' => $requiredSkills,
        ];

        /*
         * getEligibleEmployees() already filters employees:
         * - at least one active employee skill
         * - latest L&K assessment for every active skill is Approved
         */
        $employees = $this->getEligibleEmployees()
            ->map(function ($employee) {
                $employeeData = (array) $employee;
                $employeeData['skills'] = [];

                foreach ($employee->skills as $skill) {
                    $skillData = (array) $skill;

                    $skillData['lk_assessment'] = $skill->lk_assessment
                        ? (array) $skill->lk_assessment
                        : null;

                    $employeeData['skills'][] = $skillData;
                }

                return $employeeData;
            })
            ->values()
            ->all();

        /*
         * The AI service receives the actual project requirements
         * and only the L&K-eligible employees.
         */
        $aiResult = $aiService->recommend(
            $employees,
            $projectData
        );

        return response()->json([
            'success' => true,
            'project' => $projectData,
            'resource_need' => $projectData['required_resources'],
            'required_skills' => $requiredSkills,
            'total_required_skills' => count($requiredSkills),
            'total_eligible_employees' => count($employees),
            'eligible_employees' => $employees,
            'ai' => $aiResult,
        ]);
    } catch (\Throwable $e) {
        Log::error('AI recommendations failed', [
            'requirement_id' => $id,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Unable to generate AI recommendations.',
        ], 500);
    }
}

 public function reviedByManager(){
    
 } 
}