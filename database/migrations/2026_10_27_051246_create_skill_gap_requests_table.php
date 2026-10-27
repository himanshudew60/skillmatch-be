<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_gap_requests', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('employee_id');

            $table->unsignedBigInteger('project_requirement_id')->nullable();

            $table->unsignedBigInteger('skill_id');

            $table->decimal('current_proficiency', 2, 1)->nullable();

            $table->decimal('required_proficiency', 2, 1);

            $table->text('resource_manager_feedback')->nullable();

            $table->unsignedBigInteger('requested_by');

            $table->enum('status', [
                'Pending L&K',
                'Training',
                'Assessment Submitted',
                'Pending Resource Manager',
                'Approved',
                'Rejected',
                'Cancelled',
            ])->default('Pending L&K');

            $table->text('lk_remarks')->nullable();

            $table->unsignedBigInteger('assessed_by')->nullable();

            $table->timestamp('assessed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_gap_requests');
    }
};