<?php

namespace Devsrealm\TonicsTemplateSystem\Exceptions;

use Throwable;

class TonicsTemplateInvalidCharacterUponOpeningTag extends \LogicException
{
    public function __construct($message = "Invalid Character Upon Opening Tag", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}