<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Internship extends Model
{
    protected $fillable = ['location', 'period', 'certificate_issued'];

    protected function casts(): array
    {
        return ['certificate_issued' => 'boolean'];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(ScholarshipApplicant::class, 'scholarship_applicant_id');
    }
}
