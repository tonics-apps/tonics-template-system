<?php

namespace Devsrealm\TonicsTemplateSystem\Tokenizer\State;

use Devsrealm\TonicsTemplateSystem\AbstractClasses\TonicsTemplateTokenizerStateAbstract;
use Devsrealm\TonicsTemplateSystem\Exceptions\TonicsTemplateInvalidCharacterUponOpeningTag;
use Devsrealm\TonicsTemplateSystem\Exceptions\TonicsTemplateInvalidSigilIdentifier;
use Devsrealm\TonicsTemplateSystem\Exceptions\TonicsTemplateInvalidTagNameIdentifier;
use Devsrealm\TonicsTemplateSystem\Exceptions\TonicsTemplateUnexpectedEOF;
use Devsrealm\TonicsTemplateSystem\TonicsView;
use Devsrealm\TonicsTemplateSystem\Tokenizer\Token\Token;

/**
 * NOTE: IN State that test against ASCII-ALPHA, EOF should always be above, this is because I use 'EOF' to signify we are at the bottom of the
 * character stack, failure to do so, would disrupt the state.
 */
class DefaultTokenizerState extends TonicsTemplateTokenizerStateAbstract
{
    private static int $numberOfQuotationFoundBeforeEncounteringSpace = 1;

    /**
     * - If the character is a LeftSquareBracket([): Emit character and Switch the state to TonicsTagLeftSquareBracket
     * - If the character is a RightSquareBracket(]): Emit character and Switch the state to TonicsTagClosingStateHandler
     * - anything Else: Append character to the current character token
     * @param TonicsView $tv
     */
    public static function InitialStateHandler(TonicsView $tv): void
    {
        if ($tv->charIsLeftSquareBracket()) {
            $tv->emit(Token::Character);
            $tv->switchState(self::TonicsTagLeftSquareBracketStateHandler);
            return;
        }

        if ($tv->charIsRightSquareBracket()) {
            $tv->emit(Token::Character);
            $tv->switchState(self::TonicsTagClosingStateHandler);
            return;
        }

        if ($tv->charIsEOF()) {
            $tv->emit(Token::Character);
            return;
        }

        $tv->appendToCharacterToken($tv->getChar());
    }

    /**
     * - If the character is a LeftSquareBracket([): Switch state to TonicsTagOpenState
     * - Anything Else: Throw an exception
     * @param TonicsView $tv
     */
    public static function TonicsTagLeftSquareBracketStateHandler(TonicsView $tv): void
    {
        if ($tv->charIsLeftSquareBracket()) {
            $tv->incrementSigilCounter();
            $tv->switchState(self::TonicsTagOpenStateHandler);
            return;
        }

        if ($tv->charIsEOF()) {
            $tv->exception(TonicsTemplateUnexpectedEOF::class, ["Unexpected End of File"]);
        }

        $tv->exception(TonicsTemplateInvalidSigilIdentifier::class, ["Invalid Sigil Identifier"]);
    }

    public static function TonicsTagOpenStateHandler(TonicsView $tv): void
    {
        if ($tv->charIsLeftSquareBracket()) {
            $tv->switchState(self::TonicsRawStateStateHandler);
            return;
        }

        if ($tv->charIsDash()) {
            $tv->switchState(self::TonicsCommentStateStateHandler);
            return;
        }

        if ($tv->charIsEOF()) {
            $tv->exception(TonicsTemplateUnexpectedEOF::class, ["Unexpected End of File"]);
        }

        if ($tv->charIsAsciiAlphaOrAsciiDigitOrUnderscore()) {
            $tv->createNewTagInOpenStackTag('')
                ->reconsumeIn(self::TonicsTagNameStateHandler);
            return;
        }

        $tv->exception(TonicsTemplateInvalidCharacterUponOpeningTag::class);
    }

    /**
     * If you have gotten here, then it means we have encountered three left square bracket `[[[`,
     * if we discover more [, we increment the `incrementNumberOfOpenRawSigilFoundInBeforeEncounteringAChar()` function,
     * it could be that user wanna include `[[[` in a raw content.
     *
     * If you want to add 5 left square bracket [[[[[ in a raw content, you use 6 left square bracket to encapsulate it, e.g:
     *
     * ```
     * [[[[[[
     *
     *  This can include [ to [[[[[ left square
     *
     * ]]]]]]
     * ```
     *
     * If you want to add 6, use 7 as an encapsulation, makes sense?
     */
    public static function TonicsRawStateStateHandler(TonicsView $tv): void
    {
        if ($tv->charIsRightSquareBracket()) {
            $totalOpenRawTag = 3 + $tv->getNumberOfOpenRawSigilFoundInBeforeEncounteringAChar();
            if ($tv->consumeMultipleCharactersIf(str_repeat(']', $totalOpenRawTag))) {
                $tv->decrementSigilCounter();
                $tv->setNumberOfOpenRawSigilFoundInBeforeEncounteringAChar(0);
                $tv->setStopIncrementingOpenTagSigilCharacter(false);
                $tv->emit(Token::Character);
                $tv->switchState(self::InitialStateHandler);
                return;
            }
            # Don't Return here, let it fall downwards, why? cos it should ;p
        }

        if ($tv->charIsLeftSquareBracket()) {
            if ($tv->hasStoppedIncrementingOpenTagSigilCharacter() === false) {
                $tv->incrementNumberOfOpenRawSigilFoundInBeforeEncounteringAChar();
                return;
            }
        }

        if ($tv->charIsEOF()) {
            $tv->exception(TonicsTemplateUnexpectedEOF::class, ["Unexpected End of File"]);
        }

        $tv->appendToCharacterToken($tv->getChar());
        $tv->setStopIncrementingOpenTagSigilCharacter(true);
    }

    public static function TonicsCommentStateStateHandler(TonicsView $tv): void
    {
        if ($tv->charIsDash()) {
            ## End of Comment
            if ($tv->consumeMultipleCharactersIf('-]]')) {
                $tv->decrementSigilCounter();
                $tv->switchState(self::InitialStateHandler);
                return;
            }
            # Don't Return here, let it fall downwards, why? cos it should ;p
        }

        if ($tv->charIsEOF()) {
            $tv->exception(TonicsTemplateUnexpectedEOF::class, ["Unexpected End of File"]);
        }

        # At this point we should have a comment string, we ignore it...
        return;
    }

    public static function TonicsTagNameStateHandler(TonicsView $tv): void
    {
        $char = $tv->getChar();

        if ($tv->charIsEOF()) {
            $tv->exception(TonicsTemplateUnexpectedEOF::class, ["Unexpected End of File"]);
        }

        if ($tv->charIsAsciiAlphaOrAsciiDigitOrUnderscore()) {
            $tv->appendCharToCurrentTokenTagName($char);
            return;
        }

        if ($tv->charIsTabOrLFOrFFOrSpace()) {
            return;
        }

        if ($tv->charIsRightSquareBracket()) {
            $tv->switchState(self::TonicsTagClosingStateHandler);
            return;
        }

        if ($tv->charIsLeftParenthesis()) {
            $tv->switchState(self::TonicsTagOpenParenThesisStateHandler);
            return;
        }

        $tv->exception(TonicsTemplateInvalidTagNameIdentifier::class);
    }

    public static function TonicsTagOpenParenThesisStateHandler(TonicsView $tv): void
    {
        if ($tv->charIsApostrophe()) {
            $tv->startNewArgsInCurrentTagToken();
            $tv->switchState(self::TonicsTagOpenArgValueSingleQuotedStateHandler);
            return;
        }

        if ($tv->charIsQuotationMark()) {
            $tv->startNewArgsInCurrentTagToken();
            $tv->reconsumeIn(self::TonicsTagBeforeOpenArgValueDoubleQuotedStateHandler);
            return;
        }

        if ($tv->charIsRightParenthesis()) {
            $tv->switchState(self::TonicsTagCloseParenThesisStateHandler);
            return;
        }
        if ($tv->charIsEOF()) {
            $tv->exception(TonicsTemplateUnexpectedEOF::class, ["Unexpected End of File"]);
        }

        $tv->exception(TonicsTemplateInvalidTagNameIdentifier::class, ['Invalid Identifier After Opening Arg Parenthesis']);
    }

    /**
     * Note: Depending on how the ModeHandler wanna react to the tag Token,
     * it’s arg can be  anything (except left/right parenthesis) but when it sees APOSTROPHE ('), it switches
     * @param TonicsView $tv
     */
    public static function TonicsTagOpenArgValueSingleQuotedStateHandler(TonicsView $tv): void
    {
        $char = $tv->getChar();
        if ($tv->charIsApostrophe()) {
            $tv->switchState(self::TonicsAfterTagArqValueQuotedStateHandler);
            return;
        }

        if ($tv->charIsRightParenthesis() || $tv->charIsLeftParenthesis()) {
            $tv->exception(TonicsTemplateInvalidSigilIdentifier::class, ["`$char` is an invalid arg identifier"]);
        }

        if ($tv->charIsEOF()) {
            $tv->exception(TonicsTemplateUnexpectedEOF::class, ["Unexpected End of File"]);
        }

        $tv->appendCharToArgValue($char);
    }

    /**
     * Sometimes you might wanna include a quote inside a quote arg, e.g """,
     * if you do it that way you'll get an error, you can instead do "" " "".
     *
     * What if you wanna include 2 quotes, you surround the arg with three quotes e.g. """ "" """,
     * to include 4 quotes, you surround the arg with 5 quotes, etc.
     * @param TonicsView $tv
     * @return void
     */
    public static function TonicsTagBeforeOpenArgValueDoubleQuotedStateHandler(TonicsView $tv): void
    {
        // if it contains more than one quotation mark, then it is time for escaping
        if ($tv->isNextChar('"')) {
            self::$numberOfQuotationFoundBeforeEncounteringSpace = 1;
            $tv->consumeUntil(function ($c) use ($tv) {
                if ($tv->charIsQuotationMark($c)) {
                    ++self::$numberOfQuotationFoundBeforeEncounteringSpace;
                    return true;
                } else {
                    return false;
                }
            });
        }

        $tv->switchState(self::TonicsTagOpenArgValueDoubleQuotedStateHandler);
    }

    public static function TonicsTagOpenArgValueDoubleQuotedStateHandler(TonicsView $tv): void
    {
        $char = $tv->getChar();

        if ($tv->charIsQuotationMark()) {
            if ($tv->consumeMultipleCharactersIf(str_repeat('"', self::$numberOfQuotationFoundBeforeEncounteringSpace))) {
                self::$numberOfQuotationFoundBeforeEncounteringSpace = 1;
                $tv->switchState(self::TonicsAfterTagArqValueQuotedStateHandler);
                return;
            }
        }

        if (self::$numberOfQuotationFoundBeforeEncounteringSpace === 1){
            if ($tv->charIsRightParenthesis() || $tv->charIsLeftParenthesis()) {
                $tv->exception(TonicsTemplateInvalidSigilIdentifier::class, ["`$char` is an invalid arg identifier"]);
            }
        }

        if ($tv->charIsEOF()) {
            $tv->exception(TonicsTemplateUnexpectedEOF::class, ["Unexpected End of File"]);
        }

        $tv->appendCharToArgValue($char);
    }

    public static function TonicsAfterTagArqValueQuotedStateHandler(TonicsView $tv): void
    {
        if ($tv->charIsTabOrLFOrFFOrSpace() || $tv->charIsComma()) {
            return;
        }

        if ($tv->charIsEOF()) {
            $tv->exception(TonicsTemplateUnexpectedEOF::class, ["Unexpected End of File"]);
        }

        if ($tv->charIsApostrophe()) {
            $tv->startNewArgsInCurrentTagToken();
            $tv->switchState(self::TonicsTagOpenArgValueSingleQuotedStateHandler);
            return;
        }

        if ($tv->charIsQuotationMark()) {
            $tv->startNewArgsInCurrentTagToken();
            $tv->reconsumeIn(self::TonicsTagBeforeOpenArgValueDoubleQuotedStateHandler);
            return;
        }

        if ($tv->charIsRightParenthesis()) {
            $tv->switchState(self::TonicsTagCloseParenThesisStateHandler);
            return;
        }

        $tv->exception(TonicsTemplateInvalidSigilIdentifier::class, ['Close Tag Arg With Right Parenthesis']);
    }

    public static function TonicsTagCloseParenThesisStateHandler(TonicsView $tv): void
    {
        $char = $tv->getChar();
        if ($tv->charIsRightSquareBracket()) {
            $tv->switchState(self::TonicsTagClosingStateHandler);
            return;
        }

        if ($tv->charIsEOF()) {
            $tv->exception(TonicsTemplateUnexpectedEOF::class, ["Unexpected End of File"]);
        }

        if ($tv->charIsLeftSquareBracket()) {
            $tv->emit(Token::Character);
            $tv->switchState(self::TonicsTagLeftSquareBracketStateHandler);
            return;
        }

        $tv->appendCharToCurrentTokenTagContent($char);
    }

    public static function TonicsTagClosingStateHandler(TonicsView $tv): void
    {
        if ($tv->charIsRightSquareBracket()) {
            $tv->decrementSigilCounter();
            $tv->switchState(self::InitialStateHandler);
            $tv->emit(Token::Tag);
            return;
        }

        if ($tv->charIsEOF()) {
            $tv->exception(TonicsTemplateUnexpectedEOF::class, ["Unexpected End of File"]);
        }

        $tv->exception(TonicsTemplateInvalidSigilIdentifier::class, ["Invalid Sigil Identifier"]);
    }

    public static function TonicsTagParenthesisValueSingleQuotedStateHandler(TonicsView $tv): void
    {

    }

    public static function TonicsTagParenthesisValueDoubleQuotedStateHandler(TonicsView $tv): void
    {

    }

    public static function TonicsTagParenthesisAfterValueQuotedStateHandler(TonicsView $tv): void
    {

    }

    /**
     * @return int
     */
    public static function getNumberOfQuotationFoundBeforeEncounteringSpace(): int
    {
        return self::$numberOfQuotationFoundBeforeEncounteringSpace;
    }

    /**
     * @param int $numberOfQuotationFoundBeforeEncounteringSpace
     */
    public static function setNumberOfQuotationFoundBeforeEncounteringSpace(int $numberOfQuotationFoundBeforeEncounteringSpace): void
    {
        self::$numberOfQuotationFoundBeforeEncounteringSpace = $numberOfQuotationFoundBeforeEncounteringSpace;
    }
}