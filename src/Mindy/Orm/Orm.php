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
 * @date 03/01/14.01.2014 21:52
 */

namespace Mindy\Orm;


use Exception;
use Mindy\Orm\Fields\Field;
use Mindy\Orm\Fields\ManyToManyField;
use Mindy\Query\Connection;

class Orm extends Base
{
    /**
     * @var array validation errors (attribute name => array of errors)
     */
    private $_errors = [];

    /**
     * @var bool Returns a value indicating whether the current record is new.
     */
    public $isNewRecord = true;

    /**
     * TODO move to manager
     * @var \Mindy\Query\Connection
     */
    private static $_connection;

    /**
     * @var string
     */
    public $autoField = '\Mindy\Orm\Fields\AutoField';

    /**
     * @var string
     */
    public $relatedField = '\Mindy\Orm\Fields\RelatedField';

    /**
     * @var string
     */
    public $foreignField = '\Mindy\Orm\Fields\ForeignField';

    /**
     * @var string
     */
    public $manyToManyField = '\Mindy\Orm\Fields\ManyToManyField';

    /**
     * @var array
     */
    private $_fields = [];

    /**
     * @var array
     */
    private $_fkFields = [];

    /**
     * @var array
     */
    private $_manyFields = [];

    /**
     * TODO move to manager
     * @param Connection $connection
     */
    public static function setConnection(Connection $connection)
    {
        self::$_connection = $connection;
    }

    /**
     * TODO move to manager
     * @return \Mindy\Query\Connection
     */
    public static function getConnection()
    {
        return self::$_connection;
    }

    /**
     * TODO move to manager
     * Returns the schema information of the DB table associated with this AR class.
     * @return \Mindy\Query\TableSchema the schema information of the DB table associated with this AR class.
     * @throws Exception if the table for the AR class does not exist.
     */
    public static function getTableSchema()
    {
        $schema = self::getConnection()->getTableSchema(static::tableName());
        if ($schema !== null) {
            return $schema;
        } else {
            throw new Exception("The table does not exist: " . static::tableName());
        }
    }

    /**
     * TODO move to manager
     * Creates an active record instance.
     * This method is called by [[create()]].
     * You may override this method if the instance being created
     * depends on the row data to be populated into the record.
     * For example, by creating a record based on the value of a column,
     * you may implement the so-called single-table inheritance mapping.
     * @param array $row row data to be populated into the record.
     * @return \Mindy\Orm\Model the newly created active record
     */
    public static function instantiate($row)
    {
        return new static;
    }

    /**
     * TODO move to manager
     * Creates an active record object using a row of data.
     * This method is called by [[ActiveQuery]] to populate the query results
     * into Active Records. It is not meant to be used to create new records.
     * @param array $row attribute values (name => value)
     * @return \Mindy\Orm\Model the newly created active record.
     */
    public static function create($row)
    {
        $record = static::instantiate($row);
        foreach ($row as $name => $value) {
            if ($record->hasField($name)) {
                $record->getField($name)->setValue($value);
            }
        }
        // TODO afterFind event
        return $record;
    }

    /**
     * TODO move to manager
     * Refresh primary key value after save model
     * @return void
     */
    protected function refreshPrimaryKeyValue()
    {
        $table = $this->getTableSchema();
        if ($table->sequenceName !== null) {
            foreach ($table->primaryKey as $name) {
                $field = $this->getField($name, false);
                if ($field->getValue() === null) {
                    $id = $this->getConnection()->getLastInsertID($table->sequenceName);
                    $field->setValue($id);
                    break;
                }
            }
        }
    }

    /**
     * TODO move to manager
     * @return bool
     */
    protected function insert()
    {
        $values = $this->getChangedValues();

        $connection = $this->getConnection();

        $command = $connection->createCommand()->insert(static::tableName(), $values);
        if(!$command->execute()) {
            return false;
        }

        $this->isNewRecord = false;
        $this->refreshPrimaryKeyValue();

        return true;
    }

    /**
     * TODO move to manager
     * Updates the whole table using the provided attribute values and conditions.
     * For example, to change the status to be 1 for all customers whose status is 2:
     *
     * ~~~
     * Customer::updateAll(['status' => 1], 'status = 2');
     * ~~~
     *
     * @param array $attributes attribute values (name-value pairs) to be saved into the table
     * @param string|array $condition the conditions that will be put in the WHERE part of the UPDATE SQL.
     * Please refer to [[Query::where()]] on how to specify this parameter.
     * @param array $params the parameters (name => value) to be bound to the query.
     * @return integer the number of rows updated
     */
    public static function updateAll($attributes, $condition = '', $params = [])
    {
        $command = static::getConnection()->createCommand();
        $command->update(static::tableName(), $attributes, $condition, $params);
        return $command->execute();
    }

    /**
     * TODO move to trait
     * @return array
     */
    protected function getChangedValues()
    {
        $values = [];
        foreach($this->getFieldsInit() as $name => $field) {
            if(is_a($field, $this->manyToManyField)) {
                continue;
            }

            if(is_a($field, $this->foreignField)) {
                $name .= '_id';
                /* @var $field \Mindy\Orm\Fields\ForeignField */
                $value = $field->getValue()->pk;
            } else {
                /* @var $field \Mindy\Orm\Fields\Field */
                $value = $field->getValue();
            }

            $values[$name] = $value;
        }

        return $values;
    }

    /**
     * TODO move to manager
     * @return bool
     * @throws \Exception
     */
    protected function update()
    {
        // TODO beforeSave
        $values = $this->getChangedValues();

        $name = $this->primaryKey();
        $condition = [
            $name => $this->getField($name)->getValue()
        ];

        // We do not check the return value of updateAll() because it's possible
        // that the UPDATE statement doesn't change anything and thus returns 0.
        return (bool) $this->updateAll($values, $condition);
    }

    /**
     * TODO move to manager
     * @return bool
     */
    public function save()
    {
        return $this->isNewRecord ? $this->insert() : $this->update();
    }

    /**
     * @return string|null
     */
    public static function primaryKey()
    {
        $className = get_called_class();
        $model = new $className();
        return $model->getPkName();
        // return static::getTableSchema()->primaryKey;
    }

    // TODO documentation, refactoring
    public function getPkName()
    {
        foreach ($this->getFieldsInit() as $name => $field) {
            if (is_a($field, $this->autoField)) {
                return $name;
            }
        }

        return null;
    }

    public static function objects()
    {
        $className = get_called_class();
        return new Manager(new $className);
    }

    public function __construct()
    {
        $this->initFields();
    }

    /**
     * Sets value of an object property.
     *
     * Do not call this method directly as it is a PHP magic method that
     * will be implicitly called when executing `$object->property = $value;`.
     * @param string $name the property name or the event name
     * @param mixed $value the property value
     * @throws \Exception
     * @see __set()
     */
    public function __set($name, $value)
    {
        if ($this->hasField($name)) {
            $field = $this->getField($name);
            if(is_a($field, $this->foreignField)) {
                /** @var $field \Mindy\Orm\Fields\ForeignField */
                $this->_fkFields[$name . '_' . $field->getForeignPrimaryKey()] = $name;
            }
            $field->setValue($value);
        } else if($this->hasForeignKey($name)) {
            $this->getForeignKey($name)->setValue($value);
        } else if(false) {
            // TODO add support for m2m setter. Example:
            /**
             * $model->items = []; override all related records.
             */
        } else {
            throw new Exception('Setting unknown property: ' . get_class($this) . '::' . $name);
        }
    }

    /**
     * Returns the value of an object property.
     *
     * Do not call this method directly as it is a PHP magic method that
     * will be implicitly called when executing `$value = $object->property;`.
     * @param string $name
     * @return mixed the property value
     * @throws \Exception
     * @see __get()
     */
    public function __get($name)
    {
        if($name == 'pk') {
            return $this->getPk();
        }

        if ($this->hasField($name)) {
            $field = $this->getField($name);
            if (is_a($field, $this->relatedField)) {
                if (is_a($field, $this->foreignField)) {
                    return $field->getValue();
                } else if (is_a($field, $this->manyToManyField)) {
                    /* @var $field \Mindy\Orm\Fields\ManyToManyField */
                    return $field->getQuerySet();
                } else {
                    throw new Exception("Unknown field type " . $name . " in " . get_class($this));
                }
            } else {
                return $field->getValue();
            }
        } else if($this->hasForeignKey($name)) {
            return $this->getForeignKey($name)->getValue()->getPk();
        }

        throw new Exception('Getting unknown property: ' . get_class($this) . '::' . $name);
    }

    /**
     * @param $name
     * @return bool
     */
    private function hasForeignKey($name)
    {
        return array_key_exists($name, $this->_fkFields);
    }

    /**
     * @param $name
     * @return \Mindy\Orm\Fields\ForeignField
     */
    private function getForeignKey($name)
    {
        return $this->getField($this->_fkFields[$name]);
    }

    public function getPk()
    {
        /* @var $field \Mindy\Orm\Fields\Field */
        if($this->hasField('id')) {
            return $this->getField('id')->getValue();
        } else {
            foreach ($this->getFieldsInit() as $name => $field) {
                if (is_a($field, $this->autoField)) {
                    return $field->getValue();
                }
            }
        }

        return null;
    }

    /**
     * Adds a new error to the specified attribute.
     * @param string $attribute attribute name
     * @param string $error new error message
     */
    public function addError($attribute, $error = '')
    {
        $this->_errors[$attribute][] = $error;
    }

    /**
     * Removes errors for all attributes or a single attribute.
     * @param string $attribute attribute name. Use null to remove errors for all attribute.
     */
    public function clearErrors($attribute = null)
    {
        if ($attribute === null) {
            $this->_errors = [];
        } else {
            unset($this->_errors[$attribute]);
        }
    }

    /**
     * Returns a value indicating whether there is any validation error.
     * @param string|null $attribute attribute name. Use null to check all attributes.
     * @return boolean whether there is any error.
     */
    public function hasErrors($attribute = null)
    {
        return $attribute === null ? !empty($this->_errors) : isset($this->_errors[$attribute]);
    }

    /**
     * Returns the errors for all attribute or a single attribute.
     * @param string $attribute attribute name. Use null to retrieve errors for all attributes.
     * @property array An array of errors for all attributes. Empty array is returned if no error.
     * The result is a two-dimensional array. See [[getErrors()]] for detailed description.
     * @return array errors for all attributes or the specified attribute. Empty array is returned if no error.
     * Note that when returning errors for all attributes, the result is a two-dimensional array, like the following:
     *
     * ~~~
     * [
     *     'username' => [
     *         'Username is required.',
     *         'Username must contain only word characters.',
     *     ],
     *     'email' => [
     *         'Email address is invalid.',
     *     ]
     * ]
     * ~~~
     *
     * @see getFirstErrors()
     * @see getFirstError()
     */
    public function getErrors($attribute = null)
    {
        if ($attribute === null) {
            return $this->_errors === null ? [] : $this->_errors;
        } else {
            return isset($this->_errors[$attribute]) ? $this->_errors[$attribute] : [];
        }
    }

    /**
     * @return bool
     */
    public function isValid()
    {
        $this->clearErrors();

        /* @var $field \Mindy\Orm\Fields\Field */
        foreach ($this->getFieldsInit() as $name => $field) {
            if ($field->isValid() === false) {
                foreach ($field->getErrors() as $error) {
                    $this->addError($name, $error);
                }
            }
        }

        return $this->hasErrors() === false;
    }

    /**
     * Initialize fields
     * @void
     */
    public function initFields()
    {
        $needPk = true;

        foreach ($this->getFields() as $name => $field) {
            /* @var $field \Mindy\Orm\Fields\Field */
            if (is_a($field, $this->autoField)) {
                $needPk = false;
            }

            if (is_a($field, $this->relatedField)) {
                /* @var $field \Mindy\Orm\Fields\RelatedField */
                if (is_a($field, $this->manyToManyField)) {
                    /* @var $field \Mindy\Orm\Fields\ManyToManyField */
                    $this->_manyFields[$name] = $field;
                } else {
                    /* @var $field \Mindy\Orm\Fields\ForeignField */
                    $this->_fields[$name] = $field;
                    $this->_fkFields[$name . '_' . $field->getForeignPrimaryKey()] = $name;
                }
            } else {
                $this->_fields[$name] = $field;
            }
        }

        if ($needPk) {
            $this->_fields = array_merge([
                'id' => new $this->autoField()
            ], $this->_fields);
        }

        foreach($this->_manyFields as $name => $field) {
            /* @var $field \Mindy\Orm\Fields\ManyToManyField */
            $field->setModel($this);

            // @TODO
            /* @var $newField \Mindy\Orm\Fields\ManyToManyField */
//                    $newField = new $this->manyToManyField(static::className());
//                    $newField->setModel($this);
//                    self::$_relations[$field->relatedName] = $newField->getRelation();
//                    $this->_fields[$name] = $newField;

            $this->_fields[$name] = $field;
        }
    }

    public function getManyFields()
    {
        return $this->_manyFields;
    }

    /**
     * Return initialized fields
     */
    public function getFieldsInit()
    {
        return $this->_fields;
    }

    /**
     * Example usage:
     * return [
     *     'name' => new CharField(['length' => 250, 'default' => '']),
     *     'email' => new EmailField(),
     * ]
     * @return array
     */
    public function getFields()
    {
        return [];
    }

    /**
     * @param $name
     * @return bool
     */
    public function hasField($name)
    {
        return isset($this->_fields[$name]);
    }

    /**
     * @param $name
     * @return \Mindy\Orm\Fields\Field|null
     */
    public function getField($name, $throw = true)
    {
        if($this->hasField($name)) {
            return $this->_fields[$name];
        }

        if($throw) {
            throw new Exception('Field ' . $name . ' not found');
        } else {
            return null;
        }
    }
}
