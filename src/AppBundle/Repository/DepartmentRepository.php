<?php

namespace AppBundle\Repository;

use AppBundle\Entity\AccessControlItem;
use AppBundle\Entity\Client;
use AppBundle\Entity\Department;
use AppBundle\Entity\Office;
use AppBundle\Entity\SystemConfig;
use AppBundle\Entity\UserRole;
use AppBundle\Enumerator\CompanyXUserRole;
use AppBundle\Enumerator\SystemConfigKey;
use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;

/**
 * Class DepartmentRepository.
 */
class DepartmentRepository extends NestedTreeRepository
{
	// TODO maybe no longer needed during DB switch with seperated Office and Department
	// TODO use find() instead

	/**
	 * Fond one REAL department by ID.
	 *
	 * @param $departmentId
	 *
	 * @return mixed
	 */
	public function findOneChildDepartment($departmentId)
	{
		$meta = $this->getClassMetadata();
		$config = $this->listener->getConfiguration($this->_em, $meta->name);
		$qb = $this->getQueryBuilder();
		$qb
			->select('node')
			->from($config['useObjectClass'], 'node')
			->where(
				$qb->expr()->isNotNull('node.'.$config['parent']),
				$qb->expr()->eq('node.id', $departmentId)
			);
		$result = $qb->getQuery()->getOneOrNullResult();

		if (empty($result)) {
			return null;
		}

		return $result;
	}

	// TODO maybe no long needed during DB switch with real Office object
	// TODO use findBy() instead

	/**
	 * Find all departments in hierarchy tree structure of an Office by ID.
	 *
	 * @param $officeId
	 *
	 * @return array|null|string
	 */
	public function findByOffice($officeId)
	{
		$office = $this->getRootNode($officeId);

		// Top (company) has id 0, cant return a 404 for that reason.
		if (0 !== intval($officeId) && !$office) {
			return null;
		}

		// Check if office is a headquarter and set its children to all offices else its normal children
		if (0 === intval($officeId) || $office->getIsHeadquarter()) {
			$officeRepo = $this->getEntityManager()->getRepository(Office::class);
			$childrenOffices = $officeRepo->findAll();
			/** @var Office $childOffice */
			foreach ($childrenOffices as $childOffice) {
				if (!$childOffice->getIsHeadquarter()) {
					$childrenDepartments = $this->findByOffice($childOffice->getId());
					foreach ($childrenDepartments as $childDepartment) {
						$childOffice->addDepartment($childDepartment);
					}
				}
			}

			$departments = $childrenOffices;
		} else {
			$departments = $this->getChildren($office, true, 'name', 'ASC', false);
		}

		return $departments;
	}

	// TODO maybe no long needed during DB switch with real Office object
	// TODO use findBy() instead

	/**
	 * Find all departments in hierarchy tree structure of an Office and an Employee by ID.
	 *
	 * @param $officeId
	 * @param $employeeId
	 *
	 * @return array|null|string
	 */
	public function findByOfficeAndEmployee($officeId, $employeeId)
	{
		$office = $this->findOneRootNodeByEmployee($officeId, $employeeId);

		if (!$office) {
			return null;
		}

		// Check if office is a headquarter and set its children to all offices else its normal children
		if ($office->getIsHeadquarter()) {
			/** @var OfficeRepository $officeRepo */
			$officeRepo = $this->getEntityManager()->getRepository(Office::class);
			$childrenOffices = $officeRepo->findByEmployee($employeeId);

			/** @var Office $childOffice */
			foreach ($childrenOffices as $childOffice) {
				if (!$childOffice->getIsHeadquarter()) {
					$childrenDepartments = $this->findByOfficeAndEmployee($childOffice->getId(), $employeeId);
					foreach ($childrenDepartments as $childDepartment) {
						$childOffice->addDepartment($childDepartment);
					}
				}
			}

			$departments = $childrenOffices;
		} else {
			$allowedDepartmentsList = $this->findByEmployee($employeeId, true);

			// Helper subquery to determine max level value of the children result set
			$childrenQueryBuilder = $this->getChildrenQueryBuilder($office, false, 'name', 'ASC');
			$maxLvl = $childrenQueryBuilder
				->select('MAX(node.lvl)')
				->getQuery()
				->getSingleScalarResult();

			// Get direct children departments AND their direct children departments of the office where the employee is part of only
			// Using 'childrenQueryBuilder' from nestedTreeRepository
			$departmentsQueryBuilder = $this->childrenQueryBuilder($office, true, 'name', 'ASC', false);

			$startAlias = 'node';
			for ($i = 1; $i < $maxLvl; ++$i) {
				$currentAlias = is_numeric(substr($startAlias, -1)) ? substr($startAlias, 0, -1).$i : $startAlias.$i;
				$departmentsQueryBuilder
					->addSelect($currentAlias)
					->leftJoin($startAlias.'.children', $currentAlias, 'WITH', $departmentsQueryBuilder->expr()->andX(
						$departmentsQueryBuilder->expr()->eq($startAlias.'.id', $currentAlias.'.parent'),
						$departmentsQueryBuilder->expr()->in($currentAlias.'.id', $allowedDepartmentsList)
					));

				$startAlias = $currentAlias;
			}

			$departments = $departmentsQueryBuilder
				->andWhere($departmentsQueryBuilder->expr()->in('node.id', $allowedDepartmentsList))
				->orderBy('node.id', 'ASC')
				->getQuery()
				->getResult();
		}

		return $departments;
	}

	/**
	 * Find all departments in hierarchy tree structure of an Office that the current logged in client are allowed to see.
	 *
	 * @param $officeId
	 * @param Client $client
	 *
	 * @return array|null|string
	 */
	public function findByOfficeWithRestrictionCheck($officeId, Client $client)
	{
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = $this->getEntityManager()->getRepository(SystemConfig::class);
		/** @var SystemConfig $dorPhonelistRestrict */
		$dorPhonelistRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
		$restriction = $dorPhonelistRestrict ? $dorPhonelistRestrict->getNormalizedValue() : false;

		if ($restriction && !$client->hasRole(CompanyXUserRole::ALL_RIGHTS)) {
			$departments = $this->findByOfficeAndEmployee($officeId, $client->getEmployee()->getId());
		} else {
			$departments = $this->findByOffice($officeId);
		}

		return $departments;
	}

	// TODO maybe no long needed during DB switch with real Office object
	// TODO use findOneBy() instead

	/**
	 * Find one department by ID of an Office by ID.
	 *
	 * @param $officeId
	 * @param $departmentId
	 *
	 * @return null|Department
	 */
	public function findOneByOffice($officeId, $departmentId)
	{
		/** @var Department $department */
		$department = $this->findOneBy(array(
			'id' => $departmentId,
			'root' => $officeId,
		));

		return $department;
	}

	/**
	 * Find one department by ID of an Office and an Employee by ID.
	 *
	 * @param $officeId
	 * @param $departmentId
	 * @param $employeeId
	 *
	 * @return array
	 */
	public function findOneByOfficeAndEmployee($officeId, $departmentId, $employeeId)
	{
		$existingDepartment = $this->find($departmentId);
		$numberOfChildrenDepartment = ($existingDepartment) ? $this->childCount($existingDepartment) : 0;

		$qb = $this->getEntityManager()->createQueryBuilder();
		$qb
			->select('department')
			->from(Department::class, 'department')
			->leftJoin('department.employees', 'employee')
			->where('department.id = :departmentId')
			->andWhere('department.root = :officeId')
			->andWhere('employee.id = :employeeId');

		if ($numberOfChildrenDepartment > 0) {
			$office = $this->findOneRootNodeByEmployee($officeId, $employeeId);
			$allowedDepartmentsList = $this->findByEmployee($employeeId, true);

			// Helper subquery to determine max level value of the children result set
			$childrenQueryBuilder = $this->getChildrenQueryBuilder($office, false, 'name', 'ASC');
			$maxLvl = $childrenQueryBuilder
				->select('MAX(node.lvl)')
				->getQuery()
				->getSingleScalarResult();

			// Using 'childrenQueryBuilder' from nestedTreeRepository
			$startAlias = 'department';
			for ($i = 1; $i < $maxLvl; ++$i) {
				$currentAlias = is_numeric(substr($startAlias, -1)) ? substr($startAlias, 0, -1).$i : $startAlias.$i;
				$qb
					->addSelect($currentAlias)
					->leftJoin($startAlias.'.children', $currentAlias, 'WITH', $qb->expr()->andX(
						$qb->expr()->eq($startAlias.'.id', $currentAlias.'.parent'),
						$qb->expr()->in($currentAlias.'.id', $allowedDepartmentsList)
					));

				$startAlias = $currentAlias;
			}
		}

		$qb
			->orderBy('department.id', 'ASC')
			->setParameters(array(
				'officeId' => $officeId,
				'departmentId' => $departmentId,
				'employeeId' => $employeeId,
			));

		$department = $qb->getQuery()->getOneOrNullResult();

		return $department;
	}

	/**
	 * Find one department of an Office by ID that the current logged in client is allowed to see.
	 *
	 * @param $officeId
	 * @param $departmentId
	 * @param Client $client
	 *
	 * @return Department|array|null
	 */
	public function findOneByOfficeWithRestrictionCheck($officeId, $departmentId, Client $client)
	{
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = $this->getEntityManager()->getRepository(SystemConfig::class);
		$dorPhonelistRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
		$restriction = $dorPhonelistRestrict ? $dorPhonelistRestrict->getNormalizedValue() : false;

		if ($restriction && !$client->hasRole(CompanyXUserRole::ALL_RIGHTS)) {
			$department = $this->findOneByOfficeAndEmployee($officeId, $departmentId, $client->getEmployee()->getId());
		} else {
			$department = $this->findOneByOffice($officeId, $departmentId);
		}

		return $department;
	}

	/**
	 * Find one RootNode by ID.
	 *
	 * @param $departmentId
	 *
	 * @return mixed|null
	 */
	public function getRootNode($departmentId)
	{
		$meta = $this->getClassMetadata();
		$config = $this->listener->getConfiguration($this->_em, $meta->name);
		$qb = $this->getQueryBuilder();
		$qb
			->select('node')
			->from($config['useObjectClass'], 'node')
			->where(
				$qb->expr()->isNull('node.'.$config['parent']),
				$qb->expr()->eq('node.id', $departmentId)
			);
		$result = $qb->getQuery()->getOneOrNullResult();

		if (empty($result)) {
			return null;
		}

		return $result;
	}

	/**
	 * Find one RootNode by ID.
	 *
	 * @param $departmentId
	 * @param $employeeId
	 *
	 * @return mixed|null
	 */
	public function findOneRootNodeByEmployee($departmentId, $employeeId)
	{
		$meta = $this->getClassMetadata();
		$config = $this->listener->getConfiguration($this->_em, $meta->name);
		$qb = $this->getQueryBuilder();
		$qb
			->select('node')
			->from($config['useObjectClass'], 'node')
			->leftJoin('node.employees', 'employee')
			->where(
				$qb->expr()->isNull('node.'.$config['parent']),
				$qb->expr()->eq('node.id', $departmentId),
				$qb->expr()->eq('employee.id', $employeeId)
			);
		$result = $qb->getQuery()->getOneOrNullResult();

		if (empty($result)) {
			return null;
		}

		return $result;
	}

	/**
	 * Find departments by employee ID.
	 *
	 * @param $employeeId
	 * @param bool $scalarResult
	 *
	 * @return Department[]|ArrayCollection
	 */
	public function findByEmployee($employeeId, $scalarResult = false)
	{
		$qb = $this->getEntityManager()->createQueryBuilder();
		$qb
			->select('department')
			->from(Department::class, 'department')
			->leftJoin('department.employees', 'employee')
			->where('employee.id = :employeeId')
			->orderBy('department.name', 'ASC')
			->setParameters(array(
				'employeeId' => $employeeId,
			));

		if ($scalarResult) {
			$departments = $qb
				->getQuery()
				->getScalarResult();
			$result = array_column($departments, 'department_id');
		} else {
			$result = $qb
				->getQuery()
				->getResult();
		}

		return $result;
	}

	/**
	 * Get the children in the deepest level of a given department and employee ID.
	 *
	 * @param Department $department
	 * @param $employeeId
	 * @param bool $scalarResult
	 *
	 * @return array
	 */
	public function findDeepestAuthorizedChildrenDepartments(Department $department, $employeeId, $scalarResult = false)
	{
		// Helper subquery to determine max level value of the children result set
		$childrenQueryBuilder = $this->getChildrenQueryBuilder($department, false, 'name', 'ASC');
		$maxLvl = $childrenQueryBuilder
			->select('MAX(node.lvl)')
			->getQuery()
			->getSingleScalarResult();

		// Only get the children department in the deepest level
		$childrenQueryBuilder = $this->getChildrenQueryBuilder($department, false, 'name', 'ASC');
		$childrenQueryBuilder
			->select('node')
			->leftJoin('node.employees', 'employee')
			->andWhere('employee.id = :employeeId')
			->setParameter('employeeId', $employeeId)
			->andWhere('node.lvl = :maxLvl')
			->setParameter('maxLvl', $maxLvl);
		if ($scalarResult) {
			$deepestChildren = $childrenQueryBuilder
				->getQuery()
				->getScalarResult();
			$result = array_column($deepestChildren, 'node_id');
		} else {
			$result = $childrenQueryBuilder
				->getQuery()
				->getResult();
		}

		return $result;
	}

	/**
	 * Get all the children in the deepest level of all offices that the authenticated Client is a member of.
	 *
	 * @param Client $client
	 * @param bool   $scalarResult
	 *
	 * @return array|Department[]
	 */
	public function findAllDeepestAuthorizedChildrenDepartmentsIdsByClient(Client $client, $scalarResult = false)
	{
		/** @var OfficeRepository $officeRepo */
		$officeRepo = $this->getEntityManager()->getRepository(Office::class);
		/** @var DepartmentRepository $departmentRepo */
		$departmentRepo = $this->getEntityManager()->getRepository(Department::class);
		$associatedOffices = $officeRepo->findByEmployee($client->getEmployee()->getId());
		$totalOfficeDeepestChildrenIds = [];

		foreach ($associatedOffices as $associatedOffice) {
			// if the requested office has children, get the employees in the children departments, else employees from itself
			if ($departmentRepo->childCount($associatedOffice->getDepartmentRoot()) > 0) {
				$officeDeepestChildrenIds = $officeRepo->findDeepestAuthorizedChildrenDepartments($associatedOffice, $client->getEmployee()->getId(), $scalarResult);
			} else {
				$officeDeepestChildrenIds = [$associatedOffice->getDepartmentRoot()->getId()];
			}

			$totalOfficeDeepestChildrenIds = array_merge($totalOfficeDeepestChildrenIds, $officeDeepestChildrenIds);
		}

		return $totalOfficeDeepestChildrenIds;
	}

	/**
	 * Find authorized departments tree by Office for a Client and one of its associated role.
	 *
	 * @param $officeId
	 * @param Client $client
	 * @param $role
	 *
	 * @return Department[]|ArrayCollection
	 */
	public function findAuthorizedDepartmentsTreeByOfficeForClientAndRole($officeId, Client $client, $role)
	{
		$office = $this->findOneRootNodeByEmployee($officeId, $client->getEmployee()->getId());

		if (!$office) {
			return null;
		}

		$authorizedDepartmentsList = $this->findAllAuthorizedDepartmentsForClientAndRole($client, $role, true);

		if (!$authorizedDepartmentsList) {
			return null;
		}

		// Helper subquery to determine max level value of the children result set
		$childrenQueryBuilder = $this->getChildrenQueryBuilder($office, false, 'name', 'ASC');
		$maxLvl = $childrenQueryBuilder
			->select('MAX(node.lvl)')
			->getQuery()
			->getSingleScalarResult();

		// Get direct children departments AND their direct children departments of the office where the employee is authorized to access
		// Using 'childrenQueryBuilder' from nestedTreeRepository
		$departmentsQueryBuilder = $this->childrenQueryBuilder($office, true, 'name', 'ASC', false);

		$startAlias = 'node';
		for ($i = 1; $i < $maxLvl; ++$i) {
			$currentAlias = is_numeric(substr($startAlias, -1)) ? substr($startAlias, 0, -1).$i : $startAlias.$i;
			$departmentsQueryBuilder
				->addSelect($currentAlias)
				->leftJoin($startAlias.'.children', $currentAlias, 'WITH', $departmentsQueryBuilder->expr()->andX(
					$departmentsQueryBuilder->expr()->eq($startAlias.'.id', $currentAlias.'.parent'),
					$departmentsQueryBuilder->expr()->in($currentAlias.'.id', $authorizedDepartmentsList)
				));
			$startAlias = $currentAlias;
		}

		$departments = $departmentsQueryBuilder
			->andWhere($departmentsQueryBuilder->expr()->in('node.id', $authorizedDepartmentsList))
			->orderBy('node.id', 'ASC')
			->getQuery()
			->getResult();

		return $departments;
	}

	// TODO FIX THIS WHEN THAT * IN TABLE auth_acl IS FIXED FOR GOOD, AS THERE IS NO WAY TO GET ASSOCIATIONS WHEN FOREIGN KEY TO DEPARTMENT IS A * GOD!

	/**
	 * Find all authorized departments and all their children departments for a Client and one of its associated role.
	 *
	 * @param Client $client
	 * @param $role
	 * @param bool $scalarResult
	 *
	 * @return array
	 */
	public function findAllAuthorizedDepartmentsForClientAndRole(Client $client, $role, $scalarResult = false)
	{
		//Prepare client roles
		$matchTable = array_flip($client->getUserRolesMatchTable());
		$fullRoleName = $matchTable[$role];

		/** @var AccessControlItemRepository $accessControlItemRepo */
		$accessControlItemRepo = $this->getEntityManager()->getRepository(AccessControlItem::class);
		$accessControlItems = $accessControlItemRepo->findByClientAndRole($client, $role);

		// Check if 'all departments' is selected by checking for '*'
		if (1 === count($accessControlItems) && '*' === $accessControlItems[0]->getDepartmentId()) {
			$result = $this->findByEmployee($client->getEmployee()->getId(), $scalarResult);
		} else {
			$qb = $this->getEntityManager()->createQueryBuilder();
			$qb
				->select('department')
				->from(Department::class, 'department')
				->join('department.accessControlItems', 'accessControlItem')
				->leftJoin(UserRole::class, 'userRole', 'WITH',
					$qb->expr()->eq('accessControlItem.userRoleIdentifierString', 'userRole.identifierString')
				)
				->where($qb->expr()->eq('accessControlItem.employee', $client->getEmployee()->getId()))
				->andWhere($qb->expr()->like('userRole.name', ':fullRoleName'))
				->setParameter('fullRoleName', $fullRoleName);

			if ($scalarResult) {
				$departments = $qb
					->getQuery()
					->getScalarResult();
				$result = array_column($departments, 'department_id');
			} else {
				$result = $qb
					->getQuery()
					->getResult();
			}
		}

		return $result;
	}

	/**
	 * Find write access departments and all their children departments by Office for a Client and one of its associated role.
	 *
	 * @param $officeId
	 * @param Client $client
	 * @param $role
	 *
	 * @return Department[]|ArrayCollection
	 */
	public function findWriteAccessDepartmentsTreeByOfficeForClientAndRole($officeId, Client $client, $role)
	{
		$office = $this->findOneRootNodeByEmployee($officeId, $client->getEmployee()->getId());

		if (!$office) {
			return null;
		}

		$authorizedDepartmentsList = $this->findAllAuthorizedDepartmentsForClientAndRole($client, $role, true);

		if (!$authorizedDepartmentsList) {
			return null;
		}

		$readAccessDepartmentsList = $this->findByEmployee($client->getEmployee()->getId(), true);

		// Helper subquery to determine max level value of the children result set
		$childrenQueryBuilder = $this->getChildrenQueryBuilder($office, false, 'name', 'ASC');
		$maxLvl = $childrenQueryBuilder
			->select('MAX(node.lvl)')
			->getQuery()
			->getSingleScalarResult();

		// Get direct children departments AND their direct children departments of the office where the employee is part of only
		// Using 'childrenQueryBuilder' from nestedTreeRepository
		$departmentsQueryBuilder = $this->childrenQueryBuilder($office, true, 'name', 'ASC', false);

		$startAlias = 'node';
		for ($i = 1; $i < $maxLvl; ++$i) {
			$currentAlias = is_numeric(substr($startAlias, -1)) ? substr($startAlias, 0, -1).$i : $startAlias.$i;
			$departmentsQueryBuilder
				->addSelect($currentAlias)
				->leftJoin($startAlias.'.children', $currentAlias, 'WITH', $departmentsQueryBuilder->expr()->andX(
					$departmentsQueryBuilder->expr()->eq($startAlias.'.id', $currentAlias.'.parent'),
					$departmentsQueryBuilder->expr()->in($currentAlias.'.id', $readAccessDepartmentsList),
					$departmentsQueryBuilder->expr()->in($currentAlias.'.id', $authorizedDepartmentsList)
				));

			$startAlias = $currentAlias;
		}

		$departments = $departmentsQueryBuilder
			->andWhere($departmentsQueryBuilder->expr()->in('node.id', $readAccessDepartmentsList))
			->andWhere($departmentsQueryBuilder->expr()->in('node.id', $authorizedDepartmentsList))
			->orderBy('node.id', 'ASC')
			->getQuery()
			->getResult();

		return $departments;
	}

	/**
	 * Find flatten read access offices and departments by employee ID.
	 *
	 * @param $employeeId
	 * @param bool $scalarResult
	 *
	 * @return Department[]|[]
	 */
	public function findFlattenReadAccessDepartmentsByEmployee($employeeId, $scalarResult = false)
	{
		return $this->findByEmployee($employeeId, $scalarResult);
	}

	/**
	 * Find flatten write access offices and departments for a Client and one of its associated role.
	 *
	 * @param Client $client
	 * @param $role
	 * @param bool $scalarResult
	 *
	 * @return Department[]|[]
	 */
	public function findFlattenWriteAccessDepartmentsByClientAndRole(Client $client, $role, $scalarResult = false)
	{
		$authorizedDepartmentsList = $this->findAllAuthorizedDepartmentsForClientAndRole($client, $role, true);

		if (!$authorizedDepartmentsList) {
			return [];
		}

		$readAccessDepartmentsList = $this->findByEmployee($client->getEmployee()->getId(), true);

		$qb = $this->getEntityManager()->createQueryBuilder();
		$qb
			->select('department')
			->from(Department::class, 'department')
			->where($qb->expr()->in('department.id', $authorizedDepartmentsList))
			->andWhere($qb->expr()->in('department.id', $readAccessDepartmentsList))
			->orderBy('department.id', 'ASC');

		if ($scalarResult) {
			$departments = $qb
				->getQuery()
				->getScalarResult();
			$result = array_column($departments, 'department_id');
		} else {
			$result = $qb
				->getQuery()
				->getResult();
		}

		return $result;
	}
}
