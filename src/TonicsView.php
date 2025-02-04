<?php

namespace Devsrealm\TonicsTemplateSystem;

use Devsrealm\TonicsTemplateSystem\AbstractClasses\TonicsTemplateViewAbstract;
use Devsrealm\TonicsTemplateSystem\AbstractClasses\TonicsTemplateTokenizerStateAbstract;
use Devsrealm\TonicsTemplateSystem\Exceptions\TonicsTemplateModeError;
use Devsrealm\TonicsTemplateSystem\Exceptions\TonicsTemplateRangeException;
use Devsrealm\TonicsTemplateSystem\Exceptions\TonicsTemplateRuntimeException;
use Devsrealm\TonicsTemplateSystem\Interfaces\TonicsModeInterface;
use Devsrealm\TonicsTemplateSystem\Interfaces\TonicsModeRendererInterface;
use Devsrealm\TonicsTemplateSystem\Interfaces\TonicsTemplateCacheInterface;
use Devsrealm\TonicsTemplateSystem\Interfaces\TonicsTemplateCustomRendererInterface;
use Devsrealm\TonicsTemplateSystem\Interfaces\TonicsTemplateHandleEOF;
use Devsrealm\TonicsTemplateSystem\Interfaces\TonicsTemplateLoaderInterface;
use Devsrealm\TonicsTemplateSystem\Tokenizer\TokenProcessor;
use Devsrealm\TonicsTemplateSystem\Tree\Mode\Handlers\BlockModeHandler;
use Devsrealm\TonicsTemplateSystem\Tree\Mode\Handlers\CharacterModeHandler;
use Devsrealm\TonicsTemplateSystem\Tree\Mode\Handlers\FunctionModeHandler;
use Devsrealm\TonicsTemplateSystem\Tree\Mode\Handlers\ImportModeHandler;
use Devsrealm\TonicsTemplateSystem\Tree\Mode\Handlers\InheritModeHandler;
use Devsrealm\TonicsTemplateSystem\Tree\Mode\Handlers\UseModeHandler;
use Devsrealm\TonicsTemplateSystem\Tree\Mode\Handlers\VariableModeHandler;

class TonicsView
{
    use TokenProcessor;

    private int $currentCharKey = 0;
    private array $characters = [];
    private TonicsTemplateTokenizerStateAbstract $tokenizerState;
    private string $returnState = '';
    private string $currentState = '';
    // $reconsumeState is only use for debugging
    private string $reconsumeState = '';
    private array $stateLifeCycle = [];
    private string|array|null $char = null;
    private TonicsTemplateLoaderInterface $templateLoader;
    private string $templateName = '';
    private string $cachePrefix = '';
    private array|\stdClass $variableData = [];
    private bool $dontCacheVariable = false;

    private array $contextFree = [];
    # Useful for storing data pertaining to the modes

    // giving modeStorages and modeHandler additional null type,
    // fixes a bug where nested views in modeStorages and modeHandlers are trying to set null (this is caused by apcu_fetch)
    // before setting the right data...
    private array|null $modeStorages = [];
    private array|null $modeHandler = [];

    private bool $debug = false;
    private string $debugChars = '';
    private bool $dontWriteDebugChar = false;
    private bool $debugSwitchIsOn = false;
    private bool $debugReconsumeIsOn = false;
    private bool $debugRemoveLastChar= false;
    private ?int $currentRenderingContentKey = null;
    private ?string $currentRenderingContentMode = null;
    private string $lastThrownException = '';
    private Content $content;
    private ?TonicsTemplateCacheInterface $templateCache = null;
    private string $final = '';
    private ?TonicsTemplateCustomRendererInterface $customRenderer = null;
    private ?TonicsTemplateHandleEOF $handleEOF = null;
    private string $previousState = '';
    private bool $endTokenization = false;

    public function __construct(
        array $settings = []
    )
    {
        if (isset($settings['templateLoader'])) {
            $this->templateLoader = $settings['templateLoader'];
        }

        if (isset($settings['tokenizerState'])) {
            $this->tokenizerState = $settings['tokenizerState'];
            // Init State
            $this->currentState = $settings['tokenizerState']::InitialStateHandler;
        }

        if (isset($settings['data'])) {
            $this->variableData = $settings['data'];
        }

        if (isset($settings['templateCache'])) {
            $this->templateCache = $settings['templateCache'];
        }

        if (isset($settings['content'])) {
            $this->content = $settings['content'];
        }

        if (isset($settings['render'])) {
            $this->customRenderer = $settings['render'];
        }

        if (isset($settings['handleEOF'])) {
            $this->handleEOF = $settings['handleEOF'];
        }

        $this->modeHandler = [
            'import' => ImportModeHandler::class,
            'inherit' => InheritModeHandler::class,

            'use' => UseModeHandler::class,
            '_use' => UseModeHandler::class,

            'usec' => UseModeHandler::class,
            '_usec' => UseModeHandler::class,

            'b' => BlockModeHandler::class,
            'block' => BlockModeHandler::class,

            'func' => FunctionModeHandler::class,
            'function' => FunctionModeHandler::class,
            'arg' => FunctionModeHandler::class, // would be used to refer to func arg

            'v' => VariableModeHandler::class,
            '_v' => VariableModeHandler::class,
            'var' => VariableModeHandler::class,

            'char' => CharacterModeHandler::class,
        ];

        $this->contextFree = [
            'import' => true,
            'inherit' => false,
            'use' => true,
            '_use' => true,
            'usec' => true,
            '_usec' => true,
            'b' => true,
            'block' => true,
            'func' => true,
            'function' => true,
            'arg' => true, // would be used to refer to func arg
            'v' => true,
            '_v' => true,
            'var' => true,
            'char' => true,
        ];

        $this->modeStorages['var'] = [];
        $this->modeStorages['block'] = [];
        $this->modeStorages['use'] = [];
        $this->modeStorages['import'] = [];
        $this->modeStorages['inherit'] = [];
        $this->modeStorages['func'] = [];
    }

    /**
     * Copies:
     * - TemplateLoaders,
     * - TokenizerState Object
     * - ModeHandlers
     * - ModeStorages
     * - ContextFree Info
     *
     * Note: It uses a new instance of Content
     * @param TonicsView $newView
     * @return void
     */
    public function copySettingsToNewViewInstance(TonicsView $newView): void
    {
        $newView->setContent(new Content());
        $newView->setTemplateLoader($this->getTemplateLoader());
        $newView->setTokenizerState($this->getTokenizerState());
        $newView->setModeHandler($this->getModeHandler());
        $newView->setModeStorages($this->getModeStorages());
        $newView->setContextFree($this->getContextFree());
    }

    public function reset(): TonicsView
    {
        $this->currentCharKey = 0;
        $this->line = 1;
        $this->stateLifeCycle = [];
        $this->switchState($this->tokenizerState::InitialStateHandler);
        $this->lastEmitted = '';
        $this->lastThrownException = '';
        $this->char = null;
        $this->returnState = '';
        $this->stackOfOpenTagEl = [];
        $this->sigilCounter = 0;
        $this->content->clearContents();
        $this->setCurrentCharacterToken();

        return $this;
    }

    /**
     * This switches the state
     */
    public function switchState(string $state): static
    {

        if ($this->debug){
            $this->debugReconsumeIsOn = false;
            $this->debugSwitchIsOn = true;
            $this->previousState = $this->currentState;
            $this->debugRemoveLastChar = false;
        }
        $this->setCurrentState($state);
        return $this;
    }


    /**
     * @return array
     */
    public function getCharacters(): array
    {
        return $this->characters;
    }

    /**
     * @param string|array $char
     * @return $this
     */
    public function addToCharacters(string|array $char): static
    {
        $this->characters[] = $char;
        return $this;
    }

    /**
     * @param array $characters
     * @return TonicsView
     */
    public function setCharacters(array $characters): TonicsView
    {
        $this->characters = $characters;
        return $this;
    }

    /**
     * @return TonicsTemplateTokenizerStateAbstract
     */
    public function getTokenizerState(): TonicsTemplateTokenizerStateAbstract
    {
        return $this->tokenizerState;
    }

    /**
     * @param string $currentState
     * @return TonicsView
     */
    public function setCurrentState(string $currentState): TonicsView
    {
        $this->currentState = $currentState;
        return $this;
    }

    /**
     * @return string
     */
    public function getCurrentState(): string
    {
        return $this->currentState;
    }

    /**
     * @param string $returnState
     * @return TonicsView
     */
    public function setReturnState(string $returnState): TonicsView
    {
        $this->returnState = $returnState;
        return $this;
    }

    /**
     * @return string
     */
    public function getReturnState(): string
    {
        return $this->returnState;
    }

    /**
     * @param int $currentCharKey
     * @return TonicsView
     */
    public function setCurrentCharKey(int $currentCharKey): TonicsView
    {
        $this->currentCharKey = $currentCharKey;
        return $this;
    }

    /**
     * @return int
     */
    public function getCurrentCharKey(): int
    {
        return $this->currentCharKey;
    }

    /**
     * @return string
     */
    public function getTemplateName(): string
    {
        return $this->templateName;
    }

    /**
     * @param string $templateName
     * @return TonicsView
     */
    public function setTemplateName(string $templateName): TonicsView
    {
        $this->templateName = $templateName;
        return $this;
    }



    public function dispatchState(string $stateHandler): void
    {
        $this->tokenizerState::$stateHandler($this);
    }

    /**
     * Would Throw and Record The Last Exception thrown.
     *
     * Note: If debug mode is on, Exception won't be thrown but printed and exit();
     * @param string $exceptionClass
     * @param array $args
     * @return void|null
     */
    public function exception(string $exceptionClass, array $args = [])
    {
        return (function () use ($exceptionClass, $args) {
            $this->lastThrownException = $exceptionClass;
            if (!$this->isDebug()) {
                $line = $this->line; $templateName = $this->templateName;
                (empty($args)) ? throw new $exceptionClass() : $args[0] = $args[0] . ". On Line $line in Template $templateName"; throw new $exceptionClass(...$args);
            }
            print "Exception $exceptionClass was Thrown in State " . $this->currentState . "with char " . $this->char . " key " . $this->currentCharKey . "<br>";
            exit();
        })();
    }

    /**
     * Could be an array or string depending on how you are tokenizing
     * @return string|array
     */
    public function getChar(): string|array
    {
        return $this->char;
    }

    /**
     * An example is @
     * @param null $var
     * Would use the current character in the getChar() method if $var isn't supplied
     * @return bool
     */
    public function charIsAt($var = null): bool
    {
        if ($var === null) {
            $var = $this->char;
        }
        return $var === '@';
    }

    /**
     * An example is -
     * @param null $var
     * Would use the current character in the getChar() method if $var isn't supplied
     * @return bool
     */
    public function charIsDash($var = null): bool
    {
        if ($var === null) {
            $var = $this->char;
        }
        return $var === '-';
    }

    /**
     * An example is !
     * @param null $var
     * Would use the current character in the getChar() method if $var isn't supplied
     * @return bool
     */
    public function charIsExclamation($var = null): bool
    {
        if ($var === null) {
            $var = $this->char;
        }
        return $var === '!';
    }

    /**
     * An example is =
     * @param null $var
     * Would use the current character in the getChar() method if $var isn't supplied
     * @return bool
     */
    public function charIsEqual($var = null): bool
    {
        if ($var === null) {
            $var = $this->char;
        }
        return $var === '=';
    }

    /**
     * @param bool $var
     * Would use the current character in the getChar() method if $var isn't supplied
     * @return bool
     */
    public function charIsNull(bool $var = false): bool
    {
        if ($var === false) {
            $var = $this->char;
        }
        return $var === null;
    }

    public function charIsTabOrLFOrFFOrSpace($var = null): bool
    {
        if ($var === null) {
            $var = $this->char;
        }

        return
            $this->charIsTab($var) ||
            $this->charIsLineFeed($var) ||
            $this->charIsFormFeed($var) ||
            $this->charIsSpace($var);
    }

    /**
     * An example is \r
     * @param null $var
     * Would use the current character in the getChar() method if $var isn't supplied
     * @return bool
     */
    public function charIsCarriageReturn($var = null): bool
    {
        if ($var === null) {
            $var = $this->char;
        }
        return $var === "\r";
    }

    /**
     * An example is \t
     * @param null $var
     * Would use the current character in the getChar() method if $var isn't supplied
     * @return bool
     */
    public function charIsTab($var = null): bool
    {
        if ($var === null) {
            $var = $this->char;
        }
        return $var === "\t";
    }

    /**
     * An example is \n
     * @param null $var
     * Would use the current character in the getChar() method if $var isn't supplied
     * @return bool
     */
    public function charIsLineFeed($var = null): bool
    {
        if ($var === null) {
            $var = $this->char;
        }
        return $var === "\n";
    }

    /**
     * An example is \f
     * @param null $var
     * Would use the current character in the getChar() method if $var isn't supplied
     * @return bool
     */
    public function charIsFormFeed($var = null): bool
    {
        if ($var === null) {
            $var = $this->char;
        }
        return $var === "\f";
    }

    /**
     * An example is well, a space
     * @param null $var
     * Would use the current character in the getChar() method if $var isn't supplied
     * @return bool
     */
    public function charIsSpace($var = null): bool
    {
        if ($var === null) {
            $var = $this->char;
        }
        return $var === " ";
    }

    /**
     * An example is "
     * @param null $var
     * Would use the current character in the getChar() method if $var isn't supplied
     * @return bool
     */
    public function charIsQuotationMark($var = null): bool
    {
        if ($var === null) {
            $var = $this->char;
        }
        return $var === "\"";
    }


    /**
     * An example is ,
     * @param null $var
     * Would use the current character in the getChar() method if $var isn't supplied
     * @return bool
     */
    public function charIsComma($var = null): bool
    {
        if ($var === null) {
            $var = $this->char;
        }
        return $var === ",";
    }

    /**
     * An example is '
     * @param null $var
     * Would use the current character in the getChar() method if $var isn't supplied
     * @return bool
     */
    public function charIsApostrophe($var = null): bool
    {
        if ($var === null) {
            $var = $this->char;
        }
        return $var === "'";
    }

    /**
     * An ASCII alpha is an ASCII upper alpha or ASCII lower alpha. e.g AbCFG...
     * @param null $var
     * Would use the current character in the getChar() method if $var isn't supplied
     * @return bool
     */
    public function charIsAsciiAlpha($var = null): bool
    {
        if ($var === null) {
            $var = $this->char;
        }
        return preg_match("/^[a-z]/i", $var) === 1;
    }

    /**
     * An ASCII digit is a code point in the range U+0030 (0) to U+0039 (9), inclusive.
     * @param null $var
     * Would use the current character in the getChar() method if $var isn't supplied
     * @return bool
     */
    public function charIsAsciiDigit($var = null): bool
    {
        if ($var === null) {
            $var = $this->char;
        }
        return trim($var, '0..9') == '';
    }

    /**
     * An underscore e.g _
     * @param null $var
     * Would use the current character in the getChar() method if $var isn't supplied
     * @return bool
     */
    public function charIsUnderscore($var = null): bool
    {
        if ($var === null) {
            $var = $this->char;
        }
        return $var === "_";
    }

    public function charIsAsciiAlphaOrAsciiDigitOrUnderscore($var = null): bool
    {
        if ($var === null) {
            $var = $this->char;
        }
        return
            $this->charIsAsciiAlpha($var) ||
            $this->charIsAsciiDigit($var) ||
            $this->charIsUnderscore($var);
    }

    public function charIsAsciiAlphaOrAsciiDigitOrUnderscoreOrDash($var = null): bool
    {
        if ($var === null) {
            $var = $this->char;
        }
        return
            $this->charIsAsciiAlpha($var) ||
            $this->charIsAsciiDigit($var) ||
            $this->charIsDash($var) ||
            $this->charIsUnderscore($var);
    }


    /**
     * An example is .
     * @param null $var
     * Would use the current character in the getChar() method if $var isn't supplied
     * @return bool
     */
    public function charIsDot($var = null): bool
    {
        if ($var === null) {
            $var = $this->char;
        }
        return $var === ".";
    }

    /**
     * An example is /
     * @param null $var
     * Would use the current character in the getChar() method if $var isn't supplied
     * @return bool
     */
    public function charIsForwardSlash($var = null): bool
    {
        if ($var === null) {
            $var = $this->char;
        }
        return $var === "/";
    }

    /**
     * An example is \
     * @param null $var
     * Would use the current character in the getChar() method if $var isn't supplied
     * @return bool
     */
    public function charIsBackwardSlash($var = null): bool
    {
        if ($var === null) {
            $var = $this->char;
        }
        return $var === "\\";
    }

    /**
     * An example is (
     * @param null $var
     * Would use the current character in the getChar() method if $var isn't supplied
     * @return bool
     */
    public function charIsLeftParenthesis($var = null): bool
    {
        if ($var === null) {
            $var = $this->char;
        }
        return $var === "(";
    }

    /**
     * An example is )
     * @param null $var
     * Would use the current character in the getChar() method if $var isn't supplied
     * @return bool
     */
    public function charIsRightParenthesis($var = null): bool
    {
        if ($var === null) {
            $var = $this->char;
        }
        return $var === ")";
    }

    /**
     * An example is [
     * @param null $var
     * Would use the current character in the getChar() method if $var isn't supplied
     * @return bool
     */
    public function charIsLeftSquareBracket($var = null): bool
    {
        if ($var === null) {
            $var = $this->char;
        }
        return $var === "[";
    }

    /**
     * An example is [
     * @param null $var
     * Would use the current character in the getChar() method if $var isn't supplied
     * @return bool
     */
    public function charIsOperator($var = null): bool
    {
        if ($var === null) {
            $var = $this->char;
        }
        return $var === "+" || $var === "-" || $var === '*'
            || $var === "/" || $var === "^" || $var === "%";
    }

    /**
     * An example is ]
     * @param null $var
     * Would use the current character in the getChar() method if $var isn't supplied
     * @return bool
     */
    public function charIsRightSquareBracket($var = null): bool
    {
        if ($var === null) {
            $var = $this->char;
        }
        return $var === "]";
    }

    /**
     * An example is EOF
     * @return bool
     */
    public function charIsEOF(): bool
    {
        return $this->char === 'EOF';
    }

    /**
     * When a state says to reconsume a matched character in a specified state, that means to switch to that state,
     * but when it attempts to consume the next input character, provide it with the current input character instead.
     *
     * The next input character is the first character in the input stream that has not yet been consumed.
     * The current input character is the last character to have been consumed.
     *
     * So for reconsume to work, we backward the characters pointer position, we set the current character to the backwarded position,
     * this way, when the state is re-consuming, it can get the last character to have been consumed.
     * @throws TonicsTemplateRangeException
     */
    public function reconsumeIn(string $reconsumeState): static
    {
        if ($this->getCurrentCharKey() === 0) {
            throw new TonicsTemplateRangeException();
        }

        $i = $this->prevCharacterKey();
        $this->currentCharKey = $i;
        $this->char = $this->characters[$i];
        if ($this->debug){
            $this->debugReconsumeIsOn = true;
            $this->debugSwitchIsOn = false;
            $this->debugRemoveLastChar = true;
        }
        $this->currentState = $reconsumeState;
        $this->reconsumeState = $reconsumeState;
        return $this;
    }

    public function mb_str_split(string $string): array
    {
        $len = strlen($string);
        $str = [];
        # default to 1 byte
        $byte = 1;
        for ($i = 0; $i < $len; $i += $byte) {
            $v = ord($string[$i]);
            if ($v < 128) {
                $byte = 1;
            } elseif ($v < 224) {
                $byte = 2;
            } elseif ($v < 240) {
                $byte = 3;
            } elseif ($v < 248) {
                $byte = 4;
            }

            $c = '';
            $peak = $i + $byte;
            for ($k = $i; $k < $peak; ++$k) {
                $c .= $string[$k];
            }
            $str[] = $c;
            // $str[] =  substr($html, $i, $byte);
        }
        return $str;
    }

    /**
     * @param string|array $char
     * @return TonicsView
     */
    public function setChar(string|array $char): TonicsView
    {
        $this->char = $char;
        return $this;
    }

    /**
     * Consume until callable $func return false.
     * Note: This would start the consumption from the next character
     * @param callable $func
     */
    public function consumeUntil(callable $func)
    {
        $i = $this->currentCharKey + 1;
        $this->char = $this->characters[$i];
        $len = count($this->characters);
        for ($this->currentCharKey = $i; $this->currentCharKey < $len; ++$this->currentCharKey) {
            $k = $this->currentCharKey;
            $currentChar = $this->characters[$k];

            # PRE-PROCESS: Skip Carriage Return, Gat Nada to do with it.
            if ($this->charIsCarriageReturn($currentChar)) {
                continue;
            }

            if ($currentChar === "\n"){
                $this->incrementLine();
            }
            if ($func($currentChar) === false){
                $this->prevCharacterKey();
                break;
            }
        }
    }


    public function tokenize(): static
    {
        # INIT
        $i = $this->currentCharKey;

        if (!key_exists($i, $this->characters)){
            throw new TonicsTemplateRuntimeException("$i current character key doesn't exist in characters");
        }

        $this->char = $this->characters[$i];
        $len = count($this->characters);

        for ($this->currentCharKey = $i; $this->currentCharKey < $len; ++$this->currentCharKey) {

            if ($this->endTokenization){
                $this->handleEOF?->handleEOF($this);
                $this->endTokenization = false;
                break;
            }

            $k = $this->currentCharKey;
            $currentChar = $this->characters[$k];

            # PRE-PROCESS: Skip Carriage Return, Gat Nada to do with it.
            if ($this->charIsCarriageReturn($currentChar)) {
                continue;
            }

            if ($currentChar === "\n"){
                $this->incrementLine();
            }

            $this->char = $currentChar;
            $this->dispatchState($this->currentState);
            if ($this->debug){
                if ($this->isDontWriteDebugChar() === false && is_string($currentChar)){
                    $this->debugChars .= $currentChar;
                }
                # reset DontWriteDebugChar
                $this->setDontWriteDebugChar(false);
                if ($this->debugSwitchIsOn){
                    $state = $this->currentState;
                    $type = "State";
                    $in = $this->previousState;
                } else {
                    $in = $this->reconsumeState;
                    $state = '';
                    $type = "Reconsume";
                }

                if ($this->debugRemoveLastChar && is_string($currentChar)){
                    $this->debugChars = substr_replace($this->debugChars, '', -1);
                    $this->debugRemoveLastChar = false;
                }

                $this->stateLifeCycle[] = [
                    'Type' => $type,
                    'In' => $in,
                    'To ' => $state,
                    'Line' => $this->line,
                    'Key' => $this->getCurrentCharKey(),
                    'Char' => $this->char,
                    'Preview' => "->". $this->debugChars . "<-",
                    'OpenTag' => $this->cloneStackOfOpenTag()
                ];
            }

            // EOF
            if ($this->charIsEOF() && $this->handleEOF !==null){
                $this->handleEOF?->handleEOF($this);
            }
        }

        return $this;
    }

    const RENDER_CONCATENATE_AND_OUTPUT = 1;
    const RENDER_CONCATENATE = 2;
    const RENDER_TOKENIZE_ONLY = 3;

    /**
     * @param string $name
     * @param bool $outputToBrowser
     * if false, would return the result, else, it echoes to the browser.
     * @return string|void
     */

    /**
     * For $condition, you can use:
     *
     * - `TonicsView::RENDER_CONCATENATE_AND_OUTPUT` if you want to concatenate and output to the browser (default)
     * - `TonicsView::RENDER_CONCATENATE` if you only want to concatenate and get the string output
     * - `TonicsView::RENDER_TOKENIZE_ONLY` if you only want to tokenize and get the view object
     *
     * Note: If you have a custom render, It won't respect $condition
     * @param string $name
     * @param int $condition
     * @return mixed
     */
    public function render(string $name, int $condition = TonicsView::RENDER_CONCATENATE_AND_OUTPUT)
    {
        $restart = false;
        $this->templateName = $name;
        if ($this->templateCache !== null){
            $cacheKey = $this->getTemplateFriendlyName();
            $cacheModeStorageKey = $this->getTemplateFriendlyName().'__modeStorage';
            if ($this->getTemplateCache()->exists($cacheKey) === false){
                $restart = true;
            } else {
                $contents = $this->getTemplateCache()->get($cacheKey);
                $this->setContent($contents);
                $cacheStorage = $this->getTemplateCache()->get($cacheModeStorageKey);
                $cacheStorage = (is_array($cacheStorage)) ? $cacheStorage : [];
                $this->setModeStorages($cacheStorage);
            }
        } else {
            $restart = true;
        }

        if ($restart){
            $this->loadTemplateString($name, true);
            $contents = $this->content;
            # the below means, if getTemplateCache() isn't null, cache the contents
            $this->getTemplateCache()?->add($this->getTemplateFriendlyName(), $contents);
            $this->getTemplateCache()?->add($this->getTemplateFriendlyName().'__modeStorage', $this->modeStorages);
        }

        # For Custom Renderer
        if ($this->customRenderer instanceof TonicsTemplateCustomRendererInterface){
            return $this->customRenderer->render($this);
        }

        # Nothing Took Care of the Renderer, we handle it ourselves
        switch ($condition){
            case TonicsView::RENDER_CONCATENATE_AND_OUTPUT:
                echo $this->outputContentData($this->content->getContents());
                break;
            case TonicsView::RENDER_CONCATENATE:
                return $this->outputContentData($this->content->getContents());
            case TonicsView::RENDER_TOKENIZE_ONLY:
                return $this;
        }

        return '';
    }

    /**
     * @param array $contents
     * @return string
     */
    public function outputContentData(array $contents = []): string
    {
        $this->final = '';
        foreach ($contents as $k => $content) {
            $mode = $this->getModeRendererHandler($content['Mode']);
            if ($mode instanceof TonicsModeRendererInterface) {
                $this->setCurrentRenderingContentKey($k);
                $this->setCurrentRenderingContentMode($content['Mode']);
                $nodes = (isset($content['nodes'])) ? $content['nodes'] : [];
                $this->final .= $mode->render($content['content'], $content['args'], $nodes);
            }
        }

        return $this->final;
    }

    /**
     * @param string $blockName
     * @return string
     */
    public function renderABlock(string $blockName): string
    {
        if (!$this->getContent()->isBlock($blockName)){
            $this->exception(TonicsTemplateModeError::class, ["`$blockName` Is Not a Known Block"]);
        }
        $blockContents = $this->getContent()->getBlock($blockName);
        $data = '';
        foreach ($blockContents as $content){
            $mode = $this->getModeRendererHandler($content['Mode']);
            if ($mode instanceof TonicsModeRendererInterface) {
                $this->setCurrentRenderingContentMode($content['Mode']);
                $nodes = (isset($content['nodes'])) ? $content['nodes'] : [];
                $data .= $mode->render($content['content'], $content['args'], $nodes);
            }
        }

        return $data;
    }

    /**
     * Would Load and SplitString Char by Char
     * @param string $name
     * @param bool $tokenize
     * If true, would also tokenize the characters
     */
    public function loadTemplateString(string $name, bool $tokenize = false)
    {
        $this->templateName = $name;
        $this->splitStringCharByChar();
        if ($tokenize){
            $this->reset();
            $this->tokenize();
        }
    }

    public function splitStringCharByChar($chars = null): void
    {
        if (!function_exists('mb_str_split')) {
            if ($chars !== null){
                $this->characters = $this->mb_str_split($chars);
            } else {
                $this->characters = $this->mb_str_split($this->templateLoader->load($this->templateName));
            }
        } else {
            if ($chars !== null){
                $this->characters = mb_str_split($chars);
            } else {
                $this->characters = mb_str_split($this->templateLoader->load($this->templateName));
            }
        }
        $this->characters[] = 'EOF';
    }

    public function getTemplateFriendlyName(): array|string|null
    {
        $cacheKey = preg_replace('/[^\p{L}\p{N}]/s', '_', $this->templateName);
        return $this->getCachePrefix() . str_replace("'", '_', $cacheKey);
    }

    /**
     * Note: This would consume from the currentKey downward, and this is for character
     * @param string $characters
     * @param bool $caseInsensitive
     * @return bool
     */
    public function consumeMultipleCharactersIf(string $characters, bool $caseInsensitive = true): bool
    {
        $splitChars = $this->mb_str_split($characters);
        $len = count($splitChars);
        $copySlice = array_slice($this->characters, $this->currentCharKey, $len);
        if ($splitChars === $copySlice) {
            $this->currentCharKey = $this->currentCharKey + ($len - 1);
            return true;
        }

        return false;
    }

    public function getNumberOfTimesCharsFoundedConsecutively(string $characters): int
    {
        $i = $this->currentCharKey;
        $timeCharFoundedInARow = 0;
        while (true){
            if (!key_exists($i, $this->characters)){
                break;
            }
            $char = $this->characters[$i];
            if ($char === $characters){
                ++$timeCharFoundedInARow;
                ++$i;
                continue;
            }
            break;
        }

        return $timeCharFoundedInARow;
    }

    /**
     * This decrements the CharactersPointer position by 1,
     * updates the charpointer key
     * and return the key
     * @return int
     */
    public function prevCharacterKey(): int
    {
        $key = $this->currentCharKey - 1;
        if (!key_exists($key, $this->characters)) {
            throw new TonicsTemplateRangeException();
        }

        $this->currentCharKey = $key;
        return $key;
    }

    /**
     * This increments the CharactersPointer position by 1, and return the key
     * @param callable|null $handleError
     * Handle the error if an issue is raised moving the pointer forward
     * @return int
     */
    public function nextCharacterKey(callable $handleError = null): int
    {
        $key = $this->currentCharKey + 1;
        if (!key_exists($key, $this->characters)) {
            if ($handleError !== null){
                $handleError();
            } else {
                throw new TonicsTemplateRangeException();
            }
        }

        $this->currentCharKey = $key;
        return $key;
    }

    /**
     * @param string $char
     * @param callable|null $callable
     * @return bool|callable
     */
    public function isNextChar(string $char = '', callable $callable = null): bool|callable
    {

        $nextChar = $this->characters[$this->nextCharacterKey()];

        # Rewind back to its prev position as we are only checking next char
        $this->prevCharacterKey();
        if (!$callable && $char) {
            return $nextChar === $char;
        }
        return $callable($nextChar);
    }

    /**
     * What next char might look like...
     * @param callable|null $handleError
     * Handle the error if an issue is raised getting hypothetical next char
     * @return mixed
     */
    public function nextCharHypothetical(callable $handleError = null): mixed
    {
        $nextChar = $this->characters[$this->nextCharacterKey($handleError)];
        # Rewind back to its prev position as we are only checking next char
        $this->prevCharacterKey();
        return $nextChar;
    }

    /**
     * @param string $names
     * @param string $sep
     * @return mixed
     */
    public function accessArrayWithSeparator(string $names, string $sep = '.'): mixed
    {
        // Regular expression pattern to match keys with arrow notation (e.g., dtRow->dtHeader.td)
        $pattern = '/(?<=->|^)([^->]+)(?=$|->)/';
        preg_match_all($pattern, $names, $matches);

        $fullName = '';
        if (count($matches[1]) > 1){
            array_shift($matches[1]);
            foreach ($matches[1] as $matchedKey) {
                $expandedKey = '.' . $this->getDataFromVariableData($matchedKey, $sep);
                // Replace the arrow notation with the expanded key in the original names string
                $names = str_replace('->'.$matchedKey, $expandedKey, $names);
            }
        }

        $this->addToVariableData('__current_var_key_exist', $this->checkArrayKeyExistence($names, $sep));
        if ($names !== '__current_var_key_name'){
            $this->addToVariableData('__current_var_key_name', $names);
        }

        return $this->getDataFromVariableData($names, $sep);
    }

    /**
     * @param string $names
     * @param string $sep
     * @return array|mixed|\stdClass|string
     */
    private function getDataFromVariableData(string $names, string $sep = '.'): mixed
    {
        # Trim whitespace from the provided string of key names
        $names = trim($names);
        # assign `$this->variableData` to newly created variable $data
        $data = $this->variableData;
        # Split the string of key names into an array of individual key names
        $splitName = explode($sep, $names);
        # Loop through each key name and access the corresponding data in the array
        foreach ($splitName as $value) {
            # If the data is an object and the current key name exists as a property, update the data to the property value
            if (is_object($data) && property_exists($data, $value)){
                $data = $data->{$value};
            }
            # If the data is an array and the current key name exists as a key, update the data to the key value
            elseif (is_array($data) && key_exists($value, $data)) {
                $data = $data[$value];
            }
            # If the data is not an object or an array, or the current key name does not exist as a property or key, return an empty string
            else {
                return '';
            }
        }
        # Return accessed data
        return $data;
    }

    /**
     * @param string $names
     * @param string $sep
     * @return bool
     */
    public function checkArrayKeyExistence(string $names, string $sep = '.'): bool
    {
        // Trim whitespace from the provided string of key names
        $names = trim($names);
        // Assign `$this->variableData` to a newly created variable $data
        $data = $this->variableData;
        // Split the string of key names into an array of individual key names
        $splitName = explode($sep, $names);

        foreach ($splitName as $value) {
            // If the data is an object and the current key name exists as a property, update the data to the property value
            if (is_object($data) && property_exists($data, $value)) {
                $data = $data->{$value};
            }
            // If the data is an array and the current key name exists as a key, update the data to the key value
            elseif (is_array($data) && array_key_exists($value, $data)) {
                $data = $data[$value];
            }
            // If the data is not an object or an array, or the current key name does not exist as a property or key, return false
            else {
                return false;
            }
        }

        // All keys exist
        return true;
    }


    /**
     * @return array
     */
    public function getModeHandler(): array
    {
        return $this->modeHandler;
    }

    /**
     * @return TonicsTemplateLoaderInterface
     */
    public function getTemplateLoader(): TonicsTemplateLoaderInterface
    {
        return $this->templateLoader;
    }

    /**
     * @param TonicsTemplateTokenizerStateAbstract $tokenizerState
     * @return TonicsView
     */
    public function setTokenizerState(TonicsTemplateTokenizerStateAbstract $tokenizerState): TonicsView
    {
        $this->tokenizerState = $tokenizerState;
        return $this;
    }

    /**
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * @param bool $debug
     */
    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    /**
     * @return Content
     */
    public function getContent(): Content
    {
        return $this->content;
    }

    public function getModeHandlerValue(string $key): TonicsTemplateViewAbstract&TonicsModeInterface
    {
        $key = strtolower($key);
        if (!key_exists($key, $this->modeHandler)) {
            $this->exception(TonicsTemplateRangeException::class, ["`$key` is an Invalid Mode Handler Identifier "]);
        }
        $mode = $this->modeHandler[$key];
        if (is_string($mode)) {
            $mode = new $mode();
            $mode->setScanner($this);
            $this->modeHandler[$key] = $mode;
        }
        /**
         * @var $mode TonicsTemplateViewAbstract&TonicsModeInterface
         */
        if ($mode->getTonicsView() === $this){
            return $mode;
        }
        // If the mode view is not the same as this then, we are in a new context of tokenizer state...
        // so, we create a new mode, this way, it won't reference the wrong context
        $mode = get_class($mode);
        $mode = new $mode();
        $mode->setScanner($this);
        $this->modeHandler[$key] = $mode;

        return $mode;
    }

    /**
     * @param string $key
     * @param TonicsTemplateViewAbstract|null $optionalModeHandler
     * Would be used as the mode if set
     * @return TonicsTemplateViewAbstract
     */
    public function getModeRendererHandler(string $key, TonicsTemplateViewAbstract $optionalModeHandler = null): TonicsTemplateViewAbstract
    {
        $key = strtolower($key);
        if (!key_exists($key, $this->modeHandler)) {
            $this->exception(TonicsTemplateRangeException::class, ["`$key` is an Invalid Mode Handler Identifier "]);
        }

        $mode = $this->modeHandler[$key];

        if ($optionalModeHandler !== null){
            $mode = $optionalModeHandler;
            $mode->setScanner($this);
            $this->modeHandler[$key] = $mode;
        } elseif (is_string($mode)) {
            $mode = new $mode();
            $mode->setScanner($this);
            $this->modeHandler[$key] = $mode;
        }
        /** @var $mode TonicsTemplateViewAbstract */
        if ($mode->getTonicsView() === $this){
            return $mode;
        }
        // If the mode view is not the same as $this, we are in a new context of tokenizer state...
        // so, we create a new mode, this way, it won't reference the wrong context
        $mode = get_class($mode);
        $mode = new $mode();
        $mode->setScanner($this);
        $this->modeHandler[$key] = $mode;
        return $mode;
    }

    /**
     * @return string
     */
    public function getLastThrownException(): string
    {
        return $this->lastThrownException;
    }

    /**
     * @param string $lastThrownException
     * @return TonicsView
     */
    public function setLastThrownException(string $lastThrownException): TonicsView
    {
        $this->lastThrownException = $lastThrownException;
        return $this;
    }

    /**
     * @param TonicsTemplateLoaderInterface $templateLoader
     * @return TonicsView
     */
    public function setTemplateLoader(TonicsTemplateLoaderInterface $templateLoader): TonicsView
    {
        $this->templateLoader = $templateLoader;
        return $this;
    }

    /**
     * @return string
     */
    public function getFinal(): string
    {
        return $this->final;
    }

    /**
     * @param array|\stdClass $variableData
     * @return TonicsView
     */
    public function setVariableData(array|\stdClass $variableData): TonicsView
    {
        $this->variableData = $variableData;
        return $this;
    }

    /**
     *  You can also add data to a nested key, e.g key1.key2, but this only works with an array
     * @param string $key
     * @param $data
     * @return $this
     */
    public function addToVariableData(string $key, $data): static
    {
        $variableDataRef = &$this->variableData;
        $keys = explode('.', $key);

        foreach ($keys as $k) {
            if (is_array($variableDataRef)) {
                if (!array_key_exists($k, $variableDataRef)) {
                    $variableDataRef[$k] = [];
                }
                $variableDataRef = &$variableDataRef[$k];
            } elseif (is_object($variableDataRef)) {
                if (!property_exists($variableDataRef, $k)) {
                    $variableDataRef->{$k} = null;
                }
                $variableDataRef = &$variableDataRef->{$k};
            } else {
                // Handle unsupported data type (neither array nor object)
                throw new Exception('Unsupported data type encountered.');
            }
        }

        $variableDataRef = $data;

        return $this;
    }

    public function removeVariableData($key): void
    {
        unset($this->variableData[$key]);
    }

    /**
     * @return array|\stdClass
     */
    public function getVariableData(): array|\stdClass
    {
        return $this->variableData;
    }

    /**
     * @param bool $endTokenization
     * @return TonicsView
     */
    public function setEndTokenization(bool $endTokenization): TonicsView
    {
        $this->endTokenization = $endTokenization;
        return $this;
    }

    /**
     * @return TonicsTemplateCacheInterface|null
     */
    public function getTemplateCache(): ?TonicsTemplateCacheInterface
    {
        return $this->templateCache;
    }

    /**
     * @param TonicsTemplateCacheInterface|null $templateCache
     * @return TonicsView
     */
    public function setTemplateCache(?TonicsTemplateCacheInterface $templateCache): TonicsView
    {
        $this->templateCache = $templateCache;
        return $this;
    }

    public function reservedModeName(): array
    {
        return [
            'import' => 'import',
            'inherit' => 'inherit',
            'use' => 'use',
            'b' => 'b',
            'block' => 'block',
            'proc' => 'proc',
            'procedure' => 'procedure',
            'v' => 'v',
            'var' => 'var',
            'func' => 'func',
            'function' => 'function',
            'char' => 'char',
            'character' => 'character',
        ];
    }

    /**
     * @param string $modeName
     * The Modename, e.g. var
     * @param string $modeHandler
     * The ModeHandler, object string
     * @param bool $contextFree
     * This is used to determine if the mode can handle recursive nested tag rendering on its own
     * @return TonicsView
     */
    public function addModeHandler(string $modeName, string $modeHandler, bool $contextFree = true): static
    {
        $modeName = strtolower($modeName);
        if (key_exists($modeName, $this->modeHandler) && key_exists($modeName, $this->reservedModeName())) {
            throw new TonicsTemplateRuntimeException("`$modeName` is a reserved mode handler identifier, you can remove it using `removeHandlers()`");
        }

        $this->modeStorages[$modeName] = [];
        $this->modeHandler[$modeName] = $modeHandler;
        $this->contextFree[$modeName] = $contextFree;
        return $this;
    }


    public function removeModeHandlers(array $modeNames = []): void
    {
        foreach ($modeNames as $modeName){
            # No Point checking if they exist, unset would implicity do that...
            unset($this->modeHandler[$modeName], $this->modeStorages[$modeName]);
        }
    }

    /**
     * @param Content|mixed $content
     * @return TonicsView
     */
    public function setContent(mixed $content): TonicsView
    {
        $this->content = $content;
        return $this;
    }

    /**
     * @param string[] $modeHandler
     * @return TonicsView
     */
    public function setModeHandler(array $modeHandler): TonicsView
    {
        $this->modeHandler = $modeHandler;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getCurrentRenderingContentKey(): ?int
    {
        return $this->currentRenderingContentKey;
    }

    /**
     * @param int|null $currentRenderingContentKey
     */
    public function setCurrentRenderingContentKey(?int $currentRenderingContentKey): void
    {
        $this->currentRenderingContentKey = $currentRenderingContentKey;
    }

    /**
     * @param string $mode
     * @return bool
     */
    public function isModeStorage(string $mode): bool
    {
        return key_exists($mode, $this->modeStorages);
    }

    public function getModeStorage(string $mode){
        $mode = strtolower($mode);
        if (!key_exists($mode, $this->modeHandler)) {
            $this->exception(TonicsTemplateRangeException::class, ["`$mode` is an Invalid Mode Handler Identifier "]);
        }

        return $this->modeStorages[$mode];
    }

    /**
     * @param string $mode
     * @param array $data
     * @return $this
     */
    public function storeDataInModeStorage(string $mode, array $data): static
    {
        $mode = strtolower($mode);
        if (!key_exists($mode, $this->modeHandler)) {
            $this->exception(TonicsTemplateRangeException::class, ["`$mode` is an Invalid Mode Handler Identifier "]);
        }

        $this->modeStorages[$mode] = $data;
        return $this;
    }

    /**
     * @param string $mode
     * @return $this
     */
    public function clearAllDataInModeStorage(string $mode): static
    {
        $mode = strtolower($mode);
        if (!key_exists($mode, $this->modeHandler)) {
            $this->exception(TonicsTemplateRangeException::class, ["`$mode` is an Invalid Mode Handler Identifier "]);
        }

        $this->modeStorages[$mode] = [];
        return $this;
    }

    /**
     * @return array
     */
    public function getModeStorages(): array
    {
        return $this->modeStorages;
    }

    /**
     * @param array $modeStorages
     */
    public function setModeStorages(array $modeStorages): void
    {
        $this->modeStorages = $modeStorages;
    }

    /**
     * @return string|null
     */
    public function getCurrentRenderingContentMode(): ?string
    {
        return $this->currentRenderingContentMode;
    }

    /**
     * @param string|null $currentRenderingContentMode
     */
    public function setCurrentRenderingContentMode(?string $currentRenderingContentMode): void
    {
        $this->currentRenderingContentMode = $currentRenderingContentMode;
    }

    public function appendToDebugChars(string $char): void
    {
        $this->debugChars .=$char;
    }

    /**
     * @param string $final
     * @return TonicsView
     */
    public function setFinal(string $final): TonicsView
    {
        $this->final = $final;
        return $this;
    }

    /**
     * @param string $final
     * @return TonicsView
     */
    public function appendToFinal(string $final): TonicsView
    {
        $this->final .= $final;
        return $this;
    }

    /**
     * @return array
     */
    public function getStateLifeCycle(): array
    {
        return $this->stateLifeCycle;
    }

    /**
     * @param array $stateLifeCycle
     */
    public function setStateLifeCycle(array $stateLifeCycle): void
    {
        $this->stateLifeCycle = $stateLifeCycle;
    }

    /**
     * Query StateLifeCycle line from - to -
     * @param int $from
     * @param int|null $to
     * @return array
     */
    public function queryStateLifeCycleLine(int $from, int $to = null): array
    {
        $stateLifeCycle = [];
        foreach ($this->stateLifeCycle as $key => $stCycle) {
            if ($stCycle['Line'] === $from || $stCycle['Line'] > $from && $stCycle['Line'] <= $to){
                $stateLifeCycle[$key] = $stCycle;
            }
        }

        return $stateLifeCycle;
    }

    /**
     * @return bool
     */
    public function isDontWriteDebugChar(): bool
    {
        return $this->dontWriteDebugChar;
    }

    /**
     * @param bool $dontWriteDebugChar
     */
    public function setDontWriteDebugChar(bool $dontWriteDebugChar): void
    {
        $this->dontWriteDebugChar = $dontWriteDebugChar;
    }

    /**
     * @return bool
     */
    public function isEndTokenization(): bool
    {
        return $this->endTokenization;
    }

    /**
     * @return array
     */
    public function getContextFree(): array
    {
        return $this->contextFree;
    }

    /**
     * @param array $contextFree
     */
    public function setContextFree(array $contextFree): void
    {
        $this->contextFree = $contextFree;
    }

    public function getModeNameContextFree(string $modeName): bool
    {
        $modeName = strtolower($modeName);
        if (!key_exists($modeName, $this->contextFree)) {
            throw new TonicsTemplateRuntimeException("`$modeName` does not exist in contextFree Storage`");
        }

        return $this->contextFree[$modeName];
    }

    /**
     * @return string
     */
    public function getReconsumeState(): string
    {
        return $this->reconsumeState;
    }

    /**
     * @param string $reconsumeState
     * @return TonicsView
     */
    public function setReconsumeState(string $reconsumeState): TonicsView
    {
        $this->reconsumeState = $reconsumeState;
        return $this;
    }

    /**
     * @return string
     */
    public function getCachePrefix(): string
    {
        return $this->cachePrefix;
    }

    /**
     * @param string $cachePrefix
     * @return TonicsView
     */
    public function setCachePrefix(string $cachePrefix): TonicsView
    {
        $this->cachePrefix = $cachePrefix;
        return $this;
    }

}