<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
    'emp_code',
    'emp_name',
    'emp_email',
    'department_id',
    'designation_id',
    'location',
    'resource_status',
    'available_from',
    'bit_delete',
];
}
