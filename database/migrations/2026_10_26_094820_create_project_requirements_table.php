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

    $table->integer('required_resources')->default(1);

    $table->unsignedBigInteger('primary_skill_id')->nullable();
    $table->decimal('primary_proficiency', 2, 1)->nullable();

    $table->unsignedBigInteger('secondary_skill_id')->nullable();
    $table->decimal('secondary_proficiency', 2, 1)->nullable();

    $table->unsignedBigInteger('tertiary_skill_id')->nullable();
    $table->decimal('tertiary_proficiency', 2, 1)->nullable();

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