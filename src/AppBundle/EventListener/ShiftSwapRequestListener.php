<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Client;
use AppBundle\Entity\Employee;
use AppBundle\Entity\ShiftSwapRequest;
use AppBundle\Enumerator\SerializerGroup;
use AppBundle\Enumerator\ShiftSwapRequestRequestRole;
use AppBundle\Util\Constants;
use DateTime;
use Doctrine\ORM\Mapping\PrePersist;
use Doctrine\ORM\Mapping\PreUpdate;
use Doctrine\ORM\Mapping\PostLoad;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface as TokenStorage;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Entity listener for ShiftSwapRequest.
 */
class ShiftSwapRequestListener
{
	/** @var TokenStorage */
	private $tokenStorage;

	/** @var SerializerInterface */
	private $serializerService;

	/** Set the date interval spec string for the expireDate calculation here */
	const EXPIRE_DURATION = 'P1Y';

	/**
	 * ShiftSwapRequestListener constructor.
	 *
	 * @param TokenStorage        $tokenStorage
	 * @param SerializerInterface $serializerService
	 */
	public function __construct(TokenStorage $tokenStorage, SerializerInterface $serializerService)
	{
		$this->tokenStorage = $tokenStorage;
		$this->serializerService = $serializerService;
	}

	/**
	 * Set applicant to the employee associated with the current authenticated client.
	 *
	 * @PrePersist
	 *
	 * @param ShiftSwapRequest   $shiftSwapRequest
	 * @param LifecycleEventArgs $event
	 */
	public function prePersistSetApplicant(ShiftSwapRequest $shiftSwapRequest, LifecycleEventArgs $event)
	{
		$employee = null;
		$user = $this->tokenStorage->getToken()->getUser();
		if ($user) {
			$client = $event->getEntityManager()->find(Client::class, $user->getId());
			$employee = $client->getEmployee();
		}

		$shiftSwapRequest->setApplicant($employee);
	}

	// TODO Delete this after DB switch

	/**
	 * Set department name to department name associated with assignment.
	 *
	 * @PrePersist
	 * @PreUpdate
	 *
	 * @param ShiftSwapRequest   $shiftSwapRequest
	 * @param LifecycleEventArgs $event
	 */
	public function prePersistSetDepartmentName(ShiftSwapRequest $shiftSwapRequest, LifecycleEventArgs $event)
	{
		$assignment = $shiftSwapRequest->getAssignment();
		$departmentName = null;
		if ($assignment) {
			$departmentName = $assignment->getDepartment()->getName();
		}
		$shiftSwapRequest->setDepartmentName($departmentName);
	}

	/**
	 * Set expireDate to hashDuration plus current date.
	 *
	 * @PrePersist
	 * @PreUpdate
	 *
	 * @param ShiftSwapRequest   $shiftSwapRequest
	 * @param LifecycleEventArgs $event
	 */
	public function prePersistSetExpireDate(ShiftSwapRequest $shiftSwapRequest, LifecycleEventArgs $event)
	{
		// Must use this instead of new DateTime() to be able to mock current time in phpunit tests
		$currentDate = DateTime::createFromFormat('U', time());
		$duration = $currentDate->add(new \DateInterval(self::EXPIRE_DURATION));
		$shiftSwapRequest->setExpireDate($duration);
	}

	/**
	 * Set startDate and endDate to that of the associated Assignment.
	 *
	 * @PrePersist
	 * @PreUpdate
	 *
	 * @param ShiftSwapRequest   $shiftSwapRequest
	 * @param LifecycleEventArgs $event
	 */
	public function prePersistSetStartDateEndDate(ShiftSwapRequest $shiftSwapRequest, LifecycleEventArgs $event)
	{
		$assignment = $shiftSwapRequest->getAssignment();
		$startDate = null;
		$endDate = null;

		if ($assignment) {
			$startDate = $assignment->getStartDate();
			$endDate = $assignment->getEndDate();
		}
		$shiftSwapRequest->setStartDate($startDate);
		$shiftSwapRequest->setEndDate($endDate);
	}

	/**
	 * Create hash from serialized SwiftSwapRequest object and set it to hash
	 * NOTE: place this at the bottom of this file.
	 *
	 * @PrePersist
	 * @PreUpdate
	 *
	 * @param ShiftSwapRequest   $shiftSwapRequest
	 * @param LifecycleEventArgs $event
	 */
	public function prePersistSetHash(ShiftSwapRequest $shiftSwapRequest, LifecycleEventArgs $event)
	{
		$serializedShiftSwapRequest = $this->serializerService->serialize($shiftSwapRequest, Constants::JSON_SERIALIZATON_FORMAT, array(
			'groups' => array(
				SerializerGroup::SHIFT_SWAP_REQUESTS,
			),
		));

		$hash = sha1($serializedShiftSwapRequest);
		$shiftSwapRequest->setHash($hash);
	}

	/**
	 * TODO Temporary virtual property to get related planner(Employee) because in DB 0 values exist and therefore invalid foreignkey constraint
	 * TODO Remove this and use the original $planner instead after DB switch.
	 *
	 * @PostLoad
	 *
	 * @param ShiftSwapRequest   $shiftSwapRequest
	 * @param LifecycleEventArgs $event
	 */
	public function postLoadSetModifiedBy(ShiftSwapRequest $shiftSwapRequest, LifecycleEventArgs $event)
	{
		if (0 === $shiftSwapRequest->getPlannerId()) {
			$shiftSwapRequest->setPlanner(null);
		}
	}

	/**
	 * Determine from whom the request is coming from (applicant|receiver|planner).
	 *
	 * @PostLoad
	 *
	 * @param ShiftSwapRequest   $shiftSwapRequest
	 * @param LifecycleEventArgs $event
	 */
	public function postLoadSetCurrentRole(ShiftSwapRequest $shiftSwapRequest, LifecycleEventArgs $event)
	{
		/** @var Employee $employee */
		$employee = null;
		$client = $this->tokenStorage->getToken() ? $this->tokenStorage->getToken()->getUser() : null;
		if ($client) {
			$client = $event->getEntityManager()->find(Client::class, $client->getId());
			$employee = $client->getEmployee();
		}

		if ($employee) {
			// Determine if the request is coming from the applicant or the receiver
			if ($shiftSwapRequest->getApplicant()->getId() === $employee->getId()) {
				$shiftSwapRequest->setCurrentRole(ShiftSwapRequestRequestRole::APPLICANT);
			} elseif ($shiftSwapRequest->getReceiver()->getId() === $employee->getId()) {
				$shiftSwapRequest->setCurrentRole(ShiftSwapRequestRequestRole::RECEIVER);
			} elseif ($shiftSwapRequest->getPlanner() && $shiftSwapRequest->getPlanner()->getId() === $employee->getId()) {
				$shiftSwapRequest->setCurrentRole(ShiftSwapRequestRequestRole::PLANNER);
			}
		}
	}
}
