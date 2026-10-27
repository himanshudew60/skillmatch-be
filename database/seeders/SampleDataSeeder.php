<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Departments
        |--------------------------------------------------------------------------
        */

        DB::table('departments')->insert([
            [
                'id' => 1,
                'name' => 'Information Technology',
                'description' => 'Software development and technology team.',
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => 'Human Resources',
                'description' => 'Human resources and employee management.',
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'name' => 'Finance',
                'description' => 'Finance and accounting department.',
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'name' => 'Operations',
                'description' => 'Business operations and resource management.',
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Designations
        |--------------------------------------------------------------------------
        */

        DB::table('designations')->insert([
            [
                'id' => 1,
                'name' => 'Software Engineer',
                'description' => 'Develops and maintains software applications.',
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => 'Senior Software Engineer',
                'description' => 'Senior-level software development role.',
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'name' => 'Tech Lead',
                'description' => 'Technical leadership and architecture.',
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'name' => 'Project Manager',
                'description' => 'Manages projects and project delivery.',
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 5,
                'name' => 'Business Analyst',
                'description' => 'Business analysis and requirement management.',
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Roles
        |--------------------------------------------------------------------------
        */

        DB::table('roles')->insert([
            [
                'id' => 1,
                'name' => 'System Administrator',
                'description' => 'Manages users, roles, settings and system configuration.',
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => 'L&K Administrator',
                'description' => 'Manages learning and knowledge and employee skill profiles.',
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'name' => 'Resource Manager',
                'description' => 'Validates employee skills and resource availability.',
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'name' => 'Project Manager',
                'description' => 'Creates projects and project requirements.',
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 5,
                'name' => 'Management',
                'description' => 'Reviews recommendations and makes final allocation decisions.',
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 6,
                'name' => 'Read Only Management',
                'description' => 'Can view dashboards and reports.',
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Skills
        |--------------------------------------------------------------------------
        */

        DB::table('skills')->insert([
            [
                'id' => 1,
                'name' => 'PHP',
                'category' => 'Programming',
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => 'Laravel',
                'category' => 'Backend',
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'name' => 'Angular',
                'category' => 'Frontend',
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'name' => 'MySQL',
                'category' => 'Database',
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 5,
                'name' => 'JavaScript',
                'category' => 'Programming',
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 6,
                'name' => 'TypeScript',
                'category' => 'Programming',
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 7,
                'name' => 'Node.js',
                'category' => 'Backend',
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 8,
                'name' => 'Docker',
                'category' => 'DevOps',
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 9,
                'name' => 'AWS',
                'category' => 'Cloud',
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 10,
                'name' => 'Git',
                'category' => 'DevOps',
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Employees
        |--------------------------------------------------------------------------
        |
        | All employees start as Available.
        | Resource allocation changes happen later in the workflow.
        |--------------------------------------------------------------------------
        */

        DB::table('employees')->insert([
            [
                'id' => 1,
                'emp_code' => 'EMP001',
                'emp_name' => 'Rahul Sharma',
                'emp_email' => 'rahul.sharma@example.com',
                'department_id' => 1,
                'designation_id' => 3,
                'location' => 'Ahmedabad',
                'resource_status' => 0,
                'available_from' => null,
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'emp_code' => 'EMP002',
                'emp_name' => 'Priya Patel',
                'emp_email' => 'priya.patel@example.com',
                'department_id' => 1,
                'designation_id' => 2,
                'location' => 'Ahmedabad',
                'resource_status' => 0,
                'available_from' => null,
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'emp_code' => 'EMP003',
                'emp_name' => 'Amit Shah',
                'emp_email' => 'amit.shah@example.com',
                'department_id' => 1,
                'designation_id' => 1,
                'location' => 'Ahmedabad',
                'resource_status' => 0,
                'available_from' => null,
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'emp_code' => 'EMP004',
                'emp_name' => 'Neha Mehta',
                'emp_email' => 'neha.mehta@example.com',
                'department_id' => 1,
                'designation_id' => 1,
                'location' => 'Pune',
                'resource_status' => 0,
                'available_from' => null,
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 5,
                'emp_code' => 'EMP005',
                'emp_name' => 'Vikram Joshi',
                'emp_email' => 'vikram.joshi@example.com',
                'department_id' => 1,
                'designation_id' => 2,
                'location' => 'Mumbai',
                'resource_status' => 0,
                'available_from' => null,
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 6,
                'emp_code' => 'EMP006',
                'emp_name' => 'Sneha Desai',
                'emp_email' => 'sneha.desai@example.com',
                'department_id' => 1,
                'designation_id' => 1,
                'location' => 'Ahmedabad',
                'resource_status' => 0,
                'available_from' => null,
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 7,
                'emp_code' => 'EMP007',
                'emp_name' => 'Arjun Mehta',
                'emp_email' => 'arjun.mehta@example.com',
                'department_id' => 4,
                'designation_id' => 4,
                'location' => 'Ahmedabad',
                'resource_status' => 0,
                'available_from' => null,
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 8,
                'emp_code' => 'EMP008',
                'emp_name' => 'Kavita Rao',
                'emp_email' => 'kavita.rao@example.com',
                'department_id' => 4,
                'designation_id' => 5,
                'location' => 'Bangalore',
                'resource_status' => 0,
                'available_from' => null,
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Employee Skills
        |--------------------------------------------------------------------------
        |
        | All skills start with Self validation.
        | L&K assessment is performed later.
        |--------------------------------------------------------------------------
        */
/*
|--------------------------------------------------------------------------
| Employee Skills
|--------------------------------------------------------------------------
|
| Rules:
| - Minimum 1 skill per employee
| - Maximum 3 skills per employee
| - Maximum 1 Primary skill
| - Maximum 1 Secondary skill
| - Maximum 1 Tertiary skill
| - All skills start with Self validation
| - No employee assessment score is stored here
|--------------------------------------------------------------------------
*/

DB::table('employee_skills')->insert([

    // Rahul - PHP / Laravel / Angular
    [
        'employee_id' => 1,
        'skill_id' => 1,
        'skill_type' => 'Primary',
        'proficiency' => 5.0,
        'experience' => 60,
        'certification' => 'PHP Certified Developer',
        'project_experience' => 54,
        'skill_validation_status' => 'Self',
        'bit_delete' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'employee_id' => 1,
        'skill_id' => 2,
        'skill_type' => 'Secondary',
        'proficiency' => 5.0,
        'experience' => 48,
        'certification' => 'Laravel Certification',
        'project_experience' => 42,
        'skill_validation_status' => 'Self',
        'bit_delete' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'employee_id' => 1,
        'skill_id' => 3,
        'skill_type' => 'Tertiary',
        'proficiency' => 4.0,
        'experience' => 36,
        'certification' => null,
        'project_experience' => 30,
        'skill_validation_status' => 'Self',
        'bit_delete' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ],

    // Priya - Angular / TypeScript / JavaScript
    [
        'employee_id' => 2,
        'skill_id' => 3,
        'skill_type' => 'Primary',
        'proficiency' => 5.0,
        'experience' => 54,
        'certification' => 'Angular Certification',
        'project_experience' => 48,
        'skill_validation_status' => 'Self',
        'bit_delete' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'employee_id' => 2,
        'skill_id' => 6,
        'skill_type' => 'Secondary',
        'proficiency' => 5.0,
        'experience' => 48,
        'certification' => null,
        'project_experience' => 42,
        'skill_validation_status' => 'Self',
        'bit_delete' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'employee_id' => 2,
        'skill_id' => 5,
        'skill_type' => 'Tertiary',
        'proficiency' => 4.0,
        'experience' => 48,
        'certification' => null,
        'project_experience' => 40,
        'skill_validation_status' => 'Self',
        'bit_delete' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ],

    // Amit - PHP / Angular / MySQL
    [
        'employee_id' => 3,
        'skill_id' => 1,
        'skill_type' => 'Primary',
        'proficiency' => 5.0,
        'experience' => 48,
        'certification' => null,
        'project_experience' => 42,
        'skill_validation_status' => 'Self',
        'bit_delete' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'employee_id' => 3,
        'skill_id' => 3,
        'skill_type' => 'Secondary',
        'proficiency' => 4.0,
        'experience' => 36,
        'certification' => null,
        'project_experience' => 30,
        'skill_validation_status' => 'Self',
        'bit_delete' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'employee_id' => 3,
        'skill_id' => 4,
        'skill_type' => 'Tertiary',
        'proficiency' => 4.0,
        'experience' => 42,
        'certification' => null,
        'project_experience' => 36,
        'skill_validation_status' => 'Self',
        'bit_delete' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ],

    // Neha - PHP / MySQL / Laravel
    [
        'employee_id' => 4,
        'skill_id' => 1,
        'skill_type' => 'Primary',
        'proficiency' => 5.0,
        'experience' => 72,
        'certification' => 'PHP Certified Developer',
        'project_experience' => 60,
        'skill_validation_status' => 'Self',
        'bit_delete' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'employee_id' => 4,
        'skill_id' => 4,
        'skill_type' => 'Secondary',
        'proficiency' => 5.0,
        'experience' => 60,
        'certification' => 'MySQL Certification',
        'project_experience' => 54,
        'skill_validation_status' => 'Self',
        'bit_delete' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'employee_id' => 4,
        'skill_id' => 2,
        'skill_type' => 'Tertiary',
        'proficiency' => 4.0,
        'experience' => 48,
        'certification' => null,
        'project_experience' => 42,
        'skill_validation_status' => 'Self',
        'bit_delete' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ],

    // Vikram - Laravel / PHP / MySQL
    [
        'employee_id' => 5,
        'skill_id' => 2,
        'skill_type' => 'Primary',
        'proficiency' => 5.0,
        'experience' => 66,
        'certification' => 'Laravel Certification',
        'project_experience' => 60,
        'skill_validation_status' => 'Self',
        'bit_delete' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'employee_id' => 5,
        'skill_id' => 1,
        'skill_type' => 'Secondary',
        'proficiency' => 4.0,
        'experience' => 60,
        'certification' => null,
        'project_experience' => 54,
        'skill_validation_status' => 'Self',
        'bit_delete' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'employee_id' => 5,
        'skill_id' => 4,
        'skill_type' => 'Tertiary',
        'proficiency' => 4.0,
        'experience' => 48,
        'certification' => null,
        'project_experience' => 42,
        'skill_validation_status' => 'Self',
        'bit_delete' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ],

    // Sneha - Angular / TypeScript / JavaScript
    [
        'employee_id' => 6,
        'skill_id' => 3,
        'skill_type' => 'Primary',
        'proficiency' => 4.0,
        'experience' => 42,
        'certification' => null,
        'project_experience' => 36,
        'skill_validation_status' => 'Self',
        'bit_delete' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'employee_id' => 6,
        'skill_id' => 6,
        'skill_type' => 'Secondary',
        'proficiency' => 4.0,
        'experience' => 36,
        'certification' => null,
        'project_experience' => 30,
        'skill_validation_status' => 'Self',
        'bit_delete' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'employee_id' => 6,
        'skill_id' => 5,
        'skill_type' => 'Tertiary',
        'proficiency' => 4.0,
        'experience' => 30,
        'certification' => null,
        'project_experience' => 24,
        'skill_validation_status' => 'Self',
        'bit_delete' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ],
]);
        /*
        |--------------------------------------------------------------------------
        | Projects
        |--------------------------------------------------------------------------
        */

        DB::table('projects')->insert([
            [
                'id' => 1,
                'project_code' => 'PRJ0001',
                'project_name' => 'IWWMS',
                'description' => 'Integrated Workforce and Work Management System.',
                'client_name' => 'Internal',
                'department_id' => 1,
                'project_manager_id' => 7,
                'start_date' => '2026-11-01',
                'end_date' => '2027-05-31',
                'status' => 'Active',
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'project_code' => 'PRJ0002',
                'project_name' => 'HR Portal',
                'description' => 'Employee HR and leave management portal.',
                'client_name' => 'Internal HR',
                'department_id' => 2,
                'project_manager_id' => 7,
                'start_date' => '2026-12-01',
                'end_date' => '2027-04-30',
                'status' => 'Draft',
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'project_code' => 'PRJ0003',
                'project_name' => 'Finance Dashboard',
                'description' => 'Finance analytics and reporting dashboard.',
                'client_name' => 'Internal Finance',
                'department_id' => 3,
                'project_manager_id' => 7,
                'start_date' => '2027-01-15',
                'end_date' => '2027-06-30',
                'status' => 'Draft',
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Project Requirements
        |--------------------------------------------------------------------------
        |
        | IWWMS:
        | 4 resources
        | PHP      = Primary   = 5
        | Angular  = Secondary = 4
        | MySQL    = Tertiary  = 4
        |--------------------------------------------------------------------------
        */

        DB::table('project_requirements')->insert([
            [
                'id' => 1,
                'project_id' => 1,
                'required_resources' => 4,

                'primary_skill_id' => 1,
                'primary_proficiency' => 5.0,

                'secondary_skill_id' => 3,
                'secondary_proficiency' => 4.0,

                'tertiary_skill_id' => 4,
                'tertiary_proficiency' => 4.0,

                'requirement_description' =>
                    'IWWMS requires four resources with strong PHP skills, Angular as a secondary skill and MySQL as a tertiary skill.',

                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'project_id' => 2,
                'required_resources' => 2,

                'primary_skill_id' => 3,
                'primary_proficiency' => 4.0,

                'secondary_skill_id' => 6,
                'secondary_proficiency' => 4.0,

                'tertiary_skill_id' => 4,
                'tertiary_proficiency' => 3.0,

                'requirement_description' =>
                    'HR Portal requires Angular developers with strong TypeScript and basic MySQL knowledge.',

                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'project_id' => 3,
                'required_resources' => 2,

                'primary_skill_id' => 4,
                'primary_proficiency' => 4.0,

                'secondary_skill_id' => 3,
                'secondary_proficiency' => 3.0,

                'tertiary_skill_id' => 5,
                'tertiary_proficiency' => 3.0,

                'requirement_description' =>
                    'Finance Dashboard requires MySQL as the primary skill with Angular and JavaScript as supporting skills.',

                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Users
        |--------------------------------------------------------------------------
        */

        DB::table('users')->insert([
            [
                'id' => 1,
                'employee_id' => 1,
                'user_id' => 'remp001',
                'password' => Hash::make('password123'),
                'role_id' => 1,
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'employee_id' => 2,
                'user_id' => 'pemp002',
                'password' => Hash::make('password123'),
                'role_id' => 2,
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'employee_id' => 3,
                'user_id' => 'aemp003',
                'password' => Hash::make('password123'),
                'role_id' => 3,
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'employee_id' => 7,
                'user_id' => 'aemp007',
                'password' => Hash::make('password123'),
                'role_id' => 4,
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 5,
                'employee_id' => 8,
                'user_id' => 'kemp008',
                'password' => Hash::make('password123'),
                'role_id' => 5,
                'bit_delete' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
