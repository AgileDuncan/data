<?php // vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

class Field_Many 
{
    use \atk4\core\TrackableTrait {
        init as _init;
    }
    use \atk4\core\InitializerTrait;


    /**
     * What should we pass into owner->ref() to get
     * through to this reference
     */

    protected $link;

    /**
     * Definition of the destination model, that can
     * be either an object, a callback or a string.
     */
    protected $model;

    /**
     * their field will be $table.'_id' by default.
     */
    protected $their_field = null;

    /**
     * our field will be 'id' by default
     */
    protected $our_field = null;

    /**
     * default constructor. Will copy argument into properties
     */
    function __construct($defaults = [])
    {

        if (isset($defaults[0])) {
            $this->link = $defaults[0];
            unset($defaults[0]);
        }

        foreach ($defaults as $key => $val) {
            $this->$key = $val;
        }
    }

    /**
     * Will use either foreign_alias or create #join_<table> 
     */
    public function getDesiredName()
    {
        return '#ref_'.$this->link;
    }

    public function init()
    {
        $this->_init();
    }

    protected function getModel()
    {
        if (is_callable($this->model)) {
            $c = $this->model;
            return $c($this->owner, $this);
        }

        if (is_object($this->model)) {
            return clone $this->model;
        }

        // last effort - try to add model
        $p = $this->owner->persistence;
        return $p->add($p->normalizeClassName($this->model,'Model'));

        throw new Exception([
            'Model is not defined for the relation',
            'model'=>$this->model
        ]);
    }

    protected function getOurValue()
    {
        if ($this->owner->loaded()) {
            if ($this->our_field) {
                return $this->owner[$this->our_field];
            } else {
                return $this->owner->id;
            }
        } else {
            // create expression based on exsting conditions
            return $this->owner->action(
                'fieldValues', [
                    $this->our_field ?: $this->owner->id_field
                ]);
        }
    }

    protected function referenceOurValue()
    {
        $this->owner->persistence_data['use_table_prefixes']=true;
        return $this->owner->getElement($this->our_field ?: $this->owner->id_field);
    }

    /**
     * Adding field into join will automatically associate that field
     * with this join. That means it won't be loaded from $table but
     * form the join instead
     */
    public function ref()
    {
        return $this->getModel()
            ->addCondition(
                $this->their_field ?: ($this->owner->table.'_id'),
                $this->getOurValue()
            );
    }

    /**
     * Creates model that can be used for generating sub-query acitons
     */
    public function refLink()
    {
        return $this->getModel()
            ->addCondition(
                $this->their_field ?: ($this->owner->table.'_id'),
                $this->referenceOurValue()
            );
    }

    /**
     * Adding field into join will automatically associate that field
     * with this join. That means it won't be loaded from $table but
     * form the join instead
     */
    public function addField($n, $defaults = [])
    {
        if (!isset($defaults['aggregate'])) {
            throw new Exception([
                '"aggregate" strategy should be defined for oneToMany field',
                'field'=>$n,
                'defaults'=>$defaults
            ]);
        }

        $actual = isset($defaults['actual']) ? $defaults['actual']:$n;
        $action = $this->refLink()->action('fx',[$defaults['aggregate'], $actual]);
        return $this->owner->addExpression($n, $action);
    }

    public function addFields($fields = [])
    {
        foreach ($fields as $field) {
            $name = $field[0];
            unset($field[0]);
            $this->addField($name, $field);
        }
        return $this;
    }
}
