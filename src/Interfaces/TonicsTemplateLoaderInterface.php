<?php

namespace Devsrealm\TonicsTemplateSystem\Interfaces;

interface TonicsTemplateLoaderInterface
{
    public function load(string $name);

    public function exists(string $name): bool;
}