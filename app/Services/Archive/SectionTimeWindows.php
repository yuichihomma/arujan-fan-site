<?php

namespace App\Services\Archive;

use Carbon\Carbon;

/**
 * 0次会〜4次会の想定時間帯（イベント当日0:00からの経過分）。
 * YouTube/Twitch/ツイキャスいずれの配信にも共通する、プラットフォームに依存しないルール。
 * ここに無いsection_type（special等）は時間帯では判定しない。
 */
class SectionTimeWindows
{
    public const WINDOWS_MINUTES = [
        'pre' => [16 * 60, 20 * 60 + 30],             // 16:00〜20:30
        'primary' => [21 * 60, 23 * 60 + 30],         // 21:00〜23:30
        'secondary' => [23 * 60 + 30, 25 * 60 + 30],  // 23:30〜翌01:30
        'third' => [25 * 60 + 30, 27 * 60 + 30],      // 翌01:30〜翌03:30
        'fourth' => [27 * 60 + 30, 32 * 60],          // 翌03:30〜翌08:00
    ];

    /**
     * 境界を数秒〜数分かすっただけの別配信を誤って候補にしないための最小重なり時間（分）。
     */
    public const MIN_OVERLAP_MINUTES = 10;

    /**
     * $eventDayStart（イベント当日0:00、イベントのタイムゾーン基準）を起点に、
     * $sectionTypeの時間帯と[$start, $end]が一定時間以上重なっているか。
     */
    public static function overlapsWindow(string $sectionType, Carbon $eventDayStart, Carbon $start, ?Carbon $end = null): bool
    {
        if (!isset(self::WINDOWS_MINUTES[$sectionType])) {
            return false;
        }

        $end ??= $start;
        [$startMinutes, $endMinutes] = self::WINDOWS_MINUTES[$sectionType];
        $windowStart = $eventDayStart->copy()->addMinutes($startMinutes);
        $windowEnd = $eventDayStart->copy()->addMinutes($endMinutes);

        $overlapStart = $start->max($windowStart);
        $overlapEnd = $end->min($windowEnd);

        return $overlapStart->diffInMinutes($overlapEnd, false) >= self::MIN_OVERLAP_MINUTES;
    }

    /**
     * $eventDayStartを起点に、[$start, $end]と一定時間以上重なる全section_typeを返す。
     *
     * @return string[]
     */
    public static function matchingSectionTypes(Carbon $eventDayStart, Carbon $start, ?Carbon $end = null): array
    {
        return collect(array_keys(self::WINDOWS_MINUTES))
            ->filter(fn (string $type) => self::overlapsWindow($type, $eventDayStart, $start, $end))
            ->values()
            ->all();
    }

    /**
     * $eventDayStartを起点に、$timeちょうどが属する1つのsection_typeを返す（範囲の重なりではなく点の判定）。
     * 配信が複数セクションの時間帯にまたがっていても、「開始時刻の時点でどの区分だったか」を
     * 1つだけ知りたい場合（ゲームジャンル判定など）に使う。
     */
    public static function windowContaining(Carbon $eventDayStart, Carbon $time): ?string
    {
        $types = array_keys(self::WINDOWS_MINUTES);

        foreach ($types as $index => $type) {
            [$startMinutes, $endMinutes] = self::WINDOWS_MINUTES[$type];
            $windowStart = $eventDayStart->copy()->addMinutes($startMinutes);
            $windowEnd = $eventDayStart->copy()->addMinutes($endMinutes);

            if (!$time->betweenIncluded($windowStart, $windowEnd)) {
                continue;
            }

            // 配信開始が数秒〜数分前倒しになっただけで、本来は次の区分のはずの配信が
            // 直前の区分に誤って分類されるのを防ぐため、
            // 次の区分の開始時刻の直前(MIN_OVERLAP_MINUTES以内)であれば次の区分を優先する。
            $nextType = $types[$index + 1] ?? null;

            if ($nextType !== null) {
                [$nextStartMinutes] = self::WINDOWS_MINUTES[$nextType];
                $graceStart = $eventDayStart->copy()->addMinutes($nextStartMinutes - self::MIN_OVERLAP_MINUTES);

                if ($time->greaterThanOrEqualTo($graceStart)) {
                    return $nextType;
                }
            }

            return $type;
        }

        return null;
    }
}
