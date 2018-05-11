<?php

namespace AppBundle\Validator\Constraints\RegisterConstraints;

use AppBundle\Entity\Register;
use Codeception\Util\JsonArray;
use Doctrine\ORM\EntityManager;
use DoctrineExtensions\Query\Mysql\Date;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface as TokenStorage;
use AppBundle\Entity\SystemConfig;
use AppBundle\Enumerator\SystemConfigKey;
use AppBundle\Util\Constants;

/**
 * Class VacationTimeoutValidator
 * Validator for Class VacationTimeout.
 */
class VacationTimeoutValidator extends ConstraintValidator
{
	/**
	 * @var EntityManager
	 */
	private $em;

	/** @var string */
	const VACATION = 'VACATION';

	/** @var string */
	const UNAVAILABLE = 'UNAVAILABLE';

	/**
	 * VacationTimeoutValidator constructor.
	 *
	 * @param EntityManager $em
	 * @param TokenStorage  $tokenStorage
	 */
	public function __construct(EntityManager $em, TokenStorage $tokenStorage)
	{
		$this->em = $em;
	}

	/**
	 * @param Register   $register
	 * @param Constraint $constraint
	 */
	public function validate($register, Constraint $constraint)
	{
		$systemConfigRepo = $this->em->getRepository(SystemConfig::class);
		/** @var SystemConfig $dorScheduleUseVacationTimeoutForUnavailability */
		$dorScheduleUseVacationTimeoutForUnavailability = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_SCHEDULE_USE_VACATION_TIMEOUT_FOR_UNAVAILABILITY);
		$alsoApplyForUnavailable = $dorScheduleUseVacationTimeoutForUnavailability ? $dorScheduleUseVacationTimeoutForUnavailability->getNormalizedValue() : false;
		$registerType = $register->getType();

		if ((self::VACATION === $registerType || ($alsoApplyForUnavailable && self::UNAVAILABLE === $registerType))) {
			$timeoutDate = $systemConfigRepo->getVacationTimeoutValue();

			/** @var JsonArray $timeoutDate */
			$type = array_keys($timeoutDate)[0];
			$timeoutDate = strtotime('+'.$timeoutDate[$type].' '.$type);
			$timeoutDate = date(Constants::DateFormatString, $timeoutDate);

			$registerStartDate = $register->getStartDate()->format(Constants::DateFormatString);
			$unavailableOption = $alsoApplyForUnavailable ? 'and UNAVAILABLE ' : '';

			if ($registerStartDate < $timeoutDate) {
				$this->context->buildViolation($constraint->message)
					->setParameters(array(
						'{{ startDate }}' => $registerStartDate,
						'{{ timeoutDate }}' => $timeoutDate,
						'{{ unavailableOption }}' => $unavailableOption,
					))
					->addViolation();
			}
		}
	}
}
