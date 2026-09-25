<?php

namespace App\Http\Controllers\Stats;

use App\Http\Controllers\Controller;
use App\Models\ArchiveSection;
use App\Models\AmongusMatch;
use App\Models\AmongusMatchMemberResult;
use App\Models\AmongusRecord;
use App\Models\Onedayarchive;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AmongusStatsController extends Controller
{
    public function index(Request $request)
    {
        $sort = $request->input('sort', 'desc');
        $searchDate = $request->input('search_date');

        if (!$searchDate) {
            $latestRecord = AmongusRecord::whereHas('archiveSection.onedayarchive')
                ->with('archiveSection.onedayarchive')
                ->get()
                ->sortByDesc(function ($record) {
                    return $record->archiveSection->onedayarchive->event_date;
                })
                ->first();

            $searchDate = $latestRecord
                ? $latestRecord->archiveSection->onedayarchive->event_date
                : null;
        }

        $periodLabel = $searchDate
            ? Carbon::parse($searchDate)->format('Y/m/d')
            : '全期間';

        $query = AmongusMatchMemberResult::with(['member', 'match.record']);

        if ($searchDate) {
            $query->whereHas('match.record.archiveSection.onedayarchive', function ($q) use ($searchDate) {
                $q->whereDate('event_date', $searchDate);
            });
        }

        $results = $query->get();

        $stats = $results
            ->groupBy('member_id')
            ->map(function ($records) {
                $win = $records->where('result', 'win')->count();
                $lose = $records->where('result', 'lose')->count();
                $total = $win + $lose;

                return [
                    'name' => $records->first()->member->name,
                    'win' => $win,
                    'lose' => $lose,
                    'rate' => $total > 0 ? round($win / $total * 100, 1) : 0,
                ];
            })
            ->sortByDesc('win')
            ->sortByDesc('rate')
            ->values();

        $rank = 0;
        $prev = null;

        $stats = $stats->map(function ($stat, $index) use (&$rank, &$prev) {
            if (
                $prev &&
                $stat['win'] === $prev['win'] &&
                $stat['rate'] === $prev['rate']
            ) {
                $stat['rank'] = $rank;
            } else {
                $rank = $index + 1;
                $stat['rank'] = $rank;
            }

            $prev = $stat;

            return $stat;
        });

        $record = null;

        if ($searchDate) {
            $onedayarchive = Onedayarchive::where('event_date', $searchDate)->first();

            if ($onedayarchive) {
                $section = ArchiveSection::where('onedayarchive_id', $onedayarchive->id)
                    ->where('game_genre', 'among us')
                    ->first();

                if ($section) {
                    $record = AmongusRecord::where('archive_section_id', $section->id)->first();
                }
            }
        }

        $matchDetails = $record
            ? AmongusMatch::with(['memberResults.member', 'memberResults.role'])
                ->where('amongus_record_id', $record->id)
                ->orderBy('match_number')
                ->get()
            : collect();

        return view('stats.amongus.daily', compact('sort', 'periodLabel', 'stats', 'searchDate', 'matchDetails'));
    }

    public function total(Request $request)
    {
        $sortKey = $request->input('sort_key', 'total_boardings');
        $sortOrder = $request->input('sort_order', 'desc');

        $periodType = $request->input('period_type', 'all');
        $year = $request->input('year');
        $month = $request->input('month');

        $periodLabel = '通算';
        $eventDates = $this->completedAmongusEventDates();

        $availableYears = $eventDates
            ->map(fn ($date) => Carbon::parse($date)->format('Y'))
            ->unique()
            ->sortDesc()
            ->values();

        $availableMonths = $eventDates
            ->map(fn ($date) => Carbon::parse($date)->format('Y-m'))
            ->unique()
            ->sortDesc()
            ->values();

        $query = AmongusMatchMemberResult::with([
            'member',
            'role',
            'match.record.archiveSection.onedayarchive',
        ])
            ->whereHas('match.record', function ($q) {
                $q->where('is_completed', true);
            })
            ->whereHas('match.record.archiveSection', function ($q) {
                $q->whereRaw('LOWER(game_genre) = ?', ['among us']);
            });

        if ($periodType === 'year' && $year) {
            $query->whereHas('match.record.archiveSection.onedayarchive', function ($q) use ($year) {
                $q->whereYear('event_date', $year);
            });

            $periodLabel = $year . '年';
        }

        if ($periodType === 'month' && $month) {
            $query->whereHas('match.record.archiveSection.onedayarchive', function ($q) use ($month) {
                $q->whereYear('event_date', substr($month, 0, 4))
                    ->whereMonth('event_date', substr($month, 5, 2));
            });

            $periodLabel = $month;
        }

        $results = $query->get();

        $stats = $results
            ->groupBy('member_id')
            ->map(function ($records) {
                $totalMatches = $records->count();

                $totalBoardings = $records
                    ->map(fn ($record) => $record->match?->record?->archiveSection?->onedayarchive?->event_date)
                    ->filter()
                    ->unique()
                    ->count();

                $totalWins = $records->where('result', 'win')->count();
                $totalLosses = $records->where('result', 'lose')->count();

                $crewRecords = $records->filter(fn ($record) => $record->role?->type === 'crew');
                $impostorRecords = $records->filter(fn ($record) => $record->role?->type === 'impostor');
                $thirdRecords = $records->filter(fn ($record) => $record->role?->type === 'neutral');

                $crewWins = $crewRecords->where('result', 'win')->count();
                $crewLosses = $crewRecords->where('result', 'lose')->count();

                $impostorWins = $impostorRecords->where('result', 'win')->count();
                $impostorLosses = $impostorRecords->where('result', 'lose')->count();

                $thirdWins = $thirdRecords->where('result', 'win')->count();
                $thirdLosses = $thirdRecords->where('result', 'lose')->count();

                $calcRate = fn ($wins, $total) => $total > 0
                    ? round(($wins / $total) * 100, 1)
                    : 0;

                return [
                    'member_name' => $records->first()->member->name ?? '-',
                    'total_boardings' => $totalBoardings,
                    'total_matches' => $totalMatches,
                    'total_wins' => $totalWins,
                    'total_losses' => $totalLosses,
                    'total_win_rate' => $calcRate($totalWins, $totalMatches),
                    'crew_wins' => $crewWins,
                    'crew_losses' => $crewLosses,
                    'crew_win_rate' => $calcRate($crewWins, $crewRecords->count()),
                    'impostor_wins' => $impostorWins,
                    'impostor_losses' => $impostorLosses,
                    'impostor_win_rate' => $calcRate($impostorWins, $impostorRecords->count()),
                    'third_wins' => $thirdWins,
                    'third_losses' => $thirdLosses,
                    'third_win_rate' => $calcRate($thirdWins, $thirdRecords->count()),
                ];
            });

        $stats = $sortOrder === 'asc'
            ? $stats->sortBy($sortKey)->values()
            : $stats->sortByDesc($sortKey)->values();

        return view('stats.amongus.total', compact(
            'stats',
            'availableYears',
            'availableMonths',
            'sortKey',
            'sortOrder',
            'periodLabel'
        ));
    }

    private function completedAmongusEventDates()
    {
        return AmongusRecord::with('archiveSection.onedayarchive')
            ->where('is_completed', true)
            ->whereHas('archiveSection', function ($q) {
                $q->whereRaw('LOWER(game_genre) = ?', ['among us']);
            })
            ->whereHas('archiveSection.onedayarchive')
            ->get()
            ->pluck('archiveSection.onedayarchive.event_date')
            ->filter();
    }
}
