<?php

use App\Actions\Catalogue\SaveCategory;
use App\Enums\Role as RoleEnum;
use App\Livewire\Catalogue\Categories;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ─── 1. Role Matrix & Access Control ──────────────────────────────────────────

it('allows Owner and Pharmacy Manager to access Manage Categories screen', function () {
    $ownerUser = owner();
    $pmUser = pm();

    actAs($ownerUser, RoleEnum::Owner);
    test()->get(route('catalogue.categories'))->assertStatus(200);

    actAs($pmUser, RoleEnum::PharmacyManager);
    test()->get(route('catalogue.categories'))->assertStatus(200);
});

it('denies Pharmacy Staff and System Admin from accessing Manage Categories screen', function () {
    $staff = ps();
    $admin = sa();

    // Pharmacy Staff cannot access (403)
    actAs($staff, RoleEnum::PharmacyStaff);
    test()->get(route('catalogue.categories'))->assertStatus(403);

    // System Admin cannot access business catalogue data (BR-06)
    actAs($admin, RoleEnum::SystemAdmin);
    test()->get(route('catalogue.categories'))->assertStatus(403);
});

it('redirects unauthenticated guests to login', function () {
    test()->get(route('catalogue.categories'))->assertRedirect(route('login'));
});

it('displays Categories link in navigation for Owner and PM, but not Staff', function () {
    $ownerUser = owner();
    $pmUser = pm();
    $staff = ps();

    actAs($ownerUser, RoleEnum::Owner);
    $ownerNav = collect(Navigation::forUser($ownerUser))->firstWhere('key', 'catalogue');
    expect(collect($ownerNav['items'])->pluck('route'))->toContain('catalogue.categories');

    actAs($pmUser, RoleEnum::PharmacyManager);
    $pmNav = collect(Navigation::forUser($pmUser))->firstWhere('key', 'catalogue');
    expect(collect($pmNav['items'])->pluck('route'))->toContain('catalogue.categories');

    actAs($staff, RoleEnum::PharmacyStaff);
    $staffNav = collect(Navigation::forUser($staff))->firstWhere('key', 'catalogue');
    expect(collect($staffNav['items'])->pluck('route'))->not->toContain('catalogue.categories');
});

// ─── 2. Category Listing & Filtering ──────────────────────────────────────────

it('renders categories list with parent relations and product counts', function () {
    $ownerUser = owner();
    actAs($ownerUser, RoleEnum::Owner);

    $parent = Category::create([
        'name' => 'Cardiovascular',
        'description' => 'Heart and blood pressure drugs',
        'is_active' => true,
    ]);

    $child = Category::create([
        'name' => 'Antihypertensives',
        'parent_category_id' => $parent->id,
        'description' => 'ACE inhibitors and ARBs',
        'is_active' => true,
    ]);

    Product::create([
        'brand' => 'Amlodipine 5mg',
        'generic_name' => 'Amlodipine',
        'classification' => 'rx',
        'category_id' => $child->id,
        'unit' => 'piece',
        'is_active' => true,
    ]);

    Livewire::actingAs($ownerUser)
        ->test(Categories::class)
        ->assertSee('Cardiovascular')
        ->assertSee('Antihypertensives')
        ->assertSee('1 items');
});

it('filters categories by search keyword and status', function () {
    $ownerUser = owner();
    actAs($ownerUser, RoleEnum::Owner);

    Category::create(['name' => 'Analgesics', 'description' => 'Pain relief', 'is_active' => true]);
    Category::create(['name' => 'Antibiotics', 'description' => 'Bacterial infections', 'is_active' => true]);
    Category::create(['name' => 'Old Category', 'description' => 'Discontinued', 'is_active' => false]);

    Livewire::actingAs($ownerUser)
        ->test(Categories::class)
        ->set('search', 'Pain')
        ->assertSee('Analgesics')
        ->assertDontSee('Antibiotics')
        ->set('search', '')
        ->set('statusFilter', 'inactive')
        ->assertSee('Old Category')
        ->assertDontSee('Analgesics');
});

// ─── 3. Create Category (Action & Livewire) ───────────────────────────────────

it('allows Owner to create a new category and records audit trail', function () {
    $ownerUser = owner();
    actAs($ownerUser, RoleEnum::Owner);

    Livewire::actingAs($ownerUser)
        ->test(Categories::class)
        ->call('openCreateModal')
        ->set('name', 'Dermatology')
        ->set('description', 'Skin treatments and ointments')
        ->set('is_active', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showModal', false);

    $cat = Category::where('name', 'Dermatology')->first();
    expect($cat)->not->toBeNull();
    expect($cat->description)->toBe('Skin treatments and ointments');
    expect($cat->is_active)->toBeTrue();

    $log = AuditLog::where('action', 'catalogue.category.created')->first();
    expect($log)->not->toBeNull();
    expect($log->auditable_id)->toBe((string) $cat->id);
    expect($log->new_values['name'])->toBe('Dermatology');
});

it('allows creating a subcategory with parent category assigned', function () {
    $pmUser = pm();
    actAs($pmUser, RoleEnum::PharmacyManager);

    $parent = Category::create(['name' => 'Respiratory']);

    $action = app(SaveCategory::class);
    $sub = $action->execute([
        'name' => 'Bronchodilators',
        'parent_category_id' => $parent->id,
        'description' => 'Asthma inhalers',
        'is_active' => true,
    ], null, $pmUser);

    expect($sub->parent_category_id)->toBe($parent->id);
    expect($sub->parent->name)->toBe('Respiratory');
});

it('rejects duplicate category names', function () {
    $ownerUser = owner();
    actAs($ownerUser, RoleEnum::Owner);
    Category::create(['name' => 'Vitamins']);

    Livewire::actingAs($ownerUser)
        ->test(Categories::class)
        ->call('openCreateModal')
        ->set('name', 'Vitamins')
        ->call('save')
        ->assertHasErrors(['name']);
});

// ─── 4. Edit Category ─────────────────────────────────────────────────────────

it('allows editing an existing category and updates audit trail', function () {
    $pmUser = pm();
    actAs($pmUser, RoleEnum::PharmacyManager);
    $cat = Category::create(['name' => 'Ophthalmics', 'description' => 'Eye care']);

    Livewire::actingAs($pmUser)
        ->test(Categories::class)
        ->call('edit', $cat->id)
        ->assertSet('editingId', $cat->id)
        ->assertSet('name', 'Ophthalmics')
        ->set('name', 'Ophthalmics & Ear Drops')
        ->set('description', 'Eye and ear preparations')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showModal', false);

    $cat->refresh();
    expect($cat->name)->toBe('Ophthalmics & Ear Drops');
    expect($cat->description)->toBe('Eye and ear preparations');

    $log = AuditLog::where('action', 'catalogue.category.updated')->first();
    expect($log)->not->toBeNull();
    expect($log->auditable_id)->toBe((string) $cat->id);
});

it('prevents a category from being its own parent', function () {
    $ownerUser = owner();
    actAs($ownerUser, RoleEnum::Owner);
    $cat = Category::create(['name' => 'Supplements']);

    $action = app(SaveCategory::class);

    expect(fn () => $action->execute([
        'name' => 'Supplements',
        'parent_category_id' => $cat->id,
    ], $cat, $ownerUser))->toThrow(ValidationException::class);
});

// ─── 5. Toggle Category Status ────────────────────────────────────────────────

it('allows toggling category active status and logs audit event', function () {
    $ownerUser = owner();
    actAs($ownerUser, RoleEnum::Owner);
    $cat = Category::create(['name' => 'Orthopedics', 'is_active' => true]);

    Livewire::actingAs($ownerUser)
        ->test(Categories::class)
        ->call('toggleStatus', $cat->id);

    $cat->refresh();
    expect($cat->is_active)->toBeFalse();

    $log = AuditLog::where('action', 'catalogue.category.status_toggled')->first();
    expect($log)->not->toBeNull();
    expect($log->new_values['is_active'])->toBeFalse();
});

// ─── 6. Delete Category ───────────────────────────────────────────────────────

it('prevents deleting a category that has products attached', function () {
    $ownerUser = owner();
    actAs($ownerUser, RoleEnum::Owner);
    $cat = Category::create(['name' => 'Gastrointestinal']);

    Product::create([
        'brand' => 'Omeprazole 20mg',
        'generic_name' => 'Omeprazole',
        'classification' => 'rx',
        'category_id' => $cat->id,
        'unit' => 'piece',
        'is_active' => true,
    ]);

    Livewire::actingAs($ownerUser)
        ->test(Categories::class)
        ->call('confirmDelete', $cat->id)
        ->assertSet('deletingProductCount', 1)
        ->call('delete')
        ->assertHasErrors(['delete']);

    expect(Category::where('id', $cat->id)->exists())->toBeTrue();
});

it('deletes an unused category and decouples its subcategories', function () {
    $ownerUser = owner();
    actAs($ownerUser, RoleEnum::Owner);
    $parent = Category::create(['name' => 'Parent To Delete']);
    $child = Category::create(['name' => 'Subcategory Keep', 'parent_category_id' => $parent->id]);

    Livewire::actingAs($ownerUser)
        ->test(Categories::class)
        ->call('confirmDelete', $parent->id)
        ->call('delete')
        ->assertHasNoErrors()
        ->assertSet('showDeleteModal', false);

    expect(Category::where('id', $parent->id)->exists())->toBeFalse();

    $child->refresh();
    expect($child->parent_category_id)->toBeNull();

    $log = AuditLog::where('action', 'catalogue.category.deleted')->first();
    expect($log)->not->toBeNull();
    expect($log->old_values['name'])->toBe('Parent To Delete');
});
