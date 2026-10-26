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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('emp_code')->unique();
            $table->string('emp_name');
            $table->string('emp_email');

            $table->unsignedInteger('department_id');
            $table->unsignedInteger('designation_id');
            $table->string('location');
            
            $table->tinyInteger('resource_status')->default(0)->comment('0 = Available, 1 = Allocated, 2 = Partially Available');
            $table->date('available_from')->nullable();

            $table->tinyInteger('bit_delete')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
