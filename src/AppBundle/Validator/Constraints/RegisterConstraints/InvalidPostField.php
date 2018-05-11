<?php

namespace AppBundle\Validator\Constraints\RegisterConstraints;

use Symfony\Component\Validator\Constraint;

/**
 * Class InvalidPostField
 * Constraint rules for department property.
 *
 * @Annotation
 */
class InvalidPostField extends Constraint
{
	public $message = 'Invalid field(s) error: {{ errorMessage }}';

	public function validatedBy()
	{
		return InvalidPostFieldValidator::class;
	}

	public function getTargets()
	{
		return self::CLASS_CONSTRAINT;
	}
}
