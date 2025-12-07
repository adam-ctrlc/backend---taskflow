<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $guarded = [];

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
}
