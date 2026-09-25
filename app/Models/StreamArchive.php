<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StreamArchive extends Model
{
    protected $fillable = [
        'platform',
        'video_id',
        'member_id',
        'url',
        'title',
        'description',
        'started_at',
        'duration_seconds',
        'event_date',
        'estimated_section_type',
        'has_arujan_tag',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'event_date' => 'date',
        'has_arujan_tag' => 'boolean',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
