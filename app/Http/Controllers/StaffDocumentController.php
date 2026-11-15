<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Staff;
use App\Models\StaffDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller to securely serve private staff documents.
 * Only PM, Owner, or the staff member themselves can view the document.
 * SA is strictly denied.
 */
class StaffDocumentController extends Controller
{
    public function show(Request $request, Staff $staff, StaffDocument $document): StreamedResponse
    {
        $user = $request->user();

        // Deny SA outright (SA cannot access confidential employee documents)
        if (! $user || $user->hasRole(Role::SystemAdmin)) {
            abort(403, __('staff.documents.unauthorized'));
        }

        // Allow PM and Owner
        $isManager = $user->hasAnyRole([Role::PharmacyManager, Role::Owner]);

        // Allow the staff member themselves if linked
        $isSelf = $staff->user_id && $staff->user_id === $user->id;

        if (! $isManager && ! $isSelf) {
            abort(403, __('staff.documents.unauthorized'));
        }

        abort_unless($document->staff_id === $staff->id, 404);
        abort_unless($document->file_path && Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->response(
            $document->file_path,
            $document->file_name ?: basename($document->file_path)
        );
    }
}
