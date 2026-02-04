<?php

namespace Devsrealm\TonicsTemplateSystem\Tokenizer\Token\Events;

use Devsrealm\TonicsTemplateSystem\AbstractClasses\TonicsTemplateViewAbstract;
use Devsrealm\TonicsTemplateSystem\Node\Tag;
use Devsrealm\TonicsTemplateSystem\TonicsView;
use Generator;
use JetBrains\PhpStorm\Pure;
use phpDocumentor\Reflection\Types\This;

class OnTagToken
{
    private Tag $tag;

    public function __construct(Tag $tag)
    {
        $this->tag = $tag;
    }

    /**
     * @return string
     */
    public function getTagName(): string
    {
        return $this->tag->getTagName();
    }

    /**
     * @return array
     */
    public function getArg(): array
    {
        return $this->tag->getArgs();
    }

    public function getFirstArgChild(): mixed
    {
        return $this->tag->getFirstArgChild();
    }

    public function getLastArgChild(): mixed
    {
        return $this->tag->getLastArgChild();
    }

    /**
     * @param bool $recursive
     * @param callable|null $onBeforeCallToChildrenRecursive
     * A callback to call before call to getChildrenRecursive, if it returns true, we continue with the recursion, else, we jump out.
     *
     * @return Generator|array
     */
    public function getChildren(bool $recursive = false, ?callable $onBeforeCallToChildrenRecursive = null): Generator|array
    {
        if ($recursive){
            $result = true;
            if ($onBeforeCallToChildrenRecursive !== null){
                $result = $onBeforeCallToChildrenRecursive($this->tag);
            }
            if ($result){
                return $this->tag->getChildrenRecursive($this->tag);
            }
        }
        return $this->tag->getNodes();
    }

    public function hasChildren(): bool
    {
        return $this->tag->hasChildren();
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        return $this->tag->getContent();
    }

    /**
     * @return Tag
     */
    public function getTag(): Tag
    {
        return $this->tag;
    }

}