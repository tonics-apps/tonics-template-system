<?php

namespace Devsrealm\TonicsTemplateSystem\Tree\Mode\Handlers;

use Devsrealm\TonicsTemplateSystem\AbstractClasses\TonicsTemplateViewAbstract;
use Devsrealm\TonicsTemplateSystem\Interfaces\TonicsModeInterface;
use Devsrealm\TonicsTemplateSystem\Interfaces\TonicsModeRendererInterface;
use Devsrealm\TonicsTemplateSystem\Tokenizer\Token\Events\OnTagToken;

/**
 * VariableMode give you access to refer to variables in the view data array.
 *
 * - Here is how you can refer to a variable:
 * `[[v('new')]]` (would auto-escape the variable)
 *
 * - If you don't want to escape it, use: `[[_v('vary.in.in')]]`
 *
 * - VariableModeHandler also supports multiple condition using double dot(..), for example:
 *
 * `[[v('vary.1 .. vary.2 .. vary.3 .. vary.4')]]`
 * It would keep looking for a variable that has a value, if it founds one, it quits and return the variable value.
 *
 * To pass a default value, use: `[[v('vary.1 .. vary.2 .. vary.3 .. vary.4', 'A Default Value')]]`
 * Default value can't be a variable, it should be a string literal.
 *
 * - Advanced User: You can resolve dynamic variable inside a variable, use the arrow notation to specify dynamic variable name,
 *                  here is an example: `[[v('dtRow->dtHeader.td')]]`
 *
 * `dtHeader.td` would be processed separately and its result would be passed to the variable mode, suppose,
 * the result of `dtHeader.td` is `nested`, the variable mode handler, would process it the following way:
 *
 * `[[v('dtRow.nested')]]`
 *
 * You are not limited to one name, you can do multiple:
 *
 * `[[v('dtRow->dtRow2->dtHeader.td->..')]]`
 *
 * - Checking if a Key Exist: To check if a key exist, you can do the following:  `[[if("v[key_name] || v[__current_var_key_exist]") ... ]]`
 *    Ensure the logical OR `(||)` is included, this is because once `v[key_name]` is either true or false, it could actually be as a result of
 *      `v[key_name]` containing the value true or false, so, include a logical OR to check if it actually does exists. In the future, I would include a mode handler
 *      specifically for checking if a key exist.
 *
 * - Get the expanded name when using dynamic key name: When using dynamic variable names, you can get the complete expanded name with `[[v('__current_var_key_name')]]`
 *
 */
class VariableModeHandler extends TonicsTemplateViewAbstract implements TonicsModeInterface, TonicsModeRendererInterface
{
    private string $error = '';

    public function validate(OnTagToken $tagToken): bool
    {
        $view = $this->getTonicsView();
        return $view->validateMaxArg($tagToken->getArg(), 'Variable', 2);
    }

    public function stickToContent(OnTagToken $tagToken)
    {
        $view = $this->getTonicsView(); $tagName = $tagToken->getTagName();
        $view->getContent()->addToContent(($tagName === '_v') ? '_v' : 'var', '', $tagToken->getArg());
    }

    public function error(): string
    {
        return $this->error;
    }

    public function render(string $content, array $args, array $nodes = []): string
    {
        $view = $this->getTonicsView();
        $currentRenderingMode = $view->getCurrentRenderingContentMode();

        if (str_contains($args[0], '..')){
            $variable = explode('..', $args[0]);
            if (is_array($variable)){
                foreach ($variable as $var){
                    $variable = $view->accessArrayWithSeparator($var);
                    if (!empty($variable)){
                        break;
                    }
                }
            }
        } else {
            $variable = $view->accessArrayWithSeparator($args[0]);
            if (is_null($variable)){
                $variable = '';
            }
        }

        // If variable is empty, check if there is a default value and set it
        if (empty($variable) && isset($args[1])){
            $variable = $args[1];
        }

        return ($currentRenderingMode === '_v')
            ? $variable. $content
            : $content . htmlspecialchars($variable, ENT_QUOTES);
    }
}