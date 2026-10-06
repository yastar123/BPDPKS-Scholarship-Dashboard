<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campus extends Model
{
    protected $fillable = ['name', 'province'];

    public function programs(): HasMany
    {
        return $this->hasMany(StudyProgram::class);
    }
}
