<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmongusRecord extends Model
{
    // 一括代入できるカラムを指定する
    protected $fillable = [
        'archive_section_id',
        'regulation_change',
        'regulation_change_match_number',
        'is_completed',
        'note',
    ];

    public function matches()
    {
        // 1つの記録に複数試合が紐づく
        return $this->hasMany(AmongusMatch::class);
    }

    public function archiveSection()
    {
        // 記録の親である archive_section に紐づく
        return $this->belongsTo(ArchiveSection::class, 'archive_section_id');
    }

    public function regulations()
    {
        return $this->hasMany(AmongusRegulation::class, 'amongus_record_id');
    }

    public function regulationChanges()
    {
        return $this->hasMany(AmongusRegulationChange::class, 'amongus_record_id');
    }
    public function members()
{
    return $this->belongsToMany(
        Member::class,
        'amongus_record_members',
        'amongus_record_id',
        'member_id'
    )->withTimestamps();
}
}