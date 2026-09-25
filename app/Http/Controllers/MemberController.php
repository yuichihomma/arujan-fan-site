<?php

namespace App\Http\Controllers;

use App\Models\Member;

class MemberController extends Controller
{
    public function index()
    {
        $mainMembers = $this->withAmongusParticipationDates(
            Member::with('records.archiveSection.onedayarchive')
                ->whereIn('name', Member::mainMemberNames())
                ->orderBy('id')
                ->get()
        );

        $subMembers = $this->withAmongusParticipationDates(
            Member::with('records.archiveSection.onedayarchive')
                ->whereIn('name', Member::subMemberNames())
                ->orderBy('id')
                ->get()
        );

        $guestMembers = $this->withAmongusParticipationDates(
            Member::with('records.archiveSection.onedayarchive')
                ->whereNotIn(
                    'name',
                    array_merge(Member::mainMemberNames(), Member::subMemberNames())
                )
                ->orderBy('id')
                ->get()
        );

        return view('members.index', compact(
            'mainMembers',
            'subMembers',
            'guestMembers'
        ));
    }

    private function withAmongusParticipationDates($members)
    {
        return $members->map(function (Member $member) {
            $participationDates = $member->records
                ->map(fn ($record) => $record->archiveSection?->onedayarchive?->event_date)
                ->filter()
                ->unique()
                ->sort()
                ->values();

            $member->amongus_first_participation_date = $participationDates->first();
            $member->amongus_latest_participation_date = $participationDates->last();

            return $member;
        });
    }
}
