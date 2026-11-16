<?php

namespace App\Actions\Catalogue;

use App\Models\Category;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Toggles category active status (UC-01).
 */
class ToggleCategoryStatus
{
    /**
     * @throws ValidationException
     */
    public function execute(Category $category, ?User $user = null): Category
    {
        $actor = $user ?? Auth::user();

        if (! $actor || ! $actor->hasPermission('catalogue.manage')) {
            throw ValidationException::withMessages([
                'status' => __('catalogue.unauthorized_catalogue_manage'),
            ]);
        }

        $oldActive = $category->is_active;
        $category->update(['is_active' => ! $oldActive]);

        AuditLogger::record(
            action: 'catalogue.category.status_toggled',
            subject: $category,
            old: ['is_active' => $oldActive],
            new: ['is_active' => $category->is_active],
            user: $actor,
            description: "Category {$category->name} set to ".($category->is_active ? 'active' : 'inactive')
        );

        return $category;
    }
}
