<?php

namespace Devsrealm\TonicsTemplateSystem\Exceptions;

use Throwable;

class TonicsTemplateRuntimeException extends \RuntimeException
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
       parent::__construct($message, $code, $previous);
    }
}