<?php

namespace App\DTOs\Auth;

final readonly class LoginData
{
    public function __construct(
        public string $email,
        public string $password,
        public string $deviceName = 'api',
        public ?string $role = null,
    ) {
    }
}
