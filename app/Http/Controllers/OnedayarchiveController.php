<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Onedayarchive;

class OnedayarchiveController extends Controller
{
    public function index()
    {
        $events = Onedayarchive::all();

        return view('onedayarchives.index', compact('events'));
    }
}