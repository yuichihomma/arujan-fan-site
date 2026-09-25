<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Onedayarchive;
use App\Models\ArchiveSection;
use App\Models\AmongusRecord;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $currentMonth = $request->filled('month') && preg_match('/^\d{4}-\d{2}$/', $request->month)
            ? Carbon::createFromFormat('Y-m', $request->month)->startOfMonth()
            : Carbon::now()->startOfMonth();

        $startOfCalendar = $currentMonth->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $endOfCalendar = $currentMonth->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $archives = Onedayarchive::with('sections')
            ->whereBetween('event_date', [
                $startOfCalendar->format('Y-m-d'),
                $endOfCalendar->format('Y-m-d'),
            ])
            ->get()
            ->keyBy(function ($archive) {
                return Carbon::parse($archive->event_date)->format('Y-m-d');
            });

        // Among Us セクションをまとめて取得
        $sections = ArchiveSection::whereIn('onedayarchive_id', $archives->pluck('id'))
            ->where('game_genre', 'among us')
            ->get()
            ->keyBy('onedayarchive_id');

        // Among Us レコードを matches 付きで取得
        $records = AmongusRecord::with('matches')
            ->whereIn('archive_section_id', $sections->pluck('id'))
            ->get()
            ->keyBy('archive_section_id');

        // 日付ごとの状態
        $dayStatuses = [];

        $currentDate = $startOfCalendar->copy();

        while ($currentDate->lte($endOfCalendar)) {
            $dateString = $currentDate->format('Y-m-d');

            $archive = $archives->get($dateString);

            // 元データなし
            if (!$archive) {
                $dayStatuses[$dateString] = [
                    'label' => '編集できません',
                    'class' => 'disabled',
                ];
                $currentDate->addDay();
                continue;
            }

            // among us セクションなし
            $section = $sections->get($archive->id);

            if (!$section) {
                $dayStatuses[$dateString] = [
                    'label' => '編集できません',
                    'class' => 'disabled',
                ];
                $currentDate->addDay();
                continue;
            }

            $record = $records->get($section->id);

            // recordなし or matchesなし
            if (!$record || $record->matches->isEmpty()) {
                $dayStatuses[$dateString] = [
                    'label' => '未登録',
                    'class' => 'empty',
                ];
                $currentDate->addDay();
                continue;
            }

            // 完了済み / 作成中
            if ($record->is_completed) {
                $dayStatuses[$dateString] = [
                    'label' => '登録完了',
                    'class' => 'completed',
                ];
            } else {
                $dayStatuses[$dateString] = [
                    'label' => '作成中',
                    'class' => 'draft',
                ];
            }

            $currentDate->addDay();
        }

        $period = CarbonPeriod::create($startOfCalendar, $endOfCalendar);

        $calendarWeeks = [];
        $week = [];

        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            $archive = $archives->get($dateString);

            $week[] = [
                'day' => $date->day,
                'date' => $dateString,
                'isCurrentMonth' => $date->month === $currentMonth->month,
                'isSunday' => $date->dayOfWeek === Carbon::SUNDAY,
                'isSaturday' => $date->dayOfWeek === Carbon::SATURDAY,
                'archive' => $archive,
                'hasAmongUs' => $archive
                    ? $archive->sections->contains('game_genre', 'among us')
                    : false,
            ];

            if (count($week) === 7) {
                $calendarWeeks[] = $week;
                $week = [];
            }
        }

        return view('admin.stats.amongus.calendar', [
            'currentMonth' => $currentMonth,
            'calendarWeeks' => $calendarWeeks,
            'prevYear' => $currentMonth->copy()->subYear()->format('Y-m'),
            'prevMonth' => $currentMonth->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $currentMonth->copy()->addMonth()->format('Y-m'),
            'nextYear' => $currentMonth->copy()->addYear()->format('Y-m'),
            'dayStatuses' => $dayStatuses,
        ]);
    }
}
