<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_requirements', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('skill_id');

            $table->decimal('required_proficiency', 2, 1)->default(1.0);

            $table->integer('required_experience')->default(0);

            $table->integer('required_resources')->default(1);

            $table->string('priority')->default('Medium');

            $table->text('requirement_description')->nullable();

            $table->tinyInteger('bit_delete')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_requirements');
    }
};