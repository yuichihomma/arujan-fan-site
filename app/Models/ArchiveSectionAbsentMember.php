<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 「参加者として名前は挙がったが、急遽不参加になったことが確認済み」のメンバーを記録する。
 * 他メンバーの動画の概要欄に名前が残っている限り、参加者抽出は毎回この人を検出してしまうため、
 * 単純にセクションから削除するだけでは再抽出のたびに復活してしまう。ここに記録しておくことで、
 * 該当の日付・区分については以後の抽出結果から除外する。
 */
class ArchiveSectionAbsentMember extends Model
{
    protected $fillable = [
        'event_date',
        'section_type',
        'member_id',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
