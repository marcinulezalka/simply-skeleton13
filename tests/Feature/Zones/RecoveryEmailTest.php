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

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Simplysmart\Admiro\Auth\AccountKey;
use Simplysmart\Admiro\Mail\LoginCodeMail;
use Simplysmart\Admiro\Mail\SecurityCodeMail;
use Tests\Fixtures\Zones\AlphaAccount;

/**
 * Zapasowy adres e-mail i reset PIN-u przez niego: adres potwierdzony kodem wysłanym na ten adres (inny niż adres
 * konta), usunięcie, wpisy w historii konta; na stronie „Nie pamiętam PIN-u” kod idzie na zapasowy adres, poprawny
 * usuwa PIN i wylogowuje wszędzie, a odpowiedź nie zdradza, czy konto ma zapasowy adres. Kod resetu nie loguje.
 */
class RecoveryEmailTest extends SignInTestCase
{
    private const string RECOVERY = 'zapas@example.com';

    public function test_account_confirms_a_recovery_e_mail_with_a_code_sent_to_it_and_removes_it(): void
    {
        $account = $this->account(AlphaAccount::class, pin: self::PIN);
        $this->signedIn($account, 'alpha');

        $this->post('/alpha/account/recovery-email/code', ['recovery_email' => ' Zapas@Example.com '])
            ->assertRedirect('/alpha/account/security#recovery')
            ->assertSessionHas('status', text('account.recovery.code_sent'));
        $this->get('/alpha/account/security')->assertOk()->assertSee(text('account.recovery.code_hint', ['email' => self::RECOVERY]));
        $this->put('/alpha/account/recovery-email', ['recovery_code' => $this->wrongCode($this->securityCode(self::RECOVERY))]);
        $this->assertSessionError('recovery_code', text('account.code_invalid'));
        $this->assertNull($account->fresh()?->recoveryEmail());

        $this->put('/alpha/account/recovery-email', ['recovery_code' => $this->securityCode(self::RECOVERY)])
            ->assertSessionHas('status', text('account.recovery.saved'));
        $saved = $account->fresh();
        $this->assertNotNull($saved);
        $this->assertSame(self::RECOVERY, $saved->recoveryEmail());
        $this->assertNotSame(self::RECOVERY, DB::table('alpha_accounts')->where('id', $account->getKey())->value('recovery_email'));
        $this->get('/alpha/account/security')->assertSee(self::RECOVERY)->assertSee(text('audit.events.recovery_email_set'));

        $this->delete('/alpha/account/recovery-email')->assertSessionHas('status', text('account.recovery.removed'));
        $this->assertNull($saved->fresh()?->recoveryEmail());
        $this->get('/alpha/account/security')->assertSee(text('audit.events.recovery_email_removed'));
    }

    public function test_recovery_e_mail_must_differ_from_the_account_address(): void
    {
        $this->signedIn($this->account(AlphaAccount::class, pin: self::PIN), 'alpha');

        $this->post('/alpha/account/recovery-email/code', ['recovery_email' => strtoupper(self::EMAIL)]);

        $this->assertSessionError('recovery_email', text('account.recovery.same_as_primary'));
        Mail::assertNothingQueued();
    }

    public function test_forgotten_pin_is_removed_with_a_code_sent_to_the_recovery_e_mail(): void
    {
        $account = $this->account(AlphaAccount::class, pin: self::PIN);
        $account->forceFill(['recovery_email' => self::RECOVERY])->save();

        $this->post('/alpha/signin/forgot-pin/code', ['email' => self::EMAIL])
            ->assertRedirect('/alpha/signin/forgot-pin')
            ->assertSessionHas('status', text('sign_in.forgot_pin.recovery.code_sent'));
        $this->get('/alpha/signin/forgot-pin')->assertOk()->assertSee(text('sign_in.forgot_pin.recovery.code_lead'));
        $code = $this->securityCode(self::RECOVERY);
        Mail::assertNotQueued(SecurityCodeMail::class, static fn(SecurityCodeMail $mail): bool => $mail->hasTo(self::EMAIL));

        $this->post('/alpha/signin/forgot-pin', ['code' => $code])
            ->assertRedirect('/alpha/signin')
            ->assertSessionHas('status', text('sign_in.forgot_pin.recovery.done'));

        $fresh = $account->fresh();
        $this->assertNotNull($fresh);
        $this->assertFalse($fresh->hasPin());
        $this->assertSame(1, $fresh->authEpoch());
        $this->assertDatabaseHas('audit_events', ['event' => 'access revoked', 'account_id' => (string) AccountKey::of($account)]);
    }

    public function test_reset_request_looks_the_same_without_a_recovery_e_mail_and_the_code_cannot_sign_in(): void
    {
        $account = $this->account(AlphaAccount::class, pin: self::PIN);

        $this->post('/alpha/signin/forgot-pin/code', ['email' => self::EMAIL])->assertSessionHas('status', text('sign_in.forgot_pin.recovery.code_sent'));
        $this->post('/alpha/signin/forgot-pin/code', ['email' => 'nikt@example.com'])->assertSessionHas('status', text('sign_in.forgot_pin.recovery.code_sent'));
        Mail::assertNotQueued(SecurityCodeMail::class);

        $account->forceFill(['recovery_email' => self::RECOVERY])->save();
        $this->post('/alpha/signin/forgot-pin/code', ['email' => self::EMAIL]);
        $code = $this->securityCode(self::RECOVERY);

        $this->requestCode();
        $this->post('/alpha/signin/code', ['code' => $code]);
        $this->assertTrue($account->fresh()?->hasPin());
        $this->assertNotSame($code, $this->sentCode());
        Mail::assertSent(LoginCodeMail::class);
    }

    private function securityCode(string $email): string
    {
        $code = null;

        Mail::assertQueued(SecurityCodeMail::class, function (SecurityCodeMail $mail) use ($email, &$code): bool {
            if ($mail->hasTo($email)) {
                $code = $mail->code;
            }

            return $mail->hasTo($email);
        });
        $this->assertIsString($code);

        return $code;
    }
}
