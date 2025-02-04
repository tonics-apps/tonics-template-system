<?php

namespace Devsrealm\TonicsTemplateSystem\Tree\Mode\Handlers;

use Devsrealm\TonicsTemplateSystem\AbstractClasses\TonicsTemplateViewAbstract;
use Devsrealm\TonicsTemplateSystem\Exceptions\TonicsTemplateModeError;
use Devsrealm\TonicsTemplateSystem\Interfaces\TonicsModeInterface;
use Devsrealm\TonicsTemplateSystem\Node\Tag;
use Devsrealm\TonicsTemplateSystem\Tokenizer\Token\Events\OnTagToken;

class BlockModeHandler extends TonicsTemplateViewAbstract implements TonicsModeInterface
{
    private string $error = '';

    public function validate(OnTagToken $tagToken): bool
    {
        $view = $this->getTonicsView();
        $result = true;
        if ($view->validateMaxArg($tagToken->getArg(), 'Block', 2)) {
            if ($tagToken->hasChildren()){
                /** @var Tag $child */
                foreach ($tagToken->getChildren() as $child){
                        /**@var Tag $child */
                        if ($child->getTagName() === 'b' || $child->getTagName() === 'block'){
                            $this->error = "You Can't Nest Block Tag in a Block Tag";
                            $result = false;
                        }
                        // Validate Child...
                        $mode = $view->getModeHandlerValue($child->getTagName());
                        $childOnTagToken = new OnTagToken($child);
                        $mode->validate($childOnTagToken);
                }
            }
        }
        return $result;
    }

    public function stickToContent(OnTagToken $tagToken)
    {
        $view = $this->getTonicsView();
        $view->getContent()->addChildrenToBlock($tagToken->getArg()[0], []);
        $view->getContent()
            ->addToBlockChild($tagToken->getArg()[0],
            $view->getContent()->previewAddContent('char', $tagToken->getContent(), [])
            );

        if ($tagToken->hasChildren()){
            foreach ($tagToken->getChildren() as $child){
                /**@var Tag $child */
                $view->getContent()->addToBlockChild(
                    $tagToken->getArg()[0],
                    $view->getContent()->previewAddContent($child->getTagName(), $child->getContent(), $child->getArgs(), $child->getNodes()));
            }
        }
    }

    /**
     * @return string
     */
    public function error(): string
    {
        return $this->error;
    }
}