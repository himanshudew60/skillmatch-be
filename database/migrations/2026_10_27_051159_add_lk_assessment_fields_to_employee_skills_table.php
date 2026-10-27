<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_skills', function (Blueprint $table) {
            $table->text('assessment_remarks')
                ->nullable()
                ->after('skill_validation_status');

            $table->unsignedBigInteger('assessed_by')
                ->nullable()
                ->after('assessment_remarks');

            $table->timestamp('assessed_at')
                ->nullable()
                ->after('assessed_by');
        });
    }

    public function down(): void
    {
        Schema::table('employee_skills', function (Blueprint $table) {
            $table->dropColumn([
                'assessment_remarks',
                'assessed_by',
                'assessed_at',
            ]);
        });
    }
};