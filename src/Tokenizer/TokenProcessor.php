<?php

namespace Devsrealm\TonicsTemplateSystem\Tokenizer;

use Devsrealm\TonicsTemplateSystem\Exceptions\TonicsTemplateInvalidSigilIdentifier;
use Devsrealm\TonicsTemplateSystem\Exceptions\TonicsTemplateModeError;
use Devsrealm\TonicsTemplateSystem\Node\Tag;
use Devsrealm\TonicsTemplateSystem\TonicsView;
use Devsrealm\TonicsTemplateSystem\Tokenizer\Token\Events\OnTagToken;
use Devsrealm\TonicsTemplateSystem\Tokenizer\Token\Token;
use InvalidArgumentException;

/**
 * This Trait Should Only Be Used Inside The TonicsView Class, it Won't work in any other class,
 * in short, it is part of the TonicsView class, I just split it for convenience
 */
trait TokenProcessor
{

    private int $line = 1;

    private array $characterToken = [
        'tokenType' => Token::Character,
        'data' => ''
    ];
    private ?Tag $lastCreateTag = null;
    private array $stackOfOpenTagEl = [];
    private array $currentTagSorted = [];
    private int $sigilCounter = 0;
    // in order to switch to raw tag, you need three [[[, if we discover more [,
    // we increment it, (could be user wanna include [[[ in a raw content)
    private int $numberOfOpenRawSigilFoundInBeforeEncounteringAChar = 0;
    private bool $stopIncrementingOpenTagSigilCharacter = false;
    private ?object $EOF = null;
    private bool $escape = true;
    private string $lastEmitted = '';


    /**
     * In order to switch to the RAW-STATE, you need three of left square bracket `[[[` (it doesn't have to be 3 but depends on the tokenizer implementation),
     * if we discover more [, we increment the `incrementNumberOfOpenRawSigilFoundInBeforeEncounteringAChar()` function,
     * it could be that user want to include `[[[` in a raw content.
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
     * If you wanna add 6, use 7 as an encapsulation, makes sense?
     */
    public function incrementNumberOfOpenRawSigilFoundInBeforeEncounteringAChar(): void
    {
        $this->numberOfOpenRawSigilFoundInBeforeEncounteringAChar = $this->numberOfOpenRawSigilFoundInBeforeEncounteringAChar + 1;
    }

    public function incrementSigilCounter(): void
    {
        $this->sigilCounter = $this->sigilCounter + 1;
    }

    public function decrementSigilCounter(): void
    {
        $this->sigilCounter = $this->sigilCounter - 1;
    }

    /**
     * Return true if stack of open tag is empty, otherwise, false
     * @return bool
     */
    public function stackOfOpenTagIsEmpty(): bool
    {
        return empty($this->stackOfOpenTagEl);
    }

    /**
     * Return true if stack of open tag is not empty, otherwise, false
     * @return bool
     */
    public function stackOfOpenTagIsNotEmpty(): bool
    {
        return !empty($this->stackOfOpenTagEl);
    }

    /**
     * @param string $tagName
     * @return $this
     */
    public function createNewTagInOpenStackTag(string $tagName): static
    {
        $newTag =  new Tag($tagName);
        $newTag->setDiscoveredOpenTagOnLine($this->getLine());
        $this->stackOfOpenTagEl[] = $newTag;
        $this->lastCreateTag = $newTag;
        return $this;
    }

    public function removeLastOpenTag(): void
    {
        array_pop($this->stackOfOpenTagEl);
    }

    public function getLastOpenTag(): Tag
    {
        $key = array_key_last($this->stackOfOpenTagEl);
        return $this->stackOfOpenTagEl[$key];
    }

    public function replaceCurrentTokenTagArgKey($replaceValue): static
    {
        $tag = $this->getLastOpenTag();
        $tag->replaceLastArgKey($replaceValue);
        return $this;
    }

    /**
     * @param string $char
     * @return $this
     */
    public function appendCharToCurrentTokenTagContent(string $char): static
    {
        $tag = $this->getLastOpenTag();
        $tag->appendCharacterToContent($char);
        return $this;
    }

    /**
     * @param string $char
     * @return $this
     */
    public function prependTooCurrentTokenTagContent(string $char): static
    {
        $tag = $this->getLastOpenTag();
        $tag->prependCharacterToContent($char);
        return $this;
    }


    public function appendCharToCurrentTokenTagName(string $char): static
    {
        $tag = $this->getLastOpenTag();
        $tag->appendCharacterToTagname($char);
        return $this;
    }

    public function startNewArgsInCurrentTagToken(string $char = ''): static
    {
        $tag = $this->getLastOpenTag();
        $tag->addArgs([$char]);
        return $this;
    }

    public function appendCharToArgValue(string $char = ''): static
    {
        $tag = $this->getLastOpenTag();
        $tag->appendCharacterToLastArg($char);
        return $this;
    }

    public function addTagInStackOfOpenElKey($key, Tag $tag): void
    {
        if (key_exists($key, $this->stackOfOpenTagEl)){
            $this->stackOfOpenTagEl[$key] = $tag;
        }
    }

    public function unsetKeyInStackOfOpenEl($key): void
    {
        unset($this->stackOfOpenTagEl[$key]);
    }

    public function clearStackOfOpenEl(): void
    {
        $this->stackOfOpenTagEl = [];
        $this->lastCreateTag = null;
    }

    public function setCurrentCharacterToken(?string $data = null): static
    {
        $this->characterToken['data'] = $data;
        return $this;
    }

    public function appendToCharacterToken(string $char): static
    {
        $this->characterToken['data'] .= $char;
        return $this;
    }

    public function prependToCharacterToken(string $char): static
    {
        $this->characterToken['data'] = $char . $this->characterToken['data'];
        return $this;
    }

    public function prependAndAppendToCharacterToken(string $prepend, string $append): static
    {
        $this->characterToken['data'] = $prepend . $this->characterToken['data'] . $append;
        return $this;
    }

    /**
     * Handles emission, to handle emission yourself use the `$handleEmission` Callable
     * @param string $toEmit
     * @param bool $emitCurrentTagToken
     * This would only be in effect if $handleEmission callback isn't use
     * @param callable|null $handleEmission
     * You get the token user wanna emit e.g Token::Tag or Token::Character
     */
    public function emit(string $toEmit, bool $emitCurrentTagToken = true, ?callable $handleEmission = null)
    {
        if ($handleEmission !== null) {
            $handleEmission();
        } else {
            if ($toEmit === Token::Character && !empty($this->characterToken['data'])) {
                $this->createNewTagInOpenStackTag('char');
                $this->appendCharToCurrentTokenTagContent($this->characterToken['data']);
                $this->clearCharacterTokenData();
                if ($emitCurrentTagToken) {
                    $this->emitCurrentTagToken();
                    $this->lastEmitted = Token::Character;
                }
            }

            if ($toEmit === Token::Tag) {
                if ($emitCurrentTagToken) {
                    $this->emitCurrentTagToken();
                    $this->lastEmitted = Token::Tag;
                }
            }
        }
    }

    public function clearCharacterTokenData(): static
    {
        $this->characterToken['data'] = '';
        return $this;
    }

    public function sortStackOfOpenTagEl()
    {
        $revs = array_reverse($this->stackOfOpenTagEl, true);
        /**@var Tag $tag */
        $closed = [];
        foreach ($revs as $key => &$tag) {
            $this->checkAndSetAppropriateContextFree($tag);
            if ($tag->isCloseState()) {
                array_unshift($closed, $tag);
                unset($this->stackOfOpenTagEl[$key]);
            }

            if ($tag->isOpenState() && !empty($closed)) {
                $tag->appendChildren($closed);
                $this->stackOfOpenTagEl[$key] = $tag;
                /**@var Tag $t */
                foreach ($closed as $t) {
                    $t->setParentNode($tag);
                }
                break;
            }
        }
    }

    public function emitCurrentTagToken(): static
    {
        if ($this->sigilCounter === 0) {
            /**@var Tag $parent */
            $parent = array_shift($this->stackOfOpenTagEl);
            $parent->setCloseState(true);
            $parent->setDiscoveredCloseTagOnLine($this->getLine());
            $event = new OnTagToken($parent);
            $this->checkAndSetAppropriateContextFree($parent);
            $handler = $this->getModeHandlerValue($parent->getTagName());
            if ($handler->validate($event)) {
                if ($this->debug) {
                    print "CurrentTagToken Emitted In " . get_class($handler) . "<br>";
                }
                $handler->stickToContent($event);
                $this->clearStackOfOpenEl();
            } else {
                $this->exception(TonicsTemplateModeError::class, [$handler->error()]);
            }
            return $this;
        }

        if ($this->sigilCounter < 0) {
            $this->exception(TonicsTemplateInvalidSigilIdentifier::class, ["Mis-nested Tags"]);
        }
        /**@var Tag $last */
        $last = $this->stackOfOpenTagEl[array_key_last($this->stackOfOpenTagEl)];
        $last->setCloseState(true);
        $this->sortStackOfOpenTagEl();
        return $this;
    }

    public function checkAndSetAppropriateContextFree(Tag $tag): void
    {
        $tag->setContextFree($this->getModeNameContextFree($tag->getTagName()));
    }

    /**
     * @param array $arg
     * @param string $mode
     * @param int $maxArgAllowed
     * @param int $minArgAllowed
     * @return bool
     */
    public function validateMaxArg(array $arg, string $mode, int $maxArgAllowed = 1, int $minArgAllowed = 1): bool
    {
        $argN = count($arg);
        if ($argN > $maxArgAllowed || $argN < $minArgAllowed) {
            $this->exception(InvalidArgumentException::class, ["Max of $maxArgAllowed and Min of $minArgAllowed Arg Allowed In $mode Mode Handler, $argN Provided"]);
        }

        return true;
    }

    /**
     * @return bool
     */
    public function getEscape(): bool
    {
        return $this->escape;
    }

    /**
     * @param bool $escape
     */
    public function setEscape(bool $escape): void
    {
        $this->escape = $escape;
    }

    /**
     * @return array
     */
    public function getStackOfOpenTagEl(): array
    {
        return $this->stackOfOpenTagEl;
    }

    /**
     * it would insert elements or tags in that position and push down the
     * former elements or tags using the position
     * @param $elements
     * @param int $position
     * @return $this
     */
    public function addElementInStackOfOpenPosition($elements, int $position): static
    {
        $array = $this->stackOfOpenTagEl;
        array_splice($array, $position, 0, $elements);
        $this->stackOfOpenTagEl = $array;
        return $this;
    }



    public function reverseStackOfOpenTagEl(): array
    {
        return array_reverse($this->stackOfOpenTagEl, true);
    }

    /**
     * @return string
     */
    public function getLastEmitted(): string
    {
        return $this->lastEmitted;
    }

    /**
     * @param string $lastEmitted
     * @return TonicsView
     */
    public function setLastEmitted(string $lastEmitted): TonicsView
    {
        $this->lastEmitted = $lastEmitted;
        return $this;
    }

    /**
     * @return array
     */
    public function getCharacterToken(): array
    {
        return $this->characterToken;
    }

    /**
     * @param array $stackOfOpenTagEl
     * @return TokenProcessor
     */
    public function setStackOfOpenTagEl(array $stackOfOpenTagEl): static
    {
        $this->stackOfOpenTagEl = $stackOfOpenTagEl;
        return $this;
    }

    /**
     * @return int
     */
    public function getSigilCounter(): int
    {
        return $this->sigilCounter;
    }

    /**
     * @param int $sigilCounter
     */
    public function setSigilCounter(int $sigilCounter): void
    {
        $this->sigilCounter = $sigilCounter;
    }

    /**
     * @return int
     */
    public function getLine(): int
    {
        return $this->line;
    }

    public function incrementLine(): int
    {
        $this->line = $this->line + 1;
        return $this->line;
    }

    /**
     * @param int $line
     * @return TonicsView
     */
    public function setLine(int $line): TonicsView
    {
        $this->line = $line;
        return $this;
    }

    /**
     * @return int
     */
    public function getNumberOfOpenRawSigilFoundInBeforeEncounteringAChar(): int
    {
        return $this->numberOfOpenRawSigilFoundInBeforeEncounteringAChar;
    }

    /**
     * @param int $numberOfOpenRawSigilFoundInBeforeEncounteringAChar
     */
    public function setNumberOfOpenRawSigilFoundInBeforeEncounteringAChar(int $numberOfOpenRawSigilFoundInBeforeEncounteringAChar): void
    {
        $this->numberOfOpenRawSigilFoundInBeforeEncounteringAChar = $numberOfOpenRawSigilFoundInBeforeEncounteringAChar;
    }

    /**
     * @return bool
     */
    public function hasStoppedIncrementingOpenTagSigilCharacter(): bool
    {
        return $this->stopIncrementingOpenTagSigilCharacter;
    }

    /**
     * @param bool $stopIncrementingOpenTagSigilCharacter
     */
    public function setStopIncrementingOpenTagSigilCharacter(bool $stopIncrementingOpenTagSigilCharacter): void
    {
        $this->stopIncrementingOpenTagSigilCharacter = $stopIncrementingOpenTagSigilCharacter;
    }

    public function cloneStackOfOpenTag(): array
    {
        $stack = [];
        foreach ($this->stackOfOpenTagEl as $st)
        {
            $stack[] = clone $st;
        }

        return $stack;
    }

    /**
     * @return Tag|null
     */
    public function getLastCreateTag(): ?Tag
    {
        return $this->lastCreateTag;
    }
}