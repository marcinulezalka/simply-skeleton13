<?php

declare(strict_types=1);

/**
 * This file is part of simplysmart/skeleton13.
 *
 * Copyright (c) 2026 Marcin Ulezalka <marcin.ulezalka@simplysmart.pl>
 *
 * Licensed under the Simplysmart Restricted Source License 1.0.
 * See the LICENSE file distributed with this source code.
 */

namespace Tests\Feature\Permissions;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Schema;
use Simplysmart\Admiro\Contracts\CurrentOrganization;
use Simplysmart\Admiro\Contracts\ZoneRegistry;
use Simplysmart\Admiro\Database\Columns;
use Simplysmart\Admiro\Models\Organization;
use Simplysmart\Admiro\Models\Permissions\Permission;
use Simplysmart\Admiro\Models\Permissions\PermissionDenial;
use Simplysmart\Admiro\Permissions\OrganizationSelector;
use Simplysmart\Admiro\Permissions\PermissionTables;
use Tests\Fixtures\Zones\AlphaAccount;
use Tests\Fixtures\Zones\AlphaNote;
use Tests\Fixtures\Zones\BetaAccount;

/**
 * Organizacja żądania: strefa bez zespołów działa w organizacji operatora, strefa z zespołami w organizacji
 * wybranej przez konto spośród jego członkostw. Trasy z uprawnieniem (`zone.permission`) i wiązania rekordów
 * działają tylko w tej organizacji; po żądaniu organizacja jest czyszczona.
 */
class CurrentOrganizationTest extends ZoneRolesTestCase
{
    public function test_zone_without_teams_works_in_the_operator_organization(): void
    {
        $this->signedIn($this->account(BetaAccount::class), 'beta')
            ->get('/beta/organization')
            ->assertSee('organization:' . Organization::OPERATOR_ID);
    }

    public function test_zone_with_teams_uses_the_first_membership_and_the_chosen_one(): void
    {
        $first = $this->organization('Pierwsza');
        $second = $this->organization('Druga');
        $account = $this->account(AlphaAccount::class);
        $this->member($account, $first);
        $this->member($account, $second);
        $this->signedIn($account, 'alpha');

        $this->get('/alpha/organization')->assertSee('organization:' . $first->id);
        $this->post('/alpha/account/organization', ['organization' => $second->token])->assertRedirect('/alpha/dashboard');
        $this->get('/alpha/organization')->assertSee('organization:' . $second->id);
    }

    public function test_layout_offers_the_switch_only_with_several_organizations(): void
    {
        $account = $this->account(AlphaAccount::class);
        $this->member($account, $this->organization('Pierwsza'));
        $this->signedIn($account, 'alpha')->get('/alpha/account/security')->assertOk()->assertDontSee('class="ss-account-menu ss-context-switch"', false);

        $this->member($account, $this->organization('Druga'));
        $this->signedIn($account, 'alpha')->get('/alpha/account/security')
            ->assertSee('class="ss-account-menu ss-context-switch"', false)
            ->assertSee('<form method="POST" action="' . url('/alpha/account/organization') . '">', false)
            ->assertSee('name="organization"', false)
            ->assertSee('Druga');
    }

    public function test_account_cannot_switch_to_a_foreign_organization(): void
    {
        $account = $this->account(AlphaAccount::class);
        $this->member($account, $this->organization());

        $this->signedIn($account, 'alpha')
            ->post('/alpha/account/organization', ['organization' => $this->organization('Obca')->token])
            ->assertNotFound();
    }

    public function test_organization_left_by_the_account_is_not_used_any_more(): void
    {
        $organization = $this->organization();
        $left = $this->organization('Dawna');
        $account = $this->account(AlphaAccount::class);
        $this->member($account, $organization);
        $session = new Store('alpha_session', new ArraySessionHandler(30));
        $session->put(OrganizationSelector::SESSION_KEY, $left->id);
        $selector = $this->app->make(OrganizationSelector::class);
        $zone = $this->app->make(ZoneRegistry::class)->get('alpha');

        $this->assertSame($organization->id, $selector->select($zone, $account, $session));
        $this->assertFalse($selector->choose($account, $left, $session));
        $this->assertTrue($selector->choose($account, $organization, $session));
        $this->assertNull($selector->select($zone, null, $session));
    }

    public function test_permission_route_needs_the_permission_in_the_current_organization(): void
    {
        $organization = $this->organization();
        $analyst = $this->member($this->account(AlphaAccount::class), $organization, 'analyst');
        $guest = $this->member($this->account(AlphaAccount::class, ['email' => 'guest@example.com']), $organization, 'guest');
        $outsider = $this->account(AlphaAccount::class, ['email' => 'outsider@example.com']);

        $this->signedIn($analyst, 'alpha')->get('/alpha/reports')->assertOk();
        $this->signedIn($guest, 'alpha')->get('/alpha/reports')->assertForbidden();
        $this->signedIn($guest, 'alpha')->get('/alpha/dashboard')->assertOk();
        $this->signedIn($outsider, 'alpha')->get('/alpha/reports')->assertForbidden();
    }

    public function test_denial_closes_the_permission_route(): void
    {
        $organization = $this->organization();
        $analyst = $this->member($this->account(AlphaAccount::class), $organization, 'analyst');
        $denial = new PermissionDenial();
        $denial->forceFill([
            PermissionTables::PERMISSION_FOREIGN_KEY => Permission::findByName('reports.view', 'alpha')->getKey(),
            PermissionTables::TEAM_FOREIGN_KEY => $organization->id,
        ]);
        $denial->model()->associate($analyst);
        $denial->save();

        $this->signedIn($analyst, 'alpha')->get('/alpha/reports')->assertForbidden();
    }

    public function test_guest_visitor_is_sent_to_the_sign_in(): void
    {
        $this->get('/alpha/reports')->assertRedirect('/alpha/signin');
    }

    public function test_record_of_another_organization_is_not_found(): void
    {
        Schema::create(AlphaNote::TABLE, static function (Blueprint $table): void {
            Columns::base($table);
            $table->foreignId(PermissionTables::TEAM_FOREIGN_KEY)->constrained(Organization::TABLE);
            $table->string('title');
            Columns::closing($table);
        });
        $own = $this->organization();
        $foreign = $this->organization('Obca');
        $account = $this->member($this->account(AlphaAccount::class), $own);
        $ownNote = $this->note($own, 'nasza');
        $foreignNote = $this->note($foreign, 'obca');

        $this->signedIn($account, 'alpha')->get('/alpha/notes/' . $ownNote)->assertSee('nasza');
        $this->get('/alpha/notes/' . $foreignNote)->assertNotFound();
    }

    private function note(Organization $organization, string $title): string
    {
        $note = new AlphaNote();
        $note->forceFill(['name' => $title, PermissionTables::TEAM_FOREIGN_KEY => $organization->id, 'title' => $title])->save();

        return $note->token;
    }

    public function test_organization_does_not_leak_past_the_request(): void
    {
        $this->signedIn($this->account(BetaAccount::class), 'beta')->get('/beta/organization')->assertOk();

        $this->assertNull($this->app->make(CurrentOrganization::class)->id());
    }
}
