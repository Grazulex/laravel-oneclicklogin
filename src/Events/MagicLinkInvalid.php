<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Events;

use Grazulex\OneClickLogin\Models\MagicLink;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MagicLinkInvalid
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $reason,
        public readonly ?string $token = null,
        public readonly ?MagicLink $magicLink = null
    ) {}
}
