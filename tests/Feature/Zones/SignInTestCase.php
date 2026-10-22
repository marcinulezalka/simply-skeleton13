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

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Testing\TestResponse;
use Simplysmart\Mainboard\Mail\LoginCodeMail;

/**
 * Baza testów logowania kodem i PIN-em w strefie alpha (poczta przechwytywana).
 */
abstract class SignInTestCase extends ZoneTestCase
{
    protected const string EMAIL = 'anna@example.com';

    protected const string PIN = '159357';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    /**
     * @param array<string, string> $server
     *
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    protected function requestCode(string $email = self::EMAIL, array $server = []): TestResponse
    {
        return $this->withServerVariables($server)->post('/alpha/signin', ['email' => $email]);
    }

    /**
     * Ostatni kod wysłany na adres.
     */
    protected function sentCode(string $email = self::EMAIL): string
    {
        $code = null;

        Mail::assertSent(LoginCodeMail::class, function (LoginCodeMail $mail) use ($email, &$code): bool {
            if ($mail->hasTo($email)) {
                $code = $mail->code;
            }

            return $mail->hasTo($email);
        });

        return (string) $code;
    }

    /**
     * Kod, który na pewno nie jest wysłanym.
     */
    protected function wrongCode(string $code): string
    {
        return str_pad((string) (((int) $code + 1) % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    protected function submitCode(string $code): TestResponse
    {
        return $this->post('/alpha/signin/code', ['code' => $code]);
    }

    /**
     * Pierwszy błąd pola w sesji strefy po żądaniu zalogowanego konta. TestResponse::assertSessionHasErrors()
     * startuje magazyn sesji strefy od nowa i nie widzi wtedy błędów, więc testy kont zalogowanych czytają je tu.
     */
    protected function sessionError(string $field): string
    {
        $errors = session('errors');

        return $errors instanceof ViewErrorBag ? (string) $errors->first($field) : '';
    }

    protected function assertSessionError(string $field, string $message): void
    {
        $this->assertSame($message, $this->sessionError($field));
    }

    /**
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    protected function submitPin(string $pin): TestResponse
    {
        return $this->post('/alpha/signin/pin', ['pin' => $pin]);
    }
}
