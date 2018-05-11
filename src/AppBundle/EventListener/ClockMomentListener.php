<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Client;
use AppBundle\Entity\ClockInterval;
use AppBundle\Entity\ClockMoment;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\PrePersist;
use Doctrine\ORM\Mapping\PreUpdate;
use Doctrine\ORM\Mapping\PostLoad;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Entity listener for ClockMoment.
 */
class ClockMomentListener
{
	/**
	 * @var TokenStorageInterface
	 */
	private $tokenStorage;

	/**
	 * ClockMomentListener constructor.
	 *
	 * @param TokenStorageInterface $tokenStorage
	 */
	public function __construct(TokenStorageInterface $tokenStorage)
	{
		$this->tokenStorage = $tokenStorage;
	}

	/**
	 * Set modifiedBy to current authenticated in client.
	 *
	 * @PrePersist
	 * @PreUpdate
	 *
	 * @param ClockMoment        $clockMoment
	 * @param LifecycleEventArgs $event
	 */
	public function prePersistSetModifiedBy(ClockMoment $clockMoment, LifecycleEventArgs $event)
	{
		$client = null;
		$user = $this->tokenStorage->getToken()->getUser();
		if ($user) {
			$client = $event->getEntityManager()->find(Client::class, $user->getId());
		}

		$clockMoment->setModifiedBy($client);
	}

	//    /**
	//     * Create a new ClockInterval and set clockInterval reference to its ID
	//     * @PrePersist
	//     *
	//     * @param ClockMoment $clockMoment
	//     * @param LifecycleEventArgs $event
	//     */
	//    public function prePersistCreateAndSetClockInterval(ClockMoment $clockMoment, LifecycleEventArgs $event)
	//    {
	//        $em = $event->getEntityManager();
	//        /** @var ClockMomentRepository $clockMomentRepo */
	//        $clockMomentRepo = $em->getRepository(ClockMoment::class);
//
	//        $lastClockMoment = $clockMomentRepo->findLastTwoClockMomentsOfEmployee($clockMoment->getEmployee()->getId());
	//        dump($lastClockMoment);die();
//
//
	//        if ($clockMoment->getStatus() === ClockMomentStatus::CHECK_IN) {
//
	//            $clockInterval = new ClockInterval();
	//            $clockInterval->setDate($clockMoment->getTimeStamp());
	//            $clockInterval->setEmployee($clockMoment->getEmployee());
	//            $clockInterval->setStartDate($clockMoment->getTimeStamp());
	//            $clockInterval->setEndDate($clockMoment->getTimeStamp());
	//            $clockInterval->setProblem(true);
	//            $clockInterval->setDepartment($clockMoment->getDepartment());
	//        }
	//    }

	/**
	 * TODO Temporary virtual property to get related clockInterval because in DB 0 values exist and therefore invalid foreignkey constraint
	 * TODO Remove this and use the original $clockInterval instead after DB switch.
	 *
	 * @PostLoad
	 *
	 * @param ClockMoment        $clockMoment
	 * @param LifecycleEventArgs $event
	 */
	public function postLoadSetClockInterval(ClockMoment $clockMoment, LifecycleEventArgs $event)
	{
		if ($clockMoment->getClockIntervalId()) {
			$event->getEntityManager()->detach($clockMoment->getClockInterval());
			$clockIntervalRepo = $event->getEntityManager()->getRepository(ClockInterval::class);
			$existingClockInterval = $clockIntervalRepo->find($clockMoment->getClockIntervalId());
			if (!$existingClockInterval) {
				$clockMoment->setClockInterval(null);
			}
		}
	}

	/**
	 * TODO Temporary virtual property to get related registers because in DB 0 values exist and therefore invalid foreignkey constraint
	 * TODO Remove this and use the original $register instead after DB switch.
	 *
	 * @PostLoad
	 *
	 * @param ClockMoment        $clockMoment
	 * @param LifecycleEventArgs $event
	 */
	public function postLoadSetRegister(ClockMoment $clockMoment, LifecycleEventArgs $event)
	{
		if (0 === $clockMoment->getRegisterId()) {
			$clockMoment->setRegister(null);
		}
	}

	/**
	 * TODO Temporary virtual property to get related departments because in DB 0 values exist and therefore invalid foreignkey constraint
	 * TODO Remove this and use the original $department instead after DB switch.
	 *
	 * @PostLoad
	 *
	 * @param ClockMoment        $clockMoment
	 * @param LifecycleEventArgs $event
	 */
	public function postLoadSetDepartment(ClockMoment $clockMoment, LifecycleEventArgs $event)
	{
		if (0 === $clockMoment->getDepartmentId()) {
			$clockMoment->setDepartment(null);
		}
	}
}
