<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Onedayarchive extends Model
{
    protected $fillable = [
        'event_date',
        'no_stream',
        'status',
        'title',
        'official_title',
        'official_video_url',
        'official_edited_title',
        'official_edited_video_url',
        'thumbnail_url',
        'description',
    ];

    public function sections()
    {
        return $this->hasMany(ArchiveSection::class);
    }

    /**
     * 特別回以外（0次会〜4次会）に実データ（参加メンバー）があるかどうか。
     * 特別回登録時に、通常アーカイブとして使われている日付を弾くために使う。
     */
    public function hasNonSpecialContent(): bool
    {
        return $this->sections()
            ->where('section_type', '!=', 'special')
            ->whereHas('members')
            ->exists();
    }

    /**
     * 特別回として登録済みかどうか。特別回が優先されるため、
     * 通常アーカイブ編集画面ではこれが真の日付を弾く。
     */
    public function hasSpecialSection(): bool
    {
        return $this->sections()
            ->where('section_type', 'special')
            ->whereHas('members')
            ->exists();
    }
}
