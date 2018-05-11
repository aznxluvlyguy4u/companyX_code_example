<?php

namespace AppBundle\Validator\Constraints\RegisterConstraints;

use AppBundle\Entity\Assignment;
use AppBundle\Entity\Register;
use AppBundle\Enumerator\RegisterType;
use AppBundle\Repository\AssignmentRepository;
use AppBundle\Repository\SystemConfigRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use AppBundle\Entity\SystemConfig;
use AppBundle\Util\Constants;

/**
 * Class AvailabilityBlockPlannedValidator
 * Validator for Class AvailabilityBlockPlanned.
 */
class AvailabilityBlockPlannedValidator extends ConstraintValidator
{
	/**
	 * @var EntityManager
	 */
	private $em;

	/**
	 * AvailabilityBlockPlannedValidator constructor.
	 *
	 * @param EntityManager $em
	 */
	public function __construct(EntityManager $em)
	{
		$this->em = $em;
	}

	/**
	 * Check DOR_SCHEDULE_AVAILABILITY_BLOCK_PLANNED setting to determine whether or not a client
	 * is allowed PUT/PATCH/DELETE the Register of the type AVAILABLE/UNAVAILABLE.
	 *
	 * 0. A Client can always PUT/PATCH/DELETE his AVAILABLE/UNAVAILABLE Registers.
	 *    (This is default behaviour if the key doesn't exist in DB)
	 * 1. A Client cannot PUT/PATCH/DELETE his AVAILABLE/UNAVAILABLE Registers if he
	 *    already has an Assignment on that day that is already published
	 * 2. A Client cannot PUT/PATCH/DELETE his AVAILABLE/UNAVAILABLE Registers if he
	 *    already has an Assignment REGARDLESS whether or not it is published
	 *
	 * @param Register   $register
	 * @param Constraint $constraint
	 */
	public function validate($register, Constraint $constraint)
	{
		$registerType = $register->getTypeValueName();

		if (RegisterType::AVAILABLE === $registerType || RegisterType::UNAVAILABLE === $registerType) {
			/** @var SystemConfigRepository $systemConfigRepo */
			$systemConfigRepo = $this->em->getRepository(SystemConfig::class);
			/** @var AssignmentRepository $assignmentRepo */
			$assignmentRepo = $this->em->getRepository(Assignment::class);

			$blockPlannedSettingValue = $systemConfigRepo->getScheduleAvailabilityBlockPlannedSetting();

			$registerStartDate = $register->getStartDate();
			/** @var Assignment[] $plannedAssignments */
			$plannedAssignments = $assignmentRepo->findByEmployeeAndStartDate($register->getEmployee(), $registerStartDate);

			$published = false;
			foreach ($plannedAssignments as $plannedAssignment) {
				if ($plannedAssignment->getPublished()) {
					$published = true;
				}
			}

			// If DOR_SCHEDULE_AVAILABILITY_BLOCK_PLANNED setting is 1 no availability Registers can be saved if an Assignment that is already published is planned on that day
			if (1 === $blockPlannedSettingValue && !empty($plannedAssignments) && $published) {
				$this->context->buildViolation($constraint->message)
					->setParameters(array(
						'{{ errorMessage }}' => 'An Assignment already planned on '
							.$registerStartDate->format(Constants::DateFormatString)
							.' for this employee',
					))
					->addViolation();
			}

			// If DOR_SCHEDULE_AVAILABILITY_BLOCK_PLANNED setting is 2 no availability Registers can be saved if an Assignment is planned for that day regardless if its published or not
			if (2 === $blockPlannedSettingValue && !empty($plannedAssignments)) {
				$this->context->buildViolation($constraint->message)
					->setParameters(array(
						'{{ errorMessage }}' => 'An Assignment already planned on '
							.$registerStartDate->format(Constants::DateFormatString)
							.' for this employee',
					))
					->addViolation();
			}
		}
	}
}
