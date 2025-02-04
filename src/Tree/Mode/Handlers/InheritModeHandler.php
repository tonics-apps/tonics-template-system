<?php

namespace Devsrealm\TonicsTemplateSystem\Tree\Mode\Handlers;

use Devsrealm\TonicsTemplateSystem\AbstractClasses\TonicsTemplateViewAbstract;
use Devsrealm\TonicsTemplateSystem\Content;
use Devsrealm\TonicsTemplateSystem\Interfaces\TonicsModeInterface;
use Devsrealm\TonicsTemplateSystem\Tokenizer\Token\Events\OnTagToken;
use Devsrealm\TonicsTemplateSystem\TonicsView;
use Devsrealm\TonicsTemplateSystem\Traits\TonicsTemplateSystemHelper;


/**
 * InheritModeHandler gives you the option to inherit from multiple template files. The differences between InheritMode and BlockMode
 * is that InheritModeHandler can not only inherit from multiple template file contents with their storages but would also get their blocks.
 *
 * Note: Inherit is best used with hooks
 */
class InheritModeHandler extends TonicsTemplateViewAbstract implements TonicsModeInterface
{
    use TonicsTemplateSystemHelper;

    private string $error = '';

    public function validate(OnTagToken $tagToken): bool
    {
        $result = true;
        if ($tagToken->getTag()->getDiscoveredOpenTagOnLine() !== 1){
            $this->error = "Inherit Should Be on The First Line, Found At Line {$tagToken->getTag()->getDiscoveredOpenTagOnLine()}";
            $result = false;
        }
       return $result;
    }

    /**
     * @param OnTagToken $tagToken
     *
     * @return void
     */
    public function stickToContent(OnTagToken $tagToken): void
    {
        $newView = new TonicsView();
        $this->getTonicsView()->copySettingsToNewViewInstance($newView);
        $content = new Content(); # We Keep Track of the Content so other template won't mess it up
        foreach ($tagToken->getArg() as $inheritFrom){
            $newView->render($inheritFrom, TonicsView::RENDER_TOKENIZE_ONLY);
            // $newView->reset()->loadTemplateString($inheritFrom, true);
            $content->addContents($newView->getContent()->getContents())->addBlocks($newView->getContent()->getBlocks());
        }

        # Inherit The Contents and the Mode Storages
        $this->getTonicsView()->setContent($content)->setModeStorages($newView->getModeStorages());
        # Destroy it
        unset($newView); $newView = null;
    }

    public function error(): string
    {
        return $this->error;
    }
}