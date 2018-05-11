<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Assignment;
use AppBundle\Entity\Client;
use AppBundle\Entity\SystemConfig;
use AppBundle\Enumerator\CompanyXUserRole;
use AppBundle\Repository\SystemConfigRepository;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\PostLoad;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Entity listener for Assignment.
 */
class AssignmentListener
{
	/**
	 * @var TokenStorageInterface
	 */
	private $tokenStorage;

	/**
	 * AssignmentListener constructor.
	 *
	 * @param TokenStorageInterface $tokenStorage
	 */
	public function __construct(TokenStorageInterface $tokenStorage)
	{
		$this->tokenStorage = $tokenStorage;
	}

	//TODO Temporary virtual property to show related employees because in DB 0 values exist and therefore invalid foreignkey constraint
	//TODO Remove this and use the original $employee instead after DB switch

	/**
	 * @PostLoad
	 *
	 * @param Assignment         $assignment
	 * @param LifecycleEventArgs $event
	 */
	public function postLoadSetEmployee(Assignment $assignment, LifecycleEventArgs $event)
	{
		if (0 === $assignment->getEmployeeId()) {
			$assignment->setEmployee(null);
		}
	}

	/**
	 * Show breaks in assignment output when:
	 *    - System config is not configured to hide breaks or
	 *    - User is authenticated and role allows it:
	 *        - ALL_RIGHTS or
	 *        - ADMINISTRATORS and HOURS_REGISTER.
	 *
	 * @PostLoad
	 *
	 * @param Assignment         $assignment
	 * @param LifecycleEventArgs $event
	 *
	 * @throws \Doctrine\ORM\ORMException
	 * @throws \Doctrine\ORM\OptimisticLockException
	 * @throws \Doctrine\ORM\TransactionRequiredException
	 */
	public function postLoadHideBreak(Assignment $assignment, LifecycleEventArgs $event)
	{
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = $event->getEntityManager()->getRepository(SystemConfig::class);
		$showBreaksInSchedule = $systemConfigRepo->showBreaksInSchedule();
		$client = null;
		$user = ($this->tokenStorage->getToken()) ? $this->tokenStorage->getToken()->getUser() : null;
		if ($user) {
			/** @var Client $client */
			$client = $event->getEntityManager()->find(Client::class, $user->getId());
		}

		if ($showBreaksInSchedule || ($client && $client->hasRole(CompanyXUserRole::ADMINISTRATORS))) {
			$assignment->setBreakDuration($assignment->getOriginalBreakDuration());
		}
	}
}
