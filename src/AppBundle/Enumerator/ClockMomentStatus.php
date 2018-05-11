<?php

namespace AppBundle\Enumerator;

/**
 * Class ClockMomentStatus.
 */
abstract class ClockMomentStatus
{
	const CHECK_IN = 0;
	const CHECK_OUT = 1;
	const CHECK_OUT_ALTERNATIVE = 4;
}
