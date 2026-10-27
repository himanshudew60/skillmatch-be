<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lk_skill_assessments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('employee_skill_id')->nullable();
            $table->unsignedBigInteger('skill_id');

            /*
            |--------------------------------------------------------------------------
            | Employee's current skill values
            |--------------------------------------------------------------------------
            | Read-only snapshot shown to L&K
            */

            $table->decimal('employee_proficiency', 2, 1)->nullable();
            $table->decimal('employee_score', 5, 2)->nullable();

            /*
            |--------------------------------------------------------------------------
            | L&K's assessment
            |--------------------------------------------------------------------------
            */

            $table->decimal('lk_proficiency', 2, 1)->nullable();
            $table->decimal('lk_score', 5, 2)->nullable();

            /*
            |--------------------------------------------------------------------------
            | L&K decision
            |--------------------------------------------------------------------------
            */

            $table->enum('decision', [
                'Pending',
                'Approved',
                'Upskill Required',
            ])->default('Pending');

            $table->text('feedback')->nullable();

            $table->unsignedBigInteger('assessed_by')->nullable();
            $table->timestamp('assessed_at')->nullable();

            $table->timestamps();

            $table->index('employee_id');
            $table->index('employee_skill_id');
            $table->index('skill_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lk_skill_assessments');
    }
};