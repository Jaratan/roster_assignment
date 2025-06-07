<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    use HasFactory;

    protected $fillable = [
        'talent_profile_id',
        'name',
        'text'
    ];

    public function talentProfile()
    {
        return $this->belongsTo(TalentProfile::class);
    }
}
