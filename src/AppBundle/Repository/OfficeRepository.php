<?php

namespace AppBundle\Repository;

use AppBundle\Entity\Client;
use AppBundle\Entity\Department;
use AppBundle\Entity\Office;
use AppBundle\Entity\SystemConfig;
use AppBundle\Enumerator\CompanyXUserRole;
use AppBundle\Enumerator\SystemConfigKey;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;

/**
 * OfficeRepository.
 */
class OfficeRepository extends EntityRepository
{
	/**
	 * Find all offices
	 * TODO return REAL Office objects during DB switch.
	 *
	 * @return ArrayCollection
	 */
	public function findAll()
	{
		/** @var DepartmentRepository $departmentRepo */
		$departmentRepo = $this->getEntityManager()->getRepository(Department::class);
		$departmentRootNodes = $departmentRepo->getRootNodes('name');

		$offices = new ArrayCollection();
		if ($departmentRootNodes) {
			/** @var Department $rootNode */
			foreach ($departmentRootNodes as $rootNode) {
				$office = new Office();
				$office->setName($rootNode->getName());
				$office->setDepartmentRoot($rootNode);
				$office->setId($rootNode->getId());
				$offices->add($office);
			}
		}

		return $offices;
	}

	/**
	 * Find all offices that the current logged in client are allowed to see
	 * TODO return REAL Office objects during DB switch.
	 *
	 * @param Client $client
	 *
	 * @return ArrayCollection
	 */
	public function findAllWithRestrictionCheck(Client $client)
	{
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = $this->getEntityManager()->getRepository(SystemConfig::class);
		$dorPhonelistRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
		$restriction = $dorPhonelistRestrict ? $dorPhonelistRestrict->getNormalizedValue() : false;

		if ($restriction && !$client->hasRole(CompanyXUserRole::ALL_RIGHTS)) {
			$offices = $this->findByEmployee($client->getEmployee()->getId());
		} else {
			$offices = $this->findAll();
		}

		return $offices;
	}

	/**
	 * Find one office by ID
	 * TODO return REAL Office by id during DB switch, using id from department right now.
	 *
	 * @param mixed $officeId
	 *
	 * @return Office|null
	 */
	public function find($officeId)
	{
		$em = $this->getEntityManager();
		/** @var DepartmentRepository $departmentRepo */
		$departmentRepo = $em->getRepository(Department::class);
		/** @var Department $departmentRootNode */
		$departmentRootNode = $departmentRepo->getRootNode($officeId);

		if (!$departmentRootNode) {
			return null;
		}

		$office = new Office();
		$office->setId($departmentRootNode->getId());
		$office->setDepartmentRoot($departmentRootNode);
		$office->setName($departmentRootNode->getName());

		// Check if office is a headquarter and set its children to all offices else its normal children
		if ($office->getIsHeadquarter()) {
			$directChildrenOffices = $this->findAll();

			/** @var Office $directChildOffice */
			foreach ($directChildrenOffices as $directChildOffice) {
				$office->addOffice($directChildOffice);
			}
		} else {
			$directChildrenDepartments = $departmentRepo->getChildren($departmentRootNode, true, 'name', 'ASC');
			if (!empty($directChildrenDepartments)) {
				foreach ($directChildrenDepartments as $department) {
					$office->addDepartment($department);
				}
			}
		}

		return $office;
	}

	/**
	 * Find one office by ID that the current logged in client is allowed to see
	 * TODO return REAL Office by id during DB switch, using id from department right now.
	 *
	 * @param mixed  $officeId
	 * @param Client $client
	 *
	 * @return Office|null
	 */
	public function findWithRestrictionCheck($officeId, Client $client)
	{
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = $this->getEntityManager()->getRepository(SystemConfig::class);
		$dorPhonelistRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
		$restriction = $dorPhonelistRestrict ? $dorPhonelistRestrict->getNormalizedValue() : false;

		if ($restriction && !$client->hasRole(CompanyXUserRole::ALL_RIGHTS)) {
			$office = $this->findOneByEmployee($officeId, $client->getEmployee()->getId());
		} else {
			$office = $this->find($officeId);
		}

		return $office;
	}

	/**
	 * Find one office by office ID and employee ID.
	 * TODO return REAL Offices by employee id during DB switch, using departments by employee ID right now.
	 *
	 * @param $officeId
	 * @param $employeeId
	 *
	 * @return Office
	 */
	public function findOneByEmployee($officeId, $employeeId)
	{
		/** @var DepartmentRepository $departmentRepo */
		$departmentRepo = $this->getEntityManager()->getRepository(Department::class);
		/** @var Department $departmentRootNode */
		$departmentRootNode = $departmentRepo->findOneRootNodeByEmployee($officeId, $employeeId);

		if (!$departmentRootNode) {
			return null;
		}

		$office = new Office();
		$office->setId($departmentRootNode->getId());
		$office->setDepartmentRoot($departmentRootNode);
		$office->setName($departmentRootNode->getName());

		// Check if office is a headquarter and set its children to all offices else its normal children
		if ($office->getIsHeadquarter()) {
			$directChildrenOffices = $this->findByEmployee($employeeId);

			/** @var Office $directChildOffice */
			foreach ($directChildrenOffices as $directChildOffice) {
				$office->addOffice($directChildOffice);
			}
		} else {
			// Get direct children departments of the office where the employee is part of only
			// Using 'childrenQueryBuilder' from nestedTreeRepository
			$directChildrenDepartmentsQueryBuilder = $departmentRepo->childrenQueryBuilder($departmentRootNode, true, 'name', 'ASC');
			$directChildrenDepartments = $directChildrenDepartmentsQueryBuilder
				->leftJoin('node.employees', 'employee')
				->andWhere('employee.id = :employeeId')
				->setParameter('employeeId', $employeeId)
				->getQuery()
				->getResult();

			if (!empty($directChildrenDepartments)) {
				foreach ($directChildrenDepartments as $department) {
					$office->addDepartment($department);
				}
			}
		}

		return $office;
	}

	/**
	 * Find offices by employee ID.
	 * TODO return REAL Offices by employee id during DB switch, using departments by employee ID right now.
	 *
	 * @param $employeeId
	 *
	 * @return Office[]|ArrayCollection
	 */
	public function findByEmployee($employeeId)
	{
		/** @var DepartmentRepository $departmentRepo */
		$departmentRepo = $this->getEntityManager()->getRepository(Department::class);
		$employeeDepartments = $departmentRepo->findByEmployee($employeeId);

		//check if department is an office

		$offices = new ArrayCollection();
		if ($employeeDepartments) {
			/** @var Department $employeeDepartment */
			foreach ($employeeDepartments as $employeeDepartment) {
				// return only departments that are roots (Offices)
				if (!$employeeDepartment->getParent()) {
					$office = new Office();
					$office->setName($employeeDepartment->getName());
					$office->setDepartmentRoot($employeeDepartment);
					$office->setId($employeeDepartment->getId());
					$offices->add($office);
				}
			}
		}

		return $offices;
	}

	/**
	 * Get the children in the deepest level of a given office and employee ID.
	 *
	 * @param Office $office
	 * @param $employeeId
	 * @param bool $scalarResult
	 *
	 * @return array
	 */
	public function findDeepestAuthorizedChildrenDepartments(Office $office, $employeeId, $scalarResult = false)
	{
		/** @var DepartmentRepository $departmentRepo */
		$departmentRepo = $this->getEntityManager()->getRepository(Department::class);

		// Find all authorized deparments of the given office
		$childrenDepartments = $departmentRepo->findByOfficeAndEmployee($office->getDepartmentRoot()->getId(), $employeeId);
		$officeDeepestChildrenIds = [];
		/** @var Department $childDepartment */
		foreach ($childrenDepartments as $childDepartment) {
			// if the requested department has children, get the employees in the children departments, else employees from itself
			if ($departmentRepo->childCount($childDepartment) > 0) {
				$officeDeepestChildrenIds = array_merge($departmentRepo->findDeepestAuthorizedChildrenDepartments($childDepartment, $employeeId, true), $officeDeepestChildrenIds);
			} else {
				$officeDeepestChildrenIds[] = $childDepartment->getId();
			}
		}

		if ($scalarResult) {
			return $officeDeepestChildrenIds;
		} else {
			$childrenQueryBuilder = $departmentRepo->getChildrenQueryBuilder($office->getDepartmentRoot(), false, 'name', 'ASC');
			$childrenQueryBuilder
				->andWhere($childrenQueryBuilder->expr()->in('node.id', $officeDeepestChildrenIds));

			return $childrenQueryBuilder->getQuery()->getResult();
		}
	}

	/**
	 * Find all offices and all their children departments by employee ID.
	 * TODO return REAL Offices by employee id during DB switch, using departments by employee ID right now.
	 *
	 * @param $employeeId
	 *
	 * @return Office[]|ArrayCollection
	 */
	public function findReadAccessOfficesTreeByEmployee($employeeId)
	{
		/** @var DepartmentRepository $departmentRepo */
		$departmentRepo = $this->getEntityManager()->getRepository(Department::class);
		$rootNodesQueryBuilder = $departmentRepo->getRootNodesQueryBuilder();
		$rootNodesQueryBuilder
			->leftJoin('node.employees', 'employee')
			->andWhere('employee.id = :employeeId')
			->orderBy('node.id', 'ASC')
			->setParameters(array(
				'employeeId' => $employeeId,
			));

		$readAccessOffices = $rootNodesQueryBuilder->getQuery()->getResult();

		$fullOfficeTree = new ArrayCollection();
		if ($readAccessOffices) {
			/** @var Department $readAccessOffice */
			foreach ($readAccessOffices as $readAccessOffice) {
				$office = new Office();
				$office->setName($readAccessOffice->getName());
				$office->setDepartmentRoot($readAccessOffice);
				$office->setId($readAccessOffice->getId());

				$readAccessChildrenDepartments = $departmentRepo->findByOfficeAndEmployee($readAccessOffice->getId(), $employeeId);
				/** @var Department $readAccessChildrenDepartment */
				foreach ($readAccessChildrenDepartments as $readAccessChildrenDepartment) {
					$office->addDepartment($readAccessChildrenDepartment);
				}
				$fullOfficeTree->add($office);
			}
		}

		return $fullOfficeTree;
	}

	/**
	 * Find authorized offices and all their children departments by Client and one of its associated role.
	 *
	 * @param Client $client
	 * @param $role
	 *
	 * @return Office[]|ArrayCollection
	 */
	public function findWriteAccessOfficesTreeByClientAndRole(Client $client, $role)
	{
		/** @var DepartmentRepository $departmentRepo */
		$departmentRepo = $this->getEntityManager()->getRepository(Department::class);
		$authorizedDepartmentsList = $departmentRepo->findAllAuthorizedDepartmentsForClientAndRole($client, $role, true);

		if (!$authorizedDepartmentsList) {
			return [];
		}

		$rootNodesQueryBuilder = $departmentRepo->getRootNodesQueryBuilder();
		$rootNodesQueryBuilder
			->leftJoin('node.employees', 'employee')
			->andWhere('employee.id = :employeeId')
			->andWhere($rootNodesQueryBuilder->expr()->in('node.id', $authorizedDepartmentsList))
			->orderBy('node.id', 'ASC')
			->setParameters(array(
				'employeeId' => $client->getEmployee()->getId(),
			));

		$writeAccessOffices = $rootNodesQueryBuilder->getQuery()->getResult();

		$writeAccessOfficeTree = new ArrayCollection();
		if ($writeAccessOffices) {
			/** @var Department $writeAccessOffice */
			foreach ($writeAccessOffices as $writeAccessOffice) {
				$office = new Office();
				$office->setName($writeAccessOffice->getName());
				$office->setDepartmentRoot($writeAccessOffice);
				$office->setId($writeAccessOffice->getId());

				$writeAccessChildrenDepartments = $departmentRepo->findWriteAccessDepartmentsTreeByOfficeForClientAndRole($writeAccessOffice->getId(), $client, $role);
				/** @var Department $allowedChildrenDepartment */
				foreach ($writeAccessChildrenDepartments as $allowedChildrenDepartment) {
					$office->addDepartment($allowedChildrenDepartment);
				}
				$writeAccessOfficeTree->add($office);
			}
		}

		return $writeAccessOfficeTree;
	}

	/**
	 * Find flatten read access offices and departments by employee ID.
	 *
	 * @param $employeeId
	 * @param bool $scalarResult
	 *
	 * @return array
	 */
	public function findFlattenReadAccessDepartmentsByEmployee($employeeId, $scalarResult = false)
	{
		/** @var DepartmentRepository $departmentRepo */
		$departmentRepo = $this->getEntityManager()->getRepository(Department::class);
		$results = $departmentRepo->findByEmployee($employeeId, $scalarResult);

		// Cast into Office object
		$offices = new ArrayCollection();
		$departments = new ArrayCollection();

		if ($results) {
			/** @var Department $result */
			foreach ($results as $result) {
				// return only departments that are roots (Offices)
				if (!$result->getParent()) {
					$office = new Office();
					$office->setName($result->getName());
					$office->setDepartmentRoot($result);
					$office->setId($result->getId());
					$offices->add($office);
				} else {
					$departments->add($result);
				}
			}
		}

		return ['offices' => $offices, 'departments' => $departments];
	}

	/**
	 * Find flatten write access offices and departments for a Client and one of its associated role.
	 *
	 * @param Client $client
	 * @param $role
	 * @param bool $scalarResult
	 *
	 * @return array
	 */
	public function findFlattenWriteAccessDepartmentsByClientAndRole(Client $client, $role, $scalarResult = false)
	{
		/** @var DepartmentRepository $departmentRepo */
		$departmentRepo = $this->getEntityManager()->getRepository(Department::class);

		$authorizedDepartmentsList = $departmentRepo->findAllAuthorizedDepartmentsForClientAndRole($client, $role, true);

		if (!$authorizedDepartmentsList) {
			return [];
		}

		$readAccessDepartmentsList = $departmentRepo->findByEmployee($client->getEmployee()->getId(), true);

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
			$results = array_column($departments, 'department_id');
		} else {
			$results = $qb
				->getQuery()
				->getResult();
		}

		// Cast into Office object
		$offices = new ArrayCollection();
		$departments = new ArrayCollection();

		if ($results) {
			/** @var Department $result */
			foreach ($results as $result) {
				// return only departments that are roots (Offices)
				if (!$result->getParent()) {
					$office = new Office();
					$office->setName($result->getName());
					$office->setDepartmentRoot($result);
					$office->setId($result->getId());
					$offices->add($office);
				} else {
					$departments->add($result);
				}
			}
		}

		return ['offices' => $offices, 'departments' => $departments];
	}
}
