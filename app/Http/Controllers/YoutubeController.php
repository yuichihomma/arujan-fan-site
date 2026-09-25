<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;

class YoutubeController extends Controller
{
    public function index()
    {
        $apiKey = env('YOUTUBE_API_KEY');
        $channelId = 'UCTHYu_QxFlTBKrIn7F8DGZA'; // ←チャンネルID

        $url = "https://www.googleapis.com/youtube/v3/search";

        $response = Http::get($url, [
            'key' => $apiKey,
            'channelId' => $channelId,
            'part' => 'snippet',
            'order' => 'date',
            'maxResults' => 1,
        ]);

        $video = $response->json()['items'][0] ?? null;

        return view('home', compact('video'));
    }
}