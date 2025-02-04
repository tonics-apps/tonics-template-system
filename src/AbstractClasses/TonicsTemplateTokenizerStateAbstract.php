<?php

namespace Devsrealm\TonicsTemplateSystem\AbstractClasses;

use Devsrealm\TonicsTemplateSystem\Exceptions\TonicsTemplateInvalidSigilIdentifier;
use Devsrealm\TonicsTemplateSystem\Exceptions\TonicsTemplateInvalidTagNameIdentifier;
use Devsrealm\TonicsTemplateSystem\TonicsView;

abstract class TonicsTemplateTokenizerStateAbstract
{
    const InitialStateHandler = 'InitialStateHandler';
    const TonicsTagLeftSquareBracketStateHandler = 'TonicsTagLeftSquareBracketStateHandler';
    const TonicsTagOpenStateHandler = 'TonicsTagOpenStateHandler';
    const TonicsTagNameStateHandler = 'TonicsTagNameStateHandler';
    const TonicsTagOpenParenThesisStateHandler = 'TonicsTagOpenParenThesisStateHandler';
    const TonicsTagCloseParenThesisStateHandler = 'TonicsTagCloseParenThesisStateHandler';
    const TonicsTagParenthesisValueSingleQuotedStateHandler = 'TonicsTagParenthesisValueSingleQuotedStateHandler';
    const TonicsTagParenthesisValueDoubleQuotedStateHandler = 'TonicsTagParenthesisValueDoubleQuotedStateHandler';
    const TonicsTagParenthesisAfterValueQuotedStateHandler = 'TonicsTagParenthesisAfterValueQuotedStateHandler';
    const TonicsTagClosingStateHandler = 'TonicsTagClosingStateHandler';
    const TonicsTagVariableUnescapedStateHandler = 'TonicsTagVariableUnescapedStateHandler';
    const TonicsTagOpenArgValueSingleQuotedStateHandler = 'TonicsTagOpenArgValueSingleQuotedStateHandler';
    const TonicsTagBeforeOpenArgValueDoubleQuotedStateHandler = 'TonicsTagBeforeOpenArgValueDoubleQuotedStateHandler';
    const TonicsTagOpenArgValueDoubleQuotedStateHandler = 'TonicsTagOpenArgValueDoubleQuotedStateHandler';
    const TonicsAfterTagArqValueQuotedStateHandler = 'TonicsAfterTagArqValueQuotedStateHandler';
    const TonicsRawStateStateHandler = 'TonicsRawStateStateHandler';
    const TonicsCommentStateStateHandler = 'TonicsCommentStateStateHandler';

    public static abstract function InitialStateHandler(TonicsView $tonicsView): void;

    public static abstract function TonicsTagLeftSquareBracketStateHandler(TonicsView $tonicsView): void;

    public static abstract function TonicsTagOpenStateHandler(TonicsView $view): void;

    public static abstract function TonicsTagNameStateHandler(TonicsView $tonicsView): void;

    public static abstract function TonicsTagOpenArgValueSingleQuotedStateHandler(TonicsView $tonicsView): void;

    public static abstract function TonicsTagOpenArgValueDoubleQuotedStateHandler(TonicsView $tonicsView): void;

    public static function TonicsAfterTagArqValueQuotedStateHandler(TonicsView $tonicsView): void
    {
        return;
    }

    public static function TonicsTagClosingStateHandler(TonicsView $tonicsView): void
    {
        return;
    }

    public static function TonicsTagOpenParenThesisStateHandler(TonicsView $tonicsView): void
    {
        return;
    }

    public static function TonicsTagParenthesisValueSingleQuotedStateHandler(TonicsView $tonicsView): void
    {
        return;
    }

    public static function TonicsTagParenthesisValueDoubleQuotedStateHandler(TonicsView $tonicsView): void
    {
        return;
    }

    public static function TonicsTagParenthesisAfterValueQuotedStateHandler(TonicsView $tonicsView): void
    {
        return;
    }
}