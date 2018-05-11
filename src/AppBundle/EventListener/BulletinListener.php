<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Bulletin;
use AppBundle\Entity\Client;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\PostLoad;
use AppBundle\Util\Constants;

/**
 * Entity listener for Bulletin.
 */
class BulletinListener
{
	/**
	 * Convert meta data to virtual property.
	 *
	 * @PostLoad
	 *
	 * @param Bulletin           $bulletin
	 * @param LifecycleEventArgs $event
	 */
	public function postLoadSetVirtualProperties(Bulletin $bulletin, LifecycleEventArgs $event)
	{
		$metaData = unserialize($bulletin->getMetaData());
		$clientId = $metaData['author_id'] ?? null;
		$remark = $metaData['description_planning'] ?? null;

		$client = isset($clientId) ? $event->getEntityManager()->find(Client::class, $clientId) : null;

		$bulletin->setModifiedBy($client);
		$bulletin->setRemark($remark);
	}

	/**
	 * Set endDate to 23:59:59 if startDate and endDate = 00:00:00 or startDate = 00:00:00 and endDate = 23:59:00.
	 *
	 * @PostLoad
	 *
	 * @param Bulletin           $bulletin
	 * @param LifecycleEventArgs $event
	 */
	public function postLoadSetEndDate(Bulletin $bulletin, LifecycleEventArgs $event)
	{
		$startDate = $bulletin->getStartDate();
		$endDate = $bulletin->getEndDate();

		if ($startDate == $endDate && '00:00:00' === $startDate->format(Constants::HOURS_MINUTES_SECONDS_FORMAT_STRING)) {
			$bulletin->setEndDate($endDate->add(new \DateInterval('PT23H59M59S')));
		}

		if ('00:00:00' === $startDate->format(Constants::HOURS_MINUTES_SECONDS_FORMAT_STRING) && '23:59:00' === $endDate->format(Constants::HOURS_MINUTES_SECONDS_FORMAT_STRING)) {
			$bulletin->setEndDate($endDate->add(new \DateInterval('PT59S')));
		}
	}
}
