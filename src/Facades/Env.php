<?php

namespace Daguilar\BelichEnvManager\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string|null get(string $key, $default = null)
 * @method static bool has(string $key)
 * @method static \Daguilar\BelichEnvManager\EnvManager set(string $key, string $value, ?string $inlineComment = null, array $commentsAbove = null)
 * @method static bool save()
 * @method static \Daguilar\BelichEnvManager\EnvManager remove(string $key)
 * @method static \Daguilar\BelichEnvManager\EnvManager load()
 * @method static string getEnvContent()
 *
 * @see \Daguilar\BelichEnvManager\EnvManager
 */
class Env extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Daguilar\BelichEnvManager\Env\EnvManager::class;
    }
}
