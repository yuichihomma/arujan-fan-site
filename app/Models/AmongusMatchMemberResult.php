<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmongusMatchMemberResult extends Model
{
    // 一括代入できるカラムを指定する
    protected $fillable = [
        'amongus_match_id',
        'member_id',
        'role_id',
        'result',
    ];

    public function match()
    {
        // この結果が属する試合に紐づく
        return $this->belongsTo(AmongusMatch::class, 'amongus_match_id');
    }

    public function member()
    {
        // この結果の対象メンバーに紐づく
        return $this->belongsTo(Member::class);
    }

    public function role()
    {
        // この結果の役職に紐づく
        return $this->belongsTo(Role::class);
    }
}