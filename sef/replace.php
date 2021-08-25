<?php

namespace SEF;

/**
 * Replace class allows creation of string replaceable objects for easier templating.
 * Class supports replacing values as {%%token%%}, variables as {$$token$$} and function returns as {&&token&&}.
 * Variables and functions must belong to the caller class and be publicly accesible.
 */
class Replace {
    private $caller;

    private $values = array();
    private $variables = array();
    private $functions = array();
    private $objects = array();

    /**
     * Replace class constructor.
     * @param   object  $caller  Specifies the caller object. Caller is used to access vars and variables.
     */
    function __construct(&$caller) {
        //save caller
        $this->caller = $caller;
    }

    /**
     * addReplace function adds another Replace object to the list of replacements.
     * @param   Replace  $replace_object  Replace object.
     */
    function addReplace(Replace &$replace_object) {
        array_push($this->objects, $replace_object);
    }

    /**
     * addValue function adds a value to the list of replacements.
     * @param   string  $token  Token to be replaced.
     * @param   string  $value  Replace value.
     */
    function addValue(string $token, string $value) {
        $this->values[$token] = $value;
    }

    /**
     * addVariable function adds a variable to the list of replacements.
     * @param   string  $token          Token to be replaced.
     * @param   string  $variable_name  Name of the variable. Variable belongs to the caller class, where it must be public.
     */
    function addVariable(string $token, string $variable_name) {
        $this->variables[$token] = $variable_name;
    }

    /**
     * addFunction function adds a variable to the list of replacements.
     * @param   string  $token          Token to be replaced.
     * @param   string  $function_name  Name of the function to be called in order to get the value. The function belongs to the caller class, must be public, must not have any required parameters and must return a string.
     */
    function addFunction(string $token, string $function_name) {
        $this->functions[$token] = $function_name;
    }

    /**
     * Main replace function.
     * @param   string  $content  Content which contains tokens.
     * @return  string            Content with matched tokens replaced.
     */
    function replace(string $content): string {
        $changed = 1;
        while($changed) {
            $changed = 0;
            $new_content = $this->replaceRecursive($this, $content);
            if($new_content != $content) {
                $changed = 1;
                $content = $new_content;
            }
        }

        return $content;
    }

    private function replaceRecursive(Replace &$V, string $content) {
        foreach($V->values as $old => $new) {
            $content = str_replace("{%%$old%%}", $new, $content);
        }

        foreach($V->variables as $old => $variable_name) {
            $new = $V->caller->$variable_name;
            $content = str_replace('{$$'.$old.'$$}', $new, $content);
        }

        foreach($V->functions as $old => $function_name) {
            $new = call_user_func(array($V->caller, $function_name));
            $content = str_replace("{&&$old&&}", $new, $content);
        }

        foreach($V->objects as $var) {
            $content = $V->replaceRecursive($var, $content);
        }
        
        return $content;
    }
}
