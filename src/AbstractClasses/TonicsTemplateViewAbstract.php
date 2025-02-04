<?php

namespace Devsrealm\TonicsTemplateSystem\AbstractClasses;

use Devsrealm\TonicsTemplateSystem\Interfaces\TonicsScannerInterface;
use Devsrealm\TonicsTemplateSystem\TonicsView;

abstract class TonicsTemplateViewAbstract implements TonicsScannerInterface
{
    private TonicsView $tonicsView;

    /**
     * @param TonicsView $tonicsView
     * @return TonicsTemplateViewAbstract
     */
    public function setScanner(TonicsView $tonicsView): static
    {
        $this->tonicsView = $tonicsView;
        return $this;
    }

    /**
     * @return TonicsView
     */
    public function getTonicsView(): TonicsView
    {
        return  $this->tonicsView;
    }
}