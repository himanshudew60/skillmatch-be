<?php

namespace App\Imports;

use App\Models\Employee;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class EmployeesImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {

            // Skip empty rows
            if (empty($row['emp_name']) && empty($row['emp_email'])) {
                continue;
            }

            // Generate next employee code
            $lastEmployee = Employee::orderBy('id', 'desc')->first();

            if ($lastEmployee) {

                $lastNumber = (int) str_replace(
                    'EMP',
                    '',
                    $lastEmployee->emp_code
                );

                $nextNumber = $lastNumber + 1;

            } else {

                $nextNumber = 1;
            }

            $empCode = 'EMP' . str_pad(
                $nextNumber,
                3,
                '0',
                STR_PAD_LEFT
            );

            Employee::create([
                'emp_code' => $empCode,

                'emp_name' => $row['emp_name'] ?? null,

                'emp_email' => $row['emp_email'] ?? null,

                'department_id' => $row['department_id'] ?? null,

                'designation_id' => $row['designation_id'] ?? null,

                'location' => $row['location'] ?? null,

                'resource_status' => $row['resource_status'] ?? 0,

                'available_from' => $row['available_from'] ?? null,

                'bit_delete' => 0,
            ]);
        }
    }
}