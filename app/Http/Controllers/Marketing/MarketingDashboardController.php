<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class MarketingDashboardController extends Controller
{
    public function index(): View
    {
        $department = config('departments.departments.marketing', []);

        return view('marketing.overview', [
            'department' => $department,
        ]);
    }
}
