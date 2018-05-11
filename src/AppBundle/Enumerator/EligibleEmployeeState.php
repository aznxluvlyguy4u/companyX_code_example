<?php

namespace AppBundle\Enumerator;

/**
 * Class EligibleEmployeeState.
 */
abstract class EligibleEmployeeState
{
	const PREFERENCED_EMPLOYEES = 'preferenced_employees'; //PREFERENCED ALL STATUS
	const AVAILABLE_EMPLOYEES = 'available_employees'; // AVAILABLE APPROVED,
	const UNAVAILABLE_EMPLOYEES = 'unavailable_employees'; //VACATION APPROVED, UNAVAILABLE APPROVED, SICK APPROVED
	const FREE_EMPLOYEES = 'free_employees';
	const SCHEDULED_EMPLOYEES = 'scheduled_employees';
}
