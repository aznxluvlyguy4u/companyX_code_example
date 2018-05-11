<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Assignment;
use AppBundle\Entity\ClockInterval;
use AppBundle\Entity\ClockMoment;
use AppBundle\Entity\Employee;
use AppBundle\Entity\Register;
use AppBundle\Entity\ShiftSwapRequest;
use AppBundle\Entity\SystemConfig;
use AppBundle\Enumerator\QueryParameter;
use AppBundle\Enumerator\SerializerGroup;
use AppBundle\Repository\AssignmentRepository;
use AppBundle\Repository\ClockIntervalRepository;
use AppBundle\Repository\ClockMomentRepository;
use AppBundle\Repository\EmployeeRepository;
use AppBundle\Repository\RegisterRepository;
use AppBundle\Repository\ShiftSwapRequestRepository;
use AppBundle\Repository\SystemConfigRepository;
use AppBundle\Service\EmployeePrivacyService;
use AppBundle\Service\PaginatorService;
use AppBundle\Util\Constants;
use AppBundle\Util\ResponseUtil;
use DateTime;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use Symfony\Component\HttpFoundation\Request;
use Nelmio\ApiDocBundle\Annotation\Operation;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * Class EmployeeApiController.
 *
 * @Route("/api/v1/employees")
 */
class EmployeeApiController extends BaseApiController
{
	/**
	 * TODO: not sending parameter name when not filled.
	 *
	 * @Operation(
	 *     tags={"Employees"},
	 *     summary="Find all employees, or find employees by office ID or department ID",
	 *     @SWG\Parameter(
	 *            name="Authorization",
	 *            in="header",
	 *            required=true,
	 *            type="string",
	 *            default="Bearer {jwt}",
	 *            description="Authorization"
	 *        ),
	 *     @SWG\Parameter(
	 *         name="officeId",
	 *         in="query",
	 *         description="ID of office",
	 *         required=false,
	 *         type="integer"
	 *     ),
	 *     @SWG\Parameter(
	 *         name="departmentId",
	 *         in="query",
	 *         description="ID of department",
	 *         required=false,
	 *         type="integer"
	 *     ),
	 *     @SWG\Parameter(
	 *         name="assignmentRegisterDate",
	 *         in="query",
	 *         description="Provide a date query parameter to fetch assignments and registers of an Employee by a specific date. If not given defaults at current date",
	 *         required=false,
	 *         type="string"
	 *     ),
	 *     @SWG\Parameter(
	 *         name="name",
	 *         in="query",
	 *         description="Search by first or last name",
	 *         required=false,
	 *         type="string"
	 *     ),
	 *     @SWG\Parameter(
	 *         name="page",
	 *         in="query",
	 *         description="Page number of the paginated result, defaults at 1 when not given",
	 *         required=false,
	 *         type="integer"
	 *     ),
	 *     @SWG\Parameter(
	 *         name="limit",
	 *         in="query",
	 *         description="Limit of the number of result per page, defaults at 50 when not given",
	 *         required=false,
	 *         type="integer"
	 *     ),
	 *     @SWG\Parameter(
	 *         name="sort",
	 *         in="query",
	 *         description="Field name to sort. Allowed fields are: ""lastname"", ""firstname"", ""emailAddress"", ""phoneNumber"". If not given defaults at ""lastname""",
	 *         required=false,
	 *         type="string",
	 *     	   enum={"lastname", "firstname", "emailAddress", "phoneNumber"}
	 *     ),
	 *     @SWG\Parameter(
	 *         name="direction",
	 *         in="query",
	 *         description="Limit of the number of result per page. Allowed fields are: ""asc"", ""desc"", If not given defaults at ""asc""",
	 *         required=false,
	 *         type="string",
	 *     	   enum={"asc", "desc"}
	 *     ),
	 *     @SWG\Response(
	 *         response="200",
	 *         description="OK"
	 *     ),
	 *     @SWG\Response(
	 *         response="401",
	 *         description="Unauthorized"
	 *     ),
	 *     @SWG\Response(
	 *         response="403",
	 *         description="Forbidden"
	 *     ),
	 *     @SWG\Response(
	 *         response="404",
	 *         description="Not Found"
	 *     ),
	 *     @SWG\Response(
	 *         response="500",
	 *         description="Internal Server Error"
	 *     )
	 * )
	 *
	 *
	 * @Route("")
	 * @Method("GET")
	 * @QueryParam(name="officeId", requirements="\d+", strict=true, nullable=true, default=null, description="ID of office")
	 * @QueryParam(name="departmentId", requirements="\d+", strict=true, nullable=true, default=null, description="ID of department")
	 * @QueryParam(name="assignmentRegisterDate", requirements="%iso_date_regex%", default=null, nullable=true, description="Provide a date query parameter to fetch assignments and registers of an Employee by a specific date. If not given defaults at current date")
	 * @QueryParam(name="name", requirements="\w+", default=null, nullable=true, description="Search by first or last name")
	 * @QueryParam(name="page", requirements="\d+",  strict=true, nullable=true, default=1,  description="Page number of the paginated result, defaults at 1 when not given")
	 * @QueryParam(name="limit", requirements="\d+", strict=true, nullable=true, default=1000,  description="Limit of the number of result per page, defaults at 50 when not given")
	 * @QueryParam(name="sort", requirements="(firstname|lastname|phoneNumber|emailAddress)", default="lastname", nullable=true, description="Field name to sort, defaults at lastname when not given")
	 * @QueryParam(name="direction", requirements="(asc|desc)", default="asc", nullable=true, description="Sort direction, defaults at asc when not given")
	 *
	 * @param $officeId
	 * @param $departmentId
	 * @param $assignmentRegisterDate
	 * @param $name
	 * @param $page
	 * @param $limit
	 * @param $sort
	 * @param $direction
	 * @param EmployeePrivacyService $employeePrivacyService
	 * @param PaginatorService       $paginatorService
	 *
	 * @return Response
	 */
	public function getEmployees(
		$officeId,
		$departmentId,
		$assignmentRegisterDate,
		$name,
		$page,
		$limit,
		$sort,
		$direction,
		EmployeePrivacyService $employeePrivacyService,
		PaginatorService $paginatorService
	) {
		/**
		 * Get date from assignmentRegisterDate query parameter, if null defaults today.
		 *
		 * @var DateTime
		 */
		$assignmentRegisterDate = $assignmentRegisterDate ? new DateTime($assignmentRegisterDate) : new DateTime();

		$departmentId = ('' === $departmentId) ? null : $departmentId;
		$officeId = ('' === $officeId) ? null : $officeId;

		/** @var EmployeeRepository $employeeRepository */
		$employeeRepository = $this->getEntityManager()
			->getRepository(Employee::class);

		$queryBuilder = $employeeRepository->findByParametersWithAssignmentsAndRegistersFromDate($departmentId,
			$officeId, $assignmentRegisterDate, $name, true);

		$pagination = $paginatorService->paginate($queryBuilder, $sort, $direction, $page, $limit);

		if (!$pagination->getItems()) {
			return ResponseUtil::HTTP_NOT_FOUND();
		}

		// Hide/show phone number and email address of the employees depending on access right and DOR_EMPLOYEES_PRIVACYMODE
		$employeePrivacyService->privacyModeCheck($pagination->getItems());

		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();
		$result = $serializer->normalize($pagination, Constants::JSON_SERIALIZATON_FORMAT, array(
			'groups' => array(
				SerializerGroup::EMPLOYEES,
			),
		));

		return ResponseUtil::HTTP_OK_PAGINATED($result, $pagination);
	}

	/**
	 * @Operation(
	 *     tags={"Employees"},
	 *     summary="Find employee by ID",
	 *     @SWG\Parameter(
	 *            name="Authorization",
	 *            in="header",
	 *            required=true,
	 *            type="string",
	 *            default="Bearer {jwt}",
	 *            description="Authorization"
	 *        ),
	 *     @SWG\Parameter(
	 *            name="employeeId",
	 *            in="path",
	 *            required=true,
	 *            type="integer",
	 *            description="Id of employee"
	 *        ),
	 *     @SWG\Parameter(
	 *         name="assignmentRegisterDate",
	 *         in="query",
	 *         description="Provide a date query parameter to fetch assignments and registers of an Employee by a specific date. If not given defaults at current date",
	 *         required=false,
	 *         type="string"
	 *     ),
	 *     @SWG\Response(
	 *         response="200",
	 *         description="OK"
	 *     ),
	 *     @SWG\Response(
	 *         response="401",
	 *         description="Unauthorized"
	 *     ),
	 *     @SWG\Response(
	 *         response="403",
	 *         description="Forbidden"
	 *     ),
	 *     @SWG\Response(
	 *         response="404",
	 *         description="Not Found"
	 *     ),
	 *     @SWG\Response(
	 *         response="500",
	 *         description="Internal Server Error"
	 *     )
	 * )
	 *
	 *
	 * @Route("/{employeeId}", requirements={"employeeId" = "\d+"})
	 * @Method("GET")
	 *
	 * @param Request $request
	 * @param $employeeId
	 * @param EmployeePrivacyService $employeePrivacyService
	 *
	 * @return Response
	 */
	public function getEmployee(Request $request, $employeeId, EmployeePrivacyService $employeePrivacyService)
	{
		$queryParams = $request->query->all();
		/**
		 * Get date from query parameter, if null defaults today.
		 *
		 * @var DateTime
		 */
		$dateParam = null != $request->query->get(QueryParameter::ASSIGNMENT_REGISTER_DATE) ?
			new DateTime($request->query->get(QueryParameter::ASSIGNMENT_REGISTER_DATE)) : new DateTime();

		/** @var EmployeeRepository $employeeRepository */
		$employeeRepository = $this->getEntityManager()
			->getRepository(Employee::class);

		$employee = $employeeRepository->findOneWithAssignmentsAndRegistersByDate($employeeId, $dateParam);

		// check if supplied query parameter is valid
		if (!empty($queryParams) && !array_key_exists(QueryParameter::ASSIGNMENT_REGISTER_DATE, $queryParams)) {
			$employee = null;
		}

		if (!$employee) {
			return ResponseUtil::HTTP_NOT_FOUND();
		}

		// Hide/show phone number and email address of the employees depending on access right and DOR_EMPLOYEES_PRIVACYMODE
		$employee = $employeePrivacyService->privacyModeCheck($employee);

		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();
		$result = $serializer->normalize($employee, Constants::JSON_SERIALIZATON_FORMAT, array(
			'groups' => array(
				SerializerGroup::EMPLOYEES,
			),
		));

		return ResponseUtil::HTTP_OK($result);
	}

	/**
	 * Get assignments of an employee by employee ID.
	 * If both dateFrom and dateTo query parameters are given, assignments within that interval are returned.
	 * If only dateFrom and no dateTo given, assignments are returned from the dateFrom value to the end of that month.
	 * If only dateTo and no dateFrom given, assignments are returned from the start of the month of the dateFrom to the dateFrom value.
	 * If no dateFrom and no dateTo given, assignments are returned of the current month
	 * TODO Figure out how to determine if user has access to view assignments of other employees.
	 *
	 * @Operation(
	 *     tags={"Employees"},
	 *     summary="Find assignments of an employee by employee ID.",
	 *     @SWG\Parameter(
	 *            name="Authorization",
	 *            in="header",
	 *            required=true,
	 *            type="string",
	 *            default="Bearer {jwt}",
	 *            description="Authorization"
	 *        ),
	 *     @SWG\Parameter(
	 *            name="employeeId",
	 *            in="path",
	 *            required=true,
	 *            type="integer",
	 *            description="Id of employee"
	 *        ),
	 *     @SWG\Parameter(
	 *         name="dateFrom",
	 *         in="query",
	 *         description="Provide a start date query parameter to fetch assignments of an Employee from that specific date",
	 *         required=false,
	 *         type="string"
	 *     ),
	 *     @SWG\Parameter(
	 *         name="dateTo",
	 *         in="query",
	 *         description="Provide a end date query parameter to fetch assignments of an Employee to that specific date",
	 *         required=false,
	 *         type="string"
	 *     ),
	 *     @SWG\Parameter(
	 *         name="assignmentState",
	 *         in="query",
	 *         description="Optional query parameter to specify assignments in which state are returned",
	 *         required=false,
	 *         type="string"
	 *     ),
	 *     @SWG\Response(
	 *         response="200",
	 *         description="OK"
	 *     ),
	 *     @SWG\Response(
	 *         response="401",
	 *         description="Unauthorized"
	 *     ),
	 *     @SWG\Response(
	 *         response="403",
	 *         description="Forbidden"
	 *     ),
	 *     @SWG\Response(
	 *         response="404",
	 *         description="Not Found"
	 *     ),
	 *     @SWG\Response(
	 *         response="500",
	 *         description="Internal Server Error"
	 *     )
	 * )
	 *
	 *
	 * @QueryParam(name="dateFrom", requirements="%iso_date_regex%", default=null, description="start of the date range")
	 * @QueryParam(name="dateTo", requirements="%iso_date_regex%", default=null, description="end of the date range")
	 * @QueryParam(name="assignmentState", requirements="(unassigned|all)", default=null, description="filter for assignment state")
	 *
	 * @Route("/{employeeId}/assignments", requirements={"employeeId" = "\d+"})
	 * @Method("GET")
	 *
	 * @param $employeeId
	 * @param $dateFrom
	 * @param $dateTo
	 * @param $assignmentState
	 *
	 * @return Response
	 */
	public function getEmployeeAssignments($employeeId, $dateFrom, $dateTo, $assignmentState)
	{
		$dateTimeFrom = $dateFrom ? new DateTime($dateFrom) : null;
		$dateTimeTo = $dateTo ? new DateTime($dateTo) : null;

		/** @var AssignmentRepository $assignmentRepository */
		$assignmentRepository = $this->getEntityManager()
			->getRepository(Assignment::class);

		$assignments = $assignmentRepository->findByEmployeeWithParameters($employeeId,
			$dateTimeFrom, $dateTimeTo, $assignmentState);

		if (!$assignments) {
			return ResponseUtil::HTTP_NOT_FOUND();
		}

		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();
		$result = $serializer->normalize($assignments, Constants::JSON_SERIALIZATON_FORMAT, array(
			'groups' => array(
				SerializerGroup::ASSIGNMENTS,
			),
		));

		return ResponseUtil::HTTP_OK($result);
	}

	/**
	 * Get registers of an employee by employee ID.
	 * If both dateFrom and dateTo query parameters are given, registers within that interval are returned.
	 * If only dateFrom and no dateTo given, registers are returned from the dateFrom value to the end of that month.
	 * If only dateTo and no dateFrom given, registers are returned from the start of the month of the dateFrom to the dateFrom value.
	 * If no dateFrom and no dateTo given, registers are returned of the current month
	 * TODO Figure out how to determine if user has access to view registers of other employees.
	 *
	 * @Operation(
	 *     tags={"Employees"},
	 *     summary="Find registers of an employee by employee ID.",
	 *     @SWG\Parameter(
	 *            name="Authorization",
	 *            in="header",
	 *            required=true,
	 *            type="string",
	 *            default="Bearer {jwt}",
	 *            description="Authorization"
	 *        ),
	 *     @SWG\Parameter(
	 *            name="employeeId",
	 *            in="path",
	 *            required=true,
	 *            type="integer",
	 *            description="Id of employee"
	 *        ),
	 *     @SWG\Parameter(
	 *         name="dateFrom",
	 *         in="query",
	 *         description="Provide a start date query parameter to fetch registers of an Employee from that specific date",
	 *         required=false,
	 *         type="string"
	 *     ),
	 *     @SWG\Parameter(
	 *         name="dateTo",
	 *         in="query",
	 *         description="Provide a end date query parameter to fetch registers of an Employee to that specific date",
	 *         required=false,
	 *         type="string"
	 *     ),
	 *     @SWG\Response(
	 *         response="200",
	 *         description="OK"
	 *     ),
	 *     @SWG\Response(
	 *         response="401",
	 *         description="Unauthorized"
	 *     ),
	 *     @SWG\Response(
	 *         response="403",
	 *         description="Forbidden"
	 *     ),
	 *     @SWG\Response(
	 *         response="404",
	 *         description="Not Found"
	 *     ),
	 *     @SWG\Response(
	 *         response="500",
	 *         description="Internal Server Error"
	 *     )
	 * )
	 *
	 *
	 * @QueryParam(name="dateFrom", requirements="%iso_date_regex%", default=null, description="start of the date range")
	 * @QueryParam(name="dateTo", requirements="%iso_date_regex%", default=null, description="end of the date range")
	 *
	 * @Route("/{employeeId}/registers", requirements={"employeeId" = "\d+"})
	 * @Method("GET")
	 *
	 * @param $employeeId
	 * @param $dateFrom
	 * @param $dateTo
	 *
	 * @return Response
	 */
	public function getEmployeeRegisters($employeeId, $dateFrom, $dateTo)
	{
		$dateTimeFrom = $dateFrom ? new DateTime($dateFrom) : null;
		$dateTimeTo = $dateTo ? new DateTime($dateTo) : null;

		/** @var RegisterRepository $registerRepository */
		$registerRepository = $this->getEntityManager()
			->getRepository(Register::class);

		$registers = $registerRepository->findByEmployeeWithDateParameters($employeeId, $dateTimeFrom, $dateTimeTo);

		if (!$registers) {
			return ResponseUtil::HTTP_NOT_FOUND();
		}

		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();
		$result = $serializer->normalize($registers, Constants::JSON_SERIALIZATON_FORMAT, array(
			'groups' => array(
				SerializerGroup::REGISTERS,
			),
		));

		return ResponseUtil::HTTP_OK($result);
	}

	/**
	 * Get all ShiftSwapRequests of an employee by employee ID.
	 * If both dateFrom and dateTo query parameters are given, shiftSwapRequests within the interval dateFrom and dateTo are returned
	 * If only dateFrom and no dateTo given, shiftSwapRequests are returned from the dateFrom value to the end of that month.
	 * If only dateTo and no dateFrom given, shiftSwapRequests are returned from the start of the month of the dateFrom to the dateFrom value.
	 * If no dateFrom and no dateTo given, shiftSwapRequests are returned of the current month.
	 *
	 * @Operation(
	 *     tags={"Employees"},
	 *     summary="Get all shiftSwapRequests of an employee by employee ID",
	 *     @SWG\Parameter(
	 *            name="Authorization",
	 *            in="header",
	 *            required=true,
	 *            type="string",
	 *            default="Bearer {jwt}",
	 *            description="Authorization"
	 *        ),
	 *     @SWG\Parameter(
	 *            name="employeeId",
	 *            in="path",
	 *            required=true,
	 *            type="integer",
	 *            description="Id of employee"
	 *        ),
	 *     @SWG\Parameter(
	 *         name="dateFrom",
	 *         in="query",
	 *         description="Provide a start date query parameter to fetch shiftSwapRequests of an Employee from that specific date",
	 *         required=false,
	 *         type="string"
	 *     ),
	 *     @SWG\Parameter(
	 *         name="dateTo",
	 *         in="query",
	 *         description="Provide a end date query parameter to fetch shiftSwapRequests of an Employee to that specific date",
	 *         required=false,
	 *         type="string"
	 *     ),
	 *     @SWG\Response(
	 *         response="200",
	 *         description="OK"
	 *     ),
	 *     @SWG\Response(
	 *         response="401",
	 *         description="Unauthorized"
	 *     ),
	 *     @SWG\Response(
	 *         response="403",
	 *         description="Forbidden"
	 *     ),
	 *     @SWG\Response(
	 *         response="404",
	 *         description="Not Found"
	 *     ),
	 *     @SWG\Response(
	 *         response="500",
	 *         description="Internal Server Error"
	 *     )
	 * )
	 *
	 *
	 * @QueryParam(name="dateFrom", requirements="%iso_date_regex%", default=null, description="start of the date range")
	 * @QueryParam(name="dateTo", requirements="%iso_date_regex%", default=null, description="end of the date range")
	 *
	 * @Route("/{employeeId}/shift_swap_requests", requirements={"employeeId" = "\d+"})
	 * @Method("GET")
	 *
	 * @param $employeeId
	 * @param $dateFrom
	 * @param $dateTo
	 *
	 * @return Response
	 */
	public function getEmployeeShiftSwapRequests($employeeId, $dateFrom, $dateTo)
	{
		$entityManager = $this->getEntityManager();

		// Check for systemConfig DOR_FEATURES_SHIFT_SWAP_REQUEST setting
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = $entityManager->getRepository(SystemConfig::class);
		$enableShiftSwapRequest = $systemConfigRepo->getEnableShiftSwapRequest();

		if (!$enableShiftSwapRequest) {
			return ResponseUtil::HTTP_FORBIDDEN();
		}

		$dateTimeFrom = $dateFrom ? new DateTime($dateFrom) : null;
		$dateTimeTo = $dateTo ? new DateTime($dateTo) : null;

		/** @var ShiftSwapRequestRepository $shiftSwapRequestRepository */
		$shiftSwapRequestRepository = $entityManager->getRepository(ShiftSwapRequest::class);

		$shiftSwapRequests = $shiftSwapRequestRepository->findForEmployeeByParameters($employeeId, $dateTimeFrom,
			$dateTimeTo);

		if (!$shiftSwapRequests) {
			return ResponseUtil::HTTP_NOT_FOUND();
		}

		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();
		$result = $serializer->normalize($shiftSwapRequests, Constants::JSON_SERIALIZATON_FORMAT, array(
			'groups' => array(
				SerializerGroup::SHIFT_SWAP_REQUESTS,
			),
		));

		return ResponseUtil::HTTP_OK($result);
	}

	/**
	 * Get all ClockMoments of an employee by employee ID.
	 * If both dateFrom and dateTo query parameters are given, ClockMoments within the interval dateFrom and dateTo are returned
	 * If only dateFrom and no dateTo given, ClockMoments are returned from the dateFrom value to the end of that month.
	 * If only dateTo and no dateFrom given, ClockMoments are returned from the start of the month of the dateFrom to the dateFrom value.
	 * If no dateFrom and no dateTo given, ClockMoments are returned of the current month.
	 *
	 * @Operation(
	 *     tags={"Employees"},
	 *     summary="Get all ClockMoments of an employee by employee ID",
	 *     @SWG\Parameter(
	 *            name="Authorization",
	 *            in="header",
	 *            required=true,
	 *            type="string",
	 *            default="Bearer {jwt}",
	 *            description="Authorization"
	 *        ),
	 *     @SWG\Parameter(
	 *            name="employeeId",
	 *            in="path",
	 *            required=true,
	 *            type="integer",
	 *            description="Id of employee"
	 *        ),
	 *     @SWG\Parameter(
	 *         name="dateFrom",
	 *         in="query",
	 *         description="Provide a start date query parameter to fetch ClockMoments of an Employee from that specific date",
	 *         required=false,
	 *         type="string"
	 *     ),
	 *     @SWG\Parameter(
	 *         name="dateTo",
	 *         in="query",
	 *         description="Provide a end date query parameter to fetch ClockMoments of an Employee to that specific date",
	 *         required=false,
	 *         type="string"
	 *     ),
	 *     @SWG\Response(
	 *         response="200",
	 *         description="OK"
	 *     ),
	 *     @SWG\Response(
	 *         response="401",
	 *         description="Unauthorized"
	 *     ),
	 *     @SWG\Response(
	 *         response="403",
	 *         description="Forbidden"
	 *     ),
	 *     @SWG\Response(
	 *         response="404",
	 *         description="Not Found"
	 *     ),
	 *     @SWG\Response(
	 *         response="500",
	 *         description="Internal Server Error"
	 *     )
	 * )
	 *
	 *
	 * @QueryParam(name="dateFrom", requirements="%iso_date_regex%", default=null, description="start of the date range")
	 * @QueryParam(name="dateTo", requirements="%iso_date_regex%", default=null, description="end of the date range")
	 *
	 * @Route("/{employeeId}/clock_moments", requirements={"employeeId" = "\d+"})
	 * @Method("GET")
	 *
	 * @param $employeeId
	 * @param $dateFrom
	 * @param $dateTo
	 *
	 * @return Response
	 * @Security("has_role('ROLE_ADMINISTRATORS') and has_role('ROLE_HOURS')")
	 */
	public function getEmployeeClockMoments($employeeId, $dateFrom, $dateTo)
	{
		$entityManager = $this->getEntityManager();

		$dateTimeFrom = $dateFrom ? new DateTime($dateFrom) : null;
		$dateTimeTo = $dateTo ? new DateTime($dateTo) : null;

		/** @var ClockMomentRepository $clockMomentRepository */
		$clockMomentRepository = $entityManager->getRepository(ClockMoment::class);

		$clockMoments = $clockMomentRepository->findByEmployeeWithDateParameters($employeeId, $dateTimeFrom,
			$dateTimeTo);

		if (!$clockMoments) {
			return ResponseUtil::HTTP_NOT_FOUND();
		}

		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();
		$result = $serializer->normalize($clockMoments, Constants::JSON_SERIALIZATON_FORMAT, array(
			'groups' => array(
				SerializerGroup::CLOCKMOMENTS,
			),
		));

		return ResponseUtil::HTTP_OK($result);
	}

	/**
	 * Get all ClockIntervals of an employee by employee ID.
	 * If both dateFrom and dateTo query parameters are given, ClockIntervals within the interval dateFrom and dateTo are returned
	 * If only dateFrom and no dateTo given, ClockIntervals are returned from the dateFrom value to the end of that month.
	 * If only dateTo and no dateFrom given, ClockIntervals are returned from the start of the month of the dateFrom to the dateFrom value.
	 * If no dateFrom and no dateTo given, ClockIntervals are returned of the current month.
	 *
	 * @Operation(
	 *     tags={"Employees"},
	 *     summary="Get all ClockIntervals of an employee by employee ID",
	 *     @SWG\Parameter(
	 *            name="Authorization",
	 *            in="header",
	 *            required=true,
	 *            type="string",
	 *            default="Bearer {jwt}",
	 *            description="Authorization"
	 *        ),
	 *     @SWG\Parameter(
	 *            name="employeeId",
	 *            in="path",
	 *            required=true,
	 *            type="integer",
	 *            description="Id of employee"
	 *        ),
	 *     @SWG\Parameter(
	 *         name="dateFrom",
	 *         in="query",
	 *         description="Provide a start date query parameter to fetch ClockIntervals of an Employee from that specific date",
	 *         required=false,
	 *         type="string"
	 *     ),
	 *     @SWG\Parameter(
	 *         name="dateTo",
	 *         in="query",
	 *         description="Provide a end date query parameter to fetch ClockIntervals of an Employee to that specific date",
	 *         required=false,
	 *         type="string"
	 *     ),
	 *     @SWG\Response(
	 *         response="200",
	 *         description="OK"
	 *     ),
	 *     @SWG\Response(
	 *         response="401",
	 *         description="Unauthorized"
	 *     ),
	 *     @SWG\Response(
	 *         response="403",
	 *         description="Forbidden"
	 *     ),
	 *     @SWG\Response(
	 *         response="404",
	 *         description="Not Found"
	 *     ),
	 *     @SWG\Response(
	 *         response="500",
	 *         description="Internal Server Error"
	 *     )
	 * )
	 *
	 *
	 * @QueryParam(name="dateFrom", requirements="%iso_date_regex%", default=null, description="start of the date range")
	 * @QueryParam(name="dateTo", requirements="%iso_date_regex%", default=null, description="end of the date range")
	 *
	 * @Route("/{employeeId}/clock_intervals", requirements={"employeeId" = "\d+"})
	 * @Method("GET")
	 *
	 * @param $employeeId
	 * @param $dateFrom
	 * @param $dateTo
	 *
	 * @return Response
	 * @Security("has_role('ROLE_ADMINISTRATORS') and has_role('ROLE_HOURS')")
	 */
	public function getEmployeeClockIntervals($employeeId, $dateFrom, $dateTo)
	{
		$entityManager = $this->getEntityManager();

		$dateTimeFrom = $dateFrom ? new DateTime($dateFrom) : null;
		$dateTimeTo = $dateTo ? new DateTime($dateTo) : null;

		/** @var ClockIntervalRepository $clockIntervalRepository */
		$clockIntervalRepository = $entityManager->getRepository(ClockInterval::class);

		$clockIntervals = $clockIntervalRepository->findByEmployeeWithDateParameters($employeeId, $dateTimeFrom,
			$dateTimeTo);

		if (!$clockIntervals) {
			return ResponseUtil::HTTP_NOT_FOUND();
		}

		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();
		$result = $serializer->normalize($clockIntervals, Constants::JSON_SERIALIZATON_FORMAT, array(
			'groups' => array(
				SerializerGroup::CLOCKINTERVALS,
			),
		));

		return ResponseUtil::HTTP_OK($result);
	}
}
