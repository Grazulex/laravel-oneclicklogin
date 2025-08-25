<?php

declare(strict_types=1);

if (! function_exists('createTestUser')) {
    function createTestUser(string $email): object
    {
        $userClass = config('oneclicklogin.user_model', 'App\\Models\\User');

        // For testing, we'll mock a simple user
        return new class($email)
        {
            public function __construct(public string $email) {}

            public static function where(string $field, string $value): self
            {
                return new self($value);
            }

            public function first(): ?self
            {
                return $this;
            }
        };
    }
}
