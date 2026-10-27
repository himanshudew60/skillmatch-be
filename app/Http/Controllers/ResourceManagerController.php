<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResourceManagerController extends Controller
{
    public function recommendations($id)
    {
        $requirement = DB::table('project_requirements as pr')
            ->join('projects as p', 'p.id', '=', 'pr.project_id')
            ->where('pr.id', $id)
            ->where('pr.bit_delete', 0)
            ->where('p.bit_delete', 0)
            ->select(
                'pr.*',
                'p.project_code',
                'p.project_name',
                'p.start_date',
                'p.end_date',
                'p.status as project_status'
            )
            ->first();

        if (!$requirement) {
            return response()->json([
                'success' => false,
                'message' => 'Project requirement not found.',
            ], 404);
        }

        $requiredSkills = [];

        foreach (['primary', 'secondary', 'tertiary'] as $type) {
            $skillId = $requirement->{$type . '_skill_id'};
            $proficiency = $requirement->{$type . '_proficiency'};

            if ($skillId && $proficiency !== null) {
                $requiredSkills[] = [
                    'skill_id' => (int) $skillId,
                    'required_proficiency' => (float) $proficiency,
                    'skill_type' => ucfirst($type),
                ];
            }
        }

        if (empty($requiredSkills)) {
            return response()->json([
                'success' => false,
                'message' => 'This requirement has no configured skills.',
            ], 422);
        }

        $employees = DB::table('employees as e')
            ->leftJoin('departments as d', 'd.id', '=', 'e.department_id')
            ->leftJoin('designations as des', 'des.id', '=', 'e.designation_id')
            ->where('e.bit_delete', 0)
            ->whereIn('e.resource_status', [0, 2])
            ->select(
                'e.id',
                'e.emp_code',
                'e.emp_name',
                'e.emp_email',
                'e.department_id',
                'd.name as department_name',
                'des.name as designation_name',
                'e.resource_status',
                'e.available_from'
            )
            ->orderBy('e.emp_name')
            ->get();

        $results = [];

        foreach ($employees as $employee) {
            $employeeSkills = DB::table('employee_skills as es')
                ->join('skills as s', 's.id', '=', 'es.skill_id')
                ->where('es.employee_id', $employee->id)
                ->where('es.bit_delete', 0)
                ->where('s.bit_delete', 0)
                ->select(
                    'es.id as employee_skill_id',
                    'es.skill_id',
                    's.name as skill_name',
                    'es.proficiency',
                    'es.experience',
                    'es.assessment_score',
                    'es.skill_validation_status'
                )
                ->get()
                ->keyBy('skill_id');

            $skillResults = [];
            $covered = 0;
            $proficiencyPoints = 0;
            $experiencePoints = 0;
            $evidencePoints = 0;
            $allSkillsQualified = true;
            $explanations = [];

            foreach ($requiredSkills as $required) {
                $skill = $employeeSkills->get($required['skill_id']);

                if (!$skill) {
                    $allSkillsQualified = false;

                    $skillResults[] = [
                        'skill_id' => $required['skill_id'],
                        'skill_name' => DB::table('skills')
                            ->where('id', $required['skill_id'])
                            ->value('name'),
                        'required_proficiency' =>
                            $required['required_proficiency'],
                        'actual_proficiency' => null,
                        'employee_score' => null,
                        'lk_score' => null,
                        'lk_decision' => 'Not Assessed',
                        'qualified' => false,
                    ];

                    $explanations[] =
                        'Missing required skill ID ' . $required['skill_id'];

                    continue;
                }
                

                $covered++;

                $latestAssessment = DB::table('lk_skill_assessments')
                    ->where('employee_skill_id', $skill->employee_skill_id)
                    ->orderByDesc('id')
                    ->first();

                $actual = (float) $skill->proficiency;
                $requiredLevel = $required['required_proficiency'];

                $lkApproved = $latestAssessment
                    && $latestAssessment->decision === 'Approved';

                $meetsLevel = $actual >= $requiredLevel;
                $qualified = $meetsLevel && $lkApproved;

                if (!$qualified) {
                    $allSkillsQualified = false;
                }

                // Exact or sufficient proficiency gets full points.
                // Higher proficiency does not automatically get a bonus.
                $proficiencyFit = $requiredLevel > 0
                    ? min(1, $actual / $requiredLevel)
                    : 1;

                $proficiencyPoints += $proficiencyFit;

                // Experience is a proxy until the project defines a
                // specific experience requirement.
                $experienceMonths = max(0, (float) $skill->experience);
                $experienceFit = min(1, $experienceMonths / 36);
                $experiencePoints += $experienceFit;

                // Use employee assessment and latest L&K score as evidence.
                $employeeScore = $skill->assessment_score !== null
                    ? (float) $skill->assessment_score
                    : null;

                $lkScore = $latestAssessment
                    && $latestAssessment->lk_score !== null
                    ? (float) $latestAssessment->lk_score
                    : null;

                $evidenceValues = array_values(array_filter(
                    [$employeeScore, $lkScore],
                    fn ($value) => $value !== null
                ));

                $evidenceFit = count($evidenceValues)
                    ? array_sum($evidenceValues) / count($evidenceValues) / 100
                    : 0;

                $evidencePoints += $evidenceFit;

                $skillResults[] = [
                    'skill_id' => (int) $skill->skill_id,
                    'skill_name' => $skill->skill_name,
                    'skill_type' => $required['skill_type'],
                    'required_proficiency' => $requiredLevel,
                    'actual_proficiency' => $actual,
                    'employee_score' => $employeeScore,
                    'lk_proficiency' => $latestAssessment->lk_proficiency ?? null,
                    'lk_score' => $lkScore,
                    'lk_decision' => $latestAssessment->decision ?? 'Not Assessed',
                    'experience_months' => $experienceMonths,
                    'qualified' => $qualified,
                ];

                $explanations[] = $qualified
                    ? "{$skill->skill_name}: meets proficiency and L&K approval requirements."
                    : "{$skill->skill_name}: does not yet meet all proficiency and L&K approval requirements.";
            }

            $skillCount = count($requiredSkills);

            $skillCoverage = $covered / $skillCount;
            $proficiencyFit = $proficiencyPoints / $skillCount;
            $experienceFit = $experiencePoints / $skillCount;
            $evidenceFit = $evidencePoints / $skillCount;

            // Status 0 = Available; status 2 = Partially Available.
            // Partial availability receives half availability points.
            $availabilityFit = (int) $employee->resource_status === 0
                ? 1
                : 0.5;

            $matchScore = round(
                ($skillCoverage * 40) +
                ($proficiencyFit * 25) +
                ($experienceFit * 15) +
                ($evidenceFit * 10) +
                ($availabilityFit * 10),
                2
            );

            $results[] = [
                'employee' => $employee,
                'match_score' => $matchScore,
                'eligible' => $allSkillsQualified,
                'skills' => $skillResults,
                'explanations' => $explanations,
            ];
        }

        // Eligible candidates first, then descending score.
        usort($results, function ($a, $b) {
            if ($a['eligible'] !== $b['eligible']) {
                return $b['eligible'] <=> $a['eligible'];
            }

            return $b['match_score'] <=> $a['match_score'];
        });

        return response()->json([
            'success' => true,
            'requirement' => $requirement,
            'required_skills' => $requiredSkills,
            'candidates' => $results,
        ]);
    }
}