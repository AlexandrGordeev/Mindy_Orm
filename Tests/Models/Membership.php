<?php
/**
 *
 *
 * All rights reserved.
 *
 * @author Falaleev Maxim
 * @email max@studio107.ru
 * @version 1.0
 * @company Studio107
 * @site http://studio107.ru
 * @date 04/01/14.01.2014 21:19
 */

namespace Tests\Models;


use Mindy\Orm\Fields\ForeignField;
use Mindy\Orm\Model;

class Membership extends Model
{
    public static function getFields()
    {
        return [
            'group' => [
                'class' => ForeignField::className(),
                'modelClass' => Group::className()
            ],
            'user' => [
                'class' => ForeignField::className(),
                'modelClass' => User::className()
            ]
        ];
    }
}
