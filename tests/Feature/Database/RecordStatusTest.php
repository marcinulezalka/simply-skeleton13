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

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\DB;
use Simplysmart\Admiro\Database\RecordStatusBackfill;
use Simplysmart\Admiro\Database\RecordStatusTransitionRejected;
use Simplysmart\Admiro\Database\RecordStatusTransitions;
use Simplysmart\Admiro\Enums\RecordStatus;
use Simplysmart\Admiro\Models\AdmiroAccount;
use Simplysmart\Admiro\Models\AuditEvent;
use Tests\Fixtures\Database\DemoItem;

/**
 * Standardowy stan wiersza (RecordStatus): nowy wiersz aktywny, dozwolone przejścia, zmiana tylko transakcyjnie przez
 * RecordStatusTransitions (z dziennikiem zdarzeń i spójnym checksum), migracja z powrotem do stanu sprzed niej,
 * uzupełnianie pustych stanów, scope usable() i blokada logowania konta w stanie innym niż aktywny.
 */
class RecordStatusTest extends DatabaseTestCase
{
    public function test_transitions_follow_the_lifecycle(): void
    {
        $allowed = [];

        foreach (RecordStatus::cases() as $from) {
            foreach (RecordStatus::cases() as $to) {
                if ($from->canTransitionTo($to)) {
                    $allowed[] = $from->value . '>' . $to->value;
                }
            }
        }

        $this->assertEqualsCanonicalizing([
            'draft>pending', 'pending>active', 'active>blocked', 'blocked>active', 'active>archived', 'quarantined>active',
            'draft>migrating', 'pending>migrating', 'active>migrating', 'blocked>migrating', 'quarantined>migrating', 'archived>migrating',
        ], $allowed);
        $this->assertSame('Zablokowany', RecordStatus::Blocked->label());
    }

    public function test_new_row_is_active(): void
    {
        $this->assertSame(RecordStatus::Active, $this->item()->fresh()?->status);
    }

    public function test_status_changes_in_a_transaction_with_an_audit_entry_and_a_valid_checksum(): void
    {
        $item = $this->item(['status' => RecordStatus::Draft]);
        $transitions = $this->app->make(RecordStatusTransitions::class);

        $transitions->transition($item, RecordStatus::Pending);
        $active = $transitions->transition($item, RecordStatus::Active, 'zatwierdzone');

        $this->assertSame(RecordStatus::Active, $active->status);
        $this->assertSame($active->computeChecksum(), DB::table(DemoItem::TABLE)->where('id', $item->id)->value('checksum'));
        $this->assertSame(['table' => DemoItem::TABLE, 'token' => $item->token, 'from' => 'pending', 'to' => 'active', 'reason' => 'zatwierdzone'], AuditEvent::query()->where('event', 'record status changed')->orderByDesc('id')->firstOrFail()->context);
    }

    public function test_forbidden_transition_changes_nothing(): void
    {
        $item = $this->item();

        try {
            $this->app->make(RecordStatusTransitions::class)->transition($item, RecordStatus::Draft);
            $this->fail('Przejście active → draft powinno zostać odrzucone.');
        } catch (RecordStatusTransitionRejected) {
            $this->assertSame(RecordStatus::Active, $item->fresh()?->status);
            $this->assertSame(0, AuditEvent::query()->where('event', 'record status changed')->count());
        }
    }

    public function test_migration_returns_to_the_status_before_it(): void
    {
        $item = $this->item();
        $transitions = $this->app->make(RecordStatusTransitions::class);
        $transitions->transition($item, RecordStatus::Blocked);

        $migrating = $transitions->transition($item, RecordStatus::Migrating);
        $this->assertSame(['record_status' => ['previous' => 'blocked']], $migrating->json_migrations);

        $back = $transitions->finishMigration($item);
        $this->assertSame(RecordStatus::Blocked, $back->status);
        $this->assertNull($back->json_migrations);

        $this->expectException(RecordStatusTransitionRejected::class);
        $transitions->finishMigration($item);
    }

    public function test_empty_statuses_are_filled_with_a_recomputed_checksum(): void
    {
        $item = $this->item();
        DB::table(DemoItem::TABLE)->where('id', $item->id)->update(['status' => null]);
        $updatedAt = DB::table(DemoItem::TABLE)->where('id', $item->id)->value('updated_at');

        $this->app->make(RecordStatusBackfill::class)->fill([DemoItem::class]);

        $fresh = $item->fresh();
        $this->assertSame(RecordStatus::Active, $fresh?->status);
        $this->assertSame($fresh->computeChecksum(), $fresh->checksum);
        $this->assertSame($updatedAt, DB::table(DemoItem::TABLE)->where('id', $item->id)->value('updated_at'));
    }

    public function test_usable_rows_are_active_and_switched_on(): void
    {
        $this->item(['code' => 'ON', 'is_active' => true]);
        $this->item(['code' => 'OFF', 'is_active' => false]);
        $this->item(['code' => 'BLOCKED', 'is_active' => true, 'status' => RecordStatus::Blocked]);

        $this->assertSame(['ON'], DemoItem::query()->usable()->pluck('code')->all());
    }

    public function test_account_outside_the_active_status_cannot_sign_in(): void
    {
        $account = (new AdmiroAccount())->forceFill(['is_active' => true]);
        $this->assertTrue($account->canSignIn());

        $account->forceFill(['status' => RecordStatus::Blocked]);
        $this->assertFalse($account->canSignIn());
    }
}
