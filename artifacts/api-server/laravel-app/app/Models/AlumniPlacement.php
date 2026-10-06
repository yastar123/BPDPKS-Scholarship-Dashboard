<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlumniPlacement extends Model
{
    protected $fillable = ['status', 'organization', 'placed_at'];

    protected function casts(): array
    {
        return ['placed_at' => 'date:Y-m-d'];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(ScholarshipApplicant::class, 'scholarship_applicant_id');
    }
}
