<?php

namespace Devsrealm\TonicsTemplateSystem\Interfaces;

use Devsrealm\TonicsTemplateSystem\TonicsView;

interface TonicsScannerInterface
{
    public function getTonicsView(): TonicsView;

    public function setScanner(TonicsView $tonicsView): static;
}
