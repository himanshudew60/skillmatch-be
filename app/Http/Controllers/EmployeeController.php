<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Skill;
use App\Models\Employee;
use App\Models\EmployeeSkill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Imports\EmployeesImport;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeController extends Controller
{

    // =========================================================
    // Get Departments
    // =========================================================

    public function getDepartments()
    {
        $departments = Department::where('bit_delete', 0)
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Departments fetched successfully.',
            'data' => $departments
        ], 200);
    }


    // =========================================================
    // Get Designations
    // =========================================================

    public function getDesignations()
    {
        $designations = Designation::where('bit_delete', 0)
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Designations fetched successfully.',
            'data' => $designations
        ], 200);
    }


    // =========================================================
    // Get Skills
    // =========================================================

    public function getSkills()
    {
        $skills = Skill::where('bit_delete', 0)
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Skills fetched successfully.',
            'data' => $skills
        ], 200);
    }


    // =========================================================
    // Get Employees
    // =========================================================

    public function index()
    {
        $employees = DB::table('employees as e')
        ->leftJoin('departments as d', 'e.department_id', '=', 'd.id')
        ->leftJoin('designations as des', 'e.designation_id', '=', 'des.id')
        ->where('e.bit_delete', 0)
        ->orderBy('e.id', 'desc')
        ->select([
            'e.id',
            'e.emp_code',
            'e.emp_name',
            'e.emp_email',
            'd.name as department_name',
            'des.name as designation_name',
            'e.location',
            'e.resource_status',
            'e.available_from',
        ])
        ->get();

        return response()->json([
            'success' => true,
            'message' => 'Employees fetched successfully.',
            'data' => $employees
        ], 200);
    }


    // =========================================================
    // Create Employee
    // =========================================================
 public function import(Request $request)
{
    $request->validate([
        'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
    ]);

    try {

        Excel::import(
            new EmployeesImport(),
            $request->file('file')
        );

        return response()->json([
            'success' => true,
            'message' => 'Employees imported successfully.',
        ], 200);

    } catch (\Throwable $e) {

        return response()->json([
            'success' => false,
            'message' => 'Failed to import employees.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

public function store(Request $request)
{
    $validated = $request->validate([
        'emp_name' => 'required|string|max:255',
        'emp_email' => 'required|email|max:255',
        'department_id' => 'required|exists:departments,id',
        'designation_id' => 'required|exists:designations,id',
        'location' => 'required|string|max:255',
        'resource_status' => 'required|integer|in:0,1,2',
        'available_from' => 'nullable|date',

        'skills' => 'nullable|array',
        'skills.*.skill_id' => 'required|exists:skills,id',
        'skills.*.skill_type' => 'required|in:Primary,Secondary,Tertiary',
        'skills.*.proficiency' => 'nullable|numeric|min:1|max:5',
        'skills.*.experience_months' => 'nullable|integer|min:0',
        'skills.*.certification' => 'nullable|string|max:255',
        'skills.*.project_experience_months' => 'nullable|integer|min:0',
        'skills.*.skill_validation_status' => 'required|in:Self,Manager,Assessment',
    ]);

    DB::beginTransaction();

    try {

        // Get last employee
        $lastEmployee = Employee::orderBy('id', 'desc')->first();

        if ($lastEmployee) {
            $lastNumber = (int) str_replace('EMP', '', $lastEmployee->emp_code);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        // Generate EMP001, EMP002, etc.
        $empCode = 'EMP' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

        $employee = Employee::create([
            'emp_code' => $empCode,
            'emp_name' => $validated['emp_name'],
            'emp_email' => $validated['emp_email'],
            'department_id' => $validated['department_id'],
            'designation_id' => $validated['designation_id'],
            'location' => $validated['location'],
            'resource_status' => $validated['resource_status'],
            'available_from' => $validated['available_from'] ?? null,
            'bit_delete' => 0,
        ]);

        // Save employee skills
        if (!empty($validated['skills'])) {
            foreach ($validated['skills'] as $skill) {
                EmployeeSkill::create([
                    'employee_id' => $employee->id,
                    'skill_id' => $skill['skill_id'],
                    'skill_type' => $skill['skill_type'],
                    'proficiency' => $skill['proficiency'] ?? null,
                    'experience_months' => $skill['experience_months'] ?? null,
                    'certification' => $skill['certification'] ?? null,
                    'project_experience_months' => $skill['project_experience_months'] ?? null,
                    'skill_validation_status' => 'Self',
                    'bit_delete' => 0,
                ]);
            }
        }

        DB::commit();

       return response()->json([
    'success' => true,
    'message' => 'Employee created successfully.',
    'data' => $employee
], 201);

    } catch (\Exception $e) {

        DB::rollBack();

        return response()->json([
            'success' => false,
            'message' => 'Failed to create employee.',
            'error' => $e->getMessage()
        ], 500);
    }
}


    // =========================================================
    // Get Single Employee
    // =========================================================

   public function show($id)
{
    $employee = DB::table('employees as e')
        ->leftJoin('departments as d', 'e.department_id', '=', 'd.id')
        ->leftJoin('designations as des', 'e.designation_id', '=', 'des.id')
        ->where('e.id', $id)
        ->where('e.bit_delete', 0)
        ->select([
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
            'e.available_from',
        ])
        ->first();

    if (!$employee) {
        return response()->json([
            'success' => false,
            'message' => 'Employee not found.',
        ], 404);
    }

    $skills = DB::table('employee_skills as es')
        ->leftJoin('skills as s', 'es.skill_id', '=', 's.id')
        ->where('es.employee_id', $id)
        ->where('es.bit_delete', 0)
        ->where(function ($query) {
            $query->where('s.bit_delete', 0)
                  ->orWhereNull('s.id');
        })
        ->select([
            'es.id',
            'es.skill_id',
            's.name as skill_name',
            's.category as skill_category',
            'es.skill_type',
            'es.proficiency',
            'es.experience',
           
            'es.certification',
            'es.project_experience',
            'es.skill_validation_status',
        ])
        ->get();

    return response()->json([
        'success' => true,
        'message' => 'Employee fetched successfully.',
        'data' => [
            'employee' => $employee,
            'skills' => $skills,
        ],
    ], 200);
}

    // =========================================================
    // Update Employee
    // =========================================================

    

public function update(Request $request, $id)
{
    $employee = Employee::where('bit_delete', 0)->find($id);

    if (!$employee) {
        return response()->json([
            'success' => false,
            'message' => 'Employee not found.',
        ], 404);
    }

    $validated = $request->validate([
        'emp_name' => 'required|string|max:255',
        'emp_email' => 'required|email|max:255',
        'department_id' => 'required|exists:departments,id',
        'designation_id' => 'required|exists:designations,id',
        'location' => 'required|string|max:255',
        'resource_status' => 'required|integer|in:0,1,2',
        'available_from' => 'nullable|date',

        'skills' => 'nullable|array',
        'skills.*.skill_id' => 'required|integer|distinct|exists:skills,id',
        'skills.*.skill_type' => 'required|in:Primary,Secondary,Tertiary',
        'skills.*.proficiency' => 'nullable|numeric|min:1|max:5',
        'skills.*.experience_months' => 'nullable|integer|min:0',

        'skills.*.certification' => 'nullable|string|max:255',
        'skills.*.project_experience_months' => 'nullable|integer|min:0',
        'skills.*.skill_validation_status' => 'required|in:Self,Manager,Assessment',
    ]);

    DB::beginTransaction();

    try {
        $employee->update([
            'emp_name' => $validated['emp_name'],
            'emp_email' => $validated['emp_email'],
            'department_id' => $validated['department_id'],
            'designation_id' => $validated['designation_id'],
            'location' => $validated['location'],
            'resource_status' => $validated['resource_status'],
            'available_from' => $validated['available_from'] ?? null,
        ]);

        $skills = $validated['skills'] ?? [];

        $skillIds = array_map(
            fn ($skill) => (int) $skill['skill_id'],
            $skills
        );

        // Soft-delete skills removed from the form.
        $removedSkills = EmployeeSkill::where('employee_id', $employee->id)
            ->where('bit_delete', 0);

        if (!empty($skillIds)) {
            $removedSkills->whereNotIn('skill_id', $skillIds);
        }

        $removedSkills->update(['bit_delete' => 1]);

        foreach ($skills as $skill) {
            // Find existing row, even if it was soft-deleted.
            $existingSkill = EmployeeSkill::where(
                'employee_id', $employee->id
            )->where(
                'skill_id', $skill['skill_id']
            )->first();

            $skillData = [
                'skill_type' => $skill['skill_type'],
                'proficiency' => $skill['proficiency'] ?? null,
                'experience' => $skill['experience_months'] ?? null,
                'certification' => $skill['certification'] ?? null,
                'project_experience' =>
                    $skill['project_experience_months'] ?? null,
                'skill_validation_status' =>
                    $skill['skill_validation_status'],
                'bit_delete' => 0,
            ];

            // Keep this only if the database has this column.
    

            if ($existingSkill) {
                $existingSkill->update($skillData);
            } else {
                EmployeeSkill::create(array_merge($skillData, [
                    'employee_id' => $employee->id,
                    'skill_id' => $skill['skill_id'],
                ]));
            }
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Employee updated successfully.',
        ], 200);

    } catch (\Throwable $e) {
        DB::rollBack();

        report($e);

        return response()->json([
            'success' => false,
            'message' => 'Failed to update employee.',
            'error' => $e->getMessage(),
        ], 500);
    }
}




    // =========================================================
    // Delete Employee
    // =========================================================

    public function destroy($id)
    {
        $employee = Employee::where(
            'bit_delete',
            0
        )->find($id);


        if (!$employee) {

            return response()->json([

                'success' => false,

                'message' =>
                    'Employee not found.'

            ], 404);

        }


        // Soft delete according to your structure
        $employee->update([
            'bit_delete' => 1
        ]);


        return response()->json([

            'success' => true,

            'message' =>
                'Employee deleted successfully.'

        ], 200);
    }

}