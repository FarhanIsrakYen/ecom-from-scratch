<?php

namespace App\DTOs\Auth;

final readonly class ForgotPasswordData
{
    public function __construct(
        public string $email,
    ) {
    }
}
