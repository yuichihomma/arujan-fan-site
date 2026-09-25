<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;

class OpenrecController extends Controller
{
    public function index()
    {
        $channelId = 'hatasako'; // ←OPENRECのチャンネルID

        $response = Http::get('https://public.openrec.tv/external/api/v5/movies', [
            'channel_id' => $channelId,
        ]);

        $movie = $response->json()[0] ?? null;

        return view('home', compact('movie'));
    }
}
