<?php

namespace Devsrealm\TonicsTemplateSystem\Exceptions;

use Throwable;

class TonicsTemplateInvalidTagNameIdentifier extends \LogicException
{
    public function __construct($message = "Invalid TagName Identifier; TagName Can Only Contain AsciiAlpha or AsciiDigit or an Underscore", $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}