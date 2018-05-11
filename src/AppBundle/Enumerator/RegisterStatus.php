<?php

namespace AppBundle\Enumerator;

/**
 * Class RegisterStatus.
 */
abstract class RegisterStatus
{
	const UNPROCESSED = 0;
	const DENIED = 1;
	const GRANTED = 2;
}
