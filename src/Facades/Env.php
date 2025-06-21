<?php

namespace Daguilarm\EnvManager\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string|null get(string $key, $default = null)
 * @method static bool has(string $key)
 * @method static \Daguilarm\EnvManager\EnvManager set(string $key, string $value, ?string $inlineComment = null, array $commentsAbove = null)
 * @method static bool save()
 * @method static \Daguilarm\EnvManager\EnvManager remove(string $key)
 * @method static \Daguilarm\EnvManager\EnvManager load()
 * @method static string getEnvContent()
 *
 * @see \Daguilarm\EnvManager\EnvManager
 */
class Env extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Daguilarm\EnvManager\Services\EnvManager::class;
    }
}
