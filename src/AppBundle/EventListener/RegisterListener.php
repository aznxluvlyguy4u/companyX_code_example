<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Client;
use AppBundle\Entity\Employee;
use AppBundle\Entity\SystemConfig;
use AppBundle\Enumerator\SystemConfigKey;
use AppBundle\Util\Constants;
use Doctrine\ORM\Event\LifecycleEventArgs;
use AppBundle\Entity\Register;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Mapping\PrePersist;
use Doctrine\ORM\Mapping\PreUpdate;
use Doctrine\ORM\Mapping\PostLoad;
use Doctrine\ORM\Mapping\PreFlush;
use AppBundle\Entity\Department;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Entity listener for Register.
 */
class RegisterListener
{
	/** @var TokenStorageInterface */
	private $tokenStorage;

	const WORK = 'WORK';

	const AVAILABLE = 'AVAILABLE';

	const UNAVAILABLE = 'UNAVAILABLE';

	const VACATION = 'VACATION';

	const PREFERENCE = 'PREFERENCE';

	/**
	 * RegisterListener constructor.
	 *
	 * @param TokenStorageInterface $tokenStorage
	 */
	public function __construct(TokenStorageInterface $tokenStorage)
	{
		$this->tokenStorage = $tokenStorage;
	}

	/**
	 * Set remark to precompiled string just like in the old CompanyX app according to register type.
	 *
	 * @PrePersist
	 * @PreUpdate
	 *
	 * @param Register           $register
	 * @param LifecycleEventArgs $event
	 */
	public function prePersistSetRemark(Register $register, LifecycleEventArgs $event)
	{
		$type = $register->getType();
		$assignment = $register->getAssignment();
		if (self::PREFERENCE === $type && $assignment) {
			$departmentName = $assignment->getDepartment()->getName();
			$startTime = $register->getStartDate()->format(Constants::HOURS_MINUTES_FORMAT_STRING);
			// Compile remark string just like the CompanyX App
			$newRemark = $departmentName.' '.$startTime.';';
			$register->setRemark($newRemark);
		}
	}

	/**
	 * TODO Reevaluate if this is still necessary during DB switch
	 * Set modifiedBy by current logged in client.
	 *
	 * @PrePersist
	 * @PreUpdate
	 *
	 * @param Register           $register
	 * @param LifecycleEventArgs $event
	 */
	public function prePersistSetModifiedBy(Register $register, LifecycleEventArgs $event)
	{
		$client = null;
		$user = $this->tokenStorage->getToken()->getUser();
		if ($user) {
			$client = $event->getEntityManager()->find(Client::class, $user->getId());
		}

		// TODO TEST and see if set admin_id to null gives conflict with current CompanyX code base during live testing
		switch ($register->getType()) {
			case self::WORK:
				$register->setModifiedBy(null);
				break;
			case self::AVAILABLE:
				$register->setModifiedBy($client);
				break;
			case self::UNAVAILABLE:
				$register->setModifiedBy($client);
				break;
			case self::VACATION:
				$register->setModifiedBy(null);
				break;
			default:
				$register->setModifiedBy(null);
		}
	}

	/**
	 * TODO Temporary virtual property to get related modifiedBy(client) because in DB 0 values exist and therefore invalid foreignkey constraint
	 * TODO Remove this and use the original $modifiedBy instead after DB switch.
	 *
	 * @PostLoad
	 *
	 * @param Register           $register
	 * @param LifecycleEventArgs $event
	 */
	public function postLoadSetModifiedBy(Register $register, LifecycleEventArgs $event)
	{
		if (0 === $register->getModifiedById()) {
			$register->setModifiedBy(null);
		}
	}

	/**
	 * TODO Reevaluate if this is still necessary during DB switch
	 * Set Client to the modified employee's associated client id.
	 *
	 * @PrePersist
	 * @PreUpdate
	 *
	 * @param Register           $register
	 * @param LifecycleEventArgs $event
	 */
	public function prePersistSetClient(Register $register, LifecycleEventArgs $event)
	{
		// If employee is set and if employee has a login (Client), set the Client to Register
		if ($register->getEmployee()) {
			$employee = $event->getEntityManager()->find(Employee::class, $register->getEmployee()->getId());
			// TODO client defaults at 0 in CompanyX DB, see if this mut be changed
			$client = null;
			if ($employee && $employee->getClient()) {
				$client = $employee->getClient();
			}
			$register->setClient($client);
		}
	}

	/**
	 * TODO Reevaluate if this is still necessary during DB switch
	 * Set sessionId of current logged in client.
	 *
	 * @PrePersist
	 * @PreUpdate
	 *
	 * @param Register           $register
	 * @param LifecycleEventArgs $event
	 */
	public function prePersistSetSessionId(Register $register, LifecycleEventArgs $event)
	{
		$sessionId = null;
		$user = $this->tokenStorage->getToken()->getUser();
		if ($user) {
			$sessionId = $user->getSessionId();
		}
		$register->setSessionId($sessionId);
	}

	/**
	 * TODO Reevaluate if this is still necessary during DB switch
	 * Convert location string and activity string by department ID.
	 *
	 * @PrePersist
	 * @PreUpdate
	 *
	 * @param Register           $register
	 * @param LifecycleEventArgs $event
	 */
	public function prePersistSetLocationActivity(Register $register, LifecycleEventArgs $event)
	{
		// TODO TEST and see if set Department to null gives conflict with current CompanyX code base during live testing
		if ($register->getDepartment() && self::WORK === $register->getType()) {
			/** @var Department $department */
			$department = $event->getEntityManager()->find(Department::class, $register->getDepartment()->getId());
			if ($department) {
				$departmentParent = $department->getParent();
				$locationString = $department->getName();
				while ($departmentParent) {
					$locationString = $departmentParent->getName().' > '.$locationString;
					$departmentParent = $departmentParent->getParent();
				}
				$register->setLocation($locationString);
				$register->setActivity($locationString);
			}
		} else {
			$register->setDepartment(null);
			$register->setLocation(null);
			$register->setActivity(null);
		}
	}

	/**
	 * TODO Temporary virtual property to get related departments because in DB 0 values exist and therefore invalid foreignkey constraint
	 * TODO Remove this and use the original $department instead after DB switch.
	 *
	 * @PostLoad
	 *
	 * @param Register           $register
	 * @param LifecycleEventArgs $event
	 */
	public function postLoadSetDepartment(Register $register, LifecycleEventArgs $event)
	{
		if (0 === $register->getDepartmentId()) {
			$register->setDepartment(null);
		}
	}

	/**
	 * TODO Reevaluate if this is still necessary during DB switch as the property workDuration may be deleted
	 * Calculate Work Duration when register type is 'WORK'.
	 *
	 * @PrePersist
	 * @PreUpdate
	 *
	 * @param Register           $register
	 * @param LifecycleEventArgs $event
	 */
	public function prePersistSetWorkDuration(Register $register, LifecycleEventArgs $event)
	{
		// if type is 'WORK' calculate workDuration by subtracting endDate from startDate
		$workDuration = null;
		if ($register->getType() && self::WORK === $register->getType() && $register->getStartDate() && $register->getEndDate()) {
			$workDurationInterval = $register->getStartDate()->diff($register->getEndDate());
			// calculate decimal fraction hours, must be this format for current DB backward compatibility
			$workDuration = floatval($workDurationInterval->format('%h').'.'.($workDurationInterval->format('%i') / Constants::MINUTES_IN_AN_HOUR * 100));
		}
		$register->setWorkDuration($workDuration);
	}

	/**
	 * TODO Reevaluate if this is still necessary during DB switch as the property workDuration may be deleted
	 * Calculate Work Duration by substracting the Break Duration value when register type is 'WORK' and is send to the frontend.
	 *
	 * @PostLoad
	 *
	 * @param Register           $register
	 * @param LifecycleEventArgs $event
	 */
	public function postLoadSetCalculatedWorkDuration(Register $register, LifecycleEventArgs $event)
	{
		$calculatedWorkDuration = $register->getWorkDuration();
		// if type is 'WORK' calculate calculatedWorkDuration by subtracting workDuration saved in DB with breakDuration
		if ($register->getType() && self::WORK === $register->getType() && $register->getBreakDuration() && $register->getWorkDuration()) {
			// calculate decimal fraction hours, must be this format for current DB backward compatibility
			$calculatedWorkDuration = ($register->getWorkDuration() * Constants::MINUTES_IN_AN_HOUR - $register->getBreakDuration()) / Constants::MINUTES_IN_AN_HOUR;
		}
		$register->setCalculatedWorkDuration($calculatedWorkDuration);
	}

	/**
	 * TODO Temporary virtual property to show related assignments because in DB 0 values exist and therefore invalid foreignkey constraint
	 * TODO Remove this and use the original $assignment instead after DB switch.
	 *
	 * @PostLoad
	 *
	 * @param Register           $register
	 * @param LifecycleEventArgs $event
	 */
	public function postLoadSetAssignment(Register $register, LifecycleEventArgs $event)
	{
		if (0 === $register->getAssignmentId()) {
			$register->setAssignment(null);
		}
	}

	// TODO reevaluate the necessity of this postload setter if component meal can be stored as a seperate column

	/**
	 * Clear meal boolean value if DOR_REGISTRATION_COMPONENT_MEALCHECKBOX is set to false.
	 *
	 * @PrePersist
	 * @PreUpdate
	 *
	 * @param Register           $register
	 * @param LifecycleEventArgs $event
	 */
	public function prePersistSetMeal(Register $register, LifecycleEventArgs $event)
	{
		// Check if DOR_REGISTRATION_COMPONENT_MEALCHECKBOX is set to true
		$systemConfigRepo = $event->getEntityManager()->getRepository(SystemConfig::class);
		/** @var SystemConfig $dorRegistrationComponentMealCheckbox */
		$dorRegistrationComponentMealCheckbox = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_REGISTRATION_COMPONENT_MEALCHECKBOX);
		$addMealCheckbox = $dorRegistrationComponentMealCheckbox ? $dorRegistrationComponentMealCheckbox->getNormalizedValue() : false;
		$meal = $register->getMeal();

		if (!$addMealCheckbox && isset($meal)) {
			$register->setMeal(null);
		}
	}

	// TODO reevaluate the necessity of this postload setter if component meal can be stored as a seperate column

	/**
	 * Read and convert the features value to componentMealCheckbox boolean value if DOR_REGISTRATION_COMPONENT_MEALCHECKBOX is set to true.
	 *
	 * @PostLoad
	 *
	 * @param Register           $register
	 * @param LifecycleEventArgs $event
	 */
	public function postLoadSetMeal(Register $register, LifecycleEventArgs $event)
	{
		// Check if DOR_REGISTRATION_COMPONENT_MEALCHECKBOX is set to true
		$systemConfigRepo = $event->getEntityManager()->getRepository(SystemConfig::class);
		/** @var SystemConfig $dorRegistrationComponentMealCheckbox */
		$dorRegistrationComponentMealCheckbox = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_REGISTRATION_COMPONENT_MEALCHECKBOX);
		$addMealCheckbox = $dorRegistrationComponentMealCheckbox ? $dorRegistrationComponentMealCheckbox->getNormalizedValue() : false;

		if ($addMealCheckbox) {
			$features = $register->getFeatures();
			if (0 === $features || null === $features) {
				$register->setMeal(false);
			} elseif (2 === $features) {
				$register->setMeal(true);
			}
		}
	}

	// TODO reevaluate the necessity of this postload setter if components can be stored as a seperate column

	/**
	 * Read and convert the meal boolean value to features integer value if DOR_REGISTRATION_COMPONENT_MEALCHECKBOX is set to true.
	 *
	 * @PrePersist
	 * @PreUpdate
	 *
	 * @param Register           $register
	 * @param LifecycleEventArgs $event
	 */
	public function prePersistSetFeatures(Register $register, LifecycleEventArgs $event)
	{
		// Check if DOR_REGISTRATION_COMPONENT_MEALCHECKBOX is set to true
		$systemConfigRepo = $event->getEntityManager()->getRepository(SystemConfig::class);
		/** @var SystemConfig $dorRegistrationComponentMealCheckbox */
		$dorRegistrationComponentMealCheckbox = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_REGISTRATION_COMPONENT_MEALCHECKBOX);
		$addMealCheckbox = $dorRegistrationComponentMealCheckbox ? $dorRegistrationComponentMealCheckbox->getNormalizedValue() : false;

		if ($addMealCheckbox) {
			$meal = $register->getMeal();
			if (true === $meal) {
				$register->setFeatures(2);
			} elseif (false === $meal) {
				$register->setFeatures(null);
			}
		}
	}

	/**
	 * PreFlush set remark to Original remark to save to DB.
	 *
	 * @PreFlush
	 *
	 * @param Register          $register
	 * @param PreFlushEventArgs $event
	 */
	public function preFlushSetOriginalRemark(Register $register, PreFlushEventArgs $event)
	{
		if ($register->getRemark()) {
			$register->setOriginalRemark($register->getRemark());
		}
	}

	/**
	 * PostLoad show or hide Original remark in remark depending on if Client has role HOURS_REGISTER.
	 *
	 * @PostLoad
	 *
	 * @param Register           $register
	 * @param LifecycleEventArgs $event
	 */
	public function postLoadSetRemark(Register $register, LifecycleEventArgs $event)
	{
		if ($this->tokenStorage->getToken()) {
			/** @var Client $client */
			$client = $this->tokenStorage->getToken()->getUser();
			// Set Original Remark value to Remark that passed to the Serializer/Response
			if ($client->canRegisterForOtherEmployees() && $register->getOriginalRemark()) {
				$register->setRemark($register->getOriginalRemark());
			}
		}
	}
}
