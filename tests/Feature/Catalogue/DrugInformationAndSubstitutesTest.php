<?php

use App\Actions\Catalogue\SaveProduct;
use App\Livewire\Catalogue\Form as CatalogueForm;
use App\Livewire\Catalogue\Show as CatalogueShow;
use App\Models\Category;
use App\Models\DrugInformation;
use App\Models\Product;
use App\Models\ProductSubstitute;
use Livewire\Livewire;

beforeEach(function () {
    seedRoles();
});

it('saves and retrieves clinical drug information on product creation and update', function () {
    $pm = pm();
    $cat = Category::create(['name' => 'Analgesics']);
    $action = app(SaveProduct::class);

    $product = $action->execute([
        'brand' => 'Paracetamol Extra',
        'generic_name' => 'Paracetamol',
        'strength' => '500mg',
        'dosage_form' => 'Tablet',
        'pack_size' => '10x10',
        'unit' => 'tablet',
        'category_id' => $cat->id,
        'classification' => 'otc',
        'product_code' => 'PRD-PARA-001',
        'is_cold_chain' => false,
        'min_shelf_life_days' => 365,
        'drug_information' => [
            'indications' => 'Fever and mild to moderate pain relief',
            'dosage_guidance' => '1 to 2 tablets every 4 to 6 hours as needed',
            'side_effects' => 'Rare skin rash or liver toxicity in overdose',
            'contraindications' => 'Severe hepatic impairment',
            'interactions' => 'Warfarin, alcohol',
        ],
    ], null, $pm);

    expect($product->product_code)->toBe('PRD-PARA-001')
        ->and($product->is_cold_chain)->toBeFalse()
        ->and($product->min_shelf_life_days)->toBe(365);

    $info = $product->drugInformation;
    expect($info)->not->toBeNull()
        ->and($info->indications)->toContain('Fever and mild')
        ->and($info->dosage_guidance)->toContain('1 to 2 tablets')
        ->and($info->contraindications)->toContain('Severe hepatic')
        ->and($info->updated_by)->toBe($pm->id);

    // Test static helper DrugInformation::getFor
    $retrieved = DrugInformation::getFor($product);
    expect($retrieved)->not->toBeNull()
        ->and($retrieved->id)->toBe($info->id);
});

it('supports category hierarchy with parent and child categories', function () {
    $parent = Category::create([
        'name' => 'Cardiovascular',
        'description' => 'Heart and circulation medications',
        'is_active' => true,
    ]);

    $child = Category::create([
        'name' => 'Antihypertensives',
        'parent_category_id' => $parent->id,
        'is_active' => true,
    ]);

    expect($child->parent->id)->toBe($parent->id)
        ->and($parent->children->pluck('id'))->toContain($child->id);

    // Active scope
    $inactive = Category::create([
        'name' => 'Discontinued Line',
        'is_active' => false,
    ]);

    $activeCategories = Category::active()->get();
    expect($activeCategories->pluck('id'))->toContain($parent->id)
        ->and($activeCategories->pluck('id'))->not->toContain($inactive->id);
});

it('suggests product substitutes with stock and pricing', function () {
    $cat = Category::create(['name' => 'Antibiotics']);
    $action = app(SaveProduct::class);
    $pm = pm();

    $origProduct = $action->execute([
        'brand' => 'Augmentin 625mg',
        'generic_name' => 'Amoxicillin + Clavulanic Acid',
        'classification' => 'rx',
    ], null, $pm);

    $subProduct = $action->execute([
        'brand' => 'Clavulin 625mg',
        'generic_name' => 'Amoxicillin + Clavulanic Acid',
        'classification' => 'rx',
    ], null, $pm);

    ProductSubstitute::create([
        'product_id' => $origProduct->id,
        'substitute_product_id' => $subProduct->id,
        'note' => 'Identical bioequivalent strength and formulation',
    ]);

    $suggested = ProductSubstitute::suggest($origProduct);
    expect($suggested->pluck('id'))->toContain($subProduct->id);

    expect($origProduct->substitutes->first()->id)->toBe($subProduct->id);
});

it('renders livewire catalogue show with drug clinical information and category parent', function () {
    $pm = pm();
    $parent = Category::create(['name' => 'Respiratory System']);
    $child = Category::create(['name' => 'Bronchodilators', 'parent_category_id' => $parent->id]);

    $action = app(SaveProduct::class);
    $product = $action->execute([
        'brand' => 'Ventolin Inhaler',
        'generic_name' => 'Salbutamol',
        'classification' => 'rx',
        'category_id' => $child->id,
        'is_cold_chain' => false,
        'drug_information' => [
            'indications' => 'Relief of bronchospasm in bronchial asthma',
            'dosage_guidance' => '1-2 puffs as required',
        ],
    ], null, $pm);

    actAs($pm);

    Livewire::test(CatalogueShow::class, ['product' => $product])
        ->assertSee('Ventolin Inhaler')
        ->assertSee('Respiratory System')
        ->assertSee('Bronchodilators')
        ->assertSee('Relief of bronchospasm in bronchial asthma')
        ->assertSee('1-2 puffs as required');
});

it('allows editing product code and clinical drug information in catalogue form', function () {
    $pm = pm();
    $cat = Category::create(['name' => 'Dermatologicals']);
    $action = app(SaveProduct::class);

    $product = $action->execute([
        'brand' => 'Hydrocortisone 1% Cream',
        'generic_name' => 'Hydrocortisone',
        'classification' => 'otc',
        'category_id' => $cat->id,
    ], null, $pm);

    actAs($pm);

    Livewire::test(CatalogueForm::class, ['product' => $product])
        ->set('product_code', 'PRD-DERM-001')
        ->set('indications', 'Mild inflammatory skin conditions')
        ->set('dosage_guidance', 'Apply sparingly 1-2 times daily')
        ->call('save')
        ->assertHasNoErrors();

    $product->refresh();
    expect($product->product_code)->toBe('PRD-DERM-001');

    $info = DrugInformation::getFor($product);
    expect($info)->not->toBeNull()
        ->and($info->indications)->toBe('Mild inflammatory skin conditions')
        ->and($info->dosage_guidance)->toBe('Apply sparingly 1-2 times daily');
});
