<?php

namespace Devsrealm\TonicsTemplateSystem\Tree\Mode\Handlers;

use Devsrealm\TonicsTemplateSystem\AbstractClasses\TonicsTemplateViewAbstract;
use Devsrealm\TonicsTemplateSystem\Interfaces\TonicsModeInterface;
use Devsrealm\TonicsTemplateSystem\Interfaces\TonicsModeRendererInterface;
use Devsrealm\TonicsTemplateSystem\Tokenizer\Token\Events\OnTagToken;

class CharacterModeHandler extends TonicsTemplateViewAbstract implements TonicsModeInterface, TonicsModeRendererInterface
{

    private string $error;

    public function validate(OnTagToken $tagToken): bool
    {
        return true;
    }

    public function stickToContent(OnTagToken $tagToken): void
    {
        $view = $this->getTonicsView();
        if (!empty($tagToken->getContent())){
            $view->getContent()->addToContent('char', $tagToken->getContent());
        }
    }



    public function render(string $content, array $args, array $nodes = []): string
    {
       return $content;
    }

    public function error(): string
    {
        return '';
    }
}