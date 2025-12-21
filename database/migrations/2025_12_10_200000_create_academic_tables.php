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
        // Semesters
        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Courses
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('instructor')->nullable();
            $table->unsignedTinyInteger('credit_hours')->nullable();
            $table->string('room')->nullable();
            $table->string('color_tag', 32)->nullable();
            $table->timestamps();
        });

        // Assessments
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type'); // quiz, midterm, final, assignment, project
            $table->decimal('grade_received', 8, 2)->nullable();
            $table->decimal('grade_max', 8, 2)->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('weight_percentage', 5, 2)->nullable();
            $table->timestamps();
        });

        // Notes
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('content')->nullable(); // markdown
            $table->unsignedSmallInteger('week_number')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();
        });

        // Events (calendar)
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime')->nullable();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('google_event_id')->nullable();
            $table->timestamps();
        });

        // Transactions (budget / expenses)
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // income / expense
            $table->string('category')->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('date');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        // Goals
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('target_date')->nullable();
            $table->boolean('completed')->default(false);
            $table->timestamps();
        });

        // Tasks
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->boolean('completed')->default(false);
            $table->timestamps();
        });

        // Habits
        Schema::create('habits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('frequency_type'); // daily/weekly/monthly
            $table->unsignedSmallInteger('target_count')->nullable();
            $table->timestamps();
        });

        // Habit logs
        Schema::create('habit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('habit_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedSmallInteger('count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('habit_logs');
        Schema::dropIfExists('habits');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('goals');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('events');
        Schema::dropIfExists('notes');
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('semesters');
    }
};

