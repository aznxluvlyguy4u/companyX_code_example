<?php

namespace AppBundle\Validator\Constraints\ShiftSwapRequestConstraints;

use Symfony\Component\Validator\Constraint;

/**
 * Class InvalidUpdateField
 * Constraint rules to prevent client from updating certain fields depending on the situation.
 *
 * @Annotation
 */
class InvalidUpdateField extends Constraint
{
	public $message = 'Invalid field(s) error: {{ errorMessage }}';

	public function validatedBy()
	{
		return InvalidUpdateFieldValidator::class;
	}

	public function getTargets()
	{
		return self::CLASS_CONSTRAINT;
	}
}
