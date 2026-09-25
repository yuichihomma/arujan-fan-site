<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmongusAnalysisDraft extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'archive_section_id',
        'member_id',
        'video_url',
        'match_number',
        'video_timestamp_seconds',
        'video_timestamp_label',
        'estimated_real_time',
        'member_name',
        'role_name',
        'result',
        'win_side',
        'evidence_text',
        'confidence',
        'status',
        'memo',
        'raw_payload',
    ];

    protected $casts = [
        'estimated_real_time' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function archiveSection()
    {
        return $this->belongsTo(ArchiveSection::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function timestampUrl(): string
    {
        if ($this->video_timestamp_seconds === null) {
            return $this->video_url;
        }

        $separator = str_contains($this->video_url, '?') ? '&' : '?';

        return "{$this->video_url}{$separator}t={$this->video_timestamp_seconds}";
    }
}
