<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Models\PlantProfile;
use App\Models\Settings;

final class ProfilesController extends ApiController
{
    /** GET /api/v1/profiles — lista tuturor profilurilor + cel activ. */
    public function index(Request $request): void
    {
        Response::success([
            'profiles' => PlantProfile::all(),
            'active'   => PlantProfile::active(),
        ]);
    }

    /**
     * POST /api/v1/profiles/activate — body: { profile_id: int }.
     * Marcheaza profilul ca activ si copiaza valorile lui in settings.
     */
    public function activate(Request $request): void
    {
        $id = (int) $request->input('profile_id', 0);
        if ($id <= 0) {
            Response::error('profile_id lipsa sau invalid.', 422);
        }

        try {
            $profile = PlantProfile::activate($id);
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 404);
        }

        Response::success([
            'profile'  => $profile,
            'settings' => Settings::get(),
        ]);
    }
}
