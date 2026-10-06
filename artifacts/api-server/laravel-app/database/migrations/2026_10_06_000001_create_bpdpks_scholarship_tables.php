<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 180)->unique();
            $table->string('province', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('study_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('degree', 10);
            $table->timestamps();
            $table->unique(['campus_id', 'name', 'degree']);
        });

        Schema::create('program_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_program_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('cohort');
            $table->unsignedInteger('quota');
            $table->timestamps();
            $table->unique(['study_program_id', 'cohort']);
        });

        Schema::create('budget_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_program_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('cohort');
            $table->decimal('amount', 16, 2);
            $table->timestamps();
            $table->unique(['study_program_id', 'cohort']);
        });

        Schema::create('scholarship_applicants', function (Blueprint $table) {
            $table->id();
            $table->string('registration_number', 30)->unique();
            $table->string('name', 150);
            $table->string('gender', 1);
            $table->date('birth_date');
            $table->string('school_name', 180);
            $table->string('school_type', 3);
            $table->string('province', 100);
            $table->string('regency', 100);
            $table->string('selection_path', 40);
            $table->string('selection_status', 30)->default('mendaftar');
            $table->foreignId('campus_id')->constrained()->restrictOnDelete();
            $table->foreignId('study_program_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('cohort');
            $table->decimal('latest_gpa', 3, 2)->nullable();
            $table->unsignedSmallInteger('active_semester')->nullable();
            $table->string('study_status', 20)->default('aktif');
            $table->date('graduation_date')->nullable();
            $table->unsignedSmallInteger('study_duration_months')->nullable();
            $table->timestamp('registered_at')->useCurrent();
            $table->timestamps();
            $table->index(['cohort', 'selection_status']);
            $table->index(['province', 'selection_path']);
        });

        Schema::create('academic_semester_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scholarship_applicant_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('semester');
            $table->decimal('ip', 3, 2);
            $table->timestamps();
            $table->unique(['scholarship_applicant_id', 'semester'], 'acad_sem_app_sem_uq');
        });

        Schema::create('semester_fundings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scholarship_applicant_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('semester');
            $table->string('funding_type', 30);
            $table->decimal('amount', 16, 2);
            $table->timestamps();
            $table->unique(['scholarship_applicant_id', 'semester', 'funding_type'], 'fund_app_sem_type_uq');
        });

        Schema::create('internships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scholarship_applicant_id')->constrained()->cascadeOnDelete();
            $table->string('location', 180);
            $table->string('period', 100);
            $table->boolean('certificate_issued')->default(false);
            $table->timestamps();
        });

        Schema::create('alumni_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scholarship_applicant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 30);
            $table->string('organization', 180)->nullable();
            $table->date('placed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumni_placements');
        Schema::dropIfExists('internships');
        Schema::dropIfExists('semester_fundings');
        Schema::dropIfExists('academic_semester_records');
        Schema::dropIfExists('scholarship_applicants');
        Schema::dropIfExists('budget_allocations');
        Schema::dropIfExists('program_quotas');
        Schema::dropIfExists('study_programs');
        Schema::dropIfExists('campuses');
    }
};
