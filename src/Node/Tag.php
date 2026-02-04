<?php

namespace Devsrealm\TonicsTemplateSystem\Node;

use Generator;
use JetBrains\PhpStorm\Pure;

class Tag
{
    private ?string $tagName = null;
    private ?Tag $parentNode = null;
    private string $content = '';
    private ?int $discoveredOpenTagOnLine = null;
    private ?int $discoveredCloseTagOnLine = null;
    private array $args = [];
    // Could contain a list of child nodes of a current tag,
    private array $nodes = [];
    private bool $closeState = false;
    private bool $contextFree = true;

    public function __construct($tagName = ''){
        $this->tagName = $tagName;
    }

    /**
     * @param string $character
     * @return $this
     */
    public function appendCharacterToTagname(string $character): static
    {
        $this->tagName .= $character;
        return $this;
    }

    public function parentNode(): ?Tag
    {
        return $this->parentNode;
    }

    /**
     * @param Tag|null $parentNode
     * @return Tag
     */
    public function setParentNode(?Tag $parentNode): Tag
    {
        $this->parentNode = $parentNode;
        return $this;
    }

    /**
     * Alias of getNodes method
     * @return array
     */
    #[Pure] public function childNodes(): array
    {
        return $this->getNodes();
    }

    /**
     * Clear child nodes
     * @return Tag
     */
    public function clearNodes(): Tag
    {
        $this->nodes = [];
        return $this;
    }


    /**
     * Could contain a list of child nodes of a current tag,
     * @return array
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    public function prependTagToNode(Tag $tag)
    {
        array_unshift($this->nodes, $tag);
        return $this;
    }

    /**
     * @return string|null
     */
    public function getTagName(): ?string
    {
        return strtolower($this->tagName);
    }

    /**
     * Would append array of tag nodes
     * @param array $childrenTags
     * @return $this
     */
    public function appendChildren(array $childrenTags):static
    {
        $this->setNodes([...$this->childNodes(), ...$childrenTags]);
        return $this;
    }

    /**
     * Return true if there is children in $this->nodes, else false
     * @return bool
     */
    public function hasChildren(): bool
    {
        return !empty($this->nodes);
    }

    /**
     * Return true if there is no children in node, else, false
     * @return bool
     */
    public function hasNoChildren(): bool
    {
        return empty($this->nodes);
    }

    /**
     * @param Tag $node
     * @param int|null $position
     * If you specify position,
     * it would insert tag node in that position and push down the former node using the position,
     * otherwise, it would be pushed to the bottom of the stack
     * @return $this
     */
    public function addNode(Tag $node, ?int $position = null): static
    {
        if ($position !== null){
            $array = $this->nodes;
            array_splice($array, $position, 0, [$node]);
            $this->nodes = $array;
        } else {
            $this->nodes[] = $node;
        }
        return $this;
    }

    /**
     * @param array $nodes
     * @return Tag
     */
    public function setNodes(array $nodes): Tag
    {
        $this->nodes = $nodes;
        return $this;
    }

    /**
     * Would append array to args
     * @param array $args
     * @return Tag
     */
    public function addArgs(array $args): static
    {
        $this->args = [...$this->args, ...$args];
        return $this;
    }

    /**
     * @param string $character
     * @return $this
     */
    public function appendCharacterToLastArg(string $character): static
    {
        $key = array_key_last($this->args);
        if ($key === null){
            $this->addArgs(['']);
            $key = 0;
        }
        $this->args[$key] .= $character;
        return $this;
    }

    public function replaceLastArgKey(string $characters): static
    {
        $key = array_key_last($this->args);
        if ($key === null){
            $this->addArgs(['']);
            $key = 0;
        }
        $this->args[$characters] = $this->args[$key];
        unset($this->args[$key]);
        return $this;
    }

    /**
     * @return array
     */
    public function getArgs(): array
    {
        return $this->args;
    }

    public function getFirstArgChild(): mixed
    {
        $arrayFirst = array_key_first($this->args);
        if ($arrayFirst !== null){
            return $this->args[$arrayFirst];
        }
        return $arrayFirst;
    }

    public function getLastArgChild(): mixed
    {
        $arrayLast = array_key_last($this->args);
        if ($arrayLast !== null){
            return $this->args[$arrayLast];
        }
        return $arrayLast;
    }


    public function contentIsNotEmpty(): bool
    {
        return !empty($this->content);
    }

    /**
     * @param string $char
     * @return $this
     */
    public function appendCharacterToContent(string $char = ''): static
    {
        $this->content .= $char;
        return $this;
    }

    /**
     * @param string $char
     * @return $this
     */
    public function prependCharacterToContent(string $char = ''): static
    {
        $this->content = $char . $this->content;
        return $this;
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @param string $content
     * @return Tag
     */
    public function setContent(string $content): Tag
    {
        $this->content = $content;
        return $this;
    }

    /**
     * @return bool
     */
    public function isCloseState(): bool
    {
        return $this->closeState;
    }

    public function isOpenState(): bool
    {
        return $this->closeState === false;
    }

    /**
     * @param bool $closeState
     */
    public function setCloseState(bool $closeState): void
    {
        $this->closeState = $closeState;
    }

    /**
     * To use this function, simply test if the $node has a children first, okay?
     *
     * Note: This function won't recurse into a child if the parent is not context free,
     * that is, it's usage depends on a context, e.g if condition, each loop, etc.
     * @param Tag $node
     * @param bool $obeyContext
     * @return Generator
     */
    public function getChildrenRecursive(Tag $node, bool $obeyContext = true): \Generator
    {
        foreach ($node->childNodes() as $childNode)
        {
            /**@var Tag $childNode*/
            yield $childNode;
            if ($childNode->hasChildren()){
                if (!$obeyContext || $childNode->isContextFree()){
                    yield from $this->getChildrenRecursive($childNode, $obeyContext);
                }
            }
        }
    }

    public function findLastChildrenInTagChildrenRecursive(Tag $node): ?Tag
    {
        if ($node->hasNoChildren()){
            return $node;
        }

        foreach ($node->childNodes() as $childNode)
        {
            return $this->findLastChildrenInTagChildrenRecursive($childNode);
        }
        return null;
    }

    /**
     * @param array $args
     * @return Tag
     */
    public function setArgs(array $args): Tag
    {
        $this->args = $args;
        return $this;
    }

    /**
     * @param mixed|string|null $tagName
     */
    public function setTagName(mixed $tagName): Tag
    {
        $this->tagName = $tagName;
        return $this;
    }

    /**
     * @return bool
     */
    public function isContextFree(): bool
    {
        return $this->contextFree;
    }

    /**
     * @param bool $contextFree
     * @return Tag
     */
    public function setContextFree(bool $contextFree): Tag
    {
        $this->contextFree = $contextFree;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getDiscoveredOpenTagOnLine(): ?int
    {
        return $this->discoveredOpenTagOnLine;
    }

    /**
     * @param int|null $discoveredOpenTagOnLine
     */
    public function setDiscoveredOpenTagOnLine(?int $discoveredOpenTagOnLine): void
    {
        $this->discoveredOpenTagOnLine = $discoveredOpenTagOnLine;
    }

    /**
     * @return int|null
     */
    public function getDiscoveredCloseTagOnLine(): ?int
    {
        return $this->discoveredCloseTagOnLine;
    }

    /**
     * @param int|null $discoveredCloseTagOnLine
     */
    public function setDiscoveredCloseTagOnLine(?int $discoveredCloseTagOnLine): void
    {
        $this->discoveredCloseTagOnLine = $discoveredCloseTagOnLine;
    }
}