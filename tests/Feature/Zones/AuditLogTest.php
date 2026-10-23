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

namespace Tests\Feature\Zones;

use Illuminate\Support\Facades\DB;
use Simplysmart\Mainboard\Audit\Events\AdminActionPerformed;
use Simplysmart\Mainboard\Auth\AccountActor;
use Simplysmart\Mainboard\Models\AuditEvent;
use Simplysmart\Mainboard\Queries\Audit\AuditLog;
use Simplysmart\Mainboard\View\AuditEventLabel;
use Simplysmart\Mainboard\Zones\ZoneDefinitionFactory;
use Tests\Fixtures\Zones\AlphaAccount;

/**
 * Dziennik zdarzeń w tabeli: logowanie i akcje administratorów z aktorem, IP szyfrowane, bez adresów e-mail,
 * czyszczenie po czasie przechowywania.
 */
class AuditLogTest extends SignInTestCase
{
    public function test_sign_in_is_stored_with_an_encrypted_ip_and_no_e_mail(): void
    {
        $account = $this->account(AlphaAccount::class, pin: self::PIN);

        $this->requestCode();
        $this->submitCode($this->sentCode());
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->submitPin(self::PIN);

        $entry = AuditEvent::query()->where('event', 'sign-in')->sole();
        $this->assertSame('alpha', $entry->zone);
        $id = $account->getKey();
        $this->assertIsInt($id);
        $this->assertSame((string) $id, $entry->account_id);
        $this->assertSame('203.0.113.9', $entry->ip);
        $this->assertNotSame('203.0.113.9', DB::table(AuditEvent::TABLE)->value('ip'));
        $this->assertStringNotContainsString(self::EMAIL, (string) json_encode(DB::table(AuditEvent::TABLE)->get()));
    }

    public function test_admin_action_is_stored_with_its_actor(): void
    {
        event(new AdminActionPerformed('settings updated', new AccountActor('beta', 7), context: ['language' => 'pl']));

        $entry = AuditEvent::query()->sole();
        $this->assertSame('settings updated', $entry->event);
        $this->assertSame('beta', $entry->actor_zone);
        $this->assertSame('7', $entry->actor_id);
        $this->assertSame(['language' => 'pl'], $entry->context);
    }

    public function test_organization_log_filters_one_event_and_lists_its_events(): void
    {
        $actor = new AccountActor('alpha', 1);
        event(new AdminActionPerformed('first action', $actor, organizationId: 5));
        event(new AdminActionPerformed('second action', $actor, organizationId: 5));
        event(new AdminActionPerformed('first action', $actor, organizationId: 6));
        $log = $this->app->make(AuditLog::class);

        $this->assertSame(['first action', 'second action'], $log->eventsForOrganization(5, 'alpha', []));
        $this->assertSame(['first action'], array_map(static fn(AuditEvent $entry): string => $entry->event, $log->forOrganization(5, 'alpha', [], 10, 'first action')->items()));
        $this->assertSame(2, $log->forOrganization(5, 'alpha', [], 10)->total());
    }

    public function test_event_labels_come_from_the_zone_package_then_core_then_the_event(): void
    {
        $zone = $this->app->make(ZoneDefinitionFactory::class)->make(['name' => 'shop', 'texts' => 'shop'], 'shop', __DIR__);

        $this->assertSame(text('audit.events.sign_in'), AuditEventLabel::of('sign-in'));
        $this->assertSame(text('audit.events.role_assigned'), AuditEventLabel::of('role assigned', $zone));
        $this->assertSame('custom thing', AuditEventLabel::of('custom thing', $zone));
    }

    public function test_prune_removes_entries_past_the_retention(): void
    {
        event(new AdminActionPerformed('old', new AccountActor('beta', 7)));
        $this->travel(366)->days();
        event(new AdminActionPerformed('new', new AccountActor('beta', 7)));

        $this->command('mainboard:audit:prune')->assertSuccessful();

        $this->assertSame(['new'], AuditEvent::query()->pluck('event')->all());
    }
}
