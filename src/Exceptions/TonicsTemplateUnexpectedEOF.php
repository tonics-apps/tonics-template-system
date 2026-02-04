<?php

namespace Devsrealm\TonicsTemplateSystem\Exceptions;

use Throwable;

class TonicsTemplateUnexpectedEOF extends TonicsTemplateRuntimeException
{
    public function __construct($message = "Unexpected End of File", $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}