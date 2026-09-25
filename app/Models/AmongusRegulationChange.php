<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmongusRegulationChange extends Model
{
    use HasFactory;

    protected $fillable = [
        'amongus_record_id',
        'action',
        'role_id',
        'count',
    ];

    public function record()
    {
        return $this->belongsTo(AmongusRecord::class, 'amongus_record_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}