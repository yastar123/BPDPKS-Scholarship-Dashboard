<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicSemesterRecord extends Model
{
    protected $fillable = ['semester', 'ip'];

    protected function casts(): array
    {
        return ['ip' => 'float'];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(ScholarshipApplicant::class, 'scholarship_applicant_id');
    }
}
