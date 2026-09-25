<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;

class HomeController extends Controller
{
    public function index()
    {
        $video = null;

        $response = Http::get('https://www.googleapis.com/youtube/v3/search', [
            'key' => env('YOUTUBE_API_KEY'),
            'channelId' => env('YOUTUBE_CHANNEL_ID'),
            'part' => 'snippet',
            'order' => 'date',
            'type' => 'video',
            'maxResults' => 1,
        ]);

        $video = $response->json('items.0');

        if ($response->successful() && !empty($response['items'])) {
            $video = $response['items'][0];
        }

        return view('home', compact('video'));
    }
}