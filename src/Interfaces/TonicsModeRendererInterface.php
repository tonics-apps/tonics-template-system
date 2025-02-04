<?php

namespace Devsrealm\TonicsTemplateSystem\Interfaces;

interface TonicsModeRendererInterface
{
    public function render (string $content, array $args, array $nodes = []):string;
}