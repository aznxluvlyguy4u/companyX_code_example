<?php

namespace AppBundle\Repository;

use AppBundle\Entity\AccessControlItem;
use AppBundle\Entity\Client;
use AppBundle\Entity\UserRole;
use Doctrine\ORM\EntityRepository;

/**
 * AccessControlItemRepository.
 */
class AccessControlItemRepository extends EntityRepository
{
	/**
	 * @param Client $client
	 * @param string $role
	 *
	 * @return AccessControlItem[]
	 */
	public function findByClientAndRole(Client $client, $role)
	{
		//Prepare client roles
		$matchTable = array_flip($client->getUserRolesMatchTable());
		$fullRoleName = $matchTable[$role];

		$qb = $this->getEntityManager()->createQueryBuilder();
		$qb
			->select('accessControlItem')
			->from(AccessControlItem::class, 'accessControlItem')
			->leftJoin('accessControlItem.department', 'department')
			->leftJoin(UserRole::class, 'userRole', 'WITH',
				$qb->expr()->eq('accessControlItem.userRoleIdentifierString', 'userRole.identifierString')
			)
			->where($qb->expr()->eq('accessControlItem.employee', $client->getEmployee()->getId()))
			->andWhere($qb->expr()->like('userRole.name', ':fullRoleName'))
			->setParameter('fullRoleName', $fullRoleName);

		$result = $qb->getQuery()->getResult();

		return $result;
	}
}
