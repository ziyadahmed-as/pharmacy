<?php

namespace App\Actions\Catalogue;

use App\Models\Category;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a product category (UC-01, DDD 5.3).
 */
class SaveCategory
{
    /**
     * @param  array{name: string, parent_category_id?: ?int, description?: ?string, is_active?: bool}  $data
     *
     * @throws ValidationException
     */
    public function execute(array $data, ?Category $category = null, ?User $user = null): Category
    {
        $actor = $user ?? Auth::user();

        if (! $actor || ! $actor->hasPermission('catalogue.manage')) {
            throw ValidationException::withMessages([
                'name' => __('catalogue.unauthorized_catalogue_manage'),
            ]);
        }

        $name = trim($data['name'] ?? '');
        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => __('catalogue.category_name_required'),
            ]);
        }

        $parentId = ! empty($data['parent_category_id']) ? (int) $data['parent_category_id'] : null;

        if ($category) {
            // Cannot be its own parent
            if ($parentId && $parentId === $category->id) {
                throw ValidationException::withMessages([
                    'parent_category_id' => __('catalogue.cannot_be_own_parent'),
                ]);
            }

            // Check unique name ignoring current
            $exists = Category::where('name', $name)->where('id', '!=', $category->id)->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'name' => __('catalogue.category_name_unique'),
                ]);
            }

            $old = $category->only(['name', 'parent_category_id', 'description', 'is_active']);

            $category->update([
                'name' => $name,
                'parent_category_id' => $parentId,
                'description' => trim($data['description'] ?? '') ?: null,
                'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : $category->is_active,
            ]);

            AuditLogger::record(
                action: 'catalogue.category.updated',
                subject: $category,
                old: $old,
                new: $category->only(['name', 'parent_category_id', 'description', 'is_active']),
                user: $actor,
                description: "Updated category {$category->name}"
            );

            return $category;
        }

        // Create new category
        $exists = Category::where('name', $name)->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'name' => __('catalogue.category_name_unique'),
            ]);
        }

        $newCategory = Category::create([
            'name' => $name,
            'parent_category_id' => $parentId,
            'description' => trim($data['description'] ?? '') ?: null,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
        ]);

        AuditLogger::record(
            action: 'catalogue.category.created',
            subject: $newCategory,
            new: $newCategory->only(['name', 'parent_category_id', 'description', 'is_active']),
            user: $actor,
            description: "Created category {$newCategory->name}"
        );

        return $newCategory;
    }
}
