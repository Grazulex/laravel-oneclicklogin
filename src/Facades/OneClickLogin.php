<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Facades;

use Grazulex\OneClickLogin\Services\MagicLinkManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Grazulex\OneClickLogin\Services\MagicLinkBuilder for(string $email)
 * @method static \Grazulex\OneClickLogin\Services\MagicConsumer consume(string $token)
 * @method static int prune(int $days = 7)
 * @method static \Grazulex\OneClickLogin\Models\MagicLink extend(\Grazulex\OneClickLogin\Models\MagicLink $link, int $hours)
 * @method static \Grazulex\OneClickLogin\Models\MagicLink revoke(\Grazulex\OneClickLogin\Models\MagicLink $link)
 *
 * @see MagicLinkManager
 */
class OneClickLogin extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MagicLinkManager::class;
    }
}
