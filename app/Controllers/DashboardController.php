<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\PlantProfile;
use App\Models\Settings;

final class DashboardController
{
    /** GET / — pagina principala (monitorizare live). */
    public function index(Request $request): void
    {
        Response::view('pages.dashboard', [
            'title'  => 'Dashboard',
            'active' => 'dashboard',
        ]);
    }

    /** GET /control — control manual + praguri fuzzy. */
    public function control(Request $request): void
    {
        Response::view('pages.control', [
            'title'           => 'Control',
            'active'          => 'control',
            'settings'        => Settings::get(),
            'profiles'        => PlantProfile::all(),
            'active_profile'  => PlantProfile::active(),
        ]);
    }

    /** GET /despre — informatii tehnice + autor. */
    public function despre(Request $request): void
    {
        Response::view('pages.despre', [
            'title'  => 'Despre',
            'active' => 'despre',
        ]);
    }
}
