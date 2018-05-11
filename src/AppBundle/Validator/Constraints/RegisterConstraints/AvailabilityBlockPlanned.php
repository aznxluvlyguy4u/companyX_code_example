<?php

namespace AppBundle\Validator\Constraints\RegisterConstraints;

use Symfony\Component\Validator\Constraint;

/**
 * Class AvailabilityBlockPlanned
 * Constraint rules for DOR_SCHEDULE_AVAILABILITY_BLOCK_PLANNED setting.
 *
 * @Annotation
 */
class AvailabilityBlockPlanned extends Constraint
{
	public $message = 'Constraint error: {{ errorMessage }}';

	public function validatedBy()
	{
		return AvailabilityBlockPlannedValidator::class;
	}

	public function getTargets()
	{
		return self::CLASS_CONSTRAINT;
	}
}
