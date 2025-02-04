<?php

namespace Devsrealm\TonicsTemplateSystem\Exceptions;

use JetBrains\PhpStorm\Pure;
use RangeException;
use Throwable;

class TonicsTemplateRangeException extends RangeException
{
    #[Pure] public function __construct($message = "Out Or Over Array Index", $code = 0, Throwable $previous = null)
    {
       parent::__construct($message, $code, $previous);
    }
}