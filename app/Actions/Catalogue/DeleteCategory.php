<?php

namespace App\Actions\Catalogue;

use App\Models\Category;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Deletes a product category if no products are assigned (UC-01).
 */
class DeleteCategory
{
    /**
     * @throws ValidationException
     */
    public function execute(Category $category, ?User $user = null): void
    {
        $actor = $user ?? Auth::user();

        if (! $actor || ! $actor->hasPermission('catalogue.manage')) {
            throw ValidationException::withMessages([
                'delete' => __('catalogue.unauthorized_catalogue_manage'),
            ]);
        }

        $productCount = $category->products()->count();
        if ($productCount > 0) {
            throw ValidationException::withMessages([
                'delete' => __('catalogue.category_cannot_delete_has_products', ['count' => $productCount]),
            ]);
        }

        // Reassign any subcategories to null
        $category->children()->update(['parent_category_id' => null]);

        $name = $category->name;
        $id = $category->id;

        $category->delete();

        AuditLogger::record(
            action: 'catalogue.category.deleted',
            subject: null,
            old: ['id' => $id, 'name' => $name],
            user: $actor,
            description: "Deleted category {$name}"
        );
    }
}
