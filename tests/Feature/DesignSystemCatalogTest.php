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

namespace Tests\Feature;

use LogicException;
use Simplysmart\Admiro\Contracts\DesignSystemCatalog;
use Simplysmart\Admiro\Enums\DesignSystemView;
use Simplysmart\Admiro\View\Demo\DemoInvoices;
use Tests\TestCase;

/**
 * Katalog design systemu (Contracts\DesignSystemCatalog): Core rejestruje swoje widoki, paczka może dołożyć obszar
 * i widoki bez zmian w Core (trafiają do nawigacji i pod `/design-system/views/{klucz}`), klucze są unikalne.
 */
class DesignSystemCatalogTest extends TestCase
{
    public function test_core_views_are_in_the_catalog(): void
    {
        $catalog = $this->app->make(DesignSystemCatalog::class);

        foreach (DesignSystemView::cases() as $view) {
            $this->assertSame($view->template(), $catalog->find($view->value)?->template);
        }
    }

    public function test_package_area_and_view_are_served_and_listed(): void
    {
        $catalog = $this->app->make(DesignSystemCatalog::class);
        $catalog->addArea('package-area', 'design_system.title');
        $catalog->addView('package-area', 'package-view', 'design_system.views.sidebar', DesignSystemView::Sidebar->template(), static fn(): array => [
            'view' => DesignSystemView::Sidebar,
            'invoices' => DemoInvoices::all(),
        ]);

        $this->get(route('simplysmart.design-system.view', 'package-view'))->assertOk();
        $this->get(route('simplysmart.design-system'))->assertSee(route('simplysmart.design-system.view', 'package-view'), false);
    }

    public function test_duplicate_view_key_is_rejected(): void
    {
        $this->expectException(LogicException::class);

        $this->app->make(DesignSystemCatalog::class)->addView(DesignSystemView::List->area()->value, DesignSystemView::List->value, 'x', 'x');
    }

    public function test_view_in_an_unknown_area_is_rejected(): void
    {
        $this->expectException(LogicException::class);

        $this->app->make(DesignSystemCatalog::class)->addView('missing-area', 'any-view', 'x', 'x');
    }
}
