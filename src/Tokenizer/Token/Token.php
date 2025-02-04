<?php

namespace Devsrealm\TonicsTemplateSystem\Tokenizer\Token;

final class Token
{
    private static array $tokenCollection;

    // TokenType String
    const Tag = 'Tag';
    const Comment = 'Comment';
    const Character = 'Character';
}