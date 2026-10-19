<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class LogSentMailTest extends TestCase
{
    public function test_mail_sent_by_a_real_transport_is_logged_in_the_mail_channel(): void
    {
        $mailLogger = Mockery::mock(LoggerInterface::class);
        $mailLogger->shouldReceive('info')->once()->with('Mail sent', Mockery::on(
            static fn(array $context): bool => $context['mailer'] === 'array'
                && $context['to'] === ['jan@example.com']
                && $context['subject'] === 'Powitanie',
        ));
        Log::shouldReceive('channel')->once()->with('mail')->andReturn($mailLogger);

        Mail::mailer('array')->raw('Treść', static function (Message $message): void {
            $message->to('jan@example.com')->subject('Powitanie');
        });
    }

    public function test_mail_sent_by_the_log_transport_is_not_logged_twice(): void
    {
        $mailLogger = Mockery::mock(LoggerInterface::class);
        $mailLogger->shouldReceive('debug')->once();
        $mailLogger->shouldNotReceive('info');
        Log::shouldReceive('channel')->with('mail')->andReturn($mailLogger);

        Mail::mailer('log')->raw('Treść', static function (Message $message): void {
            $message->to('jan@example.com')->subject('Powitanie');
        });
    }

    public function test_mail_channel_writes_daily_email_files(): void
    {
        $this->assertSame('daily', config('logging.channels.mail.driver'));
        $this->assertSame(storage_path('logs/email.log'), config('logging.channels.mail.path'));
    }
}
