<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ScholarshipApplicant extends Model
{
    protected $fillable = [
        'registration_number', 'name', 'gender', 'birth_date', 'school_name',
        'school_type', 'province', 'regency', 'selection_path',
        'selection_status', 'campus_id', 'study_program_id', 'cohort',
        'latest_gpa', 'active_semester', 'study_status', 'graduation_date',
        'study_duration_months', 'registered_at',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date:Y-m-d',
            'registered_at' => 'datetime',
            'latest_gpa' => 'float',
            'graduation_date' => 'date:Y-m-d',
        ];
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class, 'study_program_id');
    }

    public function academicRecords(): HasMany
    {
        return $this->hasMany(AcademicSemesterRecord::class, 'scholarship_applicant_id');
    }

    public function fundingRecords(): HasMany
    {
        return $this->hasMany(SemesterFunding::class, 'scholarship_applicant_id');
    }

    public function internships(): HasMany
    {
        return $this->hasMany(Internship::class, 'scholarship_applicant_id');
    }

    public function placement(): HasOne
    {
        return $this->hasOne(AlumniPlacement::class, 'scholarship_applicant_id');
    }
}
