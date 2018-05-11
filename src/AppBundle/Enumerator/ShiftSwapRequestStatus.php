<?php

namespace AppBundle\Enumerator;

/**
 * Class RegisterStatus.
 */
abstract class ShiftSwapRequestStatus
{
	const UNPROCESSED_BY_RECEIVER = 0;
	const GRANTED_BY_RECEIVER = 1;
	const DENIED_BY_RECEIVER = 2;
	const GRANTED_BY_PLANNER = 3;
	const DENIED_BY_PLANNER = 4;
	const WITHDRAWN_BY_APPLICANT = 5;
	const WITHDRAWN_BY_RECEIVER = 6;
}
