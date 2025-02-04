<?php

namespace Devsrealm\TonicsTemplateSystem;

use JetBrains\PhpStorm\ArrayShape;

class Content
{
    private array $blocks = [];
    private array $contents = [];

    public function addChildrenToBlock($blockName, array $subContents = [])
    {
        $this->blocks[$blockName] = $subContents;
    }

    /**
     * Add multiple blocks or merge many blocks with the existing one
     * @param array $blocks
     * @return $this
     */
    public function addBlocks(array $blocks = []): static
    {
        $this->blocks  = [...$this->blocks , ...$blocks];
        return $this;
    }

    public function addToBlockChild($blockName, array $subContent = []){
        if (key_exists($blockName, $this->blocks)){
            $this->blocks[$blockName][] = $subContent;
        }
    }

    public function isBlock(string $blockName): bool
    {
        return key_exists($blockName, $this->blocks);
    }

    /**
     * @param string $blockName
     * This won't check if the block name exist, it is your responsibility to do that
     * @return mixed
     */
    public function getBlock(string $blockName): mixed
    {
        return $this->blocks[$blockName];
    }

    /**
     * @param string $mode
     * @param string $content
     * @param array $args
     * @param array $nodes
     * @return array
     */
    public function previewAddContent(string $mode, string $content = '', array $args = [], array $nodes = []): array
    {
        return [
            'Mode' => $mode,
            'content' => $content,
            'args' => $args,
            'nodes' => $nodes
        ];
    }

    public function addContents(array $contents = []): static
    {
        $this->contents = [...$this->contents, ...$contents];
        return $this;
    }

    public function addToContent(string $mode, string $content = '', array $args = [])
    {
        $this->contents[] = [
            'Mode' => strtolower($mode),
            'content' => $content,
            'args' => $args
        ];
    }

    public function getLastContent()
    {
        $key = array_key_last($this->contents);
        if ($key === null){return null;}
        return $this->contents[$key];
    }

    public function clearContents(): static
    {
        $this->contents = [];
        return $this;
    }

    /**
     * @return array
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }

    /**
     * @return array
     */
    public function getContents(): array
    {
        return $this->contents;
    }

    public function contentIsNotEmpty(): bool
    {
        return !empty($this->contents);
    }

    /**
     * @param array $contents
     * @return Content
     */
    public function setContents(array $contents): Content
    {
        $this->contents = $contents;
        return $this;
    }

}