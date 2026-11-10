<?php

use App\Enums\Role;
use App\Support\Permissions;
use Illuminate\Support\Facades\Gate;

/** AGENTS.md section 5: the SA never reaches patient, prescription, sales, cost or financial data; roles add up. */
beforeEach(fn () => seedRoles());

it('denies the SA every business-data route and allows the other roles', function () {
    actAs(sa());
    $this->get('/pharmacy/business-data')->assertForbidden();

    foreach ([owner(), pm(), ps()] as $user) {
        actAs($user);
        $this->get('/pharmacy/business-data')->assertOk();
    }
});

it('gives the SA no permission that touches business data', function () {
    $business = ['catalogue.manage', 'price.set', 'stock.receive', 'stock.receipt.confirm', 'stock.adjust.request', 'sales.sell', 'sales.void.request', 'reports.financial', 'staff.manage', 'staff.view.own'];
    $sa = Permissions::forRole(Role::SystemAdmin, activeOnly: false);

    expect(array_intersect($sa, $business))->toBe([]);
});

it('keeps every later-stage permission inactive in Stage 1 except Stage 2 active permissions', function () {
    foreach (['sales.sell', 'stock.receive', 'backup.run', 'reports.financial'] as $p) {
        expect(Permissions::matrix()[$p]['active'])->toBeFalse($p);
        expect(Gate::has($p))->toBeFalse($p);
    }
});

it('adds permissions up across the roles a person holds', function () {
    $both = makeUser([Role::PharmacyStaff, Role::PharmacyManager]);

    expect($both->hasPermission('staff.manage'))->toBeTrue()->and($both->hasPermission('staff.view.own'))->toBeTrue()->and($both->hasPermission('users.manage'))->toBeFalse();
});

it('uses 403 pages for denied access to a restricted route', function () {
    actAs(ps());
    foreach (['/users', '/audit', '/settings', '/staff/create'] as $url) {
        $this->get($url)->assertForbidden();
    }
});

it('shows a translated 403 page', function () {
    actAs(ps());
    $this->get('/users')->assertForbidden()->assertSee(__('common.forbidden_body'));
    $this->get('/locale/am');
    $this->get('/users')->assertForbidden()->assertSee('መዳረሻ ተከልክሏል');
});
