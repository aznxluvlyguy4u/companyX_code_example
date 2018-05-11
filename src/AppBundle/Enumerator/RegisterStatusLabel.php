<?php

namespace AppBundle\Enumerator;

/**
 * Class RegisterStatusLabel.
 */
abstract class RegisterStatusLabel
{
	const VACATION_UNPROCESSED = 'processing';
	const VACATION_DENIED = 'denied';
	const VACATION_OK = 'ok';
	const AVAILABLE_UNPROCESSED = 'unprocessed';
	const AVAILABLE_DENIED = 'denied';
	const AVAILABLE_GRANTED = 'granted';
	const UNAVAILABLE_UNPROCESSED = 'unprocessed';
	const UNAVAILABLE_DENIED = 'denied';
	const UNAVAILABLE_GRANTED = 'granted';
	const WORK_UNPROCESSED = 'processing';
	const WORK_DENIED = 'denied';
	const WORK_GRANTED = 'granted';
	const PREFERENCE_UNPROCESSED = 'unprocessed';
	const PREFERENCE_DENIED = 'denied';
	const PREFERENCE_GRANTED = 'granted';
}
