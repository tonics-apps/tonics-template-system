<?php

namespace Devsrealm\TonicsTemplateSystem\Interfaces;

use Devsrealm\TonicsTemplateSystem\Node\Tag;

interface TonicsModeRenderWithTagInterface
{
    public function render (string $content, array $args, Tag $tag):string;

    public function defaultArgs(): array;
}