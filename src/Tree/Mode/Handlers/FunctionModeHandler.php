<?php

namespace Devsrealm\TonicsTemplateSystem\Tree\Mode\Handlers;

use Devsrealm\TonicsTemplateSystem\AbstractClasses\TonicsTemplateViewAbstract;
use Devsrealm\TonicsTemplateSystem\Exceptions\TonicsTemplateModeError;
use Devsrealm\TonicsTemplateSystem\Interfaces\TonicsModeInterface;
use Devsrealm\TonicsTemplateSystem\Interfaces\TonicsModeRendererInterface;
use Devsrealm\TonicsTemplateSystem\Tokenizer\Token\Events\OnCharacterToken;
use Devsrealm\TonicsTemplateSystem\Tokenizer\Token\Events\OnEOFToken;
use Devsrealm\TonicsTemplateSystem\Tokenizer\Token\Events\OnTagToken;

/**
 * `FunctionMode` gives you access to modify Block tag that support argument, its syntax is as follows:
 *
 * `[[func('block-name', 'arg-1', 'arg-2', '...')]]`
 *
 * Where `block-name` is the block-name that is waiting for 'arg-1', 'arg-2', the args should all be a block-tags.
 *
 * The following is an example of how you can use it:
 *
 * ```
 * [[b('block-name')
 *  This is [[arg('1')]], and he is [[arg('2')]] years old
 * ]]
 *
 * [[b('arg-1')Devsrealm]]
 *
 * [[b('arg-2')100]]
 * ```
 * You can then call it like so in your template: `[[func('block-name', 'arg-1', 'arg-2')]]`
 *
 * which gives you: => `This is Devsrealm, and he is 100 years old`
 *
 * If an arg is missing in the `block-name` block, it would replace it with an empty string...
 */
class FunctionModeHandler extends TonicsTemplateViewAbstract implements TonicsModeInterface, TonicsModeRendererInterface
{
    private string $error = '';

    public function validate(OnTagToken $tagToken): bool
    {
        $view = $this->getTonicsView();
        if ($this->isArg($tagToken->getTagName())){
            return $view->validateMaxArg($tagToken->getArg(), 'arg');
        }
        $result = false;
        if ($view->validateMaxArg($tagToken->getArg(), 'function', 100, 2)) {
            $result = true;
        }

        foreach ($tagToken->getArg() as $arg){
            if (!$view->getContent()->isBlock($arg)){
                $view->exception(TonicsTemplateModeError::class, [" `$arg` Is Not a Known Block"]);
            }
        }

        return $result;
    }

    public function stickToContent(OnTagToken $tagToken)
    {
        $view = $this->getTonicsView();
        if ($this->isFunc($tagToken->getTagName())){
            $funcStorage = $view->getModeStorage('func');
            $blockName = $tagToken->getArg()[0];
            foreach ($tagToken->getArg() as $k => $arg){
                if ($k === 0){
                    continue;
                }
                if (key_exists($blockName, $funcStorage)){
                    $funcStorage[$blockName][$k] =  $arg;
                } else {
                    $funcStorage[$blockName] = [
                        $k => $arg
                    ];
                }
            }

            $view->storeDataInModeStorage('func', $funcStorage);
            $view->getContent()->addToContent('func', '', $tagToken->getArg());

        }
    }

    public function error(): string
    {
        return $this->error;
    }

    /**
     * @param string $v
     * @return bool
     */
    public function isFunc(string $v): bool
    {
        return $v === 'func' || $v === 'function' || $v === 'proc' || $v === 'procedure';
    }

    /**
     * @param string $v
     * @return bool
     */
    public function isArg(string $v): bool
    {
        return $v === 'arg';
    }

    public function render(string $content, array $args, array $nodes = []): string
    {
        $renderedArgs = [];
        $view = $this->getTonicsView();
        // This is a function call, render the block
        if ($this->isFunc($view->getCurrentRenderingContentMode())){
            $blockName = array_shift($args);
            foreach ($args as $k => $arg){
                $renderedArgs[$k+1] = $view->renderABlock($arg);
            }

            // Final Rendering
            $blockContents = $view->getContent()->getBlock($blockName);
            $data = '';
            foreach ($blockContents as $content){
                $mode = $view->getModeRendererHandler($content['Mode']);

                if ($content['Mode'] === 'arg'){
                    if (key_exists($content['args'][0], $renderedArgs)){
                        $data .= $renderedArgs[$content['args'][0]];
                        continue;
                    }
                    // If args doesn't exist, continue anyway
                    continue;
                }

                if ($mode instanceof TonicsModeRendererInterface) {
                    $view->setCurrentRenderingContentMode($content['Mode']);
                    $nodes = (isset($content['nodes'])) ? $content['nodes'] : [];
                    $data .= $mode->render($content['content'], $content['args'], $nodes);
                }
            }

            return $data;
        }

        // This is an argument, this is an error argument should only be called within a function
        if ($this->isArg($view->getCurrentRenderingContentMode())){
            $arg = $args[0];
            $view->exception(TonicsTemplateModeError::class, [" [[arg(`$arg`)]] Should Only Be Called Within a Function"]);
        }

        return '';
    }
}