<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive extension of the product data model for pharmaceutical medicines.
 *
 * New tables
 * ----------
 *   manufacturers                  — master manufacturer records (replaces free-text products.manufacturer)
 *   dosage_forms                   — lookup list of dosage forms (replaces free-text products.dosage_form)
 *   routes_of_administration       — lookup list of administration routes (new concept)
 *   therapeutic_classes            — hierarchical ATC-style therapeutic classification (new concept)
 *   product_active_ingredients     — relational active-ingredient lines per product (new concept)
 *
 * New columns on products
 * -----------------------
 *   product_type                   — enum: medicine | medical_device | cosmetic | supplement | other
 *   product_description            — free-text long description
 *   manufacturer_id                — nullable FK → manufacturers (products.manufacturer text column kept intact)
 *   country_of_origin              — varchar(100)
 *   dosage_form_id                 — nullable FK → dosage_forms (products.dosage_form text column kept intact)
 *   strength_unit                  — varchar(30) e.g. mg, mcg, IU
 *   route_of_administration_id     — nullable FK → routes_of_administration
 *   therapeutic_class_id           — nullable FK → therapeutic_classes
 *   atc_code                       — varchar(10) WHO ATC code
 *   registration_number            — varchar(100) regulatory registration number
 *   prescription_required          — bool (more explicit than inferring from classification)
 *   special_storage_instructions   — text
 *   inventory_tracking_enabled     — bool default true
 *   batch_tracking_enabled         — bool default true
 *   expiry_tracking_enabled        — bool default true
 *
 * New columns on drug_information
 * --------------------------------
 *   administration_instructions    — text
 *   warnings_precautions           — text
 *   patient_counseling_information — text
 *
 * Rules
 * -----
 *   - No existing column is dropped or renamed.
 *   - All new columns on products are nullable (preserves existing rows).
 *   - Lookup tables are seeded with common pharmaceutical values.
 */
return new class extends Migration
{
    // -------------------------------------------------------------------------
    // UP
    // -------------------------------------------------------------------------

    public function up(): void
    {
        // ------------------------------------------------------------------ //
        // 1. manufacturers                                                    //
        // ------------------------------------------------------------------ //
        Schema::create('manufacturers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->unique();
            $table->string('short_name', 60)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('website', 255)->nullable();
            $table->text('address')->nullable();
            $table->string('contact_email', 150)->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['name', 'country'], 'idx_manufacturers_name_country');
        });

        // ------------------------------------------------------------------ //
        // 2. dosage_forms                                                     //
        // ------------------------------------------------------------------ //
        Schema::create('dosage_forms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();         // e.g. "Tablet"
            $table->string('abbreviation', 20)->nullable(); // e.g. "TAB"
            $table->string('route_hint', 100)->nullable(); // e.g. "Oral"
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ------------------------------------------------------------------ //
        // 3. routes_of_administration                                         //
        // ------------------------------------------------------------------ //
        Schema::create('routes_of_administration', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();         // e.g. "Oral"
            $table->string('abbreviation', 20)->nullable(); // e.g. "PO"
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ------------------------------------------------------------------ //
        // 4. therapeutic_classes (supports one level of parent-child)         //
        // ------------------------------------------------------------------ //
        Schema::create('therapeutic_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('atc_code', 15)->nullable()->unique(); // WHO ATC code for the class
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('therapeutic_classes')
                ->nullOnDelete();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['atc_code'], 'idx_therapeutic_classes_atc');
        });

        // ------------------------------------------------------------------ //
        // 5. product_active_ingredients                                       //
        // ------------------------------------------------------------------ //
        Schema::create('product_active_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            // Ingredient identity
            $table->string('ingredient_name', 150);        // INN / generic name of the ingredient
            $table->string('ingredient_code', 50)->nullable(); // e.g. CAS number, INN code
            // Strength per dosage unit
            $table->decimal('amount', 10, 4)->unsigned();  // numeric amount, e.g. 500
            $table->string('strength_unit', 30);           // e.g. mg, mcg, IU, %
            // Display order for combination products
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            // A product cannot have the same ingredient listed twice
            $table->unique(['product_id', 'ingredient_name'], 'uq_product_ingredient');
            $table->index(['ingredient_name'], 'idx_active_ingredients_name');
        });

        // ------------------------------------------------------------------ //
        // 6. New columns on products                                          //
        // ------------------------------------------------------------------ //
        Schema::table('products', function (Blueprint $table) {
            // Product type classification
            $table->enum('product_type', [
                'medicine',
                'medical_device',
                'cosmetic',
                'supplement',
                'other',
            ])->default('medicine')->after('classification');

            // Long description
            $table->text('product_description')->nullable()->after('product_type');

            // Manufacturer FK (text column products.manufacturer is kept intact)
            $table->foreignId('manufacturer_id')
                ->nullable()
                ->after('manufacturer')
                ->constrained('manufacturers')
                ->nullOnDelete();

            // Country of origin
            $table->string('country_of_origin', 100)->nullable()->after('manufacturer_id');

            // Dosage form FK (text column products.dosage_form is kept intact)
            $table->foreignId('dosage_form_id')
                ->nullable()
                ->after('dosage_form')
                ->constrained('dosage_forms')
                ->nullOnDelete();

            // Strength unit as a separate structured column
            // (products.strength free-text column is kept intact)
            $table->string('strength_unit', 30)->nullable()->after('strength');

            // Route of administration
            $table->foreignId('route_of_administration_id')
                ->nullable()
                ->after('dosage_form_id')
                ->constrained('routes_of_administration')
                ->nullOnDelete();

            // Therapeutic class
            $table->foreignId('therapeutic_class_id')
                ->nullable()
                ->after('route_of_administration_id')
                ->constrained('therapeutic_classes')
                ->nullOnDelete();

            // Regulatory fields
            $table->string('atc_code', 10)->nullable()->after('therapeutic_class_id');
            $table->string('registration_number', 100)->nullable()->after('atc_code');

            // Explicit prescription flag (classification=rx is implied, but this is explicit)
            $table->boolean('prescription_required')->default(false)->after('registration_number');

            // Special storage
            $table->text('special_storage_instructions')->nullable()->after('storage_condition');

            // Inventory tracking toggles
            $table->boolean('inventory_tracking_enabled')->default(true)->after('min_shelf_life_days');
            $table->boolean('batch_tracking_enabled')->default(true)->after('inventory_tracking_enabled');
            $table->boolean('expiry_tracking_enabled')->default(true)->after('batch_tracking_enabled');

            // Indexes for new searchable columns
            $table->index(['atc_code'], 'idx_products_atc_code');
            $table->index(['registration_number'], 'idx_products_registration_number');
            $table->index(['manufacturer_id'], 'idx_products_manufacturer_id');
            $table->index(['dosage_form_id'], 'idx_products_dosage_form_id');
            $table->index(['therapeutic_class_id'], 'idx_products_therapeutic_class_id');
            $table->index(['product_type'], 'idx_products_product_type');
        });

        // ------------------------------------------------------------------ //
        // 7. New columns on drug_information                                  //
        // ------------------------------------------------------------------ //
        Schema::table('drug_information', function (Blueprint $table) {
            $table->text('administration_instructions')->nullable()->after('dosage_guidance');
            $table->text('warnings_precautions')->nullable()->after('side_effects');
            $table->text('patient_counseling_information')->nullable()->after('contraindications');
        });

        // ------------------------------------------------------------------ //
        // 8. Seed lookup tables                                               //
        // ------------------------------------------------------------------ //
        $this->seedDosageForms();
        $this->seedRoutes();
        $this->seedTherapeuticClasses();
    }

    // -------------------------------------------------------------------------
    // DOWN
    // -------------------------------------------------------------------------

    public function down(): void
    {
        // drug_information columns
        Schema::table('drug_information', function (Blueprint $table) {
            $table->dropColumn([
                'administration_instructions',
                'warnings_precautions',
                'patient_counseling_information',
            ]);
        });

        // products columns — drop indexes first, then FKs, then columns
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_atc_code');
            $table->dropIndex('idx_products_registration_number');
            $table->dropIndex('idx_products_manufacturer_id');
            $table->dropIndex('idx_products_dosage_form_id');
            $table->dropIndex('idx_products_therapeutic_class_id');
            $table->dropIndex('idx_products_product_type');

            $table->dropForeign(['manufacturer_id']);
            $table->dropForeign(['dosage_form_id']);
            $table->dropForeign(['route_of_administration_id']);
            $table->dropForeign(['therapeutic_class_id']);

            $table->dropColumn([
                'product_type',
                'product_description',
                'manufacturer_id',
                'country_of_origin',
                'dosage_form_id',
                'strength_unit',
                'route_of_administration_id',
                'therapeutic_class_id',
                'atc_code',
                'registration_number',
                'prescription_required',
                'special_storage_instructions',
                'inventory_tracking_enabled',
                'batch_tracking_enabled',
                'expiry_tracking_enabled',
            ]);
        });

        // new tables (reverse dependency order)
        Schema::dropIfExists('product_active_ingredients');
        Schema::dropIfExists('therapeutic_classes');
        Schema::dropIfExists('routes_of_administration');
        Schema::dropIfExists('dosage_forms');
        Schema::dropIfExists('manufacturers');
    }

    // -------------------------------------------------------------------------
    // Seed helpers
    // -------------------------------------------------------------------------

    private function seedDosageForms(): void
    {
        $forms = [
            ['name' => 'Tablet',                    'abbreviation' => 'TAB',   'route_hint' => 'Oral'],
            ['name' => 'Capsule',                   'abbreviation' => 'CAP',   'route_hint' => 'Oral'],
            ['name' => 'Syrup',                     'abbreviation' => 'SYR',   'route_hint' => 'Oral'],
            ['name' => 'Suspension',                'abbreviation' => 'SUSP',  'route_hint' => 'Oral'],
            ['name' => 'Solution',                  'abbreviation' => 'SOL',   'route_hint' => 'Oral/Topical/Injectable'],
            ['name' => 'Injection',                 'abbreviation' => 'INJ',   'route_hint' => 'Parenteral'],
            ['name' => 'Infusion',                  'abbreviation' => 'INF',   'route_hint' => 'Intravenous'],
            ['name' => 'Powder for Injection',      'abbreviation' => 'PDR',   'route_hint' => 'Parenteral'],
            ['name' => 'Cream',                     'abbreviation' => 'CRM',   'route_hint' => 'Topical'],
            ['name' => 'Ointment',                  'abbreviation' => 'OIN',   'route_hint' => 'Topical'],
            ['name' => 'Gel',                       'abbreviation' => 'GEL',   'route_hint' => 'Topical'],
            ['name' => 'Lotion',                    'abbreviation' => 'LOT',   'route_hint' => 'Topical'],
            ['name' => 'Drops',                     'abbreviation' => 'DRP',   'route_hint' => 'Ophthalmic/Otic/Nasal'],
            ['name' => 'Eye Drops',                 'abbreviation' => 'EDROP', 'route_hint' => 'Ophthalmic'],
            ['name' => 'Ear Drops',                 'abbreviation' => 'ADROP', 'route_hint' => 'Otic'],
            ['name' => 'Nasal Spray',               'abbreviation' => 'NSPRAY','route_hint' => 'Nasal'],
            ['name' => 'Inhaler',                   'abbreviation' => 'INH',   'route_hint' => 'Inhalation'],
            ['name' => 'Suppository',               'abbreviation' => 'SUPP',  'route_hint' => 'Rectal'],
            ['name' => 'Patch',                     'abbreviation' => 'PTCH',  'route_hint' => 'Transdermal'],
            ['name' => 'Chewable Tablet',           'abbreviation' => 'CTAB',  'route_hint' => 'Oral'],
            ['name' => 'Dispersible Tablet',        'abbreviation' => 'DTAB',  'route_hint' => 'Oral'],
            ['name' => 'Effervescent Tablet',       'abbreviation' => 'ETAB',  'route_hint' => 'Oral'],
            ['name' => 'Modified-Release Tablet',   'abbreviation' => 'MRTAB', 'route_hint' => 'Oral'],
            ['name' => 'Modified-Release Capsule',  'abbreviation' => 'MRCAP', 'route_hint' => 'Oral'],
            ['name' => 'Lozenge',                   'abbreviation' => 'LOZ',   'route_hint' => 'Oromucosal'],
            ['name' => 'Sachet',                    'abbreviation' => 'SACH',  'route_hint' => 'Oral'],
            ['name' => 'Granules',                  'abbreviation' => 'GRAN',  'route_hint' => 'Oral'],
            ['name' => 'Powder',                    'abbreviation' => 'PWD',   'route_hint' => 'Oral/Topical'],
            ['name' => 'Pessary',                   'abbreviation' => 'PES',   'route_hint' => 'Vaginal'],
            ['name' => 'Vaginal Cream',             'abbreviation' => 'VCRM',  'route_hint' => 'Vaginal'],
        ];

        $now = now();
        foreach ($forms as $f) {
            DB::table('dosage_forms')->insertOrIgnore([
                'name'         => $f['name'],
                'abbreviation' => $f['abbreviation'],
                'route_hint'   => $f['route_hint'],
                'is_active'    => true,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }
    }

    private function seedRoutes(): void
    {
        $routes = [
            ['name' => 'Oral',              'abbreviation' => 'PO',  'description' => 'By mouth'],
            ['name' => 'Intravenous',       'abbreviation' => 'IV',  'description' => 'Into a vein'],
            ['name' => 'Intramuscular',     'abbreviation' => 'IM',  'description' => 'Into a muscle'],
            ['name' => 'Subcutaneous',      'abbreviation' => 'SC',  'description' => 'Under the skin'],
            ['name' => 'Topical',           'abbreviation' => 'TOP', 'description' => 'Applied to skin surface'],
            ['name' => 'Ophthalmic',        'abbreviation' => 'OPH', 'description' => 'Into the eye'],
            ['name' => 'Otic',              'abbreviation' => 'OTC', 'description' => 'Into the ear'],
            ['name' => 'Nasal',             'abbreviation' => 'NAS', 'description' => 'Into the nose'],
            ['name' => 'Inhalation',        'abbreviation' => 'INH', 'description' => 'Breathed into the lungs'],
            ['name' => 'Rectal',            'abbreviation' => 'PR',  'description' => 'Into the rectum'],
            ['name' => 'Vaginal',           'abbreviation' => 'PV',  'description' => 'Into the vagina'],
            ['name' => 'Sublingual',        'abbreviation' => 'SL',  'description' => 'Under the tongue'],
            ['name' => 'Buccal',            'abbreviation' => 'BUC', 'description' => 'Between cheek and gum'],
            ['name' => 'Transdermal',       'abbreviation' => 'TD',  'description' => 'Absorbed through the skin'],
            ['name' => 'Intradermal',       'abbreviation' => 'ID',  'description' => 'Into the skin layers'],
            ['name' => 'Intra-articular',   'abbreviation' => 'IA',  'description' => 'Into a joint'],
            ['name' => 'Intrathecal',       'abbreviation' => 'IT',  'description' => 'Into the spinal canal'],
        ];

        $now = now();
        foreach ($routes as $r) {
            DB::table('routes_of_administration')->insertOrIgnore([
                'name'         => $r['name'],
                'abbreviation' => $r['abbreviation'],
                'description'  => $r['description'],
                'is_active'    => true,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }
    }

    private function seedTherapeuticClasses(): void
    {
        $now = now();

        // Top-level ATC Level 1 categories
        $topLevel = [
            ['name' => 'Alimentary Tract and Metabolism',           'atc_code' => 'A'],
            ['name' => 'Blood and Blood Forming Organs',            'atc_code' => 'B'],
            ['name' => 'Cardiovascular System',                     'atc_code' => 'C'],
            ['name' => 'Dermatologicals',                           'atc_code' => 'D'],
            ['name' => 'Genito-Urinary System and Sex Hormones',    'atc_code' => 'G'],
            ['name' => 'Systemic Hormonal Preparations',            'atc_code' => 'H'],
            ['name' => 'Anti-infectives for Systemic Use',          'atc_code' => 'J'],
            ['name' => 'Antineoplastic and Immunomodulating Agents','atc_code' => 'L'],
            ['name' => 'Musculo-Skeletal System',                   'atc_code' => 'M'],
            ['name' => 'Nervous System',                            'atc_code' => 'N'],
            ['name' => 'Antiparasitic Products',                    'atc_code' => 'P'],
            ['name' => 'Respiratory System',                        'atc_code' => 'R'],
            ['name' => 'Sensory Organs',                            'atc_code' => 'S'],
            ['name' => 'Various',                                   'atc_code' => 'V'],
        ];

        $parentIds = [];
        foreach ($topLevel as $t) {
            $id = DB::table('therapeutic_classes')->insertGetId([
                'name'       => $t['name'],
                'atc_code'   => $t['atc_code'],
                'parent_id'  => null,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $parentIds[$t['atc_code']] = $id;
        }

        // Representative ATC Level 2 sub-classes (most commonly dispensed in community pharmacy)
        $subClasses = [
            // A
            ['name' => 'Antacids',                          'atc_code' => 'A02A', 'parent' => 'A'],
            ['name' => 'Drugs for Peptic Ulcer',            'atc_code' => 'A02B', 'parent' => 'A'],
            ['name' => 'Antidiabetics',                     'atc_code' => 'A10',  'parent' => 'A'],
            ['name' => 'Vitamins',                          'atc_code' => 'A11',  'parent' => 'A'],
            ['name' => 'Mineral Supplements',               'atc_code' => 'A12',  'parent' => 'A'],
            // B
            ['name' => 'Antithrombotic Agents',             'atc_code' => 'B01',  'parent' => 'B'],
            ['name' => 'Antihemorrhagics',                  'atc_code' => 'B02',  'parent' => 'B'],
            ['name' => 'Antianemic Preparations',           'atc_code' => 'B03',  'parent' => 'B'],
            // C
            ['name' => 'Cardiac Therapy',                   'atc_code' => 'C01',  'parent' => 'C'],
            ['name' => 'Antihypertensives',                 'atc_code' => 'C02',  'parent' => 'C'],
            ['name' => 'Diuretics',                         'atc_code' => 'C03',  'parent' => 'C'],
            ['name' => 'Beta-Blocking Agents',              'atc_code' => 'C07',  'parent' => 'C'],
            ['name' => 'Calcium Channel Blockers',          'atc_code' => 'C08',  'parent' => 'C'],
            ['name' => 'Agents on Renin-Angiotensin System','atc_code' => 'C09',  'parent' => 'C'],
            ['name' => 'Lipid Modifying Agents',            'atc_code' => 'C10',  'parent' => 'C'],
            // J
            ['name' => 'Antibacterials for Systemic Use',   'atc_code' => 'J01',  'parent' => 'J'],
            ['name' => 'Antimycotics for Systemic Use',     'atc_code' => 'J02',  'parent' => 'J'],
            ['name' => 'Antivirals for Systemic Use',       'atc_code' => 'J05',  'parent' => 'J'],
            ['name' => 'Vaccines',                          'atc_code' => 'J07',  'parent' => 'J'],
            // M
            ['name' => 'Anti-inflammatory and Antirheumatic Products', 'atc_code' => 'M01', 'parent' => 'M'],
            ['name' => 'Muscle Relaxants',                  'atc_code' => 'M03',  'parent' => 'M'],
            // N
            ['name' => 'Anesthetics',                       'atc_code' => 'N01',  'parent' => 'N'],
            ['name' => 'Analgesics',                        'atc_code' => 'N02',  'parent' => 'N'],
            ['name' => 'Antiepileptics',                    'atc_code' => 'N03',  'parent' => 'N'],
            ['name' => 'Antiparkinson Drugs',               'atc_code' => 'N04',  'parent' => 'N'],
            ['name' => 'Psycholeptics',                     'atc_code' => 'N05',  'parent' => 'N'],
            ['name' => 'Psychoanaleptics',                  'atc_code' => 'N06',  'parent' => 'N'],
            // P
            ['name' => 'Antiprotozoals',                    'atc_code' => 'P01',  'parent' => 'P'],
            ['name' => 'Anthelmintics',                     'atc_code' => 'P02',  'parent' => 'P'],
            // R
            ['name' => 'Nasal Preparations',                'atc_code' => 'R01',  'parent' => 'R'],
            ['name' => 'Throat Preparations',               'atc_code' => 'R02',  'parent' => 'R'],
            ['name' => 'Drugs for Obstructive Airway Diseases', 'atc_code' => 'R03', 'parent' => 'R'],
            ['name' => 'Cough and Cold Preparations',       'atc_code' => 'R05',  'parent' => 'R'],
            ['name' => 'Antihistamines for Systemic Use',   'atc_code' => 'R06',  'parent' => 'R'],
            // S
            ['name' => 'Ophthalmologicals',                 'atc_code' => 'S01',  'parent' => 'S'],
            ['name' => 'Otologicals',                       'atc_code' => 'S02',  'parent' => 'S'],
        ];

        foreach ($subClasses as $s) {
            DB::table('therapeutic_classes')->insertOrIgnore([
                'name'       => $s['name'],
                'atc_code'   => $s['atc_code'],
                'parent_id'  => $parentIds[$s['parent']] ?? null,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
