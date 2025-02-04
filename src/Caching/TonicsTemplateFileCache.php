<?php

namespace Devsrealm\TonicsTemplateSystem\Caching;

use Devsrealm\TonicsTemplateSystem\Exceptions\TonicsTemplateRuntimeException;
use Devsrealm\TonicsTemplateSystem\Interfaces\TonicsTemplateCacheInterface;
use DirectoryIterator;

class TonicsTemplateFileCache implements TonicsTemplateCacheInterface
{
    private string $dirToCacheFiles;
    private string $cacheMethod;

    /**
     * @param string $dirToCacheFiles
     * @param string $cacheMethod
     * Can be one of:
     *
     * JSON, SERIALIZE, VAR_EXPORT
     */
    public function __construct(string $dirToCacheFiles, string $cacheMethod = 'JSON')
    {
        $this->dirToCacheFiles = $dirToCacheFiles;
        $this->cacheMethod = $cacheMethod;

        if (array_search($cacheMethod, ['JSON', 'SERIALIZE', 'VAR_EXPORT'], true) === false){
            throw new TonicsTemplateRuntimeException("$cacheMethod method is not available, try one of JSON, SERIALIZE, VAR_EXPORT");
        }
        if (file_exists($dirToCacheFiles) === false){
            throw new TonicsTemplateRuntimeException("$dirToCacheFiles is either an invalid directory or it doesnt exists");
        }
    }

    private function getFileString(string $key): string
    {
        return $this->dirToCacheFiles . DIRECTORY_SEPARATOR . 'cache_' . $key;
    }

    /**
     * @inheritDoc
     */
    public function add(string $key, mixed $value): bool
    {
        $filePath = $this->getFileString($key);
        switch ($this->cacheMethod){
            case 'JSON':
                $res = file_put_contents($filePath, json_encode($value));
                break;
            case 'SERIALIZE':
                $res = file_put_contents($filePath, serialize($value));
                break;
            case 'VAR_EXPORT':
                $res = file_put_contents($filePath . '.php', "<?php\nreturn " . var_export($value, true) . ";");
                break;
            default:
                return false;
        }
        if ($res !== false){
            return true;
        }
         return false;
    }

    /**
     * @inheritDoc
     */
    public function get(string $key): mixed
    {
        $filePath = $this->getFileString($key);
        switch ($this->cacheMethod){
            case 'JSON':
                return json_decode(file_get_contents($filePath));
            case 'SERIALIZE':
                return unserialize(file_get_contents($filePath));
            case 'VAR_EXPORT':
                $data = include($filePath. '.php');
                return $data;
                break;
            default:
                return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): bool
    {
        $filePath = $this->getFileString($key);
        if ($this->cacheMethod === 'VAR_EXPORT'){
            $filePath = $filePath . '.php';
        }
        return unlink($filePath);
    }

    /**
     * @inheritDoc
     */
    public function exists(string $key): bool
    {
        $filePath = $this->getFileString($key);
        if ($this->cacheMethod === 'VAR_EXPORT'){
            $filePath = $filePath . '.php';
        }
        return file_exists($filePath);
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        $filesIterator = new DirectoryIterator($this->dirToCacheFiles);
        $done = false;
        foreach ($filesIterator as $file) {
            /**
             * @var $file \DirectoryIterator
             */
            if ($file->getFilename() === '.' || $file->getFilename() === '..') {
                continue;
            }

            if ($file->isFile() && str_contains($file->getFilename(), 'cache_')){
                $done = unlink($file);
            }
        }
        return $done;
    }

    /**
     * @return string
     */
    public function getDirToCacheFiles(): string
    {
        return $this->dirToCacheFiles;
    }

    /**
     * @return string
     */
    public function getCacheMethod(): string
    {
        return $this->cacheMethod;
    }
}