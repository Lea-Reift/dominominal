<?php

declare(strict_types=1);

namespace App\Support\Services;

use Exception;
use Brevo\Client\Api\SendersApi;
use Brevo\Client\Configuration;
use Brevo\Client\Model\CreateSender;
use Brevo\Client\Model\GetSendersListSenders;
use Brevo\Client\Model\Otp;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;

class BrevoService
{
    protected string $apiKey;
    protected SendersApi $sendersApi;
    protected Client $guzzle;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? config('mail.mailers.brevo.key');
        $this->guzzle = new Client();

        $config = Configuration::getDefaultConfiguration()->setApiKey('api-key', $this->apiKey);
        $this->sendersApi = new SendersApi($this->guzzle, $config);
    }

    /**
     * Get all valid senders from Brevo
     */
    public function getValidSenders(): Collection
    {
        try {
            $result = $this->sendersApi->getSenders();
            $senders = $result->getSenders();

            return collect($senders)->map(function (GetSendersListSenders $sender) {
                return [
                    'id' => $sender->getId(),
                    'email' => $sender->getEmail(),
                    'name' => $sender->getName(),
                    'active' => $sender->getActive(),
                ];
            });
        } catch (Exception $e) {
            throw new Exception('Error al obtener remitentes válidos: ' . $e->getMessage());
        }
    }

    /**
     * Add a new sender to Brevo
     */
    public function addSender(string $email, string $name): array
    {
        try {
            $createSender = new CreateSender([
                'name' => $name,
                'email' => $email,
            ]);

            $result = $this->sendersApi->createSender($createSender);

            return [
                'id' => $result->getId(),
            ];
        } catch (Exception $e) {
            throw new Exception('Error al agregar remitente: ' . $e->getMessage());
        }
    }

    /**
     * Get sender by email to find senderId
     */
    public function getSenderByEmail(string $email): ?array
    {
        $senders = $this->getValidSenders();
        return $senders->firstWhere('email', $email);
    }

    /**
     * Get sender ID by email
     */
    public function getSenderIdByEmail(string $email): ?int
    {
        $sender = $this->getSenderByEmail($email);
        return $sender['id'] ?? null;
    }

    /**
     * Validate sender using OTP code
     */
    public function validateSenderWithOTP(int $senderId, int $otpCode): bool
    {
        try {
            $this->guzzle->request('PUT', "https://api.brevo.com/v3/senders/{$senderId}/validate", [
                'headers' => [
                    'accept' => 'application/json',
                    'api-key' => $this->apiKey,
                    'content-type' => 'application/json',
                ],
                'body' => json_encode([
                    'otp' => $otpCode
                ]),
            ]);
            return true;
        } catch (Exception $e) {
            throw new Exception('Error al validar código OTP: ' . $e->getMessage());
        }
    }

    /**
     * Check if an email is a valid sender
     */
    public function isValidSender(string $email): bool
    {
        try {
            $validSenders = $this->getValidSenders();
            return $validSenders->where('email', $email)->where('active', true)->isNotEmpty();
        } catch (Exception) {
            return false;
        }
    }
}
