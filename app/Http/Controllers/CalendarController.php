<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Onedayarchive;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));

        $currentMonth = Carbon::parse($month . '-01')->startOfMonth();
        $startOfMonth = $currentMonth->copy()->startOfMonth();
        $endOfMonth = $currentMonth->copy()->endOfMonth();

        $selectedDate = $request->input('date');

        $streamsByDate = Onedayarchive::with(['sections.members'])
            ->where('no_stream', false)
            ->whereBetween('event_date', [
                $startOfMonth->format('Y-m-d'),
                $endOfMonth->format('Y-m-d'),
            ])
            ->orderBy('event_date')
            ->get()
            ->groupBy(function ($stream) {
                return Carbon::parse($stream->event_date)->format('Y-m-d');
            });

        $period = CarbonPeriod::create($startOfMonth, $endOfMonth);
        $calendarDays = array_fill(0, $startOfMonth->dayOfWeek, null);

        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            $streams = $streamsByDate->get($dateString, collect());

            $hasAmongUs = $streams->contains(function ($stream) {
                return $stream->sections->contains(function ($section) {
                    return strtolower($section->game_genre ?? '') === 'among us';
                });
            });

            $calendarDays[] = [
                'day' => $date->day,
                'date' => $dateString,
                'isToday' => $dateString === now()->format('Y-m-d'),
                'isSunday' => $date->dayOfWeek === Carbon::SUNDAY,
                'isSaturday' => $date->dayOfWeek === Carbon::SATURDAY,
                'streams' => $streams,
                'hasAmongUs' => $hasAmongUs,
            ];
        }

        return view('calendar.index', compact(
            'month',
            'currentMonth',
            'calendarDays',
            'selectedDate'
        ));
    }

    public function show($date)
    {
        $selectedDate = Carbon::parse($date)->format('Y-m-d');

        $streams = Onedayarchive::with(['sections.members'])
            ->where('no_stream', false)
            ->whereDate('event_date', $selectedDate)
            ->orderBy('event_date')
            ->get();

        return view('calendar.show', compact('selectedDate', 'streams'));
    }
}
