<?php

namespace AppBundle\Repository;

use AppBundle\Entity\Assignment;
use AppBundle\Entity\Client;
use AppBundle\Entity\Department;
use AppBundle\Entity\Employee;
use AppBundle\Entity\Office;
use AppBundle\Enumerator\DoctrineFilter;
use AppBundle\Enumerator\EligibleEmployeeState;
use AppBundle\Enumerator\RegisterStatus;
use AppBundle\Enumerator\RegisterType;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * Class EmployeeRepository.
 */
class EmployeeRepository extends EntityRepository
{
	/**
	 * Find one employee by ID with assignments and registers from a specific date.
	 *
	 * @param $employeeId
	 * @param DateTime $date
	 *
	 * @return Employee|null
	 */
	public function findOneWithAssignmentsAndRegistersByDate($employeeId, DateTime $date)
	{
		$qb = $this->createQueryBuilder('employee');

		$this->addAssignmentAndRegisterJoinsByDate($qb, $date);

		$qb
			->where('employee.id = :employeeId')
			->andWhere('employee.active = true')
			->setParameter('employeeId', $employeeId);

		$employee = $qb->getQuery()->getOneOrNullResult();

		return $employee;
	}

	/**
	 * Find employees by department ID.
	 *
	 * @param $departmentId
	 * @param bool $scalarResult
	 *
	 * @return Employee[]|ArrayCollection
	 */
	public function findByDepartment($departmentId, $scalarResult = false)
	{
		$qb = $this->createQueryBuilder('employee');
		$qb
			->leftJoin('employee.departments', 'department')
			->where('department.id = :departmentId')
			->andWhere('employee.active = :status')
			->orderBy('employee.lastname', 'ASC')
			->setParameters(array(
				'departmentId' => $departmentId,
				'status' => true,
			));

		if ($scalarResult) {
			$employees = $qb->getQuery()->getScalarResult();
			$employees = array_column($employees, 'employee_id');
		} else {
			$employees = $qb->getQuery()->getResult();
		}

		return $employees;
	}

	/**
	 * Find employees by department ID with assignments and registers from a specific date.
	 *
	 * @param $departmentId
	 * @param DateTime $date
	 *
	 * @return Employee[]|array
	 */
	public function findByDepartmentWithAssignmentsAndRegistersFromDate($departmentId, DateTime $date)
	{
		$qb = $this->createQueryBuilder('employee');

		$this->addAssignmentAndRegisterJoinsByDate($qb, $date);

		$qb
			->leftJoin('employee.departments', 'department')
			->andWhere('department.id = :departmentId')
			->andWhere('employee.active = true')
			->orderBy('employee.lastname', 'ASC')
			->setParameter('departmentId', $departmentId);

		$employees = $qb->getQuery()->getResult();

		return $employees;
	}

	/**
	 * Find employees by either office ID or department ID with assignments and registers from a specific date
	 * TODO Rewrite andX with proper Office to Employee object relation.
	 *
	 * @param null     $departmentId
	 * @param null     $officeId
	 * @param DateTime $date
	 * @param $name
	 * @param bool $queryBuilderOnly
	 *
	 * @return Employee[]|array|QueryBuilder
	 */
	public function findByParametersWithAssignmentsAndRegistersFromDate($departmentId = null, $officeId = null, DateTime $date, $name = null, $queryBuilderOnly = false)
	{
		$qb = $this->createQueryBuilder('employee');

		$this->addAssignmentAndRegisterJoinsByDate($qb, $date);

		$this->addFindByDepartmentIdCriteria($qb, $departmentId, $officeId);

		$this->addFindByNameCriteria($qb, $name);

		$qb
			->andWhere('employee.active = true');

		return $queryBuilderOnly
			? $qb
			: $qb->getQuery()->getResult();
	}

	/**
	 * Find employees by office ID.
	 * TODO Maybe rework logic when real Office entity relation is mapped against Employee during DB switch
	 * TODO Could be simply be replaced by findBy(array('office_id' => $officeId)) in perfect db structure.
	 *
	 * @param $officeId
	 *
	 * @return Employee[]|ArrayCollection|null
	 */
	public function findByOffice($officeId)
	{
		$officeRepo = $this->getEntityManager()->getRepository(Office::class);
		/** @var Office $office */
		$office = $officeRepo->find($officeId);

		if (!$office) {
			return null;
		}

		$employees = $this->findByDepartment($office->getDepartmentRoot()->getId());

		return $employees;
	}

	/**
	 * Find employees by office ID with assignments and registers from a specific date.
	 *
	 * @param $officeId
	 * @param DateTime $date
	 *
	 * @return array|null
	 */
	public function findByOfficeWithAssignmentsAndRegistersFromDate($officeId, DateTime $date)
	{
		$officeRepo = $this->getEntityManager()->getRepository(Office::class);
		/** @var Office $office */
		$office = $officeRepo->find($officeId);

		if (!$office) {
			return null;
		}

		$employees = $this->findByDepartmentWithAssignmentsAndRegistersFromDate($office->getDepartmentRoot()->getId(), $date);

		return $employees;
	}

	/**
	 * Find employees who are eligible for an Assignment by Assignment ID.
	 *
	 * @param $assignmentId
	 *
	 * @return Employee[]|[]
	 */
	public function findEligibleForAssignment($assignmentId)
	{
		// For this query RegisterFilter must first be disabled if it was originally enabled
		$isRegisterFilterOriginallyEnabled = $this->getEntityManager()
			->getFilters()
			->isEnabled(DoctrineFilter::REGISTER_FILTER);
		if ($isRegisterFilterOriginallyEnabled) {
			$this->getEntityManager()->getFilters()->disable(DoctrineFilter::REGISTER_FILTER);
		}

		/** @var Assignment $assignment */
		$assignment = $this->getEntityManager()->find(Assignment::class, $assignmentId);

		if (!$assignment) {
			return null;
		}

		// Find preferenced employees
		$qb = $this->getEntityManager()->createQueryBuilder();
		$qb
			->select('employee', 'register', 'assignment')
			->from(Employee::class, 'employee')
			->leftJoin('employee.departments', 'department')
			->leftJoin('employee.registers', 'register', 'WITH', $qb->expr()->andX(
				$qb->expr()->eq('employee.id', 'register.employee'),
				$qb->expr()->eq('register.type', ':registerType'),
				$qb->expr()->eq('DATE_DIFF(register.startDate, :assignmentStartDate)', 0)
			))
			->leftJoin('employee.assignments', 'assignment', 'WITH', $qb->expr()->andX(
				$qb->expr()->eq('employee.id', 'assignment.employee'),
				$qb->expr()->eq('DATE_DIFF(assignment.startDate, :assignmentStartDate)', 0)
			))
			->where('department.id = :assignmentDepartment')
			->andWhere('employee.active = :isActive')
			->groupBy('employee.id')
			->having('count(register.id) > 0')
			->andHaving('count(assignment.id) = 0')
			->orderBy('employee.lastname', 'ASC')
			->setParameters(array(
				'registerType' => RegisterType::PREFERENCE,
				'assignmentStartDate' => $assignment->getStartDate(),
				'assignmentDepartment' => $assignment->getDepartment(),
				'isActive' => true,
			))
		;

		$employees[EligibleEmployeeState::PREFERENCED_EMPLOYEES] = $qb->getQuery()->getResult();

		// Find available employees
		$qb = $this->getEntityManager()->createQueryBuilder();
		$qb
			->select('employee', 'register', 'assignment')
			->from(Employee::class, 'employee')
			->leftJoin('employee.departments', 'department')
			->leftJoin('employee.registers', 'register', 'WITH', $qb->expr()->andX(
				$qb->expr()->eq('employee.id', 'register.employee'),
				$qb->expr()->eq('register.type', ':registerType'),
				// join only registers which startDate and endDate overlaps with the startDate and endDate of the assignment
				$qb->expr()->andX(
					$qb->expr()->lte('register.startDate', ':assignmentEndDate'),
					$qb->expr()->gte('register.endDate', ':assignmentStartDate')
				)
			))
			->leftJoin('employee.assignments', 'assignment', 'WITH', $qb->expr()->andX(
				$qb->expr()->eq('employee.id', 'assignment.employee'),
				$qb->expr()->eq('DATE_DIFF(assignment.startDate, :assignmentStartDate)', 0)
			))
			->where('department.id = :assignmentDepartment')
			->andWhere('employee.active = :isActive')
			->groupBy('employee.id')
			->having('count(register.id) > 0')
			->andHaving('count(assignment.id) = 0')
			->orderBy('employee.lastname', 'ASC')
			->setParameters(array(
				'registerType' => RegisterType::AVAILABLE,
				'assignmentStartDate' => $assignment->getStartDate(),
				'assignmentEndDate' => $assignment->getEndDate(),
				'assignmentDepartment' => $assignment->getDepartment(),
				'isActive' => true,
			))
		;

		$employees[EligibleEmployeeState::AVAILABLE_EMPLOYEES] = $qb->getQuery()->getResult();

		// Find free employees
		$qb = $this->getEntityManager()->createQueryBuilder();
		$qb
			->select('employee', 'register', 'assignment')
			->from(Employee::class, 'employee')
			->leftJoin('employee.departments', 'department')
			->leftJoin('employee.registers', 'register', 'WITH', $qb->expr()->andX(
				$qb->expr()->eq('employee.id', 'register.employee'),
				$qb->expr()->in('register.type', array(
					RegisterType::PREFERENCE,
					RegisterType::AVAILABLE,
					RegisterType::UNAVAILABLE,
					RegisterType::SICK,
					RegisterType::VACATION,
				)),
				// join only registers which startDate and endDate overlaps with the startDate and endDate of the assignment
				$qb->expr()->andX(
					$qb->expr()->lte('register.startDate', ':assignmentEndDate'),
					$qb->expr()->gte('register.endDate', ':assignmentStartDate')
				),
				$qb->expr()->eq('register.status', ':registerStatusGranted')
			))
			->leftJoin('employee.assignments', 'assignment', 'WITH', $qb->expr()->andX(
				$qb->expr()->eq('employee.id', 'assignment.employee'),
				$qb->expr()->eq('DATE_DIFF(assignment.startDate, :assignmentStartDate)', 0)
			))
			->where('department.id = :assignmentDepartment')
			->andWhere('employee.active = :isActive')
			->groupBy('employee.id')
			->having('count(register.id) = 0')
			->andHaving('count(assignment.id) = 0')
			->orderBy('employee.lastname', 'ASC')
			->setParameters(array(
				'assignmentStartDate' => $assignment->getStartDate(),
				'assignmentEndDate' => $assignment->getEndDate(),
				'registerStatusGranted' => RegisterStatus::GRANTED,
				'assignmentDepartment' => $assignment->getDepartment(),
				'isActive' => true,
			))
		;

		$employees[EligibleEmployeeState::FREE_EMPLOYEES] = $qb->getQuery()->getResult();

		// Find unavailable employees
		$qb = $this->getEntityManager()->createQueryBuilder();
		$qb
			->select('employee', 'register', 'assignment')
			->from(Employee::class, 'employee')
			->leftJoin('employee.departments', 'department')
			->leftJoin('employee.registers', 'register', 'WITH', $qb->expr()->andX(
				$qb->expr()->eq('employee.id', 'register.employee'),
				$qb->expr()->in('register.type', array(
					RegisterType::UNAVAILABLE,
					RegisterType::SICK,
					RegisterType::VACATION,
				)),
				// join only registers which startDate and endDate overlaps with the startDate and endDate of the assignment
				$qb->expr()->andX(
					$qb->expr()->lte('register.startDate', ':assignmentEndDate'),
					$qb->expr()->gte('register.endDate', ':assignmentStartDate')
				),
				$qb->expr()->eq('register.status', ':registerStatusGranted')
			))
			->leftJoin('employee.assignments', 'assignment', 'WITH', $qb->expr()->andX(
				$qb->expr()->eq('employee.id', 'assignment.employee'),
				$qb->expr()->eq('DATE_DIFF(assignment.startDate, :assignmentStartDate)', 0)
			))
			->where('department.id = :assignmentDepartment')
			->andWhere('employee.active = :isActive')
			->groupBy('employee.id')
			->having('count(register.id) > 0')
			->andHaving('count(assignment.id) = 0')
			->orderBy('employee.lastname', 'ASC')
			->setParameters(array(
				'assignmentStartDate' => $assignment->getStartDate(),
				'assignmentEndDate' => $assignment->getEndDate(),
				'registerStatusGranted' => RegisterStatus::GRANTED,
				'assignmentDepartment' => $assignment->getDepartment(),
				'isActive' => true,
			))
		;

		$employees[EligibleEmployeeState::UNAVAILABLE_EMPLOYEES] = $qb->getQuery()->getResult();

		// Find scheduled employees
		$qb = $this->getEntityManager()->createQueryBuilder();
		$qb
			->select('employee', 'assignment')
			->from(Employee::class, 'employee')
			->leftJoin('employee.departments', 'department')
			->leftJoin('employee.assignments', 'assignment', 'WITH', $qb->expr()->andX(
				$qb->expr()->eq('employee.id', 'assignment.employee'),
				$qb->expr()->eq('DATE_DIFF(assignment.startDate, :assignmentStartDate)', 0)
			))
			->where('department.id = :assignmentDepartment')
			->andWhere('employee.active = :isActive')
			->groupBy('employee.id')
			->having('count(assignment.id) > 0')
			->orderBy('employee.lastname', 'ASC')
			->setParameters(array(
				'assignmentStartDate' => $assignment->getStartDate(),
				'assignmentDepartment' => $assignment->getDepartment(),
				'isActive' => true,
			))
		;

		$employees[EligibleEmployeeState::SCHEDULED_EMPLOYEES] = $qb->getQuery()->getResult();

		// Enable RegisterFilter again if it was originally enabled
		if ($isRegisterFilterOriginallyEnabled) {
			$this->getEntityManager()->getFilters()->enable(DoctrineFilter::REGISTER_FILTER);
		}

		return $employees;
	}

	/**
	 * Find all active direct colleagues of the current authenticated Client.
	 *
	 * @param Client $client
	 * @param bool   $scalarResult
	 *
	 * @return array
	 */
	public function findDirectColleaguesByClient(Client $client, $scalarResult = false)
	{
		/** @var DepartmentRepository $departmentRepo */
		$departmentRepo = $this->getEntityManager()->getRepository(Department::class);
		$allAuthorizedDepartments = $departmentRepo->findAllDeepestAuthorizedChildrenDepartmentsIdsByClient($client,
			true);

		$qb = $this->getEntityManager()->createQueryBuilder();
		$qb
			->select('DISTINCT employee')
			->from(Employee::class, 'employee')
			->leftJoin('employee.departments', 'department')
			->where($qb->expr()->eq('employee.active', true))
			->andWhere($qb->expr()->in('department.id', $allAuthorizedDepartments))
			->orderBy('employee.lastname', 'ASC');

		if ($scalarResult) {
			$employees = $qb->getQuery()->getScalarResult();
			$employees = array_column($employees, 'employee_id');
		} else {
			$employees = $qb->getQuery()->getResult();
		}

		return $employees;
	}

	/**
	 * Add leftJoin to Assignment and Register by Date.
	 *
	 * @param QueryBuilder $qb
	 * @param DateTime     $date
	 *
	 * @return QueryBuilder
	 */
	private function addAssignmentAndRegisterJoinsByDate(QueryBuilder $qb, DateTime $date)
	{
		$rootAlias = $qb->getRootAliases()[0];

		return $qb
			->leftJoin($rootAlias.'.assignments', 'assignment', 'WITH', $qb->expr()->andX(
				$qb->expr()->eq($rootAlias.'.id', 'assignment.employee'),
				$qb->expr()->eq('DATE_DIFF(assignment.startDate, :date)', 0),
				$qb->expr()->eq('assignment.published', true)
			))
			->leftJoin($rootAlias.'.registers', 'register', 'WITH', $qb->expr()->andX(
				$qb->expr()->eq($rootAlias.'.id', 'register.employee'),
				$qb->expr()->eq('DATE_DIFF(register.startDate, :date)', 0),
				$qb->expr()->in('register.type', array('onbeschikbaar', 'beschikbaar', 'vakantie', 'ziek'))
			))
			->addSelect('assignment', 'register')
			->setParameter('date', $date);
	}

	/**
	 * Add find by Department ID or Office ID criteria.
	 *
	 * @param QueryBuilder $qb
	 * @param null         $departmentId
	 * @param null         $officeId
	 *
	 * @return QueryBuilder
	 */
	private function addFindByDepartmentIdCriteria(QueryBuilder $qb, $departmentId = null, $officeId = null)
	{
		$rootAlias = $qb->getRootAliases()[0];
		$departmentQuery = null !== $departmentId
			? $qb->expr()->andX(
				$qb->expr()->eq('department.id', $departmentId),
				$qb->expr()->isNotNull('department.parent')
			)
			: null;
		$officeQuery = null !== $officeId ? $qb->expr()->eq('department.id', $officeId) : null;

		// TODO This becomes redundent if Office becomes a seperate table during DB switch
		// Check if provided office exists
		$officeRepo = $this->getEntityManager()->getRepository(Office::class);
		if ($officeId) {
			/** @var Office $office */
			$office = $officeRepo->find($officeId);
			if (!$office) {
				$officeQuery = $qb->expr()->isNull($rootAlias.'.id');
			}
		}

		return $qb
			->leftJoin($rootAlias.'.departments', 'department')
			->andWhere($qb->expr()->andX(
				$departmentQuery,
				$officeQuery
			));
	}

	/**
	 * Add find by firstname or lastname criteria.
	 *
	 * @param QueryBuilder $qb
	 * @param null         $name
	 *
	 * @return QueryBuilder
	 */
	private function addFindByNameCriteria(QueryBuilder $qb, $name = null)
	{
		$rootAlias = $qb->getRootAliases()[0];
		$searchByNameQuery = null !== $name ? $qb->expr()->orX(
			$qb->expr()->like($rootAlias.'.firstname', $qb->expr()->literal('%'.$name.'%')),
			$qb->expr()->like($rootAlias.'.lastname', $qb->expr()->literal('%'.$name.'%'))
		) : null;

		return $qb
			->andWhere($searchByNameQuery);
	}
}
