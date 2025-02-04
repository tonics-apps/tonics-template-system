<?php

namespace Devsrealm\TonicsTemplateSystem\Interfaces;

use Devsrealm\TonicsTemplateSystem\TonicsView;

interface TonicsTemplateCustomRendererInterface
{
    public function render(TonicsView $tonicsView): string;
}