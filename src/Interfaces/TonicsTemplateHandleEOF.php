<?php

namespace Devsrealm\TonicsTemplateSystem\Interfaces;

use Devsrealm\TonicsTemplateSystem\TonicsView;

interface TonicsTemplateHandleEOF
{
    public function handleEOF(TonicsView $tonicsView): void;
}