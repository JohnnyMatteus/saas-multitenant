<?php

declare(strict_types=1);

namespace App\Modules\Identity\WhatsApp;

final readonly class ContactHasher
{
    public function __construct(private string $pepper) {}

    public function contact(string $tenantId, string $contact): string
    {
        return $this->hash('contact', $tenantId, $contact);
    }

    public function code(string $tenantId, string $code): string
    {
        return $this->hash('code', $tenantId, $code);
    }

    private function hash(string $purpose, string $tenantId, string $value): string
    {
        return hash_hmac('sha256', $purpose.'|'.$tenantId.'|'.$value, $this->pepper);
    }
}
