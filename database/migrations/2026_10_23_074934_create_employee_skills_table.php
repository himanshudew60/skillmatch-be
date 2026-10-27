<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employee_skills', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('employee_id');
            $table->unsignedInteger('skill_id'); 
            
            $table->enum('skill_type', ['Primary','Secondary','Tertiary']);
            $table->decimal('proficiency',2,1)->comment('Proficiency level from 1 to 5');
            $table->unsignedSmallInteger('experience')->nullable()->comment('Experience in months');
           
            $table->string('certification', 255)->nullable();
            $table->unsignedSmallInteger('project_experience')->nullable()->comment('Experience in total months');
            $table->enum('skill_validation_status', ['Self','Manager','Assessment'])->nullable();
            $table->tinyInteger('bit_delete')->default(0);
            $table->timestamps();

            // Prevent duplicate skill for the same employee
            $table->unique(['employee_id', 'skill_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_skills');
    }
};
