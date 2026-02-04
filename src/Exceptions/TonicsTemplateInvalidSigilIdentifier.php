<?php

namespace Devsrealm\TonicsTemplateSystem\Exceptions;

use JetBrains\PhpStorm\Pure;
use LogicException;
use Throwable;

class TonicsTemplateInvalidSigilIdentifier extends LogicException
{
    #[Pure] public function __construct($message = "Invalid Sigil Identifier", $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}