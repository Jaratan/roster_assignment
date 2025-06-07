<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TalentProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'username',
        'name',
        'description'
    ];

    public function trustedClients()
    {
        return $this->hasMany(TrustedClient::class);
    }

    public function myWorks()
    {
        return $this->hasMany(MyWork::class);
    }

    public function testimonials()
    {
        return $this->hasMany(Testimonial::class);
    }

    public function expertises()
    {
        return $this->hasMany(Expertise::class);
    }
}
