<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\Admin\Auth\AdminLoginController;
use App\Http\Controllers\Admin\AdminArchiveController;
use App\Http\Controllers\Admin\ArchiveImportController;
use App\Http\Controllers\Admin\AdminSpecialArchiveController;
use App\Http\Controllers\Admin\GameController;
use App\Http\Controllers\Admin\AmongusRecordController;
use App\Http\Controllers\Admin\AmongusAnalysisDraftController;
use App\Http\Controllers\Admin\CalendarController as AdminCalendarController;
use App\Http\Controllers\Stats\AmongusStatsController;
use App\Http\Controllers\Stats\OtherGameStatsController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\Admin\MemberController as AdminMemberController;

// {date}パラメータはYYYY-MM-DD形式に限定する。これが無いと
// /admin/archives/special/... のような固定パス（specialが{date}にマッチしてしまう）が
// 先に宣言された{date}ルートに食われる。
Route::pattern('date', '\d{4}-\d{2}-\d{2}');

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
Route::get('/calendar/{date}', [CalendarController::class, 'show'])->name('calendar.show');

Route::get('/archives/{id}', [AdminArchiveController::class, 'show'])->name('archives.show');


Route::get('/admin/login', [AdminLoginController::class, 'showLoginForm'])->name('admin.login');
Route::post('/admin/login', [AdminLoginController::class, 'login'])->name('admin.login.submit');
Route::post('/admin/logout', [AdminLoginController::class, 'logout'])->name('admin.logout');

Route::middleware('admin.auth')->prefix('admin')->group(function () {
    Route::get('/', function () {
        return view('admin.home');
    })->name('admin.home');

    Route::get('/archives', [AdminArchiveController::class, 'index'])->name('admin.archives.index');
    Route::post('/archives/{date}/mark-no-stream', [AdminArchiveController::class, 'markNoStream'])->name('admin.archives.mark-no-stream');
    Route::post('/archives/{date}/mark-status', [AdminArchiveController::class, 'markStatus'])->name('admin.archives.mark-status');
    Route::get('/archives/{date}/edit', [AdminArchiveController::class, 'edit'])->name('admin.archives.edit');
    Route::post('/archives/{date}/edit', [AdminArchiveController::class, 'update'])->name('admin.archives.update');
    Route::post('/archives/{date}/autofill-games', [AdminArchiveController::class, 'autofillGames'])->name('admin.archives.autofill-games');
    Route::post('/archives/{date}/compute-participants', [AdminArchiveController::class, 'computeParticipants'])->name('admin.archives.compute-participants');
    Route::get('/archives/{date}/compute-participants/{token}', [AdminArchiveController::class, 'computeParticipantsStatus'])->name('admin.archives.compute-participants.status');
    Route::post('/archives/{date}/extract-participants-from-video', [AdminArchiveController::class, 'extractParticipantsFromVideo'])->name('admin.archives.extract-participants-from-video');
    Route::post('/archives/{date}/mark-absent', [AdminArchiveController::class, 'markAbsent'])->name('admin.archives.mark-absent');
    Route::post('/archives/{date}/remove-member', [AdminArchiveController::class, 'removeMember'])->name('admin.archives.remove-member');

    Route::get('/archives/special', [AdminSpecialArchiveController::class, 'index'])->name('admin.archives.special.index');
    Route::post('/archives/special', [AdminSpecialArchiveController::class, 'store'])->name('admin.archives.special.store');
    Route::post('/archives/special/extract-participants-from-video', [AdminSpecialArchiveController::class, 'extractParticipantsForNewRegistration'])->name('admin.archives.special.extract-participants-from-video-new');
    Route::post('/archives/special/{section}', [AdminSpecialArchiveController::class, 'update'])->name('admin.archives.special.update');
    Route::post('/archives/special/{section}/destroy', [AdminSpecialArchiveController::class, 'destroy'])->name('admin.archives.special.destroy');
    Route::post('/archives/special/{section}/extract-participants-from-video', [AdminSpecialArchiveController::class, 'extractParticipantsFromVideo'])->name('admin.archives.special.extract-participants-from-video');

    Route::post('/games', [GameController::class, 'store'])->name('admin.games.store');

    Route::get('/archive-import', [ArchiveImportController::class, 'index'])->name('admin.archive-import.index');
    Route::post('/archive-import/fetch', [ArchiveImportController::class, 'fetch'])->name('admin.archive-import.fetch');
    Route::get('/archive-import/review', [ArchiveImportController::class, 'review'])->name('admin.archive-import.review');
    Route::post('/archive-import/commit', [ArchiveImportController::class, 'commit'])->name('admin.archive-import.commit');
    Route::post('/archive-import/bulk-commit', [ArchiveImportController::class, 'bulkCommit'])->name('admin.archive-import.bulk-commit');
    Route::post('/archive-import/discard', [ArchiveImportController::class, 'discard'])->name('admin.archive-import.discard');

    Route::get('/stats/amongus/calendar', [AdminCalendarController::class, 'index'])
        ->name('admin.stats.amongus.calendar');

    Route::get('/stats/amongus/create/{date}', [AmongusRecordController::class, 'create'])
        ->name('admin.stats.amongus.create');

    Route::post('/stats/amongus/store/{date}', [AmongusRecordController::class, 'store'])
        ->name('admin.stats.amongus.store');

    Route::get('/stats/amongus/analysis-drafts', [AmongusAnalysisDraftController::class, 'index'])
        ->name('admin.stats.amongus.analysis-drafts.index');
    Route::put('/stats/amongus/analysis-drafts/{draft}', [AmongusAnalysisDraftController::class, 'update'])
        ->name('admin.stats.amongus.analysis-drafts.update');
    Route::post('/stats/amongus/analysis-drafts/{draft}/approve', [AmongusAnalysisDraftController::class, 'approve'])
        ->name('admin.stats.amongus.analysis-drafts.approve');
    Route::post('/stats/amongus/analysis-drafts/{draft}/reject', [AmongusAnalysisDraftController::class, 'reject'])
        ->name('admin.stats.amongus.analysis-drafts.reject');
    Route::post('/stats/amongus/analysis-drafts/apply', [AmongusAnalysisDraftController::class, 'apply'])
        ->name('admin.stats.amongus.analysis-drafts.apply');
});

    Route::get('/stats/other-games', [OtherGameStatsController::class, 'index'])
    ->name('stats.other-games.index');

    Route::get('/stats/other-games/{game}', [OtherGameStatsController::class, 'show'])
    ->name('stats.other-games.show');

Route::prefix('stats/amongus')->name('stats.amongus.')->group(function () {
    Route::get('/daily', [AmongusStatsController::class, 'index'])->name('daily');
    Route::get('/total', [AmongusStatsController::class, 'total'])->name('total');
});

Route::get('/members', [MemberController::class, 'index'])
    ->name('members.index');

Route::middleware('admin.auth')->group(function () {

    Route::get('/admin/members', [AdminMemberController::class, 'index'])
        ->name('admin.members.index');

    Route::post('/admin/members', [AdminMemberController::class, 'store'])
        ->name('admin.members.store');

    Route::put('/admin/members/{member}', [AdminMemberController::class, 'update'])
        ->name('admin.members.update');

});
