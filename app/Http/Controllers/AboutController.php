<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\View\View;

class AboutController extends Controller
{
    public function __invoke(): View
    {
        return view('pages.about', [
            'services' => Service::query()->published()->ordered()->limit(8)->get(),
        ]);
    }
}
