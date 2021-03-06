<?php
namespace ActivityPub\Entities;

use DateTime;

/**
 * The field table hold the JSON-LD object graph.
 * 
 * Its structure is based on https://changelog.com/posts/graph-databases-101:
 * Every row has a subject, which is a foreign key into the Objects table,
 * a predicate, which is a the JSON field that describes the graph edge relationship
 * (e.g. 'id', 'inReplyTo', 'attributedTo'), and either a value or an object.
 * A value is a string that represents the value of the JSON-LD field if the field
 * is a static value, like { "url": "https://example.com" }. An object is another foreign
 * key into the objects table that represents the value of the JSON-LD field if the
 * field is another JSON-LD object, like { "inReplyTo": { <another object } }.
 * A subject can have multiple values for the same predicate - this represents a JSON-LD
 * array.
 *
 * @Entity @Table(name="fields")
 */
class Field
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     * @var int
     */
    protected $id;

    /**
     * @ManyToOne(targetEntity="ActivityPubObject", inversedBy="fields")
     * @var ActivityPubObject The object to which this field belongs
     */
    protected $object;
    /**
     * @Column(type="string")
     * @var string The name of the field
     */
    protected $name;
    /**
     * If this is set, this is a leaf node in the object graph.
     *
     * @Column(type="string", nullable=true)
     * @var string The value of the field; mutually exclusive with $targetObject
     */
    protected $value;
    /**
     * @ManyToOne(targetEntity="ActivityPubObject", inversedBy="referencingFields")
     * @var ActivityPubObject The value of the field if it holds another object; 
     *   mutually exclusive with $value
     */
    protected $targetObject;

    /**
     * The field's creation timestamp
     * @Column(type="datetime")
     * @var DateTime The creation timestamp
     */
    protected $created;

    /**
     * The field's last updated timestamp
     * @Column(type="datetime")
     * @var DateTime The last updated timestamp
     */
    protected $lastUpdated;

    /**
     * Create a new field with a string value
     *
     * @param ActivityPubObject $object The object to which this field belongs
     * @param string $name The name of the field
     * @param string $value The value of the field
     * @return Field The new field
     */
    public static function withValue( ActivityPubObject $object, string $name, string $value )
    {
        $field = new Field();
        $field->setObject( $object );
        $field->setName( $name );
        $field->setValue( $value );
        $now = new DateTime( "now" );
        $field->setCreated( $now );
        $field->setLastUpdated( $now );
        return $field;
    }

    /**
     * Create a new field that holds another object
     *
     * @param ActivityPubObject $object The object to which this field belongs
     * @param string $name The name of the field
     * @param ActivityPubObject $targetObject The object that this field holds
     * @return Field The new field
     */
    public static function withObject( ActivityPubObject $object, string $name, Object $targetObject )
    {
        $field = new Field();
        $field->setObject( $object);
        $field->setName( $name );
        $field->setTargetObject( $targetObject );
        $now = new DateTime( "now" );
        $field->setCreated( $now );
        $field->setLastUpdated( $now );
        return $field;
    }

    protected function setObject( ActivityPubObject $object )
    {
        $object->addField( $this );
        $this->object= $object;
    }

    protected function setTargetObject( ActivityPubObject $targetObject )
    {
        $this->value = null;
        $targetObject->addReferencingField( $this );
        $this->targetObject = $targetObject;
        $this->lastUpdated = new DateTime( "now" );
    }

    protected function setName( string $name )
    {
        $this->name= $name;
    }

    public function setValue( string $value )
    {
        $this->targetObject = null;
        $this->value = $value;
        $this->lastUpdated = new DateTime( "now" );
    }

    protected function setCreated( DateTime $timestamp )
    {
        $this->created = $timestamp;
    }

    protected function setLastUpdated( DateTime $timestamp )
    {
        $this->lastUpdated = $timestamp;
    }

    /**
     * Returns the object to which this field belongs
     *
     * @return ActivityPubObject
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * Returns the name of the field
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns either the value or the target object of the field, depending on which was set
     *
     * @return string|ActivityPubObject
     */
    public function getValueOrTargetObject()
    {
        if ( ! is_null( $this->targetObject) ) {
            return $this->targetObject;
        } else {
            return $this->value;
        }
    }

    /**
     * Returns the field's creation timestamp
     *
     * @return DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Returns the field's last updated timestamp
     *
     * @return DateTime
     */
    public function getLastUpdated()
    {
        return $this->lastUpdated;
    }
}
?>
