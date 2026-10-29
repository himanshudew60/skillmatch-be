<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;

class EmployeeRecommendationService
{
    public function recommend(array $employees, array $project): array
    {
        if (empty($employees)) {
            return [
                'summary' => 'No eligible L&K-approved employees were found.',
                'recommendations' => [],
            ];
        }

        $recommendations = [];

        foreach ($employees as $employee) {
            $skillAnalysis = $this->calculateSkillMatch(
                $employee['skills'] ?? [],
                $project
            );

            $recommendations[] = [
                'employee_id' => (int) $employee['employee_id'],
                'overall_match_percentage' => $skillAnalysis['overall'],
                'skill_match_percentage' => $skillAnalysis['skill'],
                'proficiency_match_percentage' => $skillAnalysis['proficiency'],
                'experience_match_percentage' => $skillAnalysis['experience'],
                'lk_evidence_percentage' => $skillAnalysis['lk_evidence'],
                'availability_match_percentage' => $skillAnalysis['availability'],
                'rating' => $this->rating($skillAnalysis['overall']),
                'strengths' => $skillAnalysis['strengths'],
                'gaps' => $skillAnalysis['gaps'],
                'feedback' => '',
                'additional_recommendations' => [],
                'confidence' => 'Medium',
            ];
        }

        usort(
            $recommendations,
            fn ($a, $b) =>
                $b['overall_match_percentage'] <=>
                $a['overall_match_percentage']
        );

        foreach ($recommendations as $index => &$recommendation) {
            $recommendation['rank'] = $index + 1;
        }
        unset($recommendation);

        try {
            $aiContent = $this->generateExplanations(
                $employees,
                $project,
                $recommendations
            );

            $byId = [];

            foreach ($aiContent as $item) {
                if (isset($item['employee_id'])) {
                    $byId[(int) $item['employee_id']] = $item;
                }
            }

            foreach ($recommendations as &$recommendation) {
                $extra = $byId[$recommendation['employee_id']] ?? [];

                $recommendation['feedback'] =
                    $extra['feedback'] ??
                    'The match is based on the supplied project requirements and employee evidence.';

                $recommendation['additional_recommendations'] =
                    $extra['additional_recommendations'] ?? [];

                $recommendation['confidence'] =
                    $extra['confidence'] ?? 'Medium';
            }
            unset($recommendation);
        } catch (\Throwable $e) {
            // A temporary AI failure should not fabricate an explanation
            // or turn an employee into an ineligible candidate.
            Log::warning('AI explanation generation failed', [
                'error' => $e->getMessage(),
            ]);

            foreach ($recommendations as &$recommendation) {
                $recommendation['feedback'] =
                    'Numeric matching completed. AI explanation is temporarily unavailable.';
                $recommendation['additional_recommendations'] = [
                    'Review the supplied project requirements and employee evidence manually.',
                ];
            }
            unset($recommendation);
        }

        return [
            'summary' => 'Employees are ranked using project-specific criteria. Final allocation requires human review.',
            'recommendations' => $recommendations,
        ];
    }

    
private function calculateSkillMatch(array $skills, array $project): array
{
    $requirements = [];

    // Prefer the normalized project skill requirements.
    foreach (($project['required_skills'] ?? []) as $requiredSkill) {
        if (empty($requiredSkill['skill_id'])) {
            continue;
        }

        $requirements[] = [
            'skill_id' => (int) $requiredSkill['skill_id'],
            'skill_name' => $requiredSkill['skill_name']
                ?? ('Skill ID ' . $requiredSkill['skill_id']),
            'proficiency' => (float) (
                $requiredSkill['required_proficiency'] ?? 0
            ),
        ];
    }

    // Backward compatibility with the legacy project schema.
    if (empty($requirements)) {
        foreach (['primary', 'secondary', 'tertiary'] as $type) {
            $skillId = $project[$type . '_skill_id'] ?? null;

            if (!$skillId) {
                continue;
            }

            $requirements[] = [
                'skill_id' => (int) $skillId,
                'skill_name' => 'Skill ID ' . $skillId,
                'proficiency' => (float) (
                    $project[$type . '_proficiency'] ?? 0
                ),
            ];
        }
    }

    if (empty($requirements)) {
        return [
            'overall' => 0,
            'skill' => 0,
            'proficiency' => 0,
            'experience' => 0,
            'lk_evidence' => 0,
            'availability' => 0,
            'strengths' => [],
            'gaps' => [
                'No project skill requirements were supplied. Check the project requirement API.',
            ],
        ];
    }

    $matched = 0;
    $proficiencyTotal = 0;
    $lkTotal = 0;
    $strengths = [];
    $gaps = [];
    $requirementCount = count($requirements);

    foreach ($requirements as $requirement) {
        $employeeSkill = collect($skills)->first(
            fn ($skill) =>
                (int) ($skill['skill_id'] ?? 0)
                === $requirement['skill_id']
        );

        $skillName = $requirement['skill_name'];
        $target = $requirement['proficiency'];

        if (!$employeeSkill) {
            $gaps[] = "Missing required skill: {$skillName}.";

            // Missing skills contribute zero to both proficiency metrics.
            continue;
        }

        $matched++;

        $employeeProficiency = (float) (
            $employeeSkill['employee_proficiency'] ?? 0
        );

        $lkProficiency = $employeeSkill['lk_assessment']['lk_proficiency']
            ?? $employeeSkill['verified_proficiency']
            ?? null;

        // If the project has no target, don't invent a proficiency gap.
        if ($target <= 0) {
            $proficiencyTotal += 100;
            $lkTotal += $lkProficiency !== null ? 100 : 0;

            $strengths[] = "{$skillName} is recorded in the employee profile.";

            if ($lkProficiency === null) {
                $gaps[] = "L&K proficiency evidence is unavailable for {$skillName}.";
            }

            continue;
        }

        $employeePercentage = min(
            100,
            max(0, ($employeeProficiency / $target) * 100)
        );

        $lkPercentage = $lkProficiency !== null
            ? min(100, max(0, ((float) $lkProficiency / $target) * 100))
            : 0;

        $proficiencyTotal += $employeePercentage;
        $lkTotal += $lkPercentage;

        $employeeMeetsTarget = $employeeProficiency >= $target;
        $lkMeetsTarget = $lkProficiency !== null
            && (float) $lkProficiency >= $target;

        if ($employeeMeetsTarget && $lkMeetsTarget) {
            $strengths[] =
                "{$skillName} meets the employee and L&K proficiency targets.";
        } else {
            $gaps[] = sprintf(
                '%s: required %.1f/5, employee %.1f/5, L&K %s.',
                $skillName,
                $target,
                $employeeProficiency,
                $lkProficiency !== null
                    ? number_format((float) $lkProficiency, 1) . '/5'
                    : 'not assessed'
            );
        }
    }

    $skillPercentage = ($matched / $requirementCount) * 100;
    $proficiencyPercentage = $proficiencyTotal / $requirementCount;
    $lkPercentage = $lkTotal / $requirementCount;

    // Experience and availability have no configured targets yet.
    // Calculate the overall score from the assessed weights only:
    // skill coverage 40%, employee proficiency 25%, L&K evidence 10%.
    $assessedWeight = 0.40 + 0.25 + 0.10;

    $overall = (
        $skillPercentage * 0.40
        + $proficiencyPercentage * 0.25
        + $lkPercentage * 0.10
    ) / $assessedWeight;

    return [
        'overall' => (int) round($overall),
        'skill' => (int) round($skillPercentage),
        'proficiency' => (int) round($proficiencyPercentage),
        'experience' => 0,
        'lk_evidence' => (int) round($lkPercentage),
        'availability' => 0,
        'strengths' => array_values(array_unique($strengths)),
        'gaps' => array_values(array_unique($gaps)),
    ];
}


    private function experiencePercentage(
        array $skills,
        array $requirements
    ): int {
        // Experience requirements are not included in the current
        // project_requirements schema, so don't invent a target.
        // Treat the dimension as unassessed until that target exists.
        return 0;
    }

    private function rating(int $percentage): string
    {
        if ($percentage >= 85) return 'Excellent Match';
        if ($percentage >= 70) return 'Strong Match';
        if ($percentage >= 50) return 'Partial Match';

        return 'Low Match';
    }

    private function generateExplanations(
        array $employees,
        array $project,
        array $recommendations
    ): array {
        $response = OpenAI::responses()->create([
            'model' => config('openai.model', env('OPENAI_MODEL', 'gpt-5')),
            'instructions' => '
                You explain employee/project matching results.
                Use only the supplied employee data and calculated metrics.
                Never change numeric scores, rankings, or employee IDs.
                Do not invent skills, experience, certifications, availability,
                assessment results, or missing requirements.
                Explain strengths, risks and useful next steps.
                Return only the requested JSON.
            ',
            'input' => json_encode([
                'project' => $project,
                'employees' => $employees,
                'calculated_matches' => $recommendations,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'employee_explanations',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'recommendations' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'employee_id' => ['type' => 'integer'],
                                        'feedback' => ['type' => 'string'],
                                        'additional_recommendations' => [
                                            'type' => 'array',
                                            'items' => ['type' => 'string'],
                                        ],
                                        'confidence' => [
                                            'type' => 'string',
                                            'enum' => ['High', 'Medium', 'Low'],
                                        ],
                                    ],
                                    'required' => [
                                        'employee_id',
                                        'feedback',
                                        'additional_recommendations',
                                        'confidence',
                                    ],
                                    'additionalProperties' => false,
                                ],
                            ],
                        ],
                        'required' => ['recommendations'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
        ]);

        $decoded = json_decode($response->outputText ?? '', true);

        if (!is_array($decoded)) {
            throw new RuntimeException('AI response was not valid JSON.');
        }

        $eligibleIds = array_map(
            fn ($employee) => (int) $employee['employee_id'],
            $employees
        );

        return array_values(array_filter(
            $decoded['recommendations'] ?? [],
            fn ($item) =>
                isset($item['employee_id']) &&
                in_array((int) $item['employee_id'], $eligibleIds, true)
        ));
    }
}