<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Events;

use Grazulex\OneClickLogin\Models\MagicLink;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MagicLinkUsed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly MagicLink $magicLink,
        public readonly string $ipAddress,
        public readonly ?string $userAgent = null
    ) {}
}
