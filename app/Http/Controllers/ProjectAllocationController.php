<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ProjectAllocationController extends Controller
{
    // 1. List projects with required and tagged resource counts
    public function index()
    {
        try {
            $projects = DB::table('projects as p')
                ->join(
                    'project_requirements as re',
                    'p.id',
                    '=',
                    're.project_id'
                )
                ->leftJoin(
                    'employees as manager',
                    'manager.id',
                    '=',
                    'p.project_manager_id'
                )
                ->leftJoinSub(
                    DB::table('project_allocations')
                        ->select(
                            'project_id',
                            DB::raw('COUNT(*) as tagged_employees')
                        )
                        ->groupBy('project_id'),
                    'pa',
                    'p.id',
                    '=',
                    'pa.project_id'
                )
                ->select(
                    'p.id',
                    'p.project_code',
                    'p.project_name',
                    'p.client_name',
                    'manager.emp_name as project_manager',
                    're.required_resources',
                    DB::raw(
                        'COALESCE(pa.tagged_employees, 0) as tagged_employees'
                    )
                )
                ->get();

            return response()->json([
                'success' => true,
                'data' => $projects
            ], 200);

        } catch (Throwable $e) {
            Log::error('Project allocation list failed', [
                'exception' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch projects.'
            ], 500);
        }
    }

    // 2. Employees approved by the Resource Manager
    public function eligibleEmployees()
    {
        try {
            $employees = DB::table('employees')
                ->where('status', 4)
                ->select('id', 'emp_name')
                ->orderBy('emp_name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $employees
            ], 200);

        } catch (Throwable $e) {
            Log::error('Eligible employees failed', [
                'exception' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch employees.'
            ], 500);
        }
    }

    // 3. Get allocations belonging to a project
    public function projectAllocations($projectId)
    {
        try {
            $allocations = DB::table('project_allocations as pa')
                ->join(
                    'employees as e',
                    'e.id',
                    '=',
                    'pa.employee_id'
                )
                ->where('pa.project_id', $projectId)
                ->select(
                    'pa.id',
                    'pa.project_id',
                    'pa.employee_id',
                    'pa.start_date',
                    'pa.end_date',
                    'e.emp_name'
                )
                ->orderBy('pa.start_date')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $allocations
            ], 200);

        } catch (Throwable $e) {
            Log::error('Project allocations fetch failed', [
                'project_id' => $projectId,
                'exception' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch project allocations.'
            ], 500);
        }
    }

    // 4. Get one allocation for editing
    public function show($id)
    {
        try {
            $allocation = DB::table('project_allocations as pa')
                ->join('employees as e', 'e.id', '=', 'pa.employee_id')
                ->join('projects as p', 'p.id', '=', 'pa.project_id')
                ->where('pa.id', $id)
                ->select(
                    'pa.id',
                    'pa.project_id',
                    'pa.employee_id',
                    'pa.start_date',
                    'pa.end_date',
                    'e.emp_name',
                    'p.project_name'
                )
                ->first();

            if (!$allocation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Allocation not found.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $allocation
            ], 200);

        } catch (Throwable $e) {
            Log::error('Allocation show failed', [
                'id' => $id,
                'exception' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch allocation.'
            ], 500);
        }
    }

    // 5. Create an allocation
    public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'project_id' => 'required|integer|exists:projects,id',
        'employee_id' => 'required|integer|exists:employees,id',
        'start_date'  => 'required|date',
        'end_date'    => 'required|date|after_or_equal:start_date',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422);
    }

    try {
        $allocation = DB::transaction(function () use ($request) {

            // Lock the project requirement row while checking capacity.
            $requirement = DB::table('project_requirements')
                ->where('project_id', $request->project_id)
                ->lockForUpdate()
                ->first();

            if (!$requirement) {
                throw new \RuntimeException(
                    'Project requirements were not found.'
                );
            }

            // Lock the employee row and check eligibility.
            $employee = DB::table('employees')
                ->where('id', $request->employee_id)
                ->lockForUpdate()
                ->first();

            if (!$employee || (int) $employee->status !== 4) {
                throw new \RuntimeException(
                    'Only approved employees with status 4 can be allocated.'
                );
            }

            // Count existing allocations.
            $taggedCount = DB::table('project_allocations')
                ->where('project_id', $request->project_id)
                ->count();

            if ($taggedCount >= (int) $requirement->required_resources) {
                throw new \RuntimeException(
                    'This project has reached its allocation limit.'
                );
            }

            // Prevent duplicate employee allocation to this project.
            $alreadyAllocated = DB::table('project_allocations')
                ->where('project_id', $request->project_id)
                ->where('employee_id', $request->employee_id)
                ->exists();

            if ($alreadyAllocated) {
                throw new \RuntimeException(
                    'This employee is already allocated to this project.'
                );
            }

            // Create the allocation.
            $allocationId = DB::table('project_allocations')->insertGetId([
                'project_id' => $request->project_id,
                'employee_id' => $request->employee_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Change employee status from 4 to 5.
            DB::table('employees')
                ->where('id', $request->employee_id)
                ->update([
                    'status' => 5,
                    'updated_at' => now(),
                ]);

            return $allocationId;
        });

        return response()->json([
            'success' => true,
            'message' => 'Employee allocated successfully and status updated to 5.',
            'data' => ['id' => $allocation],
        ], 201);

    } catch (\RuntimeException $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 422);

    } catch (\Throwable $e) {
        Log::error('Project allocation failed', [
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Unable to allocate employee.',
        ], 500);
    }
}

    // 6. Update an allocation
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|integer|exists:projects,id',
            'employee_id' => 'required|integer|exists:employees,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = DB::transaction(function () use ($request, $id) {
                $allocation = DB::table('project_allocations')
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->first();

                if (!$allocation) {
                    return [
                        'status' => 404,
                        'body' => [
                            'success' => false,
                            'message' => 'Allocation not found.'
                        ]
                    ];
                }

                DB::table('projects')
                    ->where('id', $request->project_id)
                    ->lockForUpdate()
                    ->first();

                $employee = DB::table('employees')
                    ->where('id', $request->employee_id)
                    ->first();

                if (!$employee || (int) $employee->status !== 4) {
                    return [
                        'status' => 422,
                        'body' => [
                            'success' => false,
                            'message' => 'Select an employee approved by the Resource Manager.'
                        ]
                    ];
                }

                $requirement = DB::table('project_requirements')
                    ->where('project_id', $request->project_id)
                    ->first();

                if (!$requirement) {
                    return [
                        'status' => 422,
                        'body' => [
                            'success' => false,
                            'message' => 'No resource requirement exists for this project.'
                        ]
                    ];
                }

                $required = (int) $requirement->required_resources;

                $tagged = DB::table('project_allocations')
                    ->where('project_id', $request->project_id)
                    ->count();

                // Moving to another project consumes a new slot.
                if ((int) $allocation->project_id !== (int) $request->project_id
                    && $tagged >= $required) {
                    return [
                        'status' => 422,
                        'body' => [
                            'success' => false,
                            'message' => 'The destination project has reached capacity.'
                        ]
                    ];
                }

                $duplicate = DB::table('project_allocations')
                    ->where('project_id', $request->project_id)
                    ->where('employee_id', $request->employee_id)
                    ->where('id', '<>', $id)
                    ->exists();

                if ($duplicate) {
                    return [
                        'status' => 422,
                        'body' => [
                            'success' => false,
                            'message' => 'This employee is already allocated to this project.'
                        ]
                    ];
                }

                DB::table('project_allocations')
                    ->where('id', $id)
                    ->update([
                        'project_id' => $request->project_id,
                        'employee_id' => $request->employee_id,
                        'start_date' => $request->start_date,
                        'end_date' => $request->end_date,
                        'updated_at' => now()
                    ]);

                return [
                    'status' => 200,
                    'body' => [
                        'success' => true,
                        'message' => 'Allocation updated successfully.'
                    ]
                ];
            });

            return response()->json(
                $result['body'],
                $result['status']
            );

        } catch (Throwable $e) {
            Log::error('Allocation update failed', [
                'id' => $id,
                'exception' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update allocation.'
            ], 500);
        }
    }

    // 7. Release employee by deleting the allocation record
    public function destroy($id)
    {
        try {
            $deleted = DB::table('project_allocations')
                ->where('id', $id)
                ->delete();

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Allocation not found.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Employee released from project successfully.'
            ], 200);

        } catch (Throwable $e) {
            Log::error('Allocation release failed', [
                'id' => $id,
                'exception' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to release employee.'
            ], 500);
        }
    }
    
public function bulkUpdate(Request $request, $projectId)
{
    $validator = Validator::make($request->all(), [
        'allocations' => 'required|array|min:1',
        'allocations.*.id' => 'required|integer|distinct',
        'allocations.*.start_date' => 'required|date',
        'allocations.*.end_date' =>
            'required|date|after_or_equal:allocations.*.start_date',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422);
    }

    try {
        $updatedCount = DB::transaction(function () use ($request, $projectId) {
            $requirement = DB::table('project_requirements')
                ->where('project_id', $projectId)
                ->lockForUpdate()
                ->first();

            if (!$requirement) {
                throw new \RuntimeException(
                    'Project requirements were not found.'
                );
            }

            $items = $request->input('allocations');
            $ids = collect($items)->pluck('id')->map(
                fn ($id) => (int) $id
            );

            $existing = DB::table('project_allocations')
                ->where('project_id', $projectId)
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            if ($existing->count() !== count($items)) {
                throw new \RuntimeException(
                    'One or more allocations do not belong to this project.'
                );
            }

            foreach ($items as $item) {
                DB::table('project_allocations')
                    ->where('project_id', $projectId)
                    ->where('id', $item['id'])
                    ->update([
                        'start_date' => $item['start_date'],
                        'end_date' => $item['end_date'],
                        'updated_at' => now(),
                    ]);
            }

            return count($items);
        });

        return response()->json([
            'success' => true,
            'message' => "{$updatedCount} allocation(s) updated successfully.",
        ]);

    } catch (\RuntimeException $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 422);

    } catch (\Throwable $e) {
        Log::error('Bulk allocation update failed', [
            'project_id' => $projectId,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Unable to update allocations.',
        ], 500);
    }
}

public function bulkRelease(Request $request, $projectId)
{
    $validator = Validator::make($request->all(), [
        'allocation_ids' => 'required|array|min:1',
        'allocation_ids.*' => 'required|integer|distinct',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422);
    }

    try {
        $releasedCount = DB::transaction(function () use ($request, $projectId) {
            $requirement = DB::table('project_requirements')
                ->where('project_id', $projectId)
                ->lockForUpdate()
                ->first();

            if (!$requirement) {
                throw new \RuntimeException(
                    'Project requirements were not found.'
                );
            }

            $ids = array_map(
                'intval',
                $request->input('allocation_ids')
            );

            $allocations = DB::table('project_allocations')
                ->where('project_id', $projectId)
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            if ($allocations->count() !== count($ids)) {
                throw new \RuntimeException(
                    'One or more allocations do not belong to this project.'
                );
            }

            $employeeIds = $allocations
                ->pluck('employee_id')
                ->unique()
                ->values()
                ->all();

            DB::table('project_allocations')
                ->where('project_id', $projectId)
                ->whereIn('id', $ids)
                ->delete();

            // Only restore status for employees with no remaining allocations.
            foreach ($employeeIds as $employeeId) {
                $hasOtherAllocations = DB::table('project_allocations')
                    ->where('employee_id', $employeeId)
                    ->exists();

                if (!$hasOtherAllocations) {
                    DB::table('employees')
                        ->where('id', $employeeId)
                        ->where('status', 5)
                        ->update([
                            'status' => 2,
                            'updated_at' => now(),
                        ]);
                }
            }

            return count($ids);
        });

        return response()->json([
            'success' => true,
            'message' => "{$releasedCount} allocation(s) released successfully.",
        ]);

    } catch (\RuntimeException $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 422);

    } catch (\Throwable $e) {
        Log::error('Bulk allocation release failed', [
            'project_id' => $projectId,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Unable to release allocations.',
        ], 500);
    }
}
public function storeBulk(\Illuminate\Http\Request $request)
{
    $validated = $request->validate([
        'project_id' => ['required', 'integer', 'exists:projects,id'],
        'employee_ids' => ['required', 'array', 'min:1'],
        'employee_ids.*' => [
            'required',
            'integer',
            'distinct',
            'exists:employees,id',
        ],
        'start_date' => ['required', 'date'],
        'end_date' => [
            'required',
            'date',
            'after_or_equal:start_date',
        ],
    ]);

    return \Illuminate\Support\Facades\DB::transaction(
        function () use ($validated) {
            $projectId = (int) $validated['project_id'];
            $employeeIds = array_map(
                'intval',
                $validated['employee_ids']
            );

            // Lock the project's requirement row.
            $requirement = \Illuminate\Support\Facades\DB::table(
                'project_requirements'
            )
                ->where('project_id', $projectId)
                ->lockForUpdate()
                ->first();

            if (!$requirement) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project requirements were not found.',
                ], 404);
            }

            $requiredResources = (int) $requirement->required_resources;

            $allocatedCount = \Illuminate\Support\Facades\DB::table(
                'project_allocations'
            )
                ->where('project_id', $projectId)
                ->count();

            $remainingSlots = max(
                0,
                $requiredResources - $allocatedCount
            );

            if (count($employeeIds) > $remainingSlots) {
                return response()->json([
                    'success' => false,
                    'message' => "You can allocate only {$remainingSlots} more employee(s).",
                ], 422);
            }

            // Lock and verify all selected employees.
            $employees = \Illuminate\Support\Facades\DB::table('employees')
                ->whereIn('id', $employeeIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'status']);

            if ($employees->count() !== count($employeeIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'One or more employees were not found.',
                ], 422);
            }

            if ($employees->contains(
                fn ($employee) => (int) $employee->status !== 4
            )) {
                return response()->json([
                    'success' => false,
                    'message' => 'All selected employees must have status 4.',
                ], 422);
            }

            $alreadyAllocated = \Illuminate\Support\Facades\DB::table(
                'project_allocations'
            )
                ->where('project_id', $projectId)
                ->whereIn('employee_id', $employeeIds)
                ->exists();

            if ($alreadyAllocated) {
                return response()->json([
                    'success' => false,
                    'message' => 'One or more employees are already allocated to this project.',
                ], 422);
            }

            $now = now();
            $rows = [];

            foreach ($employeeIds as $employeeId) {
                $rows[] = [
                    'employee_id' => $employeeId,
                    'project_id' => $projectId,
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            \Illuminate\Support\Facades\DB::table(
                'project_allocations'
            )->insert($rows);

            \Illuminate\Support\Facades\DB::table('employees')
                ->whereIn('id', $employeeIds)
                ->update([
                    'status' => 5,
                    'updated_at' => $now,
                ]);

            return response()->json([
                'success' => true,
                'message' => count($employeeIds)
                    . ' employee(s) allocated successfully.',
                'allocated_count' => count($employeeIds),
            ], 201);
        }
    );
}

}
