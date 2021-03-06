<?php

namespace Mindy\Orm\Fields;

/**
 * Class CharField
 * @package Mindy\Orm
 */
class CharField extends Field
{
    public $length = 255;

    public function sqlType()
    {
        return 'string(' . (int)$this->length . ')';
    }

    public function sqlDefault()
    {
        return $this->default === null ? '' : "DEFAULT '{$this->default}'";
    }
}
