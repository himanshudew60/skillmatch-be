<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectAllocation extends Model
{
    protected $table = 'project_allocations';

    protected $fillable = [
        'employee_id',
        'project_id',
        'review_id',
        'allocated_by',
        'start_date',
        'end_date',
        'allocation_percent',
        'planned_working_days',
        'schedule',
        'status',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'allocation_percent' => 'integer',
        'planned_working_days' => 'integer',
    ];
}
