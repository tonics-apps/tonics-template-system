<?php

namespace Devsrealm\TonicsTemplateSystem\Loader;

use Devsrealm\TonicsTemplateSystem\Exceptions\TonicsTemplateLoaderError;
use Devsrealm\TonicsTemplateSystem\Interfaces\TonicsTemplateLoaderInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class TonicsTemplateFileLoader implements TonicsTemplateLoaderInterface
{
    private array $templates = [];
    private string $ext = '';
    private string $directory = '';
    private array $excludeDir;

    /**
     * @param string $extension
     * e.g html
     */
    public function __construct(string $extension = 'html', array $excludeDir = []){
        $this->ext = $extension;
        $this->excludeDir = $excludeDir;
    }

    /**
     * @param string $name
     * @return string
     * @throws TonicsTemplateLoaderError
     */
    private function getFileContent(string $name):string
    {
        $file = @file_get_contents($this->templates[$name]);
        if ($file !== false){
            return $file;
        }
        throw new TonicsTemplateLoaderError("`$file` Fail To Read File");
    }

    /**
     * @throws TonicsTemplateLoaderError
     */
    public function load(string $name): string
    {
        $name = $name . '.'. $this->ext;
        if ($this->exists($name)){
            return $this->getFileContent($name);
        }
        throw new TonicsTemplateLoaderError("`$name` Does Not Exist");
    }

    public function exists($name): bool
    {
        return key_exists($name, $this->templates);
    }

    /**
     * @return string
     */
    public function getExt(): string
    {
        return $this->ext;
    }

    /**
     * @return array
     */
    public function getTemplates(): array
    {
        return $this->templates;
    }

    /**
     * @param string $directory
     */
    public function setDirectory(string $directory): void
    {
        $this->directory = $directory;
    }

    /**
     * @param string $ext
     * @return TonicsTemplateFileLoader
     */
    public function setExt(string $ext): TonicsTemplateFileLoader
    {
        $this->ext = $ext;
        return $this;
    }

    /**
     * @return array
     */
    public function getExcludeDir(): array
    {
        return $this->excludeDir;
    }

    /**
     * @param array $excludeDir
     */
    public function setExcludeDir(array $excludeDir): void
    {
        $this->excludeDir = $excludeDir;
    }

    /**
     * @return string
     */
    private function getDirectory(): string
    {
        return $this->directory;
    }

    /**
     * Loads all template recursively in a given directory
     * @throws TonicsTemplateLoaderError
     */
    public function resolveTemplateFiles(string $dir)
    {
        if (file_exists($dir) === false){
            throw new TonicsTemplateLoaderError("`$dir` Is Not a Valid Directory");
        }

        $filesIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($filesIterator as $file){

            /**
             * @var $file \DirectoryIterator
             */
            if ($file->getFilename() === '.' || $file->getFilename() === '..') {
                continue;
            }

            $path = $file->getPathname();
            $skip = false;
            foreach ($this->getExcludeDir() as $excludeDir){
                if (str_starts_with($path, $excludeDir)){
                    $skip = true;
                    break;
                }
            }

            if ($skip){
                continue;
            }

            if ($file->isFile() && $file->getExtension() === $this->ext){
                if ($file->isReadable() === false || $file->isWritable() === false){
                    throw new TonicsTemplateLoaderError("`$path` Is Not Readable or Writable");
                }
                $fileKey = str_replace($dir . DIRECTORY_SEPARATOR, '', $file->getRealPath());
                $nameSpace = basename($dir) . '::';
                $this->templates[$nameSpace . $fileKey] = $path;
            }
        }
    }
}