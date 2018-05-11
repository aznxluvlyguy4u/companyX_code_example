<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\AccessControlItem;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\PostLoad;

/**
 * Entity listener for AccessControlItem.
 */
class AccessControlItemListener
{
	//TODO Temporary virtual property to show related department because in DB 0 and * values exist and therefore invalid foreignkey constraint
	//TODO Remove this and use the original $department instead after DB switch

	/**
	 * @PostLoad
	 *
	 * @param AccessControlItem  $accessControlItem
	 * @param LifecycleEventArgs $event
	 */
	public function postLoadSetDepartment(AccessControlItem $accessControlItem, LifecycleEventArgs $event)
	{
		if ('*' === $accessControlItem->getDepartmentId()) {
			$accessControlItem->setDepartment(null);
		}
	}
}
