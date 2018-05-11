<?php

namespace AppBundle\Enumerator;

// TODO This enum is supposedly temporary as it is now impossible to alter their current database structure
// TODO consider store system config value types values in a seperate column during DB Switch
/**
 * Class SystemConfigValueType.
 */
abstract class SystemConfigValueType
{
	const BOOLEAN = 'boolean';
	const INTEGER = 'integer';
	const DATE_INTERVAL = 'dateInterval';
	const DATETIME = 'datetime';
	const STRING = 'string';
	const MIXED = 'mixed';
	const DATEINTERVAL = 'dateinterval';
}
