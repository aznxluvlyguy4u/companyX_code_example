<?php

namespace AppBundle\Validator\Constraints\RegisterConstraints;

use AppBundle\Entity\Register;
use AppBundle\Enumerator\RegisterStatus;
use AppBundle\Enumerator\RegisterType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;

/**
 * Class InvalidUpdateFieldValidator
 * Validator for Class InvalidUpdateField.
 */
class InvalidUpdateFieldValidator extends InvalidFieldValidator
{
	/**
	 * @param Register   $register
	 * @param Constraint $constraint
	 */
	public function validate($register, Constraint $constraint)
	{
		// Check for constraint violations during a PUT Register request
		if (Request::METHOD_PUT === $this->currentRequestMethod) {
			// Raise constraint violation for when register type is WORK and department is missing
			$this->missingDepartmentViolationCheck($register, $constraint);

			// Raise constraint violation for when the target Employee of the to be POST/PUT/DELETE Register is not the same as the current authenticated client
			$this->invalidEmployeeViolationCheck($register, $constraint);

			// Raise constraint violation for when the register of type WORK has start_date end_date overlaps with one that already exists
			$this->overlappingRegisterViolationCheck($register, $constraint);

			// Raise constraint violation for when register type WORK has a end_date that is in the future
			$this->dateRangeInTheFutureViolationCheck($register, $constraint);

			// Prepare unit of work
			$unitOfWork = $this->em->getUnitOfWork();

			// Merge the detached Register object to be able to calculate unitOfWork
			/** @var Register $register */
			$register = $this->em->merge($register);
			// Calculate changed fields during PATCH / PUT
			$originalData = $unitOfWork->getOriginalEntityData($register);
			$unitOfWork->computeChangeSets();

			// Raise constraint violation for when a normal Client tries to edit a register of type UNAVAILABLE with status DENIED (1).
			$this->deniedUnavailabilityViolationCheck($constraint, $originalData);
		}
	}

	/**
	 * Constraint violation for when a normal Client tries to edit a register of type UNAVAILABLE with status DENIED (1).
	 *
	 * @param Constraint $constraint
	 * @param $originalData
	 */
	private function deniedUnavailabilityViolationCheck(Constraint $constraint, $originalData)
	{
		$originalRegisterType = $originalData['type'];
		$originalRegisterStatus = $originalData['status'];

		if (!$this->client->canRegisterForOtherEmployees()
			&& RegisterType::UNAVAILABLE === $originalRegisterType
			&& RegisterStatus::DENIED === $originalRegisterStatus) {
			$this->context->buildViolation($constraint->message)
				->setParameters(array(
					'{{ errorMessage }}' => 'current authenticated client is not allowed to edit denied Registers of the type UNAVAILABLE',
				))
				->addViolation();
		}
	}
}
