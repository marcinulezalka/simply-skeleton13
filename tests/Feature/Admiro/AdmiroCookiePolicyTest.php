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
use Simplysmart\Admiro\Enums\ConsentDecision;
use Simplysmart\Admiro\Enums\LegalDocumentType;
use Simplysmart\Admiro\Models\AuditEvent;
use Simplysmart\Admiro\Models\LegalDocument;

/**
 * Polityka cookies w panelu jako dokument prawny (`legal-documents/cookies`): szkic w językach interfejsu, publikacja
 * tylko z treścią w języku domyślnym, kolejne wersje, liczba zgód i opis ponownej zgody (tylko przy polityce cookies),
 * podgląd bez zmian dla `settings.view` i każda zmiana w dzienniku zdarzeń.
 */
class AdmiroCookiePolicyTest extends AdmiroTestCase
{
    private const string PAGE = '/admiro/legal-documents/cookies';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDictionaries();
    }

    public function test_owner_saves_a_draft_and_publishes_versions(): void
    {
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');

        $this->put(self::PAGE, ['title' => ['en' => 'Cookies'], 'body' => ['en' => 'Text']])->assertRedirect(self::PAGE);
        $this->post(self::PAGE . '/publish');
        $this->assertSessionError('document', text('admiro::admin.legal_document_rejected'));

        $this->put(self::PAGE, ['title' => ['pl' => 'Polityka cookies', 'en' => 'Cookie policy'], 'body' => ['pl' => '## Administrator', 'en' => '## Controller']]);
        $this->get(self::PAGE)->assertOk()->assertSee(text('admiro::admin.cookie_policy_publish_lead'));
        $this->post(self::PAGE . '/publish')->assertRedirect()->assertSessionHas('status', text('admiro::admin.legal_document_published', ['document' => LegalDocumentType::Cookies->label(), 'version' => '1']));

        $this->put(self::PAGE, ['title' => ['pl' => 'Polityka cookies'], 'body' => ['pl' => '## Administrator danych']]);
        $this->post(self::PAGE . '/publish');

        $cookies = LegalDocument::query()->where('document', LegalDocumentType::Cookies);
        $this->assertSame([2, 1], $cookies->clone()->whereNotNull('version')->orderByDesc('version')->pluck('version')->all());
        $this->assertSame(['title' => 'Polityka cookies', 'body' => '## Administrator'], $cookies->clone()->where('version', 1)->sole()->content('pl'));
        $this->postJson('/cookies/consent', ['consent_id' => (string) Str::uuid(), 'version' => 2, 'decision' => 'accepted', 'categories' => ['analytics']])->assertCreated();
        $this->get(self::PAGE)->assertOk()
            ->assertSee('## Administrator danych')
            ->assertSee(text('admiro::admin.legal_document_new_draft'))
            ->assertSee(text('admiro::admin.cookie_policy_lead'))
            ->assertSeeInOrder([text('admiro::admin.cookie_policy_consents', ['days' => 30]), ConsentDecision::Accepted->label(), '1</dd>'], false);
        $this->assertSame(['legal document draft saved', 'legal document draft saved', 'legal document published', 'legal document draft saved', 'legal document published'], AuditEvent::query()->orderBy('id')->pluck('event')->all());
    }

    public function test_viewer_reads_the_policy_but_cannot_change_it(): void
    {
        $this->signedIn($this->admin('support@example.com', 'support'), 'admiro');

        $this->get(self::PAGE)->assertOk()->assertDontSee(text('admiro::admin.legal_document_save'));
        $this->put(self::PAGE, ['title' => ['pl' => 'X'], 'body' => ['pl' => 'Y']])->assertForbidden();
        $this->post(self::PAGE . '/publish')->assertForbidden();
        $this->assertSame(0, LegalDocument::query()->count());
    }

    public function test_other_documents_have_no_consent_counts(): void
    {
        $this->signedIn($this->admin('support@example.com', 'support'), 'admiro');

        $this->get('/admiro/legal-documents/privacy')->assertOk()
            ->assertDontSee(text('admiro::admin.cookie_policy_consents', ['days' => 30]))
            ->assertSee(text('admiro::admin.legal_document_lead'));
    }
}
