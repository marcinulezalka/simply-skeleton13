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
use Illuminate\Support\Facades\Queue;
use Simplysmart\Admiro\Auth\SignIn\IssueSignInCode;
use Simplysmart\Admiro\Mail\LoginCodeMail;
use Tests\Fixtures\Zones\AlphaAccount;

/**
 * Przepływ logowania: e-mail → kod → PIN (albo ustawienie PIN-u), decoy dla nieznanych
 * i nieaktywnych kont, tylko najnowszy kod, brak przeskakiwania kroków i czas kroków.
 */
class SignInFlowTest extends SignInTestCase
{
    public function test_account_with_a_pin_signs_in_with_the_code_and_the_pin(): void
    {
        $account = $this->account(AlphaAccount::class, pin: self::PIN);

        $this->requestCode()->assertRedirect('/alpha/signin/code');
        $this->submitCode($this->sentCode())->assertRedirect('/alpha/signin/pin');
        $this->submitPin(self::PIN)->assertRedirect('/alpha/dashboard');

        $this->assertAuthenticatedAs($account, 'alpha');
        $this->assertNotNull($account->fresh()?->getAttribute('last_login_at'));
    }

    public function test_account_without_a_pin_sets_it_after_the_code(): void
    {
        $account = $this->account(AlphaAccount::class);

        $this->requestCode();
        $this->submitCode($this->sentCode())->assertRedirect('/alpha/signin/pin-setup');
        $this->post('/alpha/signin/pin-setup', ['pin' => self::PIN, 'pin_confirmation' => self::PIN])->assertRedirect('/alpha/dashboard');

        $this->assertAuthenticatedAs($account, 'alpha');
        $this->assertTrue($account->fresh()?->hasPin());
    }

    public function test_weak_pin_is_rejected(): void
    {
        $this->account(AlphaAccount::class);

        $this->requestCode();
        $this->submitCode($this->sentCode());
        $this->post('/alpha/signin/pin-setup', ['pin' => '123456', 'pin_confirmation' => '123456'])->assertSessionHasErrors('pin');

        $this->assertGuest('alpha');
    }

    public function test_unknown_address_gets_the_same_answer_and_no_mail(): void
    {
        $this->requestCode('nobody@example.com')->assertRedirect('/alpha/signin/code');
        $this->submitCode('123456')->assertSessionHasErrors(['code' => text('sign_in.failure')]);

        Mail::assertNothingSent();
    }

    public function test_inactive_account_gets_the_decoy_flow(): void
    {
        $this->account(AlphaAccount::class, ['is_active' => false], self::PIN);

        $this->requestCode()->assertRedirect('/alpha/signin/code');

        Mail::assertNothingSent();
    }

    public function test_known_and_unknown_addresses_take_the_same_path(): void
    {
        Queue::fake();
        $this->account(AlphaAccount::class, pin: self::PIN);

        $known = $this->requestCode();
        $unknown = $this->requestCode('nobody@example.com');

        $this->assertSame($known->headers->get('Location'), $unknown->headers->get('Location'));
        Queue::assertPushed(IssueSignInCode::class, 2);
    }

    public function test_only_the_newest_code_is_valid(): void
    {
        $this->account(AlphaAccount::class, pin: self::PIN);

        $this->requestCode();
        $first = $this->sentCode();

        $this->travel(61)->seconds();
        $this->post('/alpha/signin/code/resend')->assertRedirect('/alpha/signin/code');

        $second = null;
        Mail::assertSent(LoginCodeMail::class, function (LoginCodeMail $mail) use (&$second): bool {
            $second = $mail->code;

            return true;
        });

        if ($first !== $second) {
            $this->submitCode($first)->assertSessionHasErrors('code');
        }

        $this->submitCode((string) $second)->assertRedirect('/alpha/signin/pin');
    }

    public function test_double_submit_of_the_address_sends_one_code(): void
    {
        $this->account(AlphaAccount::class, pin: self::PIN);

        $this->requestCode();
        $this->requestCode();

        Mail::assertSentCount(1);
    }

    public function test_code_is_stored_only_as_a_hash(): void
    {
        $this->account(AlphaAccount::class, pin: self::PIN);

        $this->requestCode();
        $code = $this->sentCode();

        $this->assertDatabaseMissing('alpha_login_challenges', ['code_hash' => $code]);
        $hash = DB::table('alpha_login_challenges')->value('code_hash');
        $this->assertIsString($hash);
        $this->assertSame(64, strlen($hash));
    }

    public function test_steps_cannot_be_skipped(): void
    {
        $this->account(AlphaAccount::class, pin: self::PIN);

        $this->get('/alpha/signin/pin')->assertRedirect('/alpha/signin');

        $this->requestCode();
        $this->get('/alpha/signin/pin')->assertRedirect('/alpha/signin')->assertSessionHasErrors(['email' => text('sign_in.expired')]);
    }

    public function test_the_code_step_expires(): void
    {
        $this->account(AlphaAccount::class, pin: self::PIN);

        $this->requestCode();
        $code = $this->sentCode();
        $this->travel(11)->minutes();

        $this->submitCode($code)->assertRedirect('/alpha/signin');
        $this->assertGuest('alpha');
    }

    public function test_signed_in_account_skips_the_sign_in_pages(): void
    {
        $this->signedIn($this->account(AlphaAccount::class, pin: self::PIN), 'alpha')
            ->get('/alpha/signin')
            ->assertRedirect('/alpha/dashboard');
    }

    public function test_sign_out_ends_the_zone_session(): void
    {
        $this->signedIn($this->account(AlphaAccount::class, pin: self::PIN), 'alpha')
            ->post('/alpha/signin/out')
            ->assertRedirect('/alpha/signin');

        $this->assertGuest('alpha');
    }

    public function test_sign_out_says_goodbye_on_the_sign_in_page(): void
    {
        $this->signedIn($this->account(AlphaAccount::class, pin: self::PIN), 'alpha')
            ->post('/alpha/signin/out')
            ->assertRedirect('/alpha/signin')
            ->assertSessionHas('status', text('sign_in.signed_out'))
            ->assertCookie('alpha_session');
    }

    public function test_sign_in_email_field_shows_an_example_placeholder(): void
    {
        $this->get('/alpha/signin')->assertOk()->assertSee('placeholder="' . e(text('placeholders.email')) . '"', false);
    }

    public function test_forgot_pin_page_explains_the_reset_and_leads_back_to_sign_in(): void
    {
        $this->get('/alpha/signin/forgot-pin')
            ->assertOk()
            ->assertSee(text('sign_in.forgot_pin.title'))
            ->assertSee(text('sign_in.forgot_pin.steps.admin'))
            ->assertSee('href="' . url('/alpha/signin') . '"', false)
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_pin_step_links_to_the_forgot_pin_page(): void
    {
        $this->account(AlphaAccount::class, pin: self::PIN);
        $this->requestCode();
        $this->submitCode($this->sentCode());

        $this->get('/alpha/signin/pin')->assertOk()->assertSee('href="' . url('/alpha/signin/forgot-pin') . '"', false);
    }

    public function test_expired_form_sends_back_to_the_zone_sign_in(): void
    {
        $this->post('/alpha/expired-form')
            ->assertRedirect('/alpha/signin')
            ->assertSessionHasErrors(['email' => text('sign_in.page_expired')]);
    }

    public function test_e_mail_is_encrypted_and_found_by_its_index(): void
    {
        $this->account(AlphaAccount::class, ['email' => ' Anna@Example.COM ']);

        $row = DB::table('alpha_accounts')->first();

        $this->assertNotNull($row);
        $this->assertNotSame(self::EMAIL, $row->email);
        $this->assertIsString($row->email_hash);
        $this->assertSame(64, strlen($row->email_hash));
        $this->requestCode();
        $this->assertNotSame('', $this->sentCode());
    }
}
