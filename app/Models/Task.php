<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Task extends Model
{
    protected $guarded = [];

    protected $appends = ['submission_display'];

    protected $hidden = ['submission_base64'];

    // public function team()
    // {
    //     return $this->belongsTo(Team::class);
    // }

    public function users()
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    protected function submissionDisplay(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => 
                $attributes['submission_filename'] ?? $attributes['submission'] ?? null
        );
    }
}
