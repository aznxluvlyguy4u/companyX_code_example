<?php

namespace AppBundle\Validator\Constraints\RegisterConstraints;

use AppBundle\Entity\Client;
use AppBundle\Entity\Register;
use AppBundle\Enumerator\RegisterType;
use AppBundle\Repository\RegisterRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface as TokenStorage;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Class InvalidFieldValidator.
 */
abstract class InvalidFieldValidator extends ConstraintValidator
{
	/** @var Client */
	protected $client;

	/**
	 * @var EntityManager
	 */
	protected $em;

	/**
	 * @var string
	 */
	protected $currentRequestMethod;

	/**
	 * InvalidFieldValidator constructor.
	 *
	 * @param EntityManager $em
	 * @param TokenStorage  $tokenStorage
	 * @param RequestStack  $requestStack
	 */
	public function __construct(EntityManager $em, TokenStorage $tokenStorage, RequestStack $requestStack)
	{
		$this->client = $tokenStorage->getToken()->getUser();
		$this->em = $em;
		$this->currentRequestMethod = $requestStack->getCurrentRequest()->getMethod();
	}

	/**
	 * Constraint violation for when register type is WORK and department is missing.
	 *
	 * @param Register   $register
	 * @param Constraint $constraint
	 */
	protected function missingDepartmentViolationCheck(Register $register, Constraint $constraint)
	{
		$registerType = $register->getTypeValueName();

		if (RegisterType::WORK === $registerType && !$register->getDepartment()) {
			$this->context->buildViolation($constraint->message)
				->setParameters(array(
					'{{ errorMessage }}' => 'department is required when register type is WORK',
				))
				->addViolation();
		}
	}

	/**
	 * Constraint violation for when the target Employee of the to be POST/PUT/DELETE Register is not the same as the current authenticated client.
	 *
	 * @param Register   $register
	 * @param Constraint $constraint
	 */
	protected function invalidEmployeeViolationCheck(Register $register, Constraint $constraint)
	{
		$targetEmployeeClientId = $register->getEmployee() ? $register->getEmployee()->getClient()->getId() : null;

		if ($this->client->getId() !== $targetEmployeeClientId
			&& !$this->client->canRegisterForOtherEmployees()) {
			$this->context->buildViolation($constraint->message)
				->setParameters(array(
					'{{ errorMessage }}' => 'current authenticated client is not allowed to POST/PUT/DELETE Registers for other employees',
				))
				->addViolation();
		}
	}

	/**
	 * Constraint violation for when the register of type WORK has start_date end_date overlaps with one that already exists.
	 *
	 * @param Register   $register
	 * @param Constraint $constraint
	 */
	protected function overlappingRegisterViolationCheck(Register $register, Constraint $constraint)
	{
		$registerType = $register->getTypeValueName();
		/** @var RegisterRepository $registerRepo */
		$registerRepo = $this->em->getRepository(Register::class);
		$hasOverlappingRegister = $registerRepo->hasOverlappingRegister($register);

		if (RegisterType::WORK === $registerType && $hasOverlappingRegister) {
			$this->context->buildViolation($constraint->message)
				->setParameters(array(
					'{{ errorMessage }}' => $register->getStartDate()->format(\DateTime::RFC3339)
						.' - '.$register->getEndDate()->format(\DateTime::RFC3339)
						.' overlaps with an existing Register',
				))
				->addViolation();
		}
	}

	/**
	 * Constraint violation for when the register of type WORK has a end_date that is in the future.
	 *
	 * @param Register   $register
	 * @param Constraint $constraint
	 */
	protected function dateRangeInTheFutureViolationCheck(Register $register, Constraint $constraint)
	{
		// Must use this instead of new DateTime() to be able to mock current time in phpunit tests
		$currentDate = \DateTime::createFromFormat('U', time());
		$registerEndDate = $register->getEndDate();

		$registerType = $register->getTypeValueName();

		if (RegisterType::WORK === $registerType && $registerEndDate > $currentDate) {
			$this->context->buildViolation($constraint->message)
				->setParameters(array(
					'{{ errorMessage }}' => 'date range is in the future',
				))
				->addViolation();
		}
	}
}
