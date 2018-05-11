<?php

namespace AppBundle\Repository;

use AppBundle\Entity\Client;
use AppBundle\Entity\Office;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Class ClientRepository.
 */
class ClientRepository extends EntityRepository implements UserLoaderInterface
{
	/**
	 * @param Client  $client
	 * @param Request $request
	 *
	 * @return array|null
	 */
	public function canAccess(Client $client, Request $request)
	{
		$conn = $this->getEntityManager()->getConnection();

		// TODO: Maybe don't use LIKE to match requestedUri
		$sql = /* @lang text */
			 'SELECT *
        FROM auth_users_groups_perms
          INNER JOIN auth_permissions
            ON auth_users_groups_perms.perm_id = auth_permissions.id
         JOIN auth_page_perm
            ON auth_permissions.id = auth_page_perm.perm_id
        WHERE auth_users_groups_perms.foreign_key = :user_id
          AND auth_page_perm.shpage_mask LIKE :requestedUri';

		$requestedUri = $request->getRequestUri();
		$querystring = $request->getQueryString();

		$requestedUri = str_replace($querystring, '', $requestedUri);
		$requestedUri = str_replace('?', '', $requestedUri);

		$statement = $conn->prepare($sql);
		$statement->bindValue('user_id', $client->getId());
		$statement->bindValue('requestedUri', '%'.$requestedUri.'%');
		$statement->execute();
		$result = $statement->fetchAll();

		if (!$result) {
			return null;
		}

		return $result;
	}

	/**
	 * Loads the user for the given username.
	 *
	 * This method must return null if the user is not found.
	 *
	 * @param string $username The username
	 *
	 * @return UserInterface|null
	 */
	public function loadUserByUsername($username)
	{
		$client = $this->findOneByUsername($username);

		return $client;
	}

	/**
	 * Loads the accessControlList for the given client.
	 *
	 * @param Client $client
	 * @param bool   $tree
	 *
	 * @return array
	 */
	public function loadAccessControlList(Client $client, $tree = false)
	{
		//Prepare client roles
		$roles = $client->getRoles();

		$accessControlList = array(
			'read' => array(),
			'write' => array(),
		);

		/** @var OfficeRepository $officeRepository */
		$officeRepository = $this->getEntityManager()->getRepository(Office::class);

		if ($tree) {
			$readAccessOfficesTree = $officeRepository->findReadAccessOfficesTreeByEmployee($client->getEmployee()->getId());
		} else {
			$readAccessOfficesTree = $officeRepository->findFlattenReadAccessDepartmentsByEmployee($client->getEmployee()->getId());
		}

		$accessControlList['read'] = $readAccessOfficesTree;

		//For each role that has an associated access control list, find and attach the allowed departments
		foreach ($roles as $role) {
			if ($tree) {
				$writeAccessOfficesTree = $officeRepository->findWriteAccessOfficesTreeByClientAndRole($client, $role);
			} else {
				$writeAccessOfficesTree = $officeRepository->findFlattenWriteAccessDepartmentsByClientAndRole($client, $role);
			}

			$accessControlList['write'][$role] = $writeAccessOfficesTree;
		}

		return $accessControlList;
	}
}
