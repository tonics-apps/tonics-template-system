<?php

namespace Devsrealm\TonicsTemplateSystem\Loader;

use Devsrealm\TonicsTemplateSystem\Exceptions\TonicsTemplateLoaderError;
use Devsrealm\TonicsTemplateSystem\Interfaces\TonicsTemplateLoaderInterface;

class TonicsTemplateArrayLoader implements TonicsTemplateLoaderInterface
{
    private array $templates;


    /**
     * The key should be the template name and value is the content
     * @param array $templates
     */
    public function __construct(array $templates = []){
        $this->templates = $templates;
        return $this;
    }

    /**
     * @throws TonicsTemplateLoaderError
     */
    public function load(string $name)
    {
        if ($this->exists($name)){
            return $this->templates[$name];
        }
        throw new TonicsTemplateLoaderError("`$name` Does Not Exist");
    }

    /**
     */
    public function exists(string $name): bool
    {
        return key_exists($name, $this->templates);
    }

    /**
     * @return array
     */
    public function getTemplates(): array
    {
        return $this->templates;
    }

    /**
     * @param array $templates
     * @return TonicsTemplateArrayLoader
     */
    public function setTemplates(array $templates): TonicsTemplateArrayLoader
    {
        $this->templates = $templates;
        return $this;
    }
}