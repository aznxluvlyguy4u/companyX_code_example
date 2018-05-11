<?php

namespace AppBundle\Validator\Constraints\RegisterConstraints;

use Symfony\Component\Validator\Constraint;

/**
 * Class VacationTimeout
 * Constraint rules for DOR_SCHEDULE_VACATION_TIMEOUT setting.
 *
 * @Annotation
 */
class VacationTimeout extends Constraint
{
	public $message = 'The startDate {{ startDate }} for the type VACATION {{ unavailableOption }}can not be lesser than {{ timeoutDate }}';

	public function validatedBy()
	{
		return VacationTimeoutValidator::class;
	}

	public function getTargets()
	{
		return self::CLASS_CONSTRAINT;
	}
}
