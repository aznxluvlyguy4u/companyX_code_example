<?php

namespace AppBundle\Validator\Constraints\RegisterConstraints;

use AppBundle\Entity\Register;
use AppBundle\Enumerator\RegisterType;
use AppBundle\Repository\RegisterRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;

/**
 * Class InvalidPostFieldValidator
 * Validator for Class InvalidPostField.
 */
class InvalidPostFieldValidator extends InvalidFieldValidator
{
	/**
	 * @param Register   $register
	 * @param Constraint $constraint
	 */
	public function validate($register, Constraint $constraint)
	{
		// Check for constraint violations during a Post Register request
		if (Request::METHOD_POST === $this->currentRequestMethod) {
			// Raise constraint violation for when register type is WORK and department is missing
			$this->missingDepartmentViolationCheck($register, $constraint);

			// Raise constraint violation for when the target Employee of the to be POST/PUT/DELETE Register is not the same as the current authenticated client
			$this->invalidEmployeeViolationCheck($register, $constraint);

			// Raise constraint violation for when employee already had registered preference for open assignment
			$this->duplicateRegisterPreferenceForEmployeeAndDepartmentViolationCheck($register, $constraint);

			// Raise constraint violation for when the register of type WORK has start_date end_date overlaps with one that already exists
			$this->overlappingRegisterViolationCheck($register, $constraint);

			// Raise constraint violation for when register type WORK has a end_date that is in the future
			$this->dateRangeInTheFutureViolationCheck($register, $constraint);
		}
	}

	/**
	 * Constraint violation for when employee already had registered preference for assignment.
	 *
	 * @param $register
	 * @param Constraint $constraint
	 */
	public function duplicateRegisterPreferenceForEmployeeAndDepartmentViolationCheck(
		Register $register,
		Constraint $constraint
	) {
		if (RegisterType::PREFERENCE === $register->getTypeValueName()) {
			/** @var RegisterRepository $registerRepository */
			$registerRepository = $this->em
				->getRepository(Register::class);
			$currentRegister = $registerRepository->findByEmployeeAndAssignment($register);

			if ($currentRegister) {
				$this->context->buildViolation($constraint->message)
					->setParameters(array(
						'{{ errorMessage }}' => 'Assignment may only be preferred by a employee once',
					))
					->addViolation();
			}
		}
	}
}
