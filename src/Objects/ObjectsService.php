<?php
namespace ActivityPub\Objects;

use ActivityPub\Entities\ActivityPubObject;
use ActivityPub\Entities\Field;
use ActivityPub\Utils\Util;
use Doctrine\ORM\EntityManager;

class ObjectsService
{
    protected $entityManager;

    public function __construct( EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Creates a new object with fields defined by $fields
     *
     // TODO make this happen:
     * If there is an 'id' field, and an object with that id already exists,
     *   this returns the existing object rather than creating the new object.
     *   The existing object will not have its fields modified.
     *
     * @param array $fields The fields that define the new object
     * @return ActivityPubObject The created object
     */
    public function createObject( array $fields )
    {
        // TODO validate object fields?
        // TODO don't create the object if it already exists
        // i.e. (an 'id' field exists with the same value as this one)
        // TODO attempt to fetch and create any values that are URLs
        // TODO JSON-LD compact all objects with the right context before saving them
        $object = new ActivityPubObject();
        $this->entityManager->persist( $object );
        foreach ( $fields as $name => $value ) {
            $this->persistField( $object, $name, $value );
        }
        $this->entityManager->flush();
        return $object;
    }

    /**
     * Persists a field. 
     *
     * If the field is an array, persist it as a child object
     *
     * @param ActivityPubObject $object
     * @param string $fieldName
     * @param string|array $fieldValue
     *
     */
    private function persistField( $object, $fieldName, $fieldValue )
    {
        if ( is_string( $fieldValue ) ) {
            $fieldEntity = Field::withValue( $object, $fieldName, $fieldValue );
            $this->entityManager->persist( $fieldEntity);
        } else if ( is_array( $fieldValue ) ) {
            $referencedObject = $this->createObject( $fieldValue );
            $fieldEntity = Field::withObject( $object, $fieldName, $referencedObject );
            $this->entityManager->persist( $fieldEntity );
        }
    }

    /**
     * Queries for an object with certain field values
     *
     * @param array $queryTerms An associative array where the keys are field 
     *   names and the values are the values to query for. The value for a key
     *   can also be another associative array, which represents a field
     *   containing a target object that matches the given nested query.
     *   Finally, the value could be a sequential array, which represents a field
     *   containing all of the specified values (the field could also contain more
     *   values).
     *
     * @return ActivityPubObject[] The objects that match the query, if any,
     *   ordered by created timestamp from newest to oldest
     */
    public function query( $queryTerms )
    {
        $qb = $this->getObjectQuery( $queryTerms );
        $query = $qb->getQuery();
        return $query->getResult();
    }

    /**
     * Generates the Doctrine QueryBuilder that represents the query
     *
     * This function is recursive; it traverses the query tree to build up the 
     *   final expression
     *
     * @param array $queryTerms The query terms from which to generate the expressions
     * @param int $nonce A nonce value to differentiate field names
     * @return QueryBuilder The expression
     */
    protected function getObjectQuery( $queryTerms, $nonce = 0 )
    {
        $qb = $this->entityManager->createQueryBuilder();
        $exprs = array();
        foreach( $queryTerms as $fieldName => $fieldValue ) {
            if ( is_array( $fieldValue ) ) {
                $subQuery = $this->getObjectQuery( $fieldValue, $nonce + 1 );
                $exprs[] = $qb->expr()->andX(
                    $qb->expr()->like(
                        "field$nonce.name",
                        $qb->expr()->literal( (string) $fieldName )
                    ),
                    $qb->expr()->in( "field$nonce.targetObject", $subQuery->getDql())
                );
            } else {
                $exprs[] = $qb->expr()->andX(
                    $qb->expr()->like(
                        "field$nonce.name",
                        $qb->expr()->literal( (string) $fieldName )
                    ),
                    $qb->expr()->like(
                        "field$nonce.value",
                        $qb->expr()->literal( $fieldValue )
                    )
                );
            }
        }
        return $qb->select( "object$nonce" )
            ->from( 'ActivityPub\Entities\ActivityPubObject', "object$nonce" )
            ->join( "object{$nonce}.fields", "field$nonce" )
            ->where( call_user_func_array(
                array( $qb->expr(), 'orX' ),
                $exprs
            ) )
            ->groupBy( "object$nonce" )
            ->having( $qb->expr()->eq(
                $qb->expr()->count( "field$nonce" ),
                count( $queryTerms )
            ) );
    }
}
?>
