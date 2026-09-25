<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $game = Game::firstOrCreate(['name' => trim($validated['name'])]);

        return response()->json(['id' => $game->id, 'name' => $game->name]);
    }
}
