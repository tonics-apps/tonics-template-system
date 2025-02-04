<?php

namespace Devsrealm\TonicsTemplateSystem\Tree\Mode\Handlers;

use Devsrealm\TonicsTemplateSystem\AbstractClasses\TonicsTemplateViewAbstract;
use Devsrealm\TonicsTemplateSystem\Content;
use Devsrealm\TonicsTemplateSystem\Interfaces\TonicsModeInterface;
use Devsrealm\TonicsTemplateSystem\Tokenizer\State\DefaultTokenizerState;
use Devsrealm\TonicsTemplateSystem\Tokenizer\Token\Events\OnTagToken;
use Devsrealm\TonicsTemplateSystem\TonicsView;

class ImportModeHandler extends TonicsTemplateViewAbstract implements TonicsModeInterface
{

    private string $error;

    public function validate(OnTagToken $tagToken): bool
    {
        $view = $this->getTonicsView();
        $result = true;
        if ($view->validateMaxArg($tagToken->getArg(), 'Import')) {
            if ($tagToken->hasChildren()){
                $this->error = "Import Can't Have a Nested Tag";
                $result = false;
            }
        }

        return $result;
    }

    public function stickToContent(OnTagToken $tagToken)
    {
        $view =  $this->getTonicsView();
        ## We might have tried cloning the $view, but the objects in the $view are inter-twined,
        ## meaning cloning would be more trouble, so, starting from a clean view is superb and faster
        $newView = new TonicsView();

        $view->copySettingsToNewViewInstance($newView);
        $newView->reset()->loadTemplateString($tagToken->getArg()[0], true);
        #
        # The Import Mode Only Cares About The Block We were able to extract in the newScanner,
        # We discard any other thing, ;)
        #
        $view->getContent()->addBlocks($newView->getContent()->getBlocks());

        # Destroy it
        unset($newView); $newView = null;
    }

    public function error(): string
    {
       return $this->error;
    }
}