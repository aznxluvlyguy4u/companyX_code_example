<?php

namespace AppBundle\EventListener;

use AppBundle\Doctrine\AssignmentFilter;
use AppBundle\Doctrine\DepartmentFilter;
use AppBundle\Doctrine\EmployeeFilter;
use AppBundle\Doctrine\RegisterFilter;
use AppBundle\Entity\Assignment;
use AppBundle\Entity\Client;
use AppBundle\Entity\Department;
use AppBundle\Entity\Employee;
use AppBundle\Entity\SystemConfig;
use AppBundle\Enumerator\DoctrineFilter;
use AppBundle\Enumerator\CompanyXUserRole;
use AppBundle\Repository\AssignmentRepository;
use AppBundle\Repository\EmployeeRepository;
use AppBundle\Repository\SystemConfigRepository;
use Doctrine\ORM\EntityManager;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTAuthenticatedEvent;

class OnAuthenticationSuccessSetFilterListener
{
	/**
	 * @var EntityManager
	 */
	protected $em;

	/** @var Client */
	protected $client;

	/**
	 * OnAuthenticationSuccessSetFilterListener constructor.
	 *
	 * @param EntityManager $customerEntityManager
	 */
	public function __construct(EntityManager $customerEntityManager)
	{
		$this->em = $customerEntityManager;
	}

	/**
	 * Fired when onAuthenticationSuccess event is dispatched.
	 *
	 * @param JWTAuthenticatedEvent $event
	 */
	public function onAuthenticationSuccess(JWTAuthenticatedEvent $event)
	{
		/** @var Client $client */
		$client = $event->getToken()->getUser();

		$this->setAssignmentFilter($client);

		$this->setEmployeeFilter($client);

		$this->setRegisterFilter($client);

		$this->setDepartmentFilter($client);
	}

	/**
	 * Enable/Disable 'assignment_filter' depending on the authenticated Client.
	 *
	 * @param Client $client
	 */
	private function setAssignmentFilter(Client $client)
	{
		// Disable filters if authenticated Client is a ADMINISTRATORS and has ROLE_CREATING_SCHEDULES OR has access to Telephone list
		if ($client->canAssignForOtherEmployees() || $client->hasRole(CompanyXUserRole::TELEPHONELIST_ACCESS)) {
			$this->disableFilter(DoctrineFilter::ASSIGNMENT_FILTER);

			return;
		}

		// Find the assignments that are associated with the Client in ShiftSwapRequests beforehand and pass it to the filter
		/** @var AssignmentRepository $assignmentRepository */
		$assignmentRepository = $this->em->getRepository(Assignment::class);
		$extraAllowedAssignments = $assignmentRepository->findAssignmentsFromShiftSwapRequestsByEmployee($client->getEmployee()->getId(), true);
		// If array is empty, adds a single id = 0 in the array so that the IN (0) still works
		$extraAllowedAssignments = empty($extraAllowedAssignments) ? [0] : $extraAllowedAssignments;

		if (!$this->em->getFilters()->isEnabled(DoctrineFilter::ASSIGNMENT_FILTER)) {
			/** @var AssignmentFilter $filter */
			$filter = $this->em->getFilters()->enable(DoctrineFilter::ASSIGNMENT_FILTER);
			$filter->setClient($client);
			$filter->setExtraAllowedAssignments($extraAllowedAssignments);
			$this->em->clear();
		}
	}

	/**
	 * Enable/Disable 'employee_filter' depending on the authenticated Client.
	 *
	 * @param Client $client
	 */
	private function setEmployeeFilter(Client $client)
	{
		// Check for DOR_PHONELIST_RESTRICT to see if restriction should be on
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = $this->em->getRepository(SystemConfig::class);
		$restriction = $systemConfigRepo->getPhonelistRestriction();
		// Disable filters if DOR_PHONELIST_RESTRICT is or and authenticated Client has ALL_RIGHTS
		if (!$restriction || $client->hasRole(CompanyXUserRole::ALL_RIGHTS)) {
			$this->disableFilter(DoctrineFilter::EMPLOYEE_FILTER);

			return;
		}

		/** @var EmployeeRepository $employeeRepo */
		$employeeRepo = $this->em->getRepository(Employee::class);
		$directColleagueIds = $employeeRepo->findDirectColleaguesByClient($client, true);

		if (!$this->em->getFilters()->isEnabled(DoctrineFilter::EMPLOYEE_FILTER)) {
			/** @var EmployeeFilter $filter */
			$filter = $this->em->getFilters()->enable(DoctrineFilter::EMPLOYEE_FILTER);
			$filter->setDirectColleagueIds($directColleagueIds);
			$this->em->clear();
		}
	}

	/**
	 * Enable/Disable 'register_filter' depending on the authenticated Client.
	 *
	 * @param Client $client
	 */
	private function setRegisterFilter(Client $client)
	{
		// Disable filters if authenticated Client has ROLE_ALL_RIGHTS or ROLE_HOURS_REGISTER
		if ($client->canRegisterForOtherEmployees()) {
			$this->disableFilter(DoctrineFilter::REGISTER_FILTER);

			return;
		}

		if (!$this->em->getFilters()->isEnabled(DoctrineFilter::REGISTER_FILTER)) {
			/** @var RegisterFilter $filter */
			$filter = $this->em->getFilters()->enable(DoctrineFilter::REGISTER_FILTER);
			$filter->setClient($client);
			$this->em->clear();
		}
	}

	/**
	 * Enable/Disable 'department_filter' depending on the authenticated Client.
	 *
	 * @param Client $client
	 *
	 * @return mixed
	 */
	private function setDepartmentFilter(Client $client)
	{
		// Check for DOR_PHONELIST_RESTRICT to see if restriction should be on
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = $this->em->getRepository(SystemConfig::class);
		$restriction = $systemConfigRepo->getPhonelistRestriction();

		if (!$restriction || $client->hasRole(CompanyXUserRole::ALL_RIGHTS)) {
			$this->disableFilter(DoctrineFilter::DEPARTMENT_FILTER);

			return;
		}

		// Check if the client is an employee of the given departmentId or officeId
		$allowedDepartments = array_map(
			function ($department) {
				/* @var Department $department */
				return $department->getId();
			},
			$client->getDepartments()->toArray()
		);

		if (!$this->em->getFilters()->isEnabled(DoctrineFilter::DEPARTMENT_FILTER)) {
			/** @var DepartmentFilter $filter */
			$filter = $this->em->getFilters()->enable(DoctrineFilter::DEPARTMENT_FILTER);
			$filter->setAllowedDepartments($allowedDepartments);
			$this->em->clear();
		}
	}

	/**
	 * Disable a given Doctrine filter.
	 *
	 * @param $filter
	 */
	private function disableFilter($filter)
	{
		if (!$this->em->getFilters()->has($filter)) {
			throw new \InvalidArgumentException('Filter doesn\'t exist');
		}

		if ($this->em->getFilters()->isEnabled($filter)) {
			$this->em
				->getFilters()
				->disable($filter);
			$this->em->clear();
		}
	}
}
