<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmongusMatch extends Model
{
    // 一括代入できるカラムを指定する
    protected $fillable = [
        'amongus_record_id',
        'match_number',
        'win_side',
        'memo',
        ];

    public function record()
    {
        // 試合が属する親記録に紐づく
        return $this->belongsTo(AmongusRecord::class, 'amongus_record_id');
    }

    public function memberResults()
    {
         // 1試合に複数メンバー結果が紐づく
        return $this->hasMany(AmongusMatchMemberResult::class, 'amongus_match_id');
    }
}