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

use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Simplysmart\Admiro\Auth\PinLength;
use Simplysmart\Admiro\Auth\PinRules;
use Tests\Fixtures\Zones\AlphaAccount;

/**
 * Długość PIN-u jako zakres 4–16 cyfr: nowy PIN w zakresie strefy, wpisany istniejący PIN w pełnym zakresie
 * Core (podniesienie minimum strefy nikogo nie blokuje), błędny zakres w configu jest odrzucany.
 */
class PinLengthTest extends SignInTestCase
{
    public function test_new_pin_may_have_from_4_to_16_digits_by_default(): void
    {
        $this->assertTrue($this->passes(PinRules::forNewPin(PinLength::accepted()), '1593'));
        $this->assertTrue($this->passes(PinRules::forNewPin(PinLength::accepted()), '1593574826159374'));
        $this->assertFalse($this->passes(PinRules::forNewPin(PinLength::accepted()), '159'));
        $this->assertFalse($this->passes(PinRules::forNewPin(PinLength::accepted()), '15935748261593748'));
    }

    public function test_new_pin_follows_the_zone_minimum(): void
    {
        $this->assertFalse($this->passes(PinRules::forNewPin(new PinLength(8, PinLength::MAX)), '159357'));
        $this->assertTrue($this->passes(PinRules::forNewPin(new PinLength(8, PinLength::MAX)), '15935748'));
    }

    public function test_entered_pin_is_checked_in_the_full_range_so_a_shorter_old_pin_still_works(): void
    {
        $this->assertTrue(Validator::make(['pin' => '1593'], ['pin' => PinRules::forEnteredPin()])->passes());
        $this->assertFalse(Validator::make(['pin' => '15a3'], ['pin' => PinRules::forEnteredPin()])->passes());
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function invalidRanges(): array
    {
        return [
            'below 4' => [3, 16],
            'above 16' => [4, 17],
            'min above max' => [10, 8],
        ];
    }

    #[DataProvider('invalidRanges')]
    public function test_range_outside_4_to_16_is_rejected(int $min, int $max): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PinLength($min, $max);
    }

    public function test_pin_setup_accepts_a_16_digit_pin_and_shows_the_range(): void
    {
        $this->account(AlphaAccount::class);
        $this->requestCode();
        $this->submitCode($this->sentCode());

        $this->get('/alpha/signin/pin-setup')->assertSee(text('sign_in.pin_setup_intro', ['min' => 4, 'max' => 16]))->assertSee('maxlength="16"', false);
        $this->post('/alpha/signin/pin-setup', ['pin' => '1593574826159374', 'pin_confirmation' => '1593574826159374'])->assertRedirect('/alpha/dashboard');

        $this->assertAuthenticated('alpha');
    }

    /**
     * @param list<mixed> $rules
     */
    private function passes(array $rules, string $pin): bool
    {
        return Validator::make(['pin' => $pin, 'pin_confirmation' => $pin], ['pin' => $rules])->passes();
    }
}
