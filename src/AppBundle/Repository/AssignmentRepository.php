<?php

namespace AppBundle\Repository;

use AppBundle\Entity\Assignment;
use AppBundle\Entity\Client;
use AppBundle\Entity\Department;
use AppBundle\Entity\Employee;
use AppBundle\Entity\Office;
use AppBundle\Entity\Register;
use AppBundle\Entity\SystemConfig;
use AppBundle\Enumerator\RegisterType;
use AppBundle\Enumerator\ShiftSwapRequestStatus;
use AppBundle\Util\Constants;
use AppBundle\Enumerator\AssignmentState;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * AssignmentRepository.
 */
class AssignmentRepository extends EntityRepository
{
	/**
	 * Find Assignments of an Employee with date and assignmentState parameters that an Employee is allowed to see , returns assignments of current month by default.
	 *
	 * @param $employeeId
	 * @param DateTime|null $dateTimeFrom
	 * @param DateTime|null $dateTimeTo
	 * @param $assignmentState
	 *
	 * @return Employee[]|[]
	 */
	public function findByEmployeeWithParameters($employeeId, \DateTime $dateTimeFrom = null, \DateTime $dateTimeTo = null, $assignmentState = null)
	{
		// Check DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS and DOR_OPENSHIFTS_SHOW_IF_SCHEDULED setting to determine if unassigned assignments can be shown
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = $this->getEntityManager()->getRepository(SystemConfig::class);
		$showUnassignedAssignments = $systemConfigRepo->showUnassignedAssignments();
		$showScheduledUnassignedAssignments = $systemConfigRepo->showScheduledUnassignedAssignments();

		// Must use this instead of new DateTime() to be able to mock current time in phpunit tests
		$currentDate = DateTime::createFromFormat('U', time());
		$assignments = [];
		$unassignedAssignments = [];

		// Prepare assigned Assignment QueryBuilder
		$qb = $this->createQueryBuilder('assignment');

		$this->addShiftSwapRequestJoin($qb);

		$qb
			->addCriteria($this->createDateRangeCriteria($dateTimeFrom, $dateTimeTo))
			->andWhere($qb->expr()->eq('assignment.employee', ':employeeId'))
			//TODO In companyX code the system config setting 'PublicationClause' determines whether or not this condition is filtered out
			->andWhere('assignment.published = true')
			->orderBy('assignment.startDate', 'ASC')
			->setParameter('employeeId', $employeeId);

		$assignedAssignmentQueryBuilder = $qb;

		// Prepare unassigned Assignment QueryBuilder
		$qb = $this->createQueryBuilder('assignment');

		$this->excludePreferencedUnassignedAssignmentCriteria($qb, $employeeId);

		if (!$showScheduledUnassignedAssignments) {
			$this->excludeAlreadyScheduledDaysCriteria($qb, $employeeId);

			$this->excludeUnavailableDaysCriteria($qb, $employeeId);
		}

		$qb
			->addCriteria($this->createDateRangeCriteria($dateTimeFrom, $dateTimeTo))
			//TODO 0 is not valid foreignkey but hardcoded in dor_assignments table. Fix this during DB switch with ISNULL
			->andWhere($qb->expr()->eq('assignment.employee', '0'))
			->andWhere($qb->expr()->gte("date_format(assignment.startDate, '%Y-%m-%d')", ':currentDate'))
			//TODO In companyX code the system config setting 'PublicationClause' determines whether or not this condition is filtered out
			->andWhere('assignment.published = true')
			->groupBy('assignment.id')
			->orderBy('assignment.startDate', 'ASC')
			->setParameter('currentDate', $currentDate->format(Constants::DateFormatString));

		$unassignedAssignmentQueryBuilder = $qb;

		// Return only assigned assignments if no assignmentState given
		if (!$assignmentState) {
			$assignments = $assignedAssignmentQueryBuilder->getQuery()->getResult();
		}

		// Return only unassigned assignments if ?assignmentState=unassigned
		if (AssignmentState::UNASSIGNED === $assignmentState && $showUnassignedAssignments) {
			$assignments = $unassignedAssignmentQueryBuilder->getQuery()->getResult();
		}

		// Return both unassigned and assigned assignments if ?assignmentState=all in seperate array key
		if (AssignmentState::ALL === $assignmentState) {
			$assignments[AssignmentState::ASSIGNED_ASSIGNMENTS] = $assignedAssignmentQueryBuilder->getQuery()->getResult();

			if ($showUnassignedAssignments) {
				$unassignedAssignments = $unassignedAssignmentQueryBuilder->getQuery()->getResult();
			}

			$assignments[AssignmentState::UNASSIGNED_ASSIGNMENTS] = $unassignedAssignments;
		}

		return $assignments;
	}

	/**
	 * Find Assignments with date and assignmentState parameters that a client is allowed to see , returns assignments of current month by default.
	 *
	 * @param Client        $client
	 * @param DateTime|null $dateTimeFrom
	 * @param DateTime|null $dateTimeTo
	 * @param $assignmentState
	 * @param $officeId
	 * @param $departmentId
	 *
	 * @return Employee[] []
	 */
	public function findAllWithParameters(Client $client, \DateTime $dateTimeFrom = null, \DateTime $dateTimeTo = null, $assignmentState = null, $officeId = null, $departmentId = null)
	{
		// Check DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS and DOR_OPENSHIFTS_SHOW_IF_SCHEDULED setting to determine if unassigned assignments can be shown
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = $this->getEntityManager()->getRepository(SystemConfig::class);
		$showUnassignedAssignments = $systemConfigRepo->showUnassignedAssignments();
		$showScheduledUnassignedAssignments = $systemConfigRepo->showScheduledUnassignedAssignments();

		// Must use this instead of new DateTime() to be able to mock current time in phpunit tests
		$currentDate = DateTime::createFromFormat('U', time());

		// Prepare queries
		$assignments = [];
		$unassignedAssignments = [];

		// Prepare assigned Assignment QueryBuilder
		$qb = $this->createQueryBuilder('assignment');

		$this->addShiftSwapRequestJoin($qb);

		$this->addFindByDepartmentIdCriteria($qb, $departmentId, $officeId);

		$qb
			->addCriteria($this->createDateRangeCriteria($dateTimeFrom, $dateTimeTo))
			->andWhere($qb->expr()->notIn('assignment.employee', '0'))
			//TODO In companyX code the system config setting 'PublicationClause' determines whether or not this condition is filtered out
			->andWhere('assignment.published = true')
			->orderBy('assignment.startDate', 'ASC');

		$assignedAssignmentQueryBuilder = $qb;

		// Prepare unassigned Assignment QueryBuilder
		$qb = $this->createQueryBuilder('assignment');

		$this->excludePreferencedUnassignedAssignmentCriteria($qb, $client->getEmployee()->getId());

		$this->addFindByDepartmentIdCriteria($qb, $departmentId, $officeId);

		if (!$showScheduledUnassignedAssignments) {
			$this->excludeAlreadyScheduledDaysCriteria($qb, $client->getEmployee()->getId());

			$this->excludeUnavailableDaysCriteria($qb, $client->getEmployee()->getId());
		}

		$qb
			->addCriteria($this->createDateRangeCriteria($dateTimeFrom, $dateTimeTo))
			//TODO 0 is not valid foreignkey but hardcoded in dor_assignments table. Fix this during DB switch with ISNULL
			->andWhere($qb->expr()->eq('assignment.employee', '0'))
			->andWhere($qb->expr()->gte("date_format(assignment.startDate, '%Y-%m-%d')", ':currentDate'))
			//TODO In companyX code the system config setting 'PublicationClause' determines whether or not this condition is filtered out
			->andWhere('assignment.published = true')
			->groupBy('assignment.id')
			->orderBy('assignment.startDate', 'ASC')
			->setParameter('currentDate', $currentDate->format(Constants::DateFormatString));

		$unassignedAssignmentQueryBuilder = $qb;

		// Return only assigned assignments if no assignmentState given
		if (!$assignmentState) {
			$assignments = $assignedAssignmentQueryBuilder->getQuery()->getResult();
		}

		// Return only unassigned assignments if ?assignmentState=unassigned
		if (AssignmentState::UNASSIGNED === $assignmentState && $showUnassignedAssignments) {
			$assignments = $unassignedAssignmentQueryBuilder->getQuery()->getResult();
		}

		// Return both unassigned and assigned assignments if ?assignmentState=all in seperate array key
		if (AssignmentState::ALL === $assignmentState) {
			$assignments[AssignmentState::ASSIGNED_ASSIGNMENTS] = $assignedAssignmentQueryBuilder->getQuery()->getResult();

			if ($showUnassignedAssignments) {
				$unassignedAssignments = $unassignedAssignmentQueryBuilder->getQuery()->getResult();
			}

			$assignments[AssignmentState::UNASSIGNED_ASSIGNMENTS] = $unassignedAssignments;
		}

		return $assignments;
	}

	/**
	 * Find one Assignment by ID that an Employee is allowed to see.
	 *
	 * @param $assignmentId
	 * @param $employeeId
	 *
	 * @return Assignment|null
	 */
	public function findOneByEmployee($assignmentId, $employeeId)
	{
		// Check DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS setting to determine if unassigned assignments can be shown
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = $this->getEntityManager()->getRepository(SystemConfig::class);
		$showUnassignedAssignments = $systemConfigRepo->showUnassignedAssignments();

		$qb = $this->createQueryBuilder('assignment');

		$this->addShiftSwapRequestJoin($qb);

		$this->excludePreferencedUnassignedAssignmentCriteria($qb, $employeeId);

		$qb
			->where('assignment.id = :assignmentId')
			->andWhere($qb->expr()->eq('assignment.published', true));

		if ($showUnassignedAssignments) {
			$qb
				->andWhere($qb->expr()->orX(
					$qb->expr()->eq('assignment.employee', ':employeeId'),
					$qb->expr()->eq('assignment.employee', '0')
				));
		} else {
			$qb
				->andWhere($qb->expr()->eq('assignment.employee', ':employeeId'));
		}
		$qb
			->setParameter('assignmentId', $assignmentId)
			->setParameter('employeeId', $employeeId);

		$assignment = $qb->getQuery()->getOneOrNullResult();

		return $assignment;
	}

	/**
	 * Find Assignments of an Employee on a date.
	 *
	 * @param Employee $employee
	 * @param DateTime $startDate
	 *
	 * @return array
	 */
	public function findByEmployeeAndStartDate(Employee $employee, DateTime $startDate)
	{
		$qb = $this->getEntityManager()->createQueryBuilder();
		$qb
			->select('assignment')
			->from(Assignment::class, 'assignment')
			->where($qb->expr()->eq('assignment.employee', ':employee'))
			->andWhere($qb->expr()->eq("date_format(assignment.startDate, '%Y-%m-%d')", ':startDate'))
			->setParameters(array(
				'employee' => $employee,
				'startDate' => $startDate->format(Constants::DateFormatString),
			));

		$assignments = $qb->getQuery()->getResult();

		return $assignments;
	}

	/**
	 * Find Assignments in ShiftSwapRequests that are associated with an employee either by being the Applicant or Receiver.
	 *
	 * @param $employeeId
	 * @param bool $scalarResult
	 *
	 * @return array
	 */
	public function findAssignmentsFromShiftSwapRequestsByEmployee($employeeId, $scalarResult = false)
	{
		// Must use this instead of new DateTime() to be able to mock current time in phpunit tests
		$currentDate = DateTime::createFromFormat('U', time());

		$qb = $this->createQueryBuilder('assignment');
		$qb
			->join('assignment.shiftSwapRequests', 'shiftSwapRequest')
			->where($qb->expr()->orX(
				'shiftSwapRequest.applicant = :employeeId',
				'shiftSwapRequest.receiver = :employeeId'
			))
			->andWhere($qb->expr()->in('shiftSwapRequest.status', array(
				ShiftSwapRequestStatus::UNPROCESSED_BY_RECEIVER,
				ShiftSwapRequestStatus::GRANTED_BY_RECEIVER,
				ShiftSwapRequestStatus::GRANTED_BY_PLANNER,
			)))
			->andWhere($qb->expr()->gt("date_format(shiftSwapRequest.startDate, '%Y-%m-%d')", ':currentDate'))
			->orderBy('shiftSwapRequest.startDate', 'ASC')
			->setParameter('employeeId', $employeeId)
			->setParameter('currentDate', $currentDate->format(Constants::DateFormatString));

		if ($scalarResult) {
			$assignments = $qb->getQuery()->getScalarResult();
			$assignments = array_column($assignments, 'assignment_id');
		} else {
			$assignments = $qb->getQuery()->getResult();
		}

		return $assignments;
	}

	/**
	 * Creates select within date range criteria.
	 *
	 * @param DateTime|null $dateTimeFrom
	 * @param DateTime|null $dateTimeTo
	 *
	 * @return Criteria
	 */
	private function createDateRangeCriteria(\DateTime $dateTimeFrom = null, \DateTime $dateTimeTo = null)
	{
		// Must use this instead of new DateTime() to be able to mock current time in phpunit tests
		$currentDate = DateTime::createFromFormat('U', time());
		$intervalQuery = null;

		// if no dateTimeFrom and no dateTimeTo provided, default fetch assignments of current month
		if (!$dateTimeFrom && !$dateTimeTo) {
			$beginOfTheMonth = (new DateTime($currentDate->format(Constants::DateFormatString)))->modify('first day of this month');
			$endOfTheMonth = (new DateTime($currentDate->format(Constants::DateFormatString)))->modify('first day of next month');

			$intervalQuery = Criteria::expr()->andX(
				Criteria::expr()->gte('startDate', $beginOfTheMonth),
				Criteria::expr()->lt('startDate', $endOfTheMonth)
			);
		}

		// If both dateTimeFrom and dateTimeTo query parameters are given, assignment within that interval are returned.
		if ($dateTimeFrom && $dateTimeTo) {
			$newDateTimeTo = (new DateTime($dateTimeTo->format(Constants::DateFormatString)))->modify('+1 day');

			$intervalQuery = Criteria::expr()->andX(
				Criteria::expr()->gte('startDate', $dateTimeFrom->format(Constants::DateFormatString)),
				Criteria::expr()->lt('startDate', $newDateTimeTo)
			);
		}

		// if only dateTimeFrom and no dateTimeTo provided, return assignments from the dateTimeFrom value to the end of that month
		if ($dateTimeFrom && !$dateTimeTo) {
			$endOfTheMonth = (new DateTime($dateTimeFrom->format(Constants::DateFormatString)))->modify('first day of next month');

			$intervalQuery = Criteria::expr()->andX(
				Criteria::expr()->gte('startDate', $dateTimeFrom->format(Constants::DateFormatString)),
				Criteria::expr()->lt('startDate', $endOfTheMonth)
			);
		}

		// if only dateTimeTo and no dateTimeFrom provided, return assignments from the start of the month of the dateTimeFrom to the dateTimeFrom value
		if (!$dateTimeFrom && $dateTimeTo) {
			$beginOfTheMonth = (new DateTime($dateTimeTo->format(Constants::DateFormatString)))->modify('first day of this month');
			$newDateTimeTo = (new DateTime($dateTimeTo->format(Constants::DateFormatString)))->modify('+1 day');

			$intervalQuery = Criteria::expr()->andX(
				Criteria::expr()->gte('startDate', $beginOfTheMonth),
				Criteria::expr()->lt('startDate', $newDateTimeTo)
			);
		}

		return Criteria::create()->andWhere($intervalQuery);
	}

	/**
	 * Add leftJoin to ShiftSwapRequest.
	 *
	 * @param QueryBuilder $qb
	 *
	 * @return QueryBuilder
	 */
	private function addShiftSwapRequestJoin(QueryBuilder $qb)
	{
		$rootAlias = $qb->getRootAliases()[0];

		return $qb
			->addSelect('shiftSwapRequest')
			->leftJoin($rootAlias.'.shiftSwapRequests', 'shiftSwapRequest', 'WITH', $qb->expr()->andX(
				$qb->expr()->eq($rootAlias.'.id', 'shiftSwapRequest.assignment'),
				$qb->expr()->in('shiftSwapRequest.status', array(
					ShiftSwapRequestStatus::UNPROCESSED_BY_RECEIVER,
					ShiftSwapRequestStatus::GRANTED_BY_RECEIVER,
					ShiftSwapRequestStatus::GRANTED_BY_PLANNER,
				))
			));
	}

	/**
	 * Exclude unassigned Assignments that the employee has mark as Preferenced in Register.
	 *
	 * @param QueryBuilder $qb
	 * @param $employeeId
	 *
	 * @return QueryBuilder
	 */
	private function excludePreferencedUnassignedAssignmentCriteria(QueryBuilder $qb, $employeeId)
	{
		$rootAlias = $qb->getRootAliases()[0];

		return $qb
			->addSelect('preferenceRegister')
			->leftJoin($rootAlias.'.registers', 'preferenceRegister', 'WITH', $qb->expr()->andX(
				$qb->expr()->eq($rootAlias.'.id', 'preferenceRegister.assignment'),
				$qb->expr()->eq('preferenceRegister.type', ':registerTypePreference'),
				$qb->expr()->eq('preferenceRegister.employee', ':preferenceRegisterEmployeeId')
			))
			->setParameter('registerTypePreference', RegisterType::PREFERENCE)
			->setParameter('preferenceRegisterEmployeeId', $employeeId)
			->andHaving('count(preferenceRegister.id) = 0');
	}

	/**
	 * Exclude Unassigned assigments on a day where the Client is already scheduled.
	 *
	 * @param QueryBuilder $qb
	 * @param $employeeId
	 *
	 * @return QueryBuilder
	 */
	private function excludeAlreadyScheduledDaysCriteria(QueryBuilder $qb, $employeeId)
	{
		$rootAlias = $qb->getRootAliases()[0];

		return $qb
			->leftJoin(Assignment::class, 'assignedAssignment', 'WITH', $qb->expr()->andX(
				$qb->expr()->eq("DATE_DIFF(assignedAssignment.startDate, $rootAlias.startDate)", 0),
				$qb->expr()->eq('assignedAssignment.employee', ':assignedAssignmentEmployeeId'),
				$qb->expr()->eq('assignedAssignment.published', true)
			))
			->setParameter('assignedAssignmentEmployeeId', $employeeId)
			->andHaving('count(assignedAssignment.id) = 0');
	}

	/**
	 * Exclude Unassigned assigments on a day where the Client is unavailable.
	 *
	 * @param QueryBuilder $qb
	 * @param $employeeId
	 *
	 * @return QueryBuilder
	 */
	private function excludeUnavailableDaysCriteria(QueryBuilder $qb, $employeeId)
	{
		$rootAlias = $qb->getRootAliases()[0];

		return $qb
			->leftJoin(Register::class, 'unavailableRegister', 'WITH', $qb->expr()->andX(
				$qb->expr()->eq("DATE_DIFF(unavailableRegister.startDate, $rootAlias.startDate)", 0),
				$qb->expr()->eq('unavailableRegister.employee', ':unavailableRegisterEmployeeId'),
				$qb->expr()->in('unavailableRegister.type', array(
					RegisterType::UNAVAILABLE,
					RegisterType::VACATION,
				))
			))
			->setParameter('unavailableRegisterEmployeeId', $employeeId)
			->andHaving('count(unavailableRegister.id) = 0');
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

		$departmentQuery = null !== $departmentId ? $qb->expr()->eq("$rootAlias.department", $departmentId) : null;
		$officeQuery = null !== $officeId ? $qb->expr()->eq("$rootAlias.department", $officeId) : null;

		// TODO This becomes redundent if Office becomes a seperate table during DB switch
		// Check if provided office exists
		$officeRepo = $this->getEntityManager()->getRepository(Office::class);
		/** @var DepartmentRepository $departmentRepo */
		$departmentRepo = $this->getEntityManager()->getRepository(Department::class);

		if ($officeId) {
			//Default officeQuery when officeId doesn't return a valid Office
			$officeQuery = $qb->expr()->isNull("$rootAlias.id");

			/** @var Office $office */
			$office = $officeRepo->find($officeId);
			//Overwrite officeQuery to use all sub Departments of the office if it exist
			if ($office) {
				$subDepartments = $departmentRepo
					->getChildrenQueryBuilder($office->getDepartmentRoot())
					->getQuery()
					->getScalarResult();
				$subDepartmentIds = array_column($subDepartments, 'node_id');
				$officeQuery = $qb->expr()->in("$rootAlias.department", $subDepartmentIds);
			}
		}

		return $qb
			->andWhere($qb->expr()->andX(
				$departmentQuery,
				$officeQuery
			));
	}
}
