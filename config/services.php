<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'youtube' => [
        'api_key' => env('YOUTUBE_API_KEY'),
        'channel_id' => env('YOUTUBE_CHANNEL_ID'),
    ],

    'twitch' => [
        'client_id' => env('TWITCH_CLIENT_ID'),
        'client_secret' => env('TWITCH_CLIENT_SECRET'),
    ],

    'twitcasting' => [
        'client_id' => env('TWITCASTING_CLIENT_ID'),
        'client_secret' => env('TWITCASTING_CLIENT_SECRET'),
        // サイト管理者アカウントでAuthorization Code Grantを一度だけ実行して取得したトークン。
        // 約180日で失効するため、失効したら再認可が必要。
        'access_token' => env('TWITCASTING_ACCESS_TOKEN'),
    ],

    'google_sheets' => [
        // Google Cloudのサービスアカウント鍵(JSON)のパス。storage/app/google/ 等に置く想定。
        'credentials_path' => env('GOOGLE_SHEETS_CREDENTIALS_PATH'),
        // 配信アーカイブの一時保存に使うスプレッドシートのID(URLの/d/と/editの間の文字列)。
        'spreadsheet_id' => env('GOOGLE_SHEETS_SPREADSHEET_ID'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
