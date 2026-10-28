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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use Simplysmart\Admiro\Database\History;
use Simplysmart\Admiro\Enums\LegalDocumentType;
use Simplysmart\Admiro\Legal\LegalDocumentRejected;
use Simplysmart\Admiro\Legal\LegalDocuments;
use Simplysmart\Admiro\Models\LegalDocument;
use Simplysmart\Admiro\Zones\ZoneDefinitionFactory;
use Tests\Fixtures\Zones\AlphaAccount;
use Tests\TestCase;

/**
 * Dokumenty prawne Core (polityka cookies, polityka prywatności, klauzula RODO): wersje każdego dokumentu osobno,
 * publiczne strony z „Wstecz” i „Zobacz także”, seeder pierwszych wersji wszystkich polityk i `legal_documents`
 * w configu strefy (bez polityki cookies).
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

        $this->publish(LegalDocumentType::Privacy, 'Polityka prywatności', "## Administrator\n\n<script>alert(1)</script>Treść\n\n## Administrator\n\nDrugi");
        $this->publish(LegalDocumentType::Gdpr, 'Klauzula RODO', 'Treść');

        $this->get('/polityka-prywatnosci', ['referer' => url('/admiro/signin')])
            ->assertOk()
            ->assertSee('<h1 class="ss-content-page__title"><span class="ss-icon ss-content-page__title-icon"', false)
            ->assertSee('Polityka prywatności')
            ->assertSee('<h2 id="administrator">Administrator</h2>', false)
            ->assertSee('<a class="ss-toc__link" href="#administrator">Administrator</a>', false)
            ->assertSee('<a class="ss-toc__link" href="#administrator-2">Administrator</a>', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('class="ss-link ss-content-page__back" href="' . url('/admiro/signin') . '"', false)
            ->assertSee('<a class="ss-related" href="' . route('admiro.legal.gdpr') . '"', false)
            ->assertDontSee('<a class="ss-related" href="' . route('admiro.legal.privacy') . '"', false);

        $this->get('/rodo', ['referer' => 'https://example.org/'])
            ->assertOk()
            ->assertSee('class="ss-link ss-content-page__back" href="' . url('/') . '"', false)
            ->assertDontSee('href="https://example.org/"', false);
    }

    public function test_seeder_publishes_every_policy_once_with_the_data_controller(): void
    {
        config(['admiro.legal.controller' => ['name' => 'Jan Kowalski', 'email' => 'dane@example.com']]);

        $this->command('admiro:legal:seed')->assertSuccessful();
        $this->command('admiro:legal:seed')->assertSuccessful();

        $privacy = LegalDocument::query()->where('document', LegalDocumentType::Privacy->value)->sole()->content('pl');
        $this->assertSame('Polityka prywatności', $privacy['title']);
        $this->assertStringContainsString('Jan Kowalski, e-mail: dane@example.com', $privacy['body']);
        $this->assertStringNotContainsString(':name', $privacy['body']);
        $this->assertSame(1, LegalDocument::query()->where('document', LegalDocumentType::Gdpr->value)->count());
        $this->assertSame(1, LegalDocument::query()->where('document', LegalDocumentType::Cookies->value)->whereNotNull('version')->count());
    }

    public function test_seeder_leaves_documents_that_already_have_a_version(): void
    {
        $this->publish(LegalDocumentType::Cookies, 'Własna polityka cookies', 'Treść');

        $this->command('admiro:legal:seed')->assertSuccessful();

        $cookies = LegalDocument::query()->where('document', LegalDocumentType::Cookies->value)->get();
        $this->assertSame(['Własna polityka cookies'], $cookies->map(static fn(LegalDocument $policy): string => $policy->content('pl')['title'])->all());
        $this->assertSame(3, LegalDocument::query()->count());
    }

    public function test_zone_legal_documents_must_be_unique_known_documents(): void
    {
        $factory = $this->app->make(ZoneDefinitionFactory::class);
        $zone = $factory->make(['name' => 'shop', 'model' => AlphaAccount::class, 'legal_documents' => ['gdpr', 'privacy']], 'shop', __DIR__);

        $this->assertSame([LegalDocumentType::Gdpr, LegalDocumentType::Privacy], $zone->legalDocuments);

        foreach ([['privacy', 'terms'], ['privacy', 'privacy'], ['cookies']] as $documents) {
            try {
                $factory->make(['name' => 'shop', 'model' => AlphaAccount::class, 'legal_documents' => $documents], 'shop', __DIR__);
                $this->fail('Zone legal_documents [' . implode(', ', $documents) . '] must be rejected.');
            } catch (InvalidArgumentException) {
            }
        }
    }

    public function test_seeder_reports_every_policy_published_from_the_template(): void
    {
        config(['admiro.legal.controller' => ['name' => 'Jan Kowalski', 'email' => 'dane@example.com']]);
        /** @var LoggerInterface&MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('warning')->times(3)->withArgs(static fn(string $message): bool => str_contains($message, 'To treść szablonowa'));
        $this->app->instance(LoggerInterface::class, $logger);

        $this->command('admiro:legal:seed')
            ->expectsOutputToContain('Opublikowano szablon dokumentu „Polityka prywatności” z administratorem danych Jan Kowalski (dane@example.com).')
            ->assertSuccessful();
        $this->command('admiro:legal:seed')->doesntExpectOutputToContain('Opublikowano szablon')->assertSuccessful();
    }

    public function test_cookie_policies_move_to_legal_documents_with_their_versions_and_history(): void
    {
        $this->publish(LegalDocumentType::Cookies, 'Polityka cookies', 'Pierwsza');
        $this->publish(LegalDocumentType::Privacy, 'Polityka prywatności', 'Treść');
        $token = LegalDocument::query()->where('document', LegalDocumentType::Cookies->value)->sole()->token;
        $migration = ['--path' => $this->packagePath('simplysmart/admiro', 'database/migrations/2026_10_28_000001_move_cookie_policies_to_legal_documents.php'), '--realpath' => true];

        $this->command('migrate:rollback', $migration)->assertSuccessful();

        $this->assertSame(1, DB::table('cookie_policies')->where('token', $token)->where('version', 1)->count());
        $this->assertSame([LegalDocumentType::Privacy], LegalDocument::query()->get()->map(static fn(LegalDocument $document): LegalDocumentType => $document->document)->all());
        $oldId = DB::table('cookie_policies')->value('id');
        DB::table(History::tableFor('cookie_policies'))->insert(['row_id' => $oldId, 'action' => 'update', 'old_values' => '{}', 'new_values' => '{}', 'created_at' => now()]);

        $this->command('migrate', $migration)->assertSuccessful();

        $this->assertFalse(Schema::hasTable('cookie_policies'));
        $this->assertFalse(Schema::hasTable(History::tableFor('cookie_policies')));
        $policy = LegalDocument::query()->where('document', LegalDocumentType::Cookies->value)->sole();
        $this->assertSame([$token, 1], [$policy->token, $policy->version]);
        $this->assertSame($policy->computeChecksum(), $policy->checksum);
        $this->assertSame(1, DB::table(History::tableFor(LegalDocument::TABLE))->where('row_id', $policy->getKey())->where('action', 'update')->count());
        $this->get('/cookies')->assertOk()->assertSee('Pierwsza');
    }

    private function publish(LegalDocumentType $type, string $title, string $body): void
    {
        $documents = $this->app->make(LegalDocuments::class);
        $documents->saveDraft($type, ['pl' => ['title' => $title, 'body' => $body]]);
        $documents->publish($type, 'pl');
    }
}
