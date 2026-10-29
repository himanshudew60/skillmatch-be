
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_allocations', function (Blueprint $table) {
            $table->id();

            // IDs stored as numbers; no foreign keys
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('project_id');
            

            $table->date('start_date');
            $table->date('end_date');



       

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_allocations');
    }
};
