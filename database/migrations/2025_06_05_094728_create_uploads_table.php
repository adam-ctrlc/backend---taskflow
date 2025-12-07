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
        Schema::create('uploads', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique();
            $table->string('slug')->unique();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); 
            $table->foreignId('task_id')->nullable()->constrained('tasks')->onDelete('cascade'); // Nullable for general uploads
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uploads');
    }
};
