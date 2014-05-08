<?php

namespace Rize\UriTemplate;

use Rize\UriTemplate\Node;
use Rize\UriTemplate\Operator;

class Parser
{
    const REGEX_VARNAME = '(?:[A-z0-9_\.]|%[0-9a-fA-F]{2})';

        /**
         * gen-delims | sub-delims
         */
    public static $reserved = array(
            '%3A' => ':',
            '%2F' => '/',
            '%3F' => '?',
            '%23' => '#',
            '%5B' => '[',
            '%5D' => ']',
            '%40' => '@',
            '%21' => '!',
            '%24' => '$',
            '%26' => '&',
            '%27' => "'",
            '%28' => '(',
            '%29' => ')',
            '%2A' => '*',
            '%2B' => '+',
            '%2C' => ',',
            '%3B' => ';',
            '%3D' => '=',
        );

    /**
     * Parses URI Template and returns nodes
     */
    public function parse($template)
    {
        $parts   = preg_split('#(\{[^\}]+\})#', $template, null, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $nodes   = array();

        foreach($parts as $part) {
            $nodes[] = $this->createNode($part);
        }

        return $nodes;
    }

    protected function createNode($token)
    {
        # literal string
        if ($token[0] !== '{') {
            $node = $this->createLiteralNode($token);
        }

        else {

            # remove `{}` from expression and parse it
            $node = $this->parseExpression(substr($token, 1, -1));
        }
  
        return $node;
    }

    protected function parseExpression($expression)
    {
        $token  = $expression;
        $prefix = $token[0];

        # not a valid operator?
        if (!Operator\Abstraction::isValid($prefix)) {

            # not valid chars?
            if (!preg_match('#'.self::REGEX_VARNAME.'#', $token)) {
                throw new \Exception("Invalid operator [$prefx] found at {$token}");
            }

            # default operator
            $prefix = null;
        }

        # remove operator prefix if exists e.g. '?'
        if ($prefix) {
            $token = substr($token, 1);
        }

        # parse variables
        $vars = array();
        foreach(explode(',', $token) as $var) {
            $vars[] = $this->parseVariable($var);
        }

        return $this->createExpressionNode(
            $token,
            $this->createOperatorNode($prefix),
            $vars
        );
    }

    protected function parseVariable($var)
    {
        $var      = trim($var);
        $val      = null;
        $modifier = null;

        # check for prefix (:) / explode (*) modifier
        if (strpos($var, ':') !== false) {
            $modifier = ':';
            list($varname, $val) = explode(':', $var);

            # error checking
            if (!is_numeric($val)) {
                throw new \Exception("Value for `:` modifier must be numeric value [$varname:$val]");
            }
        }

        if (substr($var, -1) === '*') {

            # there can be only 1 modifier per var
            if ($modifier) {
                throw new \Exception("Multiple modifiers per variable are not allowed [$var]");
            }

            $modifier = '*';
            $var      = substr($var, 0, -1);
        }

        return $this->createVariableNode(
            $var,
            array(
                'modifier' => $modifier,
                'value'    => $val,
            )
        );
    }

    protected function createVariableNode($token, $options = array())
    {
        return new Node\Variable($token, $options);
    }

    protected function createExpressionNode($token, Operator\Abstraction $operator = null, array $vars = array())
    {
        return new Node\Expression($token, $operator, $vars);
    }

    protected function createLiteralNode($token)
    {
        return new Node\Literal($token);
    }

    protected function createOperatorNode($token)
    {
        return Operator\Abstraction::createById($token);
    }
}