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

namespace Tests\Fixtures\Zones;

/**
 * Strefa testowa mailing: publiczna, z własnymi e-mailami — layout i szablon kodu logowania z widoków
 * `mailfixture::mail` (tests/Fixtures/Zones/mail), kolory `mail.palette`, logo `brand.logo` i teksty `mailfixture`.
 */
class MailZoneServiceProvider extends FixtureZoneServiceProvider
{
    public const string LOGO = __DIR__ . '/mail/zone-logo.png';

    public const string ACCENT = '#123456';

    protected function zoneName(): string
    {
        return 'mailing';
    }

    protected function zoneConfig(): array
    {
        return [
            'name' => 'mailing',
            'texts' => 'mailfixture',
            'brand' => ['logo' => self::LOGO],
            'mail' => ['views' => 'mailfixture::mail', 'palette' => ['accent' => self::ACCENT]],
        ];
    }

    public function register(): void
    {
        parent::register();

        $this->loadViewsFrom(__DIR__, 'mailfixture');
    }
}
