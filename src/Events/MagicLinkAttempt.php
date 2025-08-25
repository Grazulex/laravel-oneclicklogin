<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Events;

use Carbon\Carbon;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MagicLinkAttempt
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ?string $token,
        public readonly string $ip,
        public readonly ?string $userAgent,
        public readonly bool $success,
        public readonly Carbon $timestamp
    ) {}
}
