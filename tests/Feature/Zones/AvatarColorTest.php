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

use Illuminate\Support\Facades\Blade;
use Simplysmart\Admiro\Enums\AvatarColor;
use Tests\Fixtures\Zones\AlphaAccount;

/**
 * Kolor awatara konta: przydzielany przy zakładaniu konta (z adresu e-mail, w `json_attributes`, bez naruszania innych
 * atrybutów), zmieniany w profilu konta (tylko kolory palety), pokazywany w awatarze.
 */
class AvatarColorTest extends ZoneTestCase
{
    public function test_new_account_gets_a_stable_color_from_its_email(): void
    {
        $account = $this->account(AlphaAccount::class);

        $this->assertSame(['avatar_color' => AvatarColor::forSeed('anna@example.com')->value], $account->fresh()?->json_attributes);
        $this->assertSame(AvatarColor::forSeed('anna@example.com'), $account->avatarColor());
        $this->assertSame(AvatarColor::forSeed('ANNA@example.com '), AvatarColor::forSeed('anna@example.com'));
    }

    public function test_color_is_added_next_to_other_attributes_and_an_explicit_color_wins(): void
    {
        $account = $this->account(AlphaAccount::class, ['json_attributes' => ['theme' => 'dark', 'avatar_color' => 'violet']]);

        $this->assertSame(['theme' => 'dark', 'avatar_color' => 'violet'], $account->fresh()?->json_attributes);
        $this->assertSame(AvatarColor::Violet, $account->avatarColor());
    }

    public function test_profile_shows_the_palette_and_saves_the_chosen_color(): void
    {
        $account = $this->account(AlphaAccount::class, ['json_attributes' => ['theme' => 'dark']]);
        $this->signedIn($account, 'alpha');

        $this->get('/alpha/account/profile')->assertOk()
            ->assertSee('name="avatar_color" value="pink"', false)
            ->assertSee(AvatarColor::Pink->label());

        $this->put('/alpha/account/profile', ['name' => 'Anna Nowak', 'avatar_color' => 'pink'])->assertRedirect('/alpha/account/profile');

        $this->assertSame(['theme' => 'dark', 'avatar_color' => 'pink'], $account->fresh()?->json_attributes);
    }

    public function test_profile_rejects_a_color_outside_the_palette(): void
    {
        $account = $this->account(AlphaAccount::class);
        $this->signedIn($account, 'alpha');

        $this->get('/alpha/account/profile');
        $this->put('/alpha/account/profile', ['name' => 'Anna Nowak', 'avatar_color' => 'gold'])->assertRedirect('/alpha/account/profile');

        $this->assertNotSame('', $this->sessionError('avatar_color'));

        $this->assertSame(AvatarColor::forSeed('anna@example.com'), $account->fresh()?->avatarColor());
    }

    public function test_avatar_uses_the_account_color(): void
    {
        $html = Blade::render('<x-admiro::avatar name="dr Anna Nowak" :color="$color" size="xs" />', ['color' => AvatarColor::Teal]);

        $this->assertStringContainsString('class="ss-avatar ss-avatar--xs ss-avatar--teal"', $html);
        $this->assertStringContainsString('AN', $html);
    }
}
