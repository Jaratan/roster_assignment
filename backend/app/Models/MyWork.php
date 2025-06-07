<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MyWork extends Model
{
    use HasFactory;

    protected $fillable = [
        'talent_profile_id',
        'title'
    ];

    public function talentProfile()
    {
        return $this->belongsTo(TalentProfile::class);
    }

    public function videos()
    {
        return $this->hasMany(Video::class);
    }
}
