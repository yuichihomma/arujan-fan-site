<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmongusRegulation extends Model
{
    use HasFactory;

    protected $fillable = [
        'amongus_record_id',
        'phase',
        'crew_role_id',
        'crew_count',
        'impostor_role_id',
        'impostor_count',
        'neutral_role_id',
        'neutral_count',
    ];

    public function record()
    {
        return $this->belongsTo(AmongusRecord::class, 'amongus_record_id');
    }

    public function crewRole()
    {
        return $this->belongsTo(Role::class, 'crew_role_id');
    }

    public function impostorRole()
    {
        return $this->belongsTo(Role::class, 'impostor_role_id');
    }

    public function neutralRole()
    {
        return $this->belongsTo(Role::class, 'neutral_role_id');
    }
}