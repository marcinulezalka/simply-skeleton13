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

use Illuminate\Support\Str;
use Simplysmart\Mainboard\Models\AuditEvent;
use Simplysmart\Mainboard\Models\CookiePolicy;

/**
 * Polityka cookies w panelu: szkic w językach interfejsu, publikacja tylko z treścią w języku domyślnym, kolejne
 * wersje, liczba zgód, podgląd bez zmian dla `settings.view` i każda zmiana w dzienniku zdarzeń.
 */
class AdmiroCookiePolicyTest extends AdmiroTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDictionaries();
    }

    public function test_owner_saves_a_draft_and_publishes_versions(): void
    {
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');

        $this->put('/admiro/cookie-policy', ['title' => ['en' => 'Cookies'], 'body' => ['en' => 'Text']])->assertRedirect('/admiro/cookie-policy');
        $this->post('/admiro/cookie-policy/publish');
        $this->assertSessionError('policy', text('admiro::admin.cookie_policy_rejected'));

        $this->put('/admiro/cookie-policy', ['title' => ['pl' => 'Polityka cookies', 'en' => 'Cookie policy'], 'body' => ['pl' => '## Administrator', 'en' => '## Controller']]);
        $this->post('/admiro/cookie-policy/publish')->assertRedirect()->assertSessionHas('status', text('admiro::admin.cookie_policy_published', ['version' => '1']));

        $this->put('/admiro/cookie-policy', ['title' => ['pl' => 'Polityka cookies'], 'body' => ['pl' => '## Administrator danych']]);
        $this->post('/admiro/cookie-policy/publish');

        $this->assertSame([2, 1], CookiePolicy::query()->whereNotNull('version')->orderByDesc('version')->pluck('version')->all());
        $this->assertSame(['title' => 'Polityka cookies', 'body' => '## Administrator'], CookiePolicy::query()->where('version', 1)->sole()->content('pl'));
        $this->postJson('/cookies/consent', ['consent_id' => (string) Str::uuid(), 'version' => 2, 'decision' => 'accepted', 'categories' => ['analytics']])->assertCreated();
        $this->get('/admiro/cookie-policy')->assertOk()->assertSee('## Administrator danych')->assertSee(text('admiro::admin.cookie_policy_new_draft'));
        $this->assertSame(['cookie policy draft saved', 'cookie policy draft saved', 'cookie policy published', 'cookie policy draft saved', 'cookie policy published'], AuditEvent::query()->orderBy('id')->pluck('event')->all());
    }

    public function test_viewer_reads_the_policy_but_cannot_change_it(): void
    {
        $this->signedIn($this->admin('support@example.com', 'support'), 'admiro');

        $this->get('/admiro/cookie-policy')->assertOk()->assertDontSee(text('admiro::admin.cookie_policy_save'));
        $this->put('/admiro/cookie-policy', ['title' => ['pl' => 'X'], 'body' => ['pl' => 'Y']])->assertForbidden();
        $this->post('/admiro/cookie-policy/publish')->assertForbidden();
        $this->assertSame(0, CookiePolicy::query()->count());
    }
}
