<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
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
            ->where('e.status', 2)
            
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
            DB::table('employees')
    ->where('id', $employeeId)
    ->update([
        'status' => 3,
        'updated_at' => now(),
    ]);

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

public function updateEmployeeStatus(Request $request, $id)
{
    $validated = $request->validate([
        'status' => 'required|integer|in:4',
    ]);

    $employee = DB::table('employees')
        ->where('id', $id)
        ->where('bit_delete', 0)
        ->first();

    if (!$employee) {
        return response()->json([
            'success' => false,
            'message' => 'Employee not found.',
        ], 404);
    }

    DB::table('employees')
        ->where('id', $id)
        ->update([
            'status' => $validated['status'],
            'updated_at' => now(),
        ]);

    return response()->json([
        'success' => true,
        'message' => 'Employee status updated to Resource Manager Reviewed.',
        'employee_id' => (int) $id,
        'status' => 4,
    ]);
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

 
public function aiRecommendations($id)
{
    try {
        // 1. Get project requirement details.
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

        // 2. Get the project-required skills and their names.
        $skillSlots = [
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

        foreach ($skillSlots as $slot) {
            if (empty($slot['skill_id'])) {
                continue;
            }

            $skill = DB::table('skills')
                ->where('id', $slot['skill_id'])
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
                'skill_type' => $slot['skill_type'],
                'required_proficiency' =>
                    $slot['required_proficiency'] !== null
                        ? (float) $slot['required_proficiency']
                        : null,
            ];
        }

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

        // 3. Get only employees who pass your existing L&K eligibility filter.
        $employees = $this->getEligibleEmployees()
            ->map(function ($employee) {
                $data = (array) $employee;
                $data['skills'] = [];

                foreach ($employee->skills as $skill) {
                    $skillData = (array) $skill;

                    $skillData['lk_assessment'] = $skill->lk_assessment
                        ? (array) $skill->lk_assessment
                        : null;

                    $data['skills'][] = $skillData;
                }

                return $data;
            })
            ->values()
            ->all();

        if (empty($requiredSkills)) {
            return response()->json([
                'success' => false,
                'message' => 'No valid project skills are configured.',
            ], 422);
        }

        if (empty($employees)) {
            return response()->json([
                'success' => true,
                'project' => $projectData,
                'required_skills' => $requiredSkills,
                'total_required_skills' => count($requiredSkills),
                'total_eligible_employees' => 0,
                'eligible_employees' => [],
                'ai' => [
                    'summary' => 'No eligible employees were found.',
                    'recommendations' => [],
                ],
            ]);
        }

        // 4. Send project requirements and eligible employees directly to Groq.
        $apiKey = config('services.groq.key');
        $model = config('services.groq.model', 'openai/gpt-oss-20b');

        if (empty($apiKey)) {
            throw new \RuntimeException(
                'Groq API key is missing. Configure GROQ_API_KEY in .env.'
            );
        }

        $promptData = [
            'project' => $projectData,
            'eligible_employees' => $employees,
        ];

        $systemPrompt = <<<'PROMPT'
You are an expert IT resource manager.

Your task is to match eligible employees to the supplied project requirement.

Use only the project and employee data provided in the user message.
Do not invent skills, proficiency levels, assessment results, experience,
availability, or other employee facts.

Compare each employee against every required project skill. Consider:
- Required skill coverage.
- Employee proficiency compared with the required proficiency.
- Latest L&K assessment decision and L&K proficiency.
- Relevant experience and project experience when provided.
- Employee availability and project dates when provided.

An employee's recorded proficiency alone does not prove L&K approval.
Do not describe a skill as L&K-approved unless its latest assessment decision
is Approved. Flag missing assessment information for human review.

Return valid JSON only, with this structure:
{
  "summary": "Brief project-level recommendation",
  "recommendations": [
    {
      "employee_id": 1,
      "rank": 1,
      "overall_match_percentage": 90,
      "rating": "Excellent Match",
      "recommendation": "Recommended",
      "reason": "Evidence-based explanation",
      "matched_skills": [
        {
          "skill_id": 1,
          "skill_name": "Example",
          "employee_proficiency": 4,
          "lk_proficiency": 4,
          "required_proficiency": 3,
          "assessment_decision": "Approved",
          "status": "Requirement met"
        }
      ],
      "upskill_needed": true,
      "upskill_skills": [
        {
          "skill_id": 2,
          "skill_name": "Example skill",
          "current_proficiency": 2,
          "lk_proficiency": 2,
          "required_proficiency": 4,
          "reason": "Below required proficiency"
        }
      ],
      "review_required": false
    }
  ]
}

Rules:
- Return one recommendation for every supplied eligible employee.
- Sort recommendations from best match to lowest match.
- Use each real employee_id from the supplied data.
- Match percentage must be an integer from 0 to 100, based on evidence.
- Ratings must be one of: Excellent Match, Strong Match, Partial Match, Low Match.
- Set upskill_needed to true when a required skill is missing or its recorded
  proficiency is below target. Include only skills needing upskilling in
  upskill_skills.
- If an assessment is missing, unapproved, or lacks L&K proficiency, flag
  review_required and explain the uncertainty. Do not claim approval.
- Do not recommend automatic allocation. A human manager makes the decision.
- Return {"summary":"...","recommendations":[]} if there are no candidates.
PROMPT;

        $groqResponse = Http::timeout(60)
            ->withToken($apiKey)
            ->acceptJson()
            ->post(
                'https://api.groq.com/openai/v1/chat/completions',
                [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $systemPrompt,
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode(
                                $promptData,
                                JSON_UNESCAPED_UNICODE |
                                JSON_UNESCAPED_SLASHES
                            ),
                        ],
                    ],
                    'temperature' => 0.2,
                    'max_completion_tokens' => 6000,
                    'response_format' => [
                        'type' => 'json_object',
                    ],
                ]
            );

        if (!$groqResponse->successful()) {
            Log::error('Groq resource recommendation request failed', [
                'status' => $groqResponse->status(),
                'response' => $groqResponse->json(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Groq could not generate recommendations. Please try again.',
            ], 502);
        }

        $content = $groqResponse->json('choices.0.message.content');

        if (!is_string($content) || trim($content) === '') {
            throw new \RuntimeException(
                'Groq returned an empty recommendation response.'
            );
        }

        $aiData = json_decode($content, true);

        if (
            !is_array($aiData) ||
            !isset($aiData['recommendations']) ||
            !is_array($aiData['recommendations'])
        ) {
            throw new \RuntimeException(
                'Groq returned invalid recommendation JSON.'
            );
        }

        // 5. Validate returned employee IDs against the eligible employee list.
        $eligibleIds = array_map(
            fn ($employee) => (int) $employee['employee_id'],
            $employees
        );

        $aiData['recommendations'] = collect($aiData['recommendations'])
            ->filter(function ($item) use ($eligibleIds) {
                return isset($item['employee_id']) &&
                    in_array((int) $item['employee_id'], $eligibleIds, true);
            })
            ->map(function ($item) {
                $item['employee_id'] = (int) $item['employee_id'];
                $item['overall_match_percentage'] = max(
                    0,
                    min(100, (int) ($item['overall_match_percentage'] ?? 0))
                );
                $item['upskill_needed'] = (bool) (
                    $item['upskill_needed'] ?? false
                );
                $item['upskill_skills'] = is_array(
                    $item['upskill_skills'] ?? null
                ) ? $item['upskill_skills'] : [];

                return $item;
            })
            ->sortByDesc('overall_match_percentage')
            ->values()
            ->map(function ($item, $index) {
                $item['rank'] = $index + 1;
                return $item;
            })
            ->all();

        // Return project data, employees and AI analysis to Angular.
        return response()->json([
            'success' => true,
            'project' => $projectData,
            'required_skills' => $requiredSkills,
            'total_required_skills' => count($requiredSkills),
            'total_eligible_employees' => count($employees),
            'eligible_employees' => $employees,
            'ai' => [
                'summary' => $aiData['summary'] ?? '',
                'recommendations' => $aiData['recommendations'],
            ],
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