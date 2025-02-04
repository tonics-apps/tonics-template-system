<?php

namespace Devsrealm\TonicsTemplateSystem\Tree\Mode\Handlers;

use Devsrealm\TonicsTemplateSystem\AbstractClasses\TonicsTemplateViewAbstract;
use Devsrealm\TonicsTemplateSystem\Exceptions\TonicsTemplateModeError;
use Devsrealm\TonicsTemplateSystem\Interfaces\TonicsModeInterface;
use Devsrealm\TonicsTemplateSystem\Interfaces\TonicsModeRendererInterface;
use Devsrealm\TonicsTemplateSystem\Node\Tag;
use Devsrealm\TonicsTemplateSystem\Tokenizer\Token\Events\OnCharacterToken;
use Devsrealm\TonicsTemplateSystem\Tokenizer\Token\Events\OnEOFToken;
use Devsrealm\TonicsTemplateSystem\Tokenizer\Token\Events\OnTagToken;

/**
 * `UseMode` gives you access to a block. When it is time to render the block, it would also cache it for subsequent uses.
 *
 * Syntax is: `[[use("block-name")]]`, This auto-escape the block data,
 *
 * use the following to remove the escape functionality: `[[_use("block-name")]]`
 *
 * The use block is cached once it has been rendered, if you don't want to cache it, use the following:
 *
 * [[_useC("block-name")]]
 */
class UseModeHandler extends TonicsTemplateViewAbstract implements TonicsModeInterface, TonicsModeRendererInterface
{
    private string $error;

    public function validate(OnTagToken $tagToken): bool
    {
        $view = $this->getTonicsView();
        $result = true;
        if ($view->validateMaxArg($tagToken->getArg(), 'Use')) {
            if (!$tagToken->hasChildren()) {
                return true;
            }
            foreach ($tagToken->getChildren() as $child) {
                /**@var Tag $child */
                if ($child->getTagName() === 'b' || $child->getTagName() === 'block') {
                    $this->error = "You Can't Nest Block In a Use Tag";
                    $result = false;
                }
            }
        }
        return $result;
    }

    public function stickToContent(OnTagToken $tagToken)
    {
        $view = $this->getTonicsView();
        $blockName = $tagToken->getArg()[0]; $tagName = $tagToken->getTagName();
        if (!$view->getContent()->isBlock($blockName)) {
            $arg = $tagToken->getArg()[0];
            $view->exception(TonicsTemplateModeError::class, ["`$arg` Is Not a Known Block"]);
        }

        if ($tagName === 'usec' || $tagName === '_usec'){
            $view->getContent()->addToContent(($tagName === 'usec') ? 'usec' : '_usec', '', $tagToken->getArg());
            return;
        }

        $view->getContent()->addToContent(($tagName === 'use') ? 'use' : '_use', '', $tagToken->getArg());
    }

    public function error(): string
    {
        return $this->error;
    }

    public function render(string $content, array $args, array $nodes = []): string
    {

        $view = $this->getTonicsView();
        $blockName = array_shift($args);
        $currentRenderingMode = $view->getCurrentRenderingContentMode();

        ### FOR USEC
        if ($currentRenderingMode === 'usec' || $currentRenderingMode === '_usec'){
            $blockContent = $view->renderABlock($blockName);
            return ($currentRenderingMode === '_usec')
                ? $blockContent
                : htmlspecialchars($blockContent, ENT_QUOTES);
        }

        ### FOR USE

        # If the block in the use hasn't been rendered, we do that and cache it for subsequent re-use
        if (!key_exists($blockName, $view->getModeStorage('use'))) {
            $blockContent = $view->renderABlock($blockName);
            $useStorage = $view->getModeStorage('use');
            $useStorage[$blockName] = $blockContent;
            $view->storeDataInModeStorage('use', $useStorage);
        }

        return ($currentRenderingMode === '_use')
        ? $view->getModeStorage('use')[$blockName]
        : htmlspecialchars($view->getModeStorage('use')[$blockName], ENT_QUOTES);
    }
}