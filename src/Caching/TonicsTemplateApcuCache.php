<?php

namespace Devsrealm\TonicsTemplateSystem\Caching;

use Devsrealm\TonicsTemplateSystem\Exceptions\TonicsTemplateRuntimeException;
use Devsrealm\TonicsTemplateSystem\Interfaces\TonicsTemplateCacheInterface;

class TonicsTemplateApcuCache implements TonicsTemplateCacheInterface
{
    public function __construct()
    {
        if (!function_exists('apcu_enabled')){
            throw new TonicsTemplateRuntimeException("APCU Is Not Available");
        }
    }

    /**
     * @inheritDoc
     */
    public function add(string $key, mixed $value): bool
    {
        return apcu_store($key, $value);
    }

    /**
     * @inheritDoc
     */
    public function get(string $key): mixed
    {
        return apcu_fetch($key);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): array|bool
    {
        return apcu_delete($key);
    }

    /**
     * @inheritDoc
     */
    public function exists(string $key): bool
    {
        return apcu_exists($key);
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        return apcu_clear_cache();
    }
}