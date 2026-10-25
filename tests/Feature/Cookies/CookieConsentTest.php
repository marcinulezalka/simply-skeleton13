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

namespace Tests\Feature\Cookies;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Simplysmart\Mainboard\Contracts\ConsentScripts;
use Simplysmart\Mainboard\Cookies\ConsentScript;
use Illuminate\Support\Facades\Blade;
use Simplysmart\Mainboard\Cookies\CookiePolicies;
use Simplysmart\Mainboard\Cookies\CookiePolicyRejected;
use Simplysmart\Mainboard\Enums\ConsentDecision;
use Simplysmart\Mainboard\Enums\CookieCategory;
use Simplysmart\Mainboard\Models\CookieConsent;
use Tests\TestCase;

/**
 * Zgoda na cookies: baner tylko z opublikowaną polityką albo skryptem opcjonalnym, wersje polityki (szkic,
 * publikacja z treścią w języku domyślnym), strona polityki z Markdown bez HTML-a i listą cookies, rejestr zgód bez IP
 * (bieżąca wersja, identyfikator UUID, limit żądań), skrypty opcjonalne nieaktywne do zgody i czyszczenie rejestru.
 */
class CookieConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_banner_appears_only_with_a_published_policy(): void
    {
        $this->get('/')->assertOk()->assertDontSee('data-ss-consent', false);

        $this->publish('Polityka cookies', 'Treść');

        $this->get('/')
            ->assertSee('data-ss-consent data-version="1"', false)
            ->assertSee(text('cookies.banner.title'))
            ->assertSee('href="' . url('/cookies') . '"', false)
            ->assertSee('data-cookie="ss_consent"', false);
    }

    public function test_sign_in_card_lists_the_cookie_policy_first_and_the_cookie_settings_last(): void
    {
        $render = static fn(): string => Blade::render('<x-mainboard::auth.layout title="Start" :legal-links="$links">x</x-mainboard::auth.layout>', ['links' => [['url' => '/shop/privacy', 'label' => 'Privacy']]]);

        $this->assertStringNotContainsString('data-ss-cookie-settings>', $render());

        $this->publish('Polityka cookies', 'Treść');
        $html = $render();
        $legal = substr($html, (int) strpos($html, '<nav class="ss-auth__legal"'));
        $policy = strpos($legal, 'href="' . url('/cookies') . '"');
        $privacy = strpos($legal, 'href="/shop/privacy"');
        $settings = strpos($legal, 'data-ss-cookie-settings>');

        $this->assertNotFalse($policy);
        $this->assertNotFalse($settings);
        $this->assertLessThan($privacy, $policy);
        $this->assertLessThan($settings, $privacy);
    }

    public function test_publishing_needs_default_language_content_and_numbers_versions(): void
    {
        $policies = $this->app->make(CookiePolicies::class);
        $policies->saveDraft(['en' => ['title' => 'Cookies', 'body' => 'Text']]);

        try {
            $policies->publish('pl');
            $this->fail('A draft without Polish content must not be published.');
        } catch (CookiePolicyRejected) {
            $this->addToAssertionCount(1);
        }

        $this->publish('Polityka', 'Pierwsza');
        $second = $this->publish('Polityka', 'Druga');

        $this->assertSame(2, $second->version);
        $this->assertSame('Text', $second->content('en')['body']);
    }

    public function test_policy_page_renders_markdown_without_raw_html_and_lists_cookies(): void
    {
        $this->get('/cookies')->assertNotFound();
        $this->publish('Polityka cookies', "## Administrator\n\n**Firma** <script>alert(1)</script> [link](javascript:alert(1))");

        $this->get('/cookies')
            ->assertOk()
            ->assertSee('<strong>Firma</strong>', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('javascript:alert', false)
            ->assertSee('*_session')
            ->assertSee(text('cookies.core.consent'));
    }

    public function test_consent_is_recorded_without_ip_for_the_current_version_only(): void
    {
        $this->publish('Polityka', 'Treść');
        $id = (string) Str::uuid();

        $this->postJson('/cookies/consent', ['consent_id' => $id, 'version' => 1, 'decision' => 'customized', 'categories' => ['analytics']])->assertCreated();
        $this->postJson('/cookies/consent', ['consent_id' => $id, 'version' => 0, 'decision' => 'accepted', 'categories' => []])->assertStatus(422);
        $this->postJson('/cookies/consent', ['consent_id' => str_repeat('x', 36), 'version' => 1, 'decision' => 'accepted', 'categories' => []])->assertStatus(422);
        $this->postJson('/cookies/consent', ['consent_id' => $id, 'version' => 1, 'decision' => 'accepted', 'categories' => ['tracking']])->assertStatus(422);

        $consent = CookieConsent::query()->sole();
        $this->assertSame(ConsentDecision::Customized, $consent->decision);
        $this->assertSame(['necessary', 'analytics'], $consent->categories);
        $this->assertArrayNotHasKey('ip', $consent->getAttributes());
    }

    public function test_consent_endpoint_is_throttled(): void
    {
        config(['mainboard.cookies.throttle_per_minute' => 2]);
        $this->publish('Polityka', 'Treść');
        $payload = ['consent_id' => (string) Str::uuid(), 'version' => 1, 'decision' => 'rejected', 'categories' => []];

        $this->postJson('/cookies/consent', $payload)->assertCreated();
        $this->postJson('/cookies/consent', $payload)->assertCreated();
        $this->postJson('/cookies/consent', $payload)->assertStatus(429);
    }

    public function test_optional_script_shows_the_banner_and_waits_for_consent(): void
    {
        $this->app->make(ConsentScripts::class)->add(new ConsentScript(CookieCategory::Analytics, '/js/stats.js'));

        $this->get('/')
            ->assertSee('data-ss-consent data-version="0"', false)
            ->assertSee('<script type="text/plain" data-ss-consent-script="analytics" data-src="/js/stats.js"></script>', false)
            ->assertDontSee('<script src="/js/stats.js"', false)
            ->assertDontSee('href="' . url('/cookies') . '"', false);
    }

    public function test_old_consents_are_pruned(): void
    {
        config(['mainboard.cookies.retention_days' => 30]);
        $this->publish('Polityka', 'Treść');
        $this->postJson('/cookies/consent', ['consent_id' => (string) Str::uuid(), 'version' => 1, 'decision' => 'accepted', 'categories' => ['analytics', 'marketing', 'preferences']]);
        CookieConsent::query()->update(['recorded_at' => Carbon::now()->subDays(31)]);

        $this->artisan('mainboard:cookies:prune')->assertSuccessful();

        $this->assertSame(0, CookieConsent::query()->count());
    }

    private function publish(string $title, string $body): \Simplysmart\Mainboard\Models\CookiePolicy
    {
        $policies = $this->app->make(CookiePolicies::class);
        $policies->saveDraft(['pl' => ['title' => $title, 'body' => $body]]);

        return $policies->publish('pl');
    }
}
