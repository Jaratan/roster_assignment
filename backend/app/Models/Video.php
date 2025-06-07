<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'my_work_id',
        'url'
    ];

    public function myWork()
    {
        return $this->belongsTo(MyWork::class);
    }
}
