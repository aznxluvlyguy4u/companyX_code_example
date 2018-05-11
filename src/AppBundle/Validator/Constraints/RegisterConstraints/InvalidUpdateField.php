<?php

namespace AppBundle\Validator\Constraints\RegisterConstraints;

use Symfony\Component\Validator\Constraint;

/**
 * Class InvalidUpdateField
 * Constraint rules for department property.
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
