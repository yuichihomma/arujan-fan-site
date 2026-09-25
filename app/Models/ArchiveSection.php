<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchiveSection extends Model
{
    protected $fillable = [
        'onedayarchive_id',
        'section_type',
        'game_genre',
        'section_title',
    ];

    // 親（1日アーカイブ）その日の配信のアーカイブ
    public function onedayarchive()
    {
        return $this->belongsTo(Onedayarchive::class);
    }

    // ストリーマー（多対多）
    public function members()
{
    return $this->belongsToMany(Member::class, 'archive_section_member')
        ->withPivot('video_url', 'needs_review', 'review_reason', 'no_stream', 'url_expired')
        ->withTimestamps();
}
}