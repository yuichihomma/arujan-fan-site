<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmongusRecordMember extends Model
{
    protected $fillable = [
        'amongus_record_id',
        'member_id',
    ];

    public function record()
    {
        return $this->belongsTo(AmongusRecord::class, 'amongus_record_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }
}