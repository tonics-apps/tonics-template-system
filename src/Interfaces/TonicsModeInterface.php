<?php

namespace Devsrealm\TonicsTemplateSystem\Interfaces;

use Devsrealm\TonicsTemplateSystem\Tokenizer\Token\Events\OnCharacterToken;
use Devsrealm\TonicsTemplateSystem\Tokenizer\Token\Events\OnCommentToken;
use Devsrealm\TonicsTemplateSystem\Tokenizer\Token\Events\OnEOFToken;
use Devsrealm\TonicsTemplateSystem\Tokenizer\Token\Events\OnTagToken;

interface TonicsModeInterface
{
    public function validate(OnTagToken $tagToken):bool;

    public function stickToContent(OnTagToken $tagToken);

    public function error(): string;
}