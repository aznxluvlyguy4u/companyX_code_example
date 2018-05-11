<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Department;
use AppBundle\Entity\SystemConfig;
use AppBundle\Enumerator\SystemConfigKey;
use AppBundle\Repository\SystemConfigRepository;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\PostLoad;

/**
 * Entity listener for Department.
 */
class DepartmentListener
{
	//TODO This listener is actually applicable for Office entity. But since Office currently doesn't have its own table, we listen to Department instead. Fix during DB switch

	/**
	 * Determine if Department/Office is a headquarter and set isHeadquarter.
	 *
	 * @PostLoad
	 *
	 * @param Department         $department
	 * @param LifecycleEventArgs $event
	 */
	public function postLoadSetIsHeadquarter(Department $department, LifecycleEventArgs $event)
	{
		// Check if DOR_HEADQUARTER_ID is set
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = $event->getEntityManager()->getRepository(SystemConfig::class);
		/** @var SystemConfig $dorHeadquarterId */
		$dorHeadquarterId = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_HEADQUARTER_ID);
		$headquarterOfficeId = $dorHeadquarterId ? $dorHeadquarterId->getNormalizedValue() : null;

		// If departments id equals id set in DOR_HEADQUARTER_ID and if the department doesn't have any children
		if ($department->getId() === $headquarterOfficeId && $department->getChildren()->isEmpty()) {
			$department->setIsHeadquarter(true);
		}
	}
}
