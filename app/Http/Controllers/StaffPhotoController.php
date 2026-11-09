<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Private photos are only served to users who may view the staff record. */
class StaffPhotoController extends Controller
{
    public function __invoke(Staff $staff): StreamedResponse
    {
        Gate::authorize('view', $staff);
        abort_unless($staff->photo_path && Storage::disk('local')->exists($staff->photo_path), 404);

        return Storage::disk('local')->response($staff->photo_path);
    }
}
