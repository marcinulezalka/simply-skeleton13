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

use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use LogicException;
use Simplysmart\Admiro\Contracts\CurrentZone;
use Simplysmart\Admiro\Contracts\ZoneRegistry;
use Simplysmart\Admiro\Enums\BrandAsset;
use Simplysmart\Admiro\Mail\LoginCodeMail;
use Simplysmart\Admiro\Mail\MailBrandResolver;
use Simplysmart\Admiro\Mail\MailOrganization;
use Simplysmart\Admiro\Mail\NewTrustedDeviceMail;
use Simplysmart\Admiro\Models\Organization;
use Simplysmart\Admiro\Organizations\OrganizationBranding;
use Simplysmart\Admiro\Organizations\OrganizationData;
use Simplysmart\Admiro\Organizations\OrganizationManagement;
use Simplysmart\Admiro\Permissions\OrganizationMembership;
use Simplysmart\Admiro\Zones\ZoneBrand;
use Simplysmart\Admiro\Zones\ZoneDefinitionFactory;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Tests\Fixtures\Zones\AlphaAccount;
use Tests\Fixtures\Zones\MailZoneServiceProvider;

/**
 * E-maile stref: HTML w layoucie strefy i wersja tekstowa, szablony i kolory strefy, logo osadzone w wiadomości
 * (organizacja → strefa → Core), render bez strefy żądania (worker kolejki) i organizacja maila konta.
 */
class ZoneMailTest extends ZoneTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->registerZone(MailZoneServiceProvider::class);
        Storage::fake('local');
    }

    public function test_zone_without_mail_views_sends_html_and_text_in_the_core_layout_with_the_core_logo(): void
    {
        $email = $this->send(new LoginCodeMail('alpha', null, 'Anna Nowak', '482913', 10));

        $html = (string) $email->getHtmlBody();
        $this->assertStringContainsString('482913', $html);
        $this->assertStringContainsString('background-color: #f8fafc', $html);
        $this->assertStringContainsString(text_email('login_code.code_label'), $html);
        $this->assertStringContainsString('482913', (string) $email->getTextBody());
        $this->assertStringNotContainsString('<', (string) $email->getTextBody());
        $this->assertSame(text_email('login_code.subject'), $email->getSubject());
        $this->assertEmbeddedLogo($email, (string) file_get_contents(MailBrandResolver::CORE_LOGO));
    }

    public function test_zone_mail_views_replace_the_layout_and_template_with_zone_colors_logo_and_texts(): void
    {
        $this->app->make('translator')->addLines(['emails.login_code.subject' => 'Kod strefy mailing'], 'pl', 'mailfixture');

        $email = $this->send(new LoginCodeMail('mailing', null, 'Anna Nowak', '482913', 10));

        $html = (string) $email->getHtmlBody();
        $this->assertStringContainsString('data-layout="mailing"', $html);
        $this->assertStringContainsString('data-accent="' . MailZoneServiceProvider::ACCENT . '"', $html);
        $this->assertStringContainsString('<p data-template="mailing">482913</p>', $html);
        $this->assertSame('Kod strefy mailing', $email->getSubject());
        $this->assertStringContainsString(text_email('login_code.ignore'), (string) $email->getTextBody());
        $this->assertEmbeddedLogo($email, (string) file_get_contents(MailZoneServiceProvider::LOGO));
    }

    public function test_core_template_is_used_in_the_zone_layout_when_the_zone_has_no_template_of_that_name(): void
    {
        $html = (new NewTrustedDeviceMail('mailing', null, 'Anna Nowak', 'Firefox', now(), 'https://example.com/devices'))->render();

        $this->assertStringContainsString('data-layout="mailing"', $html);
        $this->assertStringContainsString('background-color: ' . MailZoneServiceProvider::ACCENT, $html);
        $this->assertStringContainsString('https://example.com/devices', $html);
    }

    public function test_organization_logo_wins_over_the_zone_and_core_logo(): void
    {
        $organization = $this->app->make(OrganizationManagement::class)->create(new OrganizationData('Firma'));
        $logo = UploadedFile::fake()->image('logo.png', 200, 50);
        $this->app->make(OrganizationBranding::class)->store($organization, BrandAsset::Logo, $logo);

        $email = $this->send(new LoginCodeMail('mailing', $organization->id, 'Anna Nowak', '482913', 10));

        $this->assertEmbeddedLogo($email, (string) file_get_contents($logo->getPathname()));
    }

    public function test_mail_renders_with_zone_texts_and_colors_outside_a_request_zone(): void
    {
        $current = $this->app->make(CurrentZone::class);
        $this->assertFalse($current->has());

        $html = (new LoginCodeMail('mailing', null, 'Anna Nowak', '482913', 10))->render();

        $this->assertStringContainsString('data-accent="' . MailZoneServiceProvider::ACCENT . '"', $html);
        $this->assertFalse($current->has());
    }

    public function test_mail_views_without_a_layout_stop_the_mail(): void
    {
        $zone = $this->app->make(ZoneDefinitionFactory::class)->make(['name' => 'broken', 'mail' => ['views' => 'mailfixture::missing']], 'broken', __DIR__);
        $this->app->make(ZoneRegistry::class)->register($zone);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('mailfixture::missing.layout');

        (new LoginCodeMail('broken', null, 'Anna Nowak', '482913', 10))->render();
    }

    public function test_zone_brand_and_mail_config_are_validated_at_start(): void
    {
        $factory = $this->app->make(ZoneDefinitionFactory::class);

        foreach ([
            ['brand' => ['logo' => __DIR__ . '/../../../packages/simplysmart/admiro/public/assets/img/brand/logo.svg']],
            ['brand' => ['favicon' => MailZoneServiceProvider::LOGO]],
            ['mail' => ['views' => 'no-namespace']],
            ['mail' => ['palette' => ['accent' => 'blue']]],
            ['mail' => ['palette' => ['shadow' => '#000000']]],
        ] as $config) {
            try {
                $factory->make(['name' => 'invalid', ...$config], 'invalid', __DIR__);
                $this->fail('Invalid zone config was accepted: ' . json_encode($config));
            } catch (InvalidArgumentException $exception) {
                $this->assertStringStartsWith('Zone [invalid]', $exception->getMessage());
            }
        }

        $this->assertSame(MailZoneServiceProvider::LOGO, (new ZoneBrand(['logo' => MailZoneServiceProvider::LOGO]))->path(BrandAsset::Logo));
    }

    public function test_mail_organization_is_the_operator_the_only_organization_or_none(): void
    {
        $zones = $this->app->make(ZoneRegistry::class);
        $organizations = $this->app->make(MailOrganization::class);
        $membership = $this->app->make(OrganizationMembership::class);
        $management = $this->app->make(OrganizationManagement::class);
        $account = $this->account(AlphaAccount::class);
        $this->assertInstanceOf(AlphaAccount::class, $account);

        $this->assertSame(Organization::OPERATOR_ID, $organizations->for($zones->get('beta'), $account));
        $this->assertNull($organizations->for($zones->get('alpha'), $account));

        $first = $management->create(new OrganizationData('Firma'));
        $membership->add($account, $first);
        $this->assertSame($first->id, $organizations->for($zones->get('alpha'), $account));

        $membership->add($account, $management->create(new OrganizationData('Druga firma')));
        $this->assertNull($organizations->for($zones->get('alpha'), $account));
    }

    private function send(LoginCodeMail $mail): Email
    {
        Mail::to('anna@example.com')->send($mail);
        $transport = Mail::mailer()->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);
        $sent = $transport->messages()->last();
        $this->assertInstanceOf(SentMessage::class, $sent);
        $message = $sent->getOriginalMessage();
        $this->assertInstanceOf(Email::class, $message);

        return $message;
    }

    private function assertEmbeddedLogo(Email $email, string $contents): void
    {
        $attachments = $email->getAttachments();

        $this->assertCount(1, $attachments);
        $this->assertSame($contents, $attachments[0]->getBody());
        $this->assertStringContainsString('src="cid:', (string) $email->getHtmlBody());
    }
}
