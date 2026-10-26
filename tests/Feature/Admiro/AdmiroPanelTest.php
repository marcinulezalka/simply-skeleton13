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

namespace Tests\Feature\Admiro;

use Simplysmart\Admiro\Models\AdmiroAccount;
use Simplysmart\Admiro\Contracts\CurrentOrganization;
use Simplysmart\Admiro\Models\Organization;
use Simplysmart\Admiro\Models\ZoneAccount;
use Simplysmart\Admiro\Panel\Enums\OnboardingStep;
use Simplysmart\Admiro\Panel\Onboarding\OnboardingProgress;
use Simplysmart\Admiro\Permissions\PermissionFiles;
use Simplysmart\Admiro\Permissions\ZoneOwnerProvisioners;
use Tests\Fixtures\Zones\AlphaAccount;
use Tests\Fixtures\Zones\BetaAccount;

/**
 * Panel administratora admiro: sekcje według uprawnień, konta stref (podgląd, reset PIN-u, odblokowanie,
 * wyłączenie), ochrona innych administratorów, role administratorów bez eskalacji, zakładanie właścicieli
 * stref i pierwszy administrator z komendy.
 */
class AdmiroPanelTest extends AdmiroTestCase
{
    public function test_dashboard_shows_only_permitted_sections(): void
    {
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro')->get('/admiro')
            ->assertSee(text('admiro::admin.zones_title'))
            ->assertSee(text('admiro::admin.administrators_title'));

        $this->signedIn($this->admin('guest@example.com'), 'admiro')->get('/admiro')
            ->assertSee(text('admiro::admin.no_sections'))
            ->assertDontSee(text('admiro::admin.zones_title'));
    }

    public function test_onboarding_page_lists_the_steps_in_order_for_the_permitted_ones(): void
    {
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro')->get('/admiro/onboarding')
            ->assertSeeInOrder([
                text('admiro::dashboard.onboarding_title'),
                text('admiro::dashboard.onboarding_lead'),
                text('admiro::dashboard.onboarding_progress', ['done' => '1', 'total' => '8']),
                text('admiro::dashboard.onboarding_installation_title'),
                text('admiro::dashboard.onboarding_languages_title'),
                text('admiro::dashboard.onboarding_settings_title'),
                text('admiro::dashboard.onboarding_organizations_title'),
                text('admiro::dashboard.onboarding_administrators_title'),
                text('admiro::dashboard.onboarding_owners_title'),
                text('admiro::dashboard.onboarding_audit_title'),
                text('admiro::dashboard.onboarding_legal_title'),
            ])
            ->assertSee('<span class="ss-onboarding__number" aria-hidden="true">08</span>', false)
            ->assertSee('<div class="ss-context__actions ss-context__actions--progress" role="group"', false)
            ->assertSee('<progress class="ss-progress" id="ss-onboarding-progress" value="1" max="8" aria-labelledby="ss-onboarding-progress-label">', false)
            ->assertSee('<details class="ss-onboarding__progress-menu" data-ss-dropdown>', false)
            ->assertSee('<div class="ss-onboarding__progress-setting" data-ss-hide-done-control hidden>', false)
            ->assertSee('data-ss-hide-done="ss-onboarding-steps"', false)
            ->assertSee(text('admiro::dashboard.onboarding_hide_done'))
            ->assertSee('<li class="ss-onboarding__step" value="1" data-ss-done>', false)
            ->assertSee('<li class="ss-onboarding__step" value="2">', false)
            ->assertSee('<span class="ss-badge ss-badge--outline ss-badge--success">' . text('admiro::dashboard.onboarding_states.done') . '</span>', false)
            ->assertSee('<a class="ss-card ss-onboarding__link ss-card--muted" href="' . url('/admiro/onboarding/installation') . '">', false)
            ->assertSee('<a class="ss-card ss-onboarding__link" href="' . url('/admiro/onboarding/languages') . '">', false)
            ->assertDontSee('<section class="ss-card ss-onboarding"', false);

        $this->signedIn($this->admin('support@example.com', 'support'), 'admiro')->get('/admiro/onboarding')
            ->assertOk()
            ->assertSee('<a class="ss-card ss-onboarding__link" href="' . url('/admiro/onboarding/audit') . '">', false)
            ->assertDontSee(url('/admiro/onboarding/installation'), false)
            ->assertSee(text('admiro::dashboard.onboarding_progress', ['done' => '0', 'total' => '1']));
        $this->get('/admiro')->assertOk()->assertSee(text('admiro::admin.zones_title'))->assertDontSee('<ol class="ss-onboarding__steps', false);
    }

    public function test_steps_done_in_the_panel_are_marked_done_and_advance_the_progress(): void
    {
        $hero = $this->admin('hero@example.com', 'hero');
        $newcomer = $this->admin('new@example.com');

        $this->signedIn($hero, 'admiro')->post('/admiro/organizations', ['name' => 'Firma'])->assertRedirect('/admiro/organizations');
        $this->post('/admiro/administrators/' . $this->routeKey($newcomer) . '/roles', ['role' => 'support'])->assertRedirect('/admiro/administrators');

        $this->get('/admiro/onboarding')
            ->assertSee(text('admiro::dashboard.onboarding_progress', ['done' => '3', 'total' => '8']))
            ->assertSee('<a class="ss-card ss-onboarding__link" href="' . url('/admiro/onboarding/settings') . '">', false)
            ->assertSee('<a class="ss-card ss-onboarding__link ss-card--muted" href="' . url('/admiro/onboarding/organizations') . '">', false)
            ->assertSee('<a class="ss-card ss-onboarding__link ss-card--muted" href="' . url('/admiro/onboarding/administrators') . '">', false);
    }

    public function test_legal_step_needs_every_document_and_the_section_goes_once_everything_is_done(): void
    {
        $progress = $this->app->make(OnboardingProgress::class);
        $legal = OnboardingStep::Legal->milestones();

        foreach (OnboardingStep::cases() as $step) {
            if ($step !== OnboardingStep::Legal) {
                $progress->mark($step->value);
            }
        }

        foreach (array_slice($legal, 0, -1) as $milestone) {
            $progress->mark($milestone);
        }

        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro')->get('/admiro/onboarding')
            ->assertSee(text('admiro::dashboard.onboarding_step', ['number' => '8']) . ' · ' . text('admiro::dashboard.onboarding_states.in_progress'))
            ->assertSee(text('admiro::dashboard.onboarding_progress', ['done' => '7', 'total' => '8']));

        $lastLegal = end($legal);
        $this->assertIsString($lastLegal);
        $progress->mark($lastLegal);

        $this->get('/admiro/onboarding')
            ->assertSee(text('admiro::dashboard.onboarding_progress', ['done' => '8', 'total' => '8']))
            ->assertSee(text('admiro::dashboard.onboarding_complete'));
    }

    public function test_admin_without_a_role_cannot_open_the_sections(): void
    {
        $guest = $this->admin('guest@example.com');

        foreach (['/admiro/zones', '/admiro/owners/create', '/admiro/administrators'] as $path) {
            $this->signedIn($guest, 'admiro')->get($path)->assertForbidden();
        }
    }

    public function test_support_views_accounts_without_actions(): void
    {
        $support = $this->admin('support@example.com', 'support');
        $account = $this->account(AlphaAccount::class, pin: '159357');

        $this->signedIn($support, 'admiro')->get('/admiro/zones')->assertOk()->assertSee('alpha')->assertDontSee('>open<', false);
        $this->get('/admiro/zones/alpha/accounts')->assertOk()->assertSee('anna@example.com')->assertDontSee(text('admiro::admin.action.reset-pin'));
        $this->post('/admiro/zones/alpha/accounts/' . $this->routeKey($account) . '/reset-pin')->assertForbidden();
    }

    public function test_accounts_are_found_by_exact_e_mail(): void
    {
        $this->account(AlphaAccount::class);
        $this->account(AlphaAccount::class, ['email' => 'jan@example.com', 'name' => 'Jan Kowalski']);

        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro')
            ->get('/admiro/zones/alpha/accounts?email=JAN@example.com')
            ->assertSee('Jan Kowalski')
            ->assertDontSee('anna@example.com');
    }

    public function test_owner_resets_unlocks_and_disables_zone_accounts(): void
    {
        $account = $this->account(AlphaAccount::class, ['locked_until' => now()->addHour(), 'lock_level' => 1], '159357');
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');
        $url = '/admiro/zones/alpha/accounts/' . $this->routeKey($account);

        $this->post($url . '/unlock')->assertRedirect('/admiro/zones/alpha/accounts');
        $this->post($url . '/reset-pin');
        $this->post($url . '/deactivate');

        $account->refresh();
        $this->assertFalse($account->isLocked());
        $this->assertFalse($account->hasPin());
        $this->assertFalse($account->canSignIn());
        $this->assertSame(3, $account->authEpoch());
    }

    public function test_unknown_zone_account_or_action_is_not_found(): void
    {
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');

        $this->get('/admiro/zones/open/accounts')->assertNotFound();
        $this->post('/admiro/zones/alpha/accounts/missing/unlock')->assertNotFound();
        $this->post('/admiro/zones/alpha/accounts/' . $this->routeKey($this->account(AlphaAccount::class)) . '/delete')->assertNotFound();
    }

    public function test_administrator_with_more_permissions_is_protected(): void
    {
        $hero = $this->admin('hero@example.com', 'hero');
        $owner = $this->admin('owner@example.com', 'owner');

        $this->signedIn($owner, 'admiro')->post('/admiro/zones/admiro/accounts/' . $this->routeKey($hero) . '/deactivate')->assertForbidden();
        $this->assertTrue($hero->fresh()?->canSignIn());

        $this->signedIn($hero, 'admiro')->post('/admiro/zones/admiro/accounts/' . $this->routeKey($owner) . '/deactivate')->assertRedirect();
        $this->assertFalse($owner->fresh()?->canSignIn());
    }

    public function test_own_account_is_not_changed_in_the_panel(): void
    {
        $hero = $this->admin('hero@example.com', 'hero');

        $this->signedIn($hero, 'admiro')->post('/admiro/zones/admiro/accounts/' . $this->routeKey($hero) . '/deactivate');

        $this->assertTrue($hero->fresh()?->canSignIn());
        $this->assertSessionError('account', text('admiro::admin.own_account'));
    }

    public function test_roles_are_assigned_without_escalation(): void
    {
        $owner = $this->admin('owner@example.com', 'owner');
        $hero = $this->admin('hero@example.com', 'hero');
        $newcomer = $this->admin('new@example.com');

        $this->signedIn($owner, 'admiro')->post('/admiro/administrators/' . $this->routeKey($newcomer) . '/roles', ['role' => 'hero']);
        $this->assertSessionError('role', text('admiro::admin.role_denied'));
        $this->assertFalse($this->hasRole($newcomer, 'hero'));

        $this->signedIn($hero, 'admiro')->post('/admiro/administrators/' . $this->routeKey($newcomer) . '/roles', ['role' => 'support'])->assertRedirect('/admiro/administrators');
        $this->assertTrue($this->hasRole($newcomer, 'support'));

        $this->delete('/admiro/administrators/' . $this->routeKey($newcomer) . '/roles/support');
        $this->assertFalse($this->hasRole($newcomer, 'support'));
        $this->assertSame(1, $newcomer->fresh()?->authEpoch());
    }

    public function test_administrators_page_lists_roles(): void
    {
        $this->admin('support@example.com', 'support');

        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro')
            ->get('/admiro/administrators')
            ->assertOk()
            ->assertSee('support@example.com')
            ->assertSee(text('roles.admiro.support'));
    }

    public function test_owner_of_another_zone_is_provisioned_through_its_contract(): void
    {
        $provisioners = $this->app->make(ZoneOwnerProvisioners::class);
        $provisioners->addDefault('beta');
        $provisioners->addDefault('alpha');
        $this->app->make(PermissionFiles::class)->add(__DIR__ . '/../../Fixtures/Permissions');
        $this->command('admiro:permissions:sync', ['--zone' => 'beta'])->assertSuccessful();
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');

        $this->get('/admiro/owners/create')->assertOk()->assertSee('beta');
        $this->post('/admiro/owners', ['zone' => 'beta', 'name' => 'Jan Kowalski', 'email' => 'jan@example.com'])
            ->assertRedirect('/admiro/owners/create');
        $this->post('/admiro/owners', ['zone' => 'alpha', 'name' => 'Ewa Nowak', 'email' => 'ewa@example.com']);

        $this->assertSame(1, BetaAccount::query()->count());
        $this->assertSame(0, AlphaAccount::query()->count());
        $this->assertSessionError('organization', text('admiro::admin.owner_organization_required'));
    }

    public function test_first_administrator_comes_from_the_command_with_a_role(): void
    {
        $this->command('admiro:account:create', ['email' => 'root@example.com', 'name' => 'Root', '--role' => ['hero']])->assertSuccessful();
        $this->command('admiro:account:create', ['email' => 'bad@example.com', 'name' => 'Bad', '--role' => ['emperor']])->assertFailed();

        $this->assertTrue($this->hasRole(AdmiroAccount::query()->sole(), 'hero'));
    }


    private function hasRole(ZoneAccount $account, string $role): bool
    {
        return $this->app->make(CurrentOrganization::class)->within(Organization::OPERATOR_ID, static function () use ($account, $role): bool {
            $account->unsetRelation('roles');

            return $account->hasRole($role);
        });
    }
}
