<?php

namespace Daguilar\EnvManager\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string|null get(string $key, $default = null)
 * @method static bool has(string $key)
 * @method static \Daguilar\EnvManager\EnvManager set(string $key, string $value, ?string $inlineComment = null, array $commentsAbove = null)
 * @method static bool save()
 * @method static \Daguilar\EnvManager\EnvManager remove(string $key)
 * @method static \Daguilar\EnvManager\EnvManager load()
 * @method static string getEnvContent()
 *
 * @see \Daguilar\EnvManager\EnvManager
 */
class Env extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Daguilar\EnvManager\Services\EnvManager::class;
    }
}
