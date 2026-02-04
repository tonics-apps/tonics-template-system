<?php

namespace Devsrealm\TonicsTemplateSystem\Exceptions;

use JetBrains\PhpStorm\Pure;
use Throwable;

class TonicsTemplateInvalidNumberOfArgument extends \InvalidArgumentException
{
    #[Pure] public function __construct($message = "Invalid Number of Argument", $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}