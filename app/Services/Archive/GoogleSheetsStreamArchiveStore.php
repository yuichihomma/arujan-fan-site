<?php

namespace App\Services\Archive;

use Google\Client as GoogleClient;
use Google\Service\Sheets as GoogleSheets;
use Google\Service\Sheets\AddSheetRequest;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\Request as SheetsRequest;
use Google\Service\Sheets\ValueRange;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * 全メンバーの生配信アーカイブを貯めておく「all_streams」シートの読み書き。
 * archive_staging（確認待ちの候補置き場）と同じスプレッドシート・同じ認証情報を使い、シートだけ分ける。
 */
class GoogleSheetsStreamArchiveStore
{
    public const SHEET_NAME = 'all_streams';

    public const HEADER_ROW = [
        'イベント日', '配信開始', 'プラットフォーム', '配信者', 'タイトル', 'URL',
        '長さ(分)', 'アルジャンタグ', '推定区分', 'DB登録済み', 'video_id', '取得日時',
    ];

    private const PLATFORM_COLUMN = 2;
    private const VIDEO_ID_COLUMN = 10;

    private ?GoogleSheets $sheetsService = null;

    public function isConfigured(): bool
    {
        $credentialsPath = $this->credentialsPath();

        return (bool) $credentialsPath
            && (bool) config('services.google_sheets.spreadsheet_id')
            && is_file($credentialsPath);
    }

    /**
     * 既にシートにある行の「platform:video_id」一覧。重複追記を防ぐために使う。
     */
    public function existingKeys(): Collection
    {
        $this->ensureSheet();

        $values = $this->service()->spreadsheets_values
            ->get($this->spreadsheetId(), self::SHEET_NAME . '!A2:L')
            ->getValues() ?? [];

        return collect($values)
            ->map(fn (array $row) => ($row[self::PLATFORM_COLUMN] ?? '') . ':' . ($row[self::VIDEO_ID_COLUMN] ?? ''))
            ->values();
    }

    /**
     * @param Collection<int, array> $rows HEADER_ROWの順に並んだ行
     */
    public function appendRows(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $this->ensureSheet();

        $this->service()->spreadsheets_values->append(
            $this->spreadsheetId(),
            self::SHEET_NAME . '!A:L',
            new ValueRange(['values' => $rows->values()->all()]),
            ['valueInputOption' => 'RAW', 'insertDataOption' => 'INSERT_ROWS']
        );
    }

    /**
     * シートが無ければ作り、ヘッダー行が無ければ書き込む（初回実行で手作業のシート準備を不要にする）。
     */
    private function ensureSheet(): void
    {
        $spreadsheet = $this->service()->spreadsheets->get($this->spreadsheetId());
        $exists = collect($spreadsheet->getSheets())
            ->contains(fn ($sheet) => $sheet->getProperties()->getTitle() === self::SHEET_NAME);

        if (!$exists) {
            $this->service()->spreadsheets->batchUpdate(
                $this->spreadsheetId(),
                new BatchUpdateSpreadsheetRequest(['requests' => [
                    new SheetsRequest(['addSheet' => new AddSheetRequest(['properties' => ['title' => self::SHEET_NAME]])]),
                ]])
            );
        }

        $header = $this->service()->spreadsheets_values
            ->get($this->spreadsheetId(), self::SHEET_NAME . '!A1:L1')
            ->getValues();

        if (empty($header)) {
            $this->service()->spreadsheets_values->update(
                $this->spreadsheetId(),
                self::SHEET_NAME . '!A1',
                new ValueRange(['values' => [self::HEADER_ROW]]),
                ['valueInputOption' => 'RAW']
            );
        }
    }

    /**
     * GoogleSheetsStagingServiceと同じく、相対パスはプロジェクトルート基準の絶対パスに変換する。
     */
    private function credentialsPath(): ?string
    {
        $path = config('services.google_sheets.credentials_path');

        if (!$path) {
            return null;
        }

        return str_starts_with($path, '/') ? $path : base_path($path);
    }

    private function service(): GoogleSheets
    {
        if ($this->sheetsService === null) {
            if (!$this->isConfigured()) {
                throw new RuntimeException(
                    'Google Sheets連携が未設定です（GOOGLE_SHEETS_CREDENTIALS_PATH / GOOGLE_SHEETS_SPREADSHEET_ID）。'
                );
            }

            $client = new GoogleClient();
            $client->setAuthConfig($this->credentialsPath());
            $client->addScope(GoogleSheets::SPREADSHEETS);

            $this->sheetsService = new GoogleSheets($client);
        }

        return $this->sheetsService;
    }

    private function spreadsheetId(): string
    {
        return (string) config('services.google_sheets.spreadsheet_id');
    }
}
