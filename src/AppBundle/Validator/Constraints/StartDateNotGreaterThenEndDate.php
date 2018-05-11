<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Class StartDateNotGreaterThenEndDate
 * Constraint rules for inputs where the start date cannot be greater than the end date.
 *
 * @Annotation
 */
class StartDateNotGreaterThenEndDate extends Constraint
{
	public $message = 'The startDate {{ startDate }} can not be greater then the endDate {{ endDate }}';

	public function validatedBy()
	{
		return StartDateNotGreaterThenEndDateValidator::class;
	}

	public function getTargets()
	{
		return self::CLASS_CONSTRAINT;
	}
}
