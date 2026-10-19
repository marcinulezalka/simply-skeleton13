<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Config\Repository as Config;
use Illuminate\Log\LogManager;
use Illuminate\Mail\Events\MessageSent;
use Symfony\Component\Mime\Address;

/**
 * Records every mail sent by a real transport (SMTP etc.) in the mail log channel.
 *
 * The "log" transport already writes the whole message to that channel, so
 * mail sent through it is skipped to avoid logging the same message twice.
 */
class LogSentMail
{
    private const string LOG_TRANSPORT = 'log';

    public function __construct(
        private readonly LogManager $logManager,
        private readonly Config $config,
    ) {}

    public function handle(MessageSent $event): void
    {
        $mailer = $event->data['mailer'] ?? null;
        $mailer = is_string($mailer) ? $mailer : $this->config->string('mail.default');

        if ($this->config->get("mail.mailers.{$mailer}.transport") === self::LOG_TRANSPORT) {
            return;
        }

        $message = $event->message;

        $this->logManager->channel($this->config->string('mail.mailers.log.channel'))->info('Mail sent', [
            'mailer' => $mailer,
            'message_id' => $event->sent->getMessageId(),
            'from' => $this->formatAddresses($message->getFrom()),
            'to' => $this->formatAddresses($message->getTo()),
            'cc' => $this->formatAddresses($message->getCc()),
            'bcc' => $this->formatAddresses($message->getBcc()),
            'subject' => $message->getSubject(),
        ]);
    }

    /**
     * @param  array<Address>  $addresses
     * @return list<string>
     */
    private function formatAddresses(array $addresses): array
    {
        return array_values(array_map(
            static fn(Address $address): string => $address->toString(),
            $addresses,
        ));
    }
}
