<?php

namespace App\Actions\Staff;

use App\Enums\Role;
use App\Models\StaffDocument;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;

/**
 * Remove an uploaded staff document and clean up private storage.
 */
class DeleteStaffDocument
{
    /**
     * @throws AuthorizationException
     */
    public function execute(User $user, StaffDocument $document): void
    {
        if ($user->hasRole(Role::SystemAdmin) || ! $user->hasAnyRole([Role::PharmacyManager, Role::Owner])) {
            throw new AuthorizationException(__('staff.documents.unauthorized'));
        }

        $staff = $document->staff;

        if ($document->file_path && Storage::disk('local')->exists($document->file_path)) {
            Storage::disk('local')->delete($document->file_path);
        }

        $old = [
            'staff_id' => $document->staff_id,
            'type' => $document->type,
            'file_name' => $document->file_name,
        ];

        $document->delete();

        AuditLogger::record(
            action: 'staff.document.deleted',
            subject: $staff,
            old: $old,
            user: $user,
            description: "Deleted {$old['type']} document for {$staff->name}"
        );
    }
}
