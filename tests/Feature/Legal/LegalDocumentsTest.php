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

namespace Tests\Feature\Legal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Simplysmart\Mainboard\Cookies\CookiePolicies;
use Simplysmart\Mainboard\Enums\LegalDocumentType;
use Simplysmart\Mainboard\Legal\LegalDocumentRejected;
use Simplysmart\Mainboard\Legal\LegalDocuments;
use Simplysmart\Mainboard\Models\CookiePolicy;
use Simplysmart\Mainboard\Models\LegalDocument;
use Simplysmart\Mainboard\Zones\ZoneDefinitionFactory;
use Tests\Fixtures\Zones\AlphaAccount;
use Tests\TestCase;

/**
 * Dokumenty prawne Core (polityka prywatności, klauzula RODO): wersje każdego dokumentu osobno, publiczne strony
 * z „Wstecz” i „Zobacz także”, seeder pierwszych wersji wszystkich polityk i `legal_documents` w configu strefy.
 */
class LegalDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishing_needs_default_language_content_and_numbers_versions_per_document(): void
    {
        $documents = $this->app->make(LegalDocuments::class);
        $documents->saveDraft(LegalDocumentType::Privacy, ['en' => ['title' => 'Privacy', 'body' => 'Text']]);

        try {
            $documents->publish(LegalDocumentType::Privacy, 'pl');
            $this->fail('A draft without default language content must not be published.');
        } catch (LegalDocumentRejected) {
        }

        $documents->saveDraft(LegalDocumentType::Privacy, ['pl' => ['title' => 'Prywatność', 'body' => 'Treść']]);
        $this->assertSame(1, $documents->publish(LegalDocumentType::Privacy, 'pl')->version);
        $this->assertSame(['title' => 'Prywatność', 'body' => 'Treść'], $documents->draft(LegalDocumentType::Privacy)->content('pl'));
        $this->assertSame(2, $documents->publish(LegalDocumentType::Privacy, 'pl')->version);

        $documents->saveDraft(LegalDocumentType::Gdpr, ['pl' => ['title' => 'RODO', 'body' => 'Treść']]);
        $this->assertSame(1, $documents->publish(LegalDocumentType::Gdpr, 'pl')->version);
    }

    public function test_page_shows_the_current_version_with_a_back_link_and_related_documents(): void
    {
        $this->get('/polityka-prywatnosci')->assertNotFound();

        $this->publish(LegalDocumentType::Privacy, 'Polityka prywatności', "## Administrator\n\n<script>alert(1)</script>Treść");
        $this->publish(LegalDocumentType::Gdpr, 'Klauzula RODO', 'Treść');

        $this->get('/polityka-prywatnosci', ['referer' => url('/admiro/signin')])
            ->assertOk()
            ->assertSee('<h1 class="ss-content-page__title"><span class="ss-icon ss-content-page__title-icon"', false)
            ->assertSee('Polityka prywatności')
            ->assertSee('<h2>Administrator</h2>', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('class="ss-link ss-content-page__back" href="' . url('/admiro/signin') . '"', false)
            ->assertSee('<a class="ss-related" href="' . route('mainboard.legal.gdpr') . '"', false)
            ->assertDontSee('<a class="ss-related" href="' . route('mainboard.legal.privacy') . '"', false);

        $this->get('/rodo', ['referer' => 'https://example.org/'])
            ->assertOk()
            ->assertSee('class="ss-link ss-content-page__back" href="' . url('/') . '"', false)
            ->assertDontSee('href="https://example.org/"', false);
    }

    public function test_seeder_publishes_every_policy_once_with_the_data_controller(): void
    {
        config(['mainboard.legal.controller' => ['name' => 'Jan Kowalski', 'email' => 'dane@example.com']]);

        $this->command('mainboard:legal:seed')->assertSuccessful();
        $this->command('mainboard:legal:seed')->assertSuccessful();

        $privacy = LegalDocument::query()->where('document', LegalDocumentType::Privacy->value)->sole()->content('pl');
        $this->assertSame('Polityka prywatności', $privacy['title']);
        $this->assertStringContainsString('Jan Kowalski, e-mail: dane@example.com', $privacy['body']);
        $this->assertStringNotContainsString(':name', $privacy['body']);
        $this->assertSame(1, LegalDocument::query()->where('document', LegalDocumentType::Gdpr->value)->count());
        $this->assertSame(1, CookiePolicy::query()->whereNotNull('version')->count());
    }

    public function test_seeder_leaves_documents_that_already_have_a_version(): void
    {
        $policies = $this->app->make(CookiePolicies::class);
        $policies->saveDraft(['pl' => ['title' => 'Własna polityka cookies', 'body' => 'Treść']]);
        $policies->publish('pl');

        $this->command('mainboard:legal:seed')->assertSuccessful();

        $this->assertSame(['Własna polityka cookies'], CookiePolicy::query()->get()->map(static fn(CookiePolicy $policy): string => $policy->content('pl')['title'])->all());
        $this->assertSame(2, LegalDocument::query()->count());
    }

    public function test_zone_legal_documents_must_be_unique_known_documents(): void
    {
        $factory = $this->app->make(ZoneDefinitionFactory::class);
        $zone = $factory->make(['name' => 'shop', 'model' => AlphaAccount::class, 'legal_documents' => ['gdpr', 'privacy']], 'shop', __DIR__);

        $this->assertSame([LegalDocumentType::Gdpr, LegalDocumentType::Privacy], $zone->legalDocuments);

        $this->expectException(InvalidArgumentException::class);
        $factory->make(['name' => 'shop', 'model' => AlphaAccount::class, 'legal_documents' => ['privacy', 'terms']], 'shop', __DIR__);
    }

    private function publish(LegalDocumentType $type, string $title, string $body): void
    {
        $documents = $this->app->make(LegalDocuments::class);
        $documents->saveDraft($type, ['pl' => ['title' => $title, 'body' => $body]]);
        $documents->publish($type, 'pl');
    }
}
