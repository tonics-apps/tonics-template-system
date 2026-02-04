<?php

namespace Devsrealm\TonicsTemplateSystem\Traits;

trait TonicsTemplateSystemHelper
{
    /**
     * @param string $tagName
     * @param array $tagArgs
     * @param array $moreMode
     *
     * @return array[]
     */
    public function resolveArgs(string $tagName, array $tagArgs = [], array $moreMode = []): array
    {
        $mode = [
            'v[' => 'var',
            'var[' => 'var',
            'block[' => 'block',
        ];
        if (!empty($moreMode)){
            $mode = [...$mode, ...$moreMode];
        }

        $args = [];
        foreach ($tagArgs as $k => $arg){
            $catch = false;
            foreach ($mode as $mk => $mv){
                if (str_starts_with($arg, $mk)){
                    preg_match('/\\[(.*?)]/', $arg, $matches);
                    if (isset($matches[1])){
                        $args[$k] = [
                            'mode' => $mv,
                            'value' => $matches[1]
                        ];
                        $catch = true;
                    }
                    break;
                }
            }

            if ($catch === false){
                $args[$k] = [
                    'value' => $arg
                ];
            }
        }

        return [$tagName => $args ];
    }

    /**
     * @param array $args
     * @param callable|null $customMode
     *
     * @return mixed
     */
    public function expandArgs(array $args = [], ?callable $customMode = null): mixed
    {
        foreach ($args as $k => $arg){
            if (isset($arg['mode'])){
                if ($arg['mode'] === 'var'){
                    if (str_contains($arg['value'], '..')){
                        $variable = explode('..', $arg['value']);
                        if (is_array($variable)){
                            foreach ($variable as $var){
                                $variable = $this->getTonicsView()->accessArrayWithSeparator($var);
                                if (!empty($variable)){
                                    $args[$k] = $variable;
                                    break;
                                }
                            }
                        }
                    } else {
                        $args[$k] = $this->getTonicsView()->accessArrayWithSeparator($arg['value']);
                    }
                }

                if ($arg['mode'] === 'block'){
                    $args[$k] = $this->getTonicsView()->renderABlock($arg['value']);
                }

                if ($customMode !== null){
                    $args[$k] = $customMode($arg['mode'], $arg['value']);
                }

            } else {
                $args[$k] = $arg['value'];
            }
        }

        return $args;
    }
}
