<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SemesterFunding extends Model
{
    protected $fillable = ['semester', 'funding_type', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'float'];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(ScholarshipApplicant::class, 'scholarship_applicant_id');
    }
}
