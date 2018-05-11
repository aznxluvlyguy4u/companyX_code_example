<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Client;
use AppBundle\Entity\SystemConfig;
use AppBundle\Entity\UserRole;
use AppBundle\Enumerator\CompanyXUserRole;
use AppBundle\Repository\ClientRepository;
use AppBundle\Repository\SystemConfigRepository;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\PostLoad;

/**
 * Entity listener for Client.
 */
class ClientListener
{
	/** @var array */
	private $roleHierarchy;

	/**
	 * ClientListener constructor.
	 *
	 * @param $roleHierarchy
	 */
	public function __construct($roleHierarchy)
	{
		$this->roleHierarchy = $roleHierarchy;
	}

	/**
	 * @PostLoad
	 *
	 * @param Client             $client
	 * @param LifecycleEventArgs $event
	 */
	public function postLoadSetRoles(Client $client, LifecycleEventArgs $event)
	{
		$userRoles = $client->getUserRoles()->toArray();
		$convertedRolesArray = [];
		$rolesArray = $client->getUserRolesMatchTable();

		foreach ($userRoles as $userRole) {
			/* @var UserRole $userRole */
			if (isset($rolesArray[$userRole->getName()])) {
				$convertedRolesArray[] = $rolesArray[$userRole->getName()];
			}
		}

		/**
		 * TODO Currently access to the Telephonelist is determined by a very complex and outdated legacy
		 * TODO code to see rather or not the route dashboard/telefoonlijst or btools/telefoonlijst is in the
		 * TODO allowlist of the authenticated Client. We translate that here in the ROLE_TELEPHONELIST_ACCESS
		 * TODO to keep the consistency throughout the Symfony application.
		 * TODO Look for a permanent solution to fix roles once and for all.
		 */
		// Manually add the role ROLE_TELEPHONELIST_ACCESS if the current Client is allowed to access it
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = $event->getEntityManager()->getRepository(SystemConfig::class);
		$isTelephoneListHidden = $systemConfigRepo->isTelephoneListHidden();
		if (!$isTelephoneListHidden) {
			$convertedRolesArray[] = CompanyXUserRole::TELEPHONELIST_ACCESS;
		}

		// Hardcode if client id is 1, add the role ROLE_SUPER_USER to the client
		if (1 === $client->getId()) {
			$convertedRolesArray[] = CompanyXUserRole::SUPER_USER;
		}

		// Load the hierarchy roles as well
		foreach ($convertedRolesArray as $role) {
			if (!isset($this->roleHierarchy[$role])) {
				continue;
			}

			$inheritedRoles = $this->roleHierarchy[$role];
			foreach ($inheritedRoles as $inheritedRole) {
				if (!in_array($inheritedRole, $convertedRolesArray)) {
					$convertedRolesArray[] = $inheritedRole;
				}
			}
		}

		$client->setRoles($convertedRolesArray);
	}

	//TODO Temporary virtual property to show related employees because in DB 0 values exist and therefore invalid foreignkey constraint
	//TODO Remove this and use the original $employee instead after DB switch

	/**
	 * @PostLoad
	 *
	 * @param Client             $client
	 * @param LifecycleEventArgs $event
	 */
	public function postLoadSetAccessControlList(Client $client, LifecycleEventArgs $event)
	{
		/** @var ClientRepository $clientRepository */
		$clientRepository = $event->getEntityManager()->getRepository(Client::class);
		$accessControlList = $clientRepository->loadAccessControlList($client);
		$client->setAccessControlList($accessControlList);
	}
}
