<?php

namespace App\Services\Archive;

use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\Sheets as GoogleSheets;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\DeleteDimensionRequest;
use Google\Service\Sheets\DimensionRange;
use Google\Service\Sheets\Request as SheetsRequest;
use Google\Service\Sheets\ValueRange;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * 配信アーカイブ取得結果の一時保存先としてGoogle Sheetsを使うためのラッパー。
 * 「YouTube/Twitch/ツイキャス → スプシに一時保存 → 管理画面で確認 → DB保存 or 再取得」
 * というフローのうち、スプシ部分を担当する。
 */
class GoogleSheetsStagingService
{
    private const SHEET_NAME = 'archive_staging';

    private const HEADER_ROW = [
        'platform', 'member_id', 'member_name', 'video_id', 'url', 'title',
        'published_at', 'duration_seconds', 'is_live_archive', 'date', 'status', 'fetched_at',
    ];

    private ?GoogleSheets $sheetsService = null;

    public function isConfigured(): bool
    {
        $credentialsPath = $this->credentialsPath();
        $spreadsheetId = config('services.google_sheets.spreadsheet_id');

        return (bool) $credentialsPath
            && (bool) $spreadsheetId
            && is_file($credentialsPath);
    }

    /**
     * .envには相対パスで設定されることを想定しているが、CLI(artisan)とphp-fpmとで
     * カレントディレクトリの扱いが異なり、相対パスのままだとphp-fpm側で解決に失敗するため、
     * ここで必ずプロジェクトルート基準の絶対パスに変換する。
     */
    private function credentialsPath(): ?string
    {
        $path = config('services.google_sheets.credentials_path');

        if (!$path) {
            return null;
        }

        return str_starts_with($path, '/') ? $path : base_path($path);
    }

    /**
     * 取得した候補行をスプシに追記する。ヘッダー行が無ければ先に作る。
     */
    public function appendRows(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $this->ensureHeaderRow();

        $values = $rows->map(fn (array $row) => $this->toSheetRow($row))->values()->all();

        $this->service()->spreadsheets_values->append(
            $this->spreadsheetId(),
            self::SHEET_NAME . '!A:L',
            new ValueRange(['values' => $values]),
            ['valueInputOption' => 'RAW', 'insertDataOption' => 'INSERT_ROWS']
        );
    }

    /**
     * ヘッダー行を除いた全行を読み込む。各要素に実シート上の行番号(sheet_row)を含める。
     */
    public function fetchStagedRows(): Collection
    {
        $response = $this->service()->spreadsheets_values->get(
            $this->spreadsheetId(),
            self::SHEET_NAME . '!A2:L'
        );

        $values = $response->getValues() ?? [];

        return collect($values)
            ->map(function (array $row, int $index) {
                $row = array_pad($row, count(self::HEADER_ROW), '');

                return array_combine(self::HEADER_ROW, $row) + [
                    'sheet_row' => $index + 2, // 1行目はヘッダーなので実データは2行目から
                ];
            })
            ->values();
    }

    /**
     * 指定行をスプシから完全に削除する（保存・破棄いずれの場合も、確認済みの行はスプシに残さない）。
     * 行を消すと後続行が繰り上がるため、呼び出し側は毎回fetchStagedRows()で最新のsheet_rowを取り直すこと。
     */
    public function deleteRow(int $sheetRow): void
    {
        $request = new SheetsRequest([
            'deleteDimension' => new DeleteDimensionRequest([
                'range' => new DimensionRange([
                    'sheetId' => $this->sheetId(),
                    'dimension' => 'ROWS',
                    'startIndex' => $sheetRow - 1,
                    'endIndex' => $sheetRow,
                ]),
            ]),
        ]);

        $this->service()->spreadsheets->batchUpdate(
            $this->spreadsheetId(),
            new BatchUpdateSpreadsheetRequest(['requests' => [$request]])
        );
    }

    private function sheetId(): int
    {
        $spreadsheet = $this->service()->spreadsheets->get($this->spreadsheetId());

        foreach ($spreadsheet->getSheets() as $sheet) {
            if ($sheet->getProperties()->getTitle() === self::SHEET_NAME) {
                return $sheet->getProperties()->getSheetId();
            }
        }

        throw new RuntimeException('archive_stagingシートが見つかりません。');
    }

    private function ensureHeaderRow(): void
    {
        $response = $this->service()->spreadsheets_values->get(
            $this->spreadsheetId(),
            self::SHEET_NAME . '!A1:L1'
        );

        if (!empty($response->getValues())) {
            return;
        }

        $this->service()->spreadsheets_values->update(
            $this->spreadsheetId(),
            self::SHEET_NAME . '!A1',
            new ValueRange(['values' => [self::HEADER_ROW]]),
            ['valueInputOption' => 'RAW']
        );
    }

    private function toSheetRow(array $row): array
    {
        $publishedAt = $row['published_at'] ?? null;

        return [
            $row['platform'] ?? '',
            $row['member_id'] ?? '',
            $row['member_name'] ?? '',
            $row['video_id'] ?? '',
            $row['url'] ?? '',
            $row['title'] ?? '',
            $publishedAt instanceof Carbon ? $publishedAt->toDateTimeString() : (string) $publishedAt,
            $row['duration_seconds'] ?? '',
            !empty($row['is_live_archive']) ? 'yes' : 'no',
            $row['date'] ?? '',
            $row['status'] ?? 'pending',
            now()->toDateTimeString(),
        ];
    }

    private function service(): GoogleSheets
    {
        if ($this->sheetsService === null) {
            $this->sheetsService = new GoogleSheets($this->buildClient());
        }

        return $this->sheetsService;
    }

    private function buildClient(): GoogleClient
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException(
                'Google Sheets連携が未設定です（GOOGLE_SHEETS_CREDENTIALS_PATH / GOOGLE_SHEETS_SPREADSHEET_ID）。'
            );
        }

        $client = new GoogleClient();
        $client->setAuthConfig($this->credentialsPath());
        $client->addScope(GoogleSheets::SPREADSHEETS);

        return $client;
    }

    private function spreadsheetId(): string
    {
        return (string) config('services.google_sheets.spreadsheet_id');
    }
}
