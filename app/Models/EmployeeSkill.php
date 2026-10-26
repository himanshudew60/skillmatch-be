<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeSkill extends Model
{
    protected $fillable = [
    'employee_id',
    'skill_id',
    'skill_type',
    'proficiency',
    'experience',
    'assessment_score',
    'certification',
    'project_experience',
    'skill_validation_status',
    'bit_delete',
];
}
