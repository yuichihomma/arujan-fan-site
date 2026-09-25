<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    protected $fillable = [
        'name',
        'avatar',
        'description',
        'youtube_url',
        'youtube_channel_id',
        'arujan_youtube_url',
        'arujan_youtube_channel_id',
        'x_url',
        'twitch_url',
        'other_platform',
        'other_url',
    ];

    /**
     * アーカイブ抽出で検索対象にするYouTubeチャンネルID一覧（優先順）。
     * アルジャン配信専用チャンネルが登録されている場合はそちらを最優先にし、
     * メインチャンネルは後回し（補完）にする。詩人さんのように、アルジャン配信を
     * メインではなくサブ（専用）チャンネルに上げるメンバーがいるため。
     *
     * @return string[]
     */
    public function youtubeChannelIds(): array
    {
        return collect([$this->arujan_youtube_channel_id, $this->youtube_channel_id])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    // ===============================
    // アーカイブ参加情報
    // ===============================
    public function archiveSections()
    {
        return $this->belongsToMany(ArchiveSection::class, 'archive_section_member')
            ->withPivot('video_url')
            ->withTimestamps();
    }
    // ===============================
    // AmongUs戦績
    // ===============================
    public function records()
    {
        return $this->belongsToMany(
            AmongusRecord::class,
            'amongus_record_members',
            'member_id',
            'amongus_record_id'
        );
    }

    // ===============================
    // 各試合結果
    // ===============================
    public function matchResults()
    {
        return $this->hasMany(AmongusMatchMemberResult::class);
    }

    public static function mainMemberNames()
    {
        return [
            'ハッチャン',
            'ポン酢野郎',
            'なつぴょん',
            '瀬戸あさひ',
            'みさとらん',
            'はてな',
            'バブルケーキ',
        ];
    }

    public static function subMemberNames()
    {
        return [
            'オシオン',
            '詩人さん',
            '倉持京子',
            'ちゃげぽよ。',
            'とっしん',
            '比良坂芽衣',
            '偽ペンギン',
            '柑橘めたる',
            '猫月みお',
            '町山マチカ',
            'NORISTRY',
        ];
    }

    public static function isMainMemberName(string $name): bool
    {
        return in_array($name, self::mainMemberNames(), true);
    }

    public static function isHacchanName(string $name): bool
    {
        return $name === 'ハッチャン';
    }

    /**
     * 1次会の本登録の必須条件: アルジャンのメインメンバーが2人以上いるかどうか。
     */
    public static function hasEnoughMainMembers(iterable $memberIds): bool
    {
        return self::query()
            ->whereIn('id', $memberIds)
            ->get()
            ->filter(fn (self $member) => self::isMainMemberName($member->name))
            ->count() >= 2;
    }

    /**
     * 0次会・2次会以降の本登録の必須条件: ハッチャンがいるか、
     * ハッチャンを除いたメンバーが2人以上いるかどうか。
     */
    public static function hasEnoughMembersOrHacchan(iterable $memberIds): bool
    {
        $members = self::query()->whereIn('id', $memberIds)->get();

        if ($members->contains(fn (self $member) => self::isHacchanName($member->name))) {
            return true;
        }

        return $members->count() >= 2;
    }
}
