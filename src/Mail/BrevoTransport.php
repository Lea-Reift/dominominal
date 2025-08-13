<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Support\Arr;
use Symfony\Component\Mime\MessageConverter;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;

class BrevoTransport extends AbstractTransport
{
    protected string $apiKey;
    protected string $endpoint = 'https://api.brevo.com/v3/smtp/email';

    public function __construct(string $apiKey)
    {
        parent::__construct();
        $this->apiKey = $apiKey;
    }


    protected function doSend(SentMessage $message): void
    {
        // @phpstan-ignore-next-line
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $payload = [
            'sender' => [
                'name' => $email->getFrom()[0]->getName() ?: $email->getFrom()[0]->getAddress(),
                'email' => $email->getFrom()[0]->getAddress(),
            ],
            'to' => $this->formatAddress($email->getTo()),
            'subject' => $email->getSubject(),
            'htmlContent' => $email->getHtmlBody(),
        ];

        // Add CC recipients if any
        if ($email->getCc()) {
            $payload['cc'] = $this->formatAddress($email->getCc());
        }

        // Add BCC recipients if any
        if ($email->getBcc()) {
            $payload['bcc'] = $this->formatAddress($email->getBcc());
        }

        // Add text content if available
        if ($email->getTextBody()) {
            $payload['textContent'] = $email->getTextBody();
        }

        $response = Http::withHeaders([
            'api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->endpoint, $payload);

        if ($response->failed()) {
            throw new \Exception('Failed to send email via Brevo: ' . $response->body());
        }
    }

    public function __toString(): string
    {
        return 'brevo';
    }

    /**
     * @param  array<Address> $addresses
     * @return array{name: string, email: string}[]
     */
    protected function formatAddress(array $addresses): array
    {
        return Arr::map($addresses, fn (Address $address) => [
            'name' => $address->getName() ?: $address->getAddress(),
            'email' => $address->getAddress(),
        ]);
    }
}
