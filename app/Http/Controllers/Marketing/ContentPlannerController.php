<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Content\ContentLabel;

class ContentPlannerController extends Controller
{
    public function calendar()
    {
        $labels = ContentLabel::orderBy('name')->get();

        return view('content-planner.calendar', [
            'labels' => $labels,
            'platforms' => config('content-planner.platforms'),
            'statuses' => config('content-planner.statuses'),
        ]);
    }

    public function list()
    {
        $labels = ContentLabel::orderBy('name')->get();

        return view('content-planner.list', [
            'labels' => $labels,
            'platforms' => config('content-planner.platforms'),
            'statuses' => config('content-planner.statuses'),
        ]);
    }

    public function grid()
    {
        $labels = ContentLabel::orderBy('name')->get();

        return view('content-planner.grid', [
            'labels' => $labels,
            'platforms' => config('content-planner.platforms'),
        ]);
    }

    public function media()
    {
        return view('content-planner.media');
    }

    public function feed()
    {
        return view('content-planner.feed', [
            'platforms' => config('content-planner.platforms'),
        ]);
    }
}
