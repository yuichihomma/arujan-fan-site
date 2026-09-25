<?php

namespace App\Providers;

use App\Services\Archive\ArchiveImportOrchestrator;
use App\Services\Archive\GoogleSheetsStagingService;
use App\Services\Archive\OpenrecArchiveFetcher;
use App\Services\Archive\ParticipantComputationService;
use App\Services\Archive\ParticipantNameExtractor;
use App\Services\Archive\StreamArchiveCollector;
use App\Services\Archive\TwitcastingArchiveFetcher;
use App\Services\Archive\TwitchArchiveFetcher;
use App\Services\Archive\YoutubeArchiveFetcher;
use App\Services\GameGenreInferenceService;
use App\Services\YoutubeMemberArchiveSyncService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ArchiveImportOrchestrator::class, function ($app) {
            return new ArchiveImportOrchestrator(
                $this->platformFetchers($app),
                $app->make(GoogleSheetsStagingService::class),
                $app->make(GameGenreInferenceService::class),
                $app->make(ParticipantNameExtractor::class)
            );
        });

        $this->app->bind(ParticipantComputationService::class, function ($app) {
            return new ParticipantComputationService(
                $this->platformFetchers($app),
                $app->make(ParticipantNameExtractor::class),
                $app->make(GameGenreInferenceService::class),
                $app->make(YoutubeMemberArchiveSyncService::class)
            );
        });

        $this->app->bind(StreamArchiveCollector::class, function ($app) {
            return new StreamArchiveCollector(
                $app->make(YoutubeMemberArchiveSyncService::class),
                [
                    $app->make(TwitchArchiveFetcher::class),
                    $app->make(TwitcastingArchiveFetcher::class),
                    $app->make(OpenrecArchiveFetcher::class),
                ]
            );
        });
    }

    /**
     * @return array{0: YoutubeArchiveFetcher, 1: TwitchArchiveFetcher, 2: TwitcastingArchiveFetcher, 3: OpenrecArchiveFetcher}
     */
    private function platformFetchers($app): array
    {
        return [
            $app->make(YoutubeArchiveFetcher::class),
            $app->make(TwitchArchiveFetcher::class),
            $app->make(TwitcastingArchiveFetcher::class),
            $app->make(OpenrecArchiveFetcher::class),
        ];
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
