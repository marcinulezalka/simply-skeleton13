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

use Simplysmart\Admiro\Contracts\ZoneRegistry;
use Simplysmart\Admiro\DictionaryUpdates\DictionaryUpdateRejected;
use Simplysmart\Admiro\DictionaryUpdates\DictionaryUpdateRequests;
use Simplysmart\Admiro\DictionaryUpdates\DictionaryUpdaters;
use Simplysmart\Admiro\Enums\DictionaryUpdateStatus;
use Simplysmart\Admiro\Models\AdmiroAccount;
use Simplysmart\Admiro\Models\AuditEvent;
use Simplysmart\Admiro\Models\DictionaryUpdateRequest;
use Tests\Fixtures\Dictionaries\ShopProductsUpdater;

/**
 * Prośby stref o aktualizację słowników: jedna otwarta prośba na słownik, administrator zatwierdza (zadanie w kolejce
 * wywołuje słownik paczki i zapisuje wynik albo błąd) albo odrzuca; przeglądający widzi listę bez decyzji.
 */
class AdmiroDictionaryUpdatesTest extends AdmiroTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ShopProductsUpdater::$runs = 0;
        ShopProductsUpdater::$fail = false;
        $this->app->make(DictionaryUpdaters::class)->add(ShopProductsUpdater::class);
    }

    public function test_zone_asks_once_and_the_administrator_approval_runs_the_update(): void
    {
        $first = $this->ask();
        $this->assertSame($first->id, $this->ask()->id);
        $this->assertSame([DictionaryUpdateStatus::Pending, 'Produkty sklepu'], [$first->status, $first->name]);

        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');
        $html = (string) $this->get('/admiro/dictionary-updates')->assertOk()->getContent();
        $this->assertStringContainsString('<th scope="row">Produkty sklepu</th>', $html);
        $this->assertStringContainsString(DictionaryUpdateStatus::Pending->label(), $html);
        $this->assertStringContainsString(text('admiro::admin.dictionary_updates.approve'), $html);

        $this->post('/admiro/dictionary-updates/' . $first->token . '/approve')
            ->assertRedirect($this->zoneUrl('/admiro/dictionary-updates'))
            ->assertSessionHas('status', text('admiro::admin.dictionary_updates.approved', ['dictionary' => 'Produkty sklepu']));

        $first->refresh();
        $this->assertSame([DictionaryUpdateStatus::Done, 'Produktów: 12.', 1], [$first->status, $first->result, ShopProductsUpdater::$runs]);
        $this->assertNotNull($first->decided_by);
        $this->post('/admiro/dictionary-updates/' . $first->token . '/approve')->assertSessionHasErrors('dictionary_update');
        $this->assertSame(1, ShopProductsUpdater::$runs);
        $this->assertSame(['dictionary update approved'], AuditEvent::query()->pluck('event')->all());
    }

    public function test_failed_update_keeps_the_error_and_a_rejected_request_never_runs(): void
    {
        ShopProductsUpdater::$fail = true;
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');

        $this->post('/admiro/dictionary-updates/' . $this->ask()->token . '/approve');
        $failed = DictionaryUpdateRequest::query()->sole();
        $this->assertSame([DictionaryUpdateStatus::Failed, 'Źródło produktów nie odpowiada.'], [$failed->status, $failed->result]);

        $next = $this->ask();
        $this->assertNotSame($failed->id, $next->id);
        $this->post('/admiro/dictionary-updates/' . $next->token . '/reject')->assertSessionHas('status', text('admiro::admin.dictionary_updates.rejected', ['dictionary' => 'Produkty sklepu']));
        $this->assertSame(DictionaryUpdateStatus::Rejected, $next->refresh()->status);
        $this->assertSame(0, ShopProductsUpdater::$runs);
    }

    public function test_unknown_dictionary_is_rejected_and_a_viewer_cannot_decide(): void
    {
        $this->expectException(DictionaryUpdateRejected::class);

        try {
            $request = $this->ask();
            $this->signedIn($this->admin('support@example.com', 'support'), 'admiro');
            $this->get('/admiro/dictionary-updates')->assertOk()->assertDontSee(text('admiro::admin.dictionary_updates.approve') . '</button>', false);
            $this->post('/admiro/dictionary-updates/' . $request->token . '/approve')->assertForbidden();
            $this->assertSame(DictionaryUpdateStatus::Pending, $request->refresh()->status);
        } finally {
            $this->app->make(DictionaryUpdateRequests::class)->request('unknown', $this->app->make(ZoneRegistry::class)->get('admiro'), $this->asker ?? $this->admin('asker@example.com'), null);
        }
    }

    private ?AdmiroAccount $asker = null;

    private function ask(): DictionaryUpdateRequest
    {
        $asker = $this->asker ??= $this->admin('asker@example.com');

        return $this->app->make(DictionaryUpdateRequests::class)->request('shop-products', $this->app->make(ZoneRegistry::class)->get('admiro'), $asker, null);
    }
}
