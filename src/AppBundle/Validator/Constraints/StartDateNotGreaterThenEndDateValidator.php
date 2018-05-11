<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Class StartDateNotGreaterThenEndDateValidator
 * Validator for Class StartDateNotGreaterThenEndDat.
 */
class StartDateNotGreaterThenEndDateValidator extends ConstraintValidator
{
	/**
	 * @param mixed      $object
	 * @param Constraint $constraint
	 */
	public function validate($object, Constraint $constraint)
	{
		if ($object->getStartDate() > $object->getEndDate()) {
			$this->context->buildViolation($constraint->message)
				->setParameters(array(
					'{{ startDate }}' => $object->getStartDate()->format(\DateTime::RFC3339),
					'{{ endDate }}' => $object->getEndDate()->format(\DateTime::RFC3339),
				))
				->addViolation();
		}
	}
}
