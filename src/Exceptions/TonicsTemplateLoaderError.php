<?php

namespace Devsrealm\TonicsTemplateSystem\Exceptions;

use Exception;
use JetBrains\PhpStorm\Pure;
use Throwable;

class TonicsTemplateLoaderError extends Exception
{
    #[Pure] public function __construct($message = null, $code = 0, ?Throwable $previous = null)
    {
       parent::__construct($message, $code, $previous);
    }
}