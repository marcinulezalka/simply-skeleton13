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

use Simplysmart\Admiro\Enums\LegalDocumentType;
use Simplysmart\Admiro\Models\AuditEvent;
use Simplysmart\Admiro\Models\LegalDocument;

/**
 * Dokumenty prawne Core w panelu admiro (polityka prywatności, RODO): szkic i publikacja wersji każdego dokumentu
 * osobno, menu ustawień z aktywnym dokumentem, podgląd bez zmian dla `settings.view` i każda zmiana w dzienniku.
 */
class AdmiroLegalDocumentsTest extends AdmiroTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDictionaries();
    }

    public function test_owner_saves_a_draft_and_publishes_versions_of_one_document(): void
    {
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');

        $this->put('/admiro/legal-documents/privacy', ['title' => ['en' => 'Privacy'], 'body' => ['en' => 'Text']])->assertRedirect($this->zoneUrl('/admiro/legal-documents/privacy'));
        $this->post('/admiro/legal-documents/privacy/publish');
        $this->assertSessionError('document', text('admiro::admin.legal_document_rejected'));

        $this->put('/admiro/legal-documents/privacy', ['title' => ['pl' => 'Polityka prywatności'], 'body' => ['pl' => '## Administrator']]);
        $this->post('/admiro/legal-documents/privacy/publish')->assertRedirect()->assertSessionHas('status', text('admiro::admin.legal_document_published', ['document' => LegalDocumentType::Privacy->label(), 'version' => '1']));

        $this->assertSame(1, LegalDocument::query()->where('document', 'privacy')->whereNotNull('version')->count());
        $this->assertSame(0, LegalDocument::query()->where('document', 'gdpr')->count());
        $this->get('/polityka-prywatnosci')->assertOk()->assertSee('<h2 id="administrator">Administrator</h2>', false);
        $html = (string) $this->get('/admiro/legal-documents/privacy')->assertOk()->assertSee(text('admiro::admin.legal_document_new_draft'))->getContent();
        $link = static fn(string $document): string => '~href="' . preg_quote(route('admiro.legal-documents.index', ['document' => $document]), '~') . '"[^>]*aria-current="page"~';
        $this->assertMatchesRegularExpression($link('privacy'), $html);
        $this->assertDoesNotMatchRegularExpression($link('gdpr'), $html);
        $this->assertSame(['legal document draft saved', 'legal document draft saved', 'legal document published'], AuditEvent::query()->orderBy('id')->pluck('event')->all());
    }

    public function test_viewer_reads_a_document_but_cannot_change_it_and_unknown_documents_are_missing(): void
    {
        $this->signedIn($this->admin('support@example.com', 'support'), 'admiro');

        $this->get('/admiro/legal-documents/gdpr')->assertOk()->assertDontSee(text('admiro::admin.legal_document_save'));
        $this->put('/admiro/legal-documents/gdpr', ['title' => ['pl' => 'X'], 'body' => ['pl' => 'Y']])->assertForbidden();
        $this->post('/admiro/legal-documents/gdpr/publish')->assertForbidden();
        $this->get('/admiro/legal-documents/terms')->assertNotFound();
        $this->assertSame(0, LegalDocument::query()->count());
    }
}
