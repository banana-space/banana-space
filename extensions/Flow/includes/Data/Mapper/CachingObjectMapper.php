<?php

namespace Flow\Data\Mapper;

use Flow\Data\ObjectManager;
use Flow\Data\ObjectMapper;
use Flow\Data\Utils\MultiDimArray;
use Flow\Model\UUID;
use InvalidArgumentException;
use OutOfBoundsException;

/**
 * Rows with the same primary key always return the same object
 * from self::fromStorageRow.  This means that if two parts of the
 * code both load revision 123 they will receive the same object.
 */
class CachingObjectMapper implements ObjectMapper {
	/**
	 * @var callable
	 */
	protected $toStorageRow;

	/**
	 * @var callable
	 */
	protected $fromStorageRow;

	/**
	 * @var string[]
	 */
	protected $primaryKey;

	/**
	 * @var MultiDimArray
	 */
	protected $loaded;

	/**
	 * @param callable $toStorageRow
	 * @param callable $fromStorageRow
	 * @param string[] $primaryKey
	 */
	public function __construct( $toStorageRow, $fromStorageRow, array $primaryKey ) {
		$this->toStorageRow = $toStorageRow;
		$this->fromStorageRow = $fromStorageRow;
		ksort( $primaryKey );
		$this->primaryKey = $primaryKey;
		$this->clear();
	}

	/**
	 * @param string $className Fully qualified class name
	 * @param string[] $primaryKey
	 * @return CachingObjectMapper
	 */
	public static function model( $className, array $primaryKey ) {
		return new self(
			[ $className, 'toStorageRow' ],
			[ $className, 'fromStorageRow' ],
			$primaryKey
		);
	}

	public function toStorageRow( $object ) {
		$row = ( $this->toStorageRow )( $object );
		$pk = ObjectManager::splitFromRow( $row, $this->primaryKey );
		if ( $pk === null ) {
			// new object may not have pk yet, calling code
			// should call self::fromStorageRow with $object to load
			// db assigned pk and store obj in $this->loaded
		} elseif ( !isset( $this->loaded[$pk] ) ) {
			// first time this id has been seen
			$this->loaded[$pk] = $object;
		} elseif ( $this->loaded[$pk] !== $object ) {
			// loaded object of this id is not same object
			$class = get_class( $object );
			$id = json_encode( $pk );
			throw new \InvalidArgumentException( "Duplicate '$class' objects for id $id" );
		}
		return $row;
	}

	public function fromStorageRow( array $row, $object = null ) {
		$pk = ObjectManager::splitFromRow( $row, $this->primaryKey );
		if ( $pk === null ) {
			throw new \InvalidArgumentException( 'Storage row has no pk' );
		} elseif ( !isset( $this->loaded[$pk] ) ) {
			// unserialize the object
			$this->loaded[$pk] = ( $this->fromStorageRow )( $row, $object );
			return $this->loaded[$pk];
		} elseif ( $object === null ) {
			// provide previously loaded object
			return $this->loaded[$pk];
		} elseif ( $object !== $this->loaded[$pk] ) {
			// loaded object of this id is not same object
			$class = get_class( $object );
			$id = json_encode( $pk );
			throw new \InvalidArgumentException( "Duplicate '$class' objects for id $id" );
		} else {
			// object was provided, load $row into $object
			// we already know $this->loaded[$pk] === $object
			return ( $this->fromStorageRow )( $row, $object );
		}
	}

	/**
	 * @param array $primaryKey
	 * @return object|null
	 * @throws InvalidArgumentException
	 */
	public function get( array $primaryKey ) {
		$primaryKey = UUID::convertUUIDs( $primaryKey, 'alphadecimal' );
		ksort( $primaryKey );
		if ( array_keys( $primaryKey ) !== $this->primaryKey ) {
			throw new InvalidArgumentException;
		}
		try {
			return $this->loaded[$primaryKey];
		} catch ( OutOfBoundsException $e ) {
			return null;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function normalizeRow( array $row ) {
		$object = ( $this->fromStorageRow )( $row );
		return ( $this->toStorageRow )( $object );
	}

	public function clear() {
		$this->loaded = new MultiDimArray;
	}
}
