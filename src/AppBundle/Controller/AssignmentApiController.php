<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Assignment;
use AppBundle\Entity\Client;
use AppBundle\Entity\Employee;
use AppBundle\Enumerator\SerializerGroup;
use AppBundle\Repository\AssignmentRepository;
use AppBundle\Repository\EmployeeRepository;
use AppBundle\Util\Constants;
use AppBundle\Util\ResponseUtil;
use DateTime;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use Nelmio\ApiDocBundle\Annotation\Operation;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;

/**
 * Class AssignmentApiController.
 *
 * @Route("/api/v1/assignments")
 */
class AssignmentApiController extends BaseApiController
{
	/**
	 * Get all assignments.
	 * If both dateFrom and dateTo query parameters are given, assignments within the interval dateFrom and dateTo are returned
	 * If only dateFrom and no dateTo given, assignments are returned from the dateFrom value to the end of that month.
	 * If only dateTo and no dateFrom given, assignments are returned from the start of the month of the dateFrom to the dateFrom value.
	 * If no dateFrom and no dateTo given, assignments are returned of the current month.
	 * If no assignmentState given, only assigned assignments are returned.
	 * if assignmentState=unassigned given, only unassigned assignments are returned.
	 * if assignmentState=all given, a mixed result set containing assigned and unassigned assignments are returned.
	 *
	 * @Operation(
	 *     tags={"Assignments"},
	 *     summary="Get all assignments the clients are allowed to see.",
	 *     @SWG\Parameter(
	 *            name="Authorization",
	 *            in="header",
	 *            required=true,
	 *            type="string",
	 *            default="Bearer {jwt}",
	 *            description="Authorization"
	 *        ),
	 *     @SWG\Parameter(
	 *         name="dateFrom",
	 *         in="query",
	 *         description="Provide a start date query parameter to fetch assignments of an Employee from the specified date",
	 *         required=false,
	 *         type="string"
	 *     ),
	 *     @SWG\Parameter(
	 *         name="dateTo",
	 *         in="query",
	 *         description="Provide a end date query parameter to fetch assignments of an Employee to the specified end date",
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
	 *         type="integer",
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
	 * @param $dateFrom
	 * @param $dateTo
	 * @param $assignmentState
	 * @param $officeId
	 * @param $departmentId
	 *
	 * @return Response
	 * @QueryParam(name="dateFrom", requirements="%iso_date_regex%", default=null, description="start of the date range")
	 * @QueryParam(name="dateTo", requirements="%iso_date_regex%", default=null, description="end of the date range")
	 * @QueryParam(name="assignmentState", requirements="(unassigned|all)", default=null, description="filter for assignment state")
	 * @QueryParam(name="officeId", requirements="\d+", strict=true, nullable=true, default=null, description="ID of an office")
	 * @QueryParam(name="departmentId", requirements="\d+", strict=true, nullable=true, default=null, description="ID of a department")
	 *
	 * @Route("")
	 * @Method("GET")
	 */
	public function getAssignments($dateFrom, $dateTo, $assignmentState, $officeId, $departmentId)
	{
		/** @var Client $client */
		$client = $this->getUser();

		$dateTimeFrom = $dateFrom ? new DateTime($dateFrom) : null;
		$dateTimeTo = $dateTo ? new DateTime($dateTo) : null;
		$departmentId = ('' === $departmentId) ? null : $departmentId;
		$officeId = ('' === $officeId) ? null : $officeId;

		/** @var AssignmentRepository $assignmentRepository */
		$assignmentRepository = $this->getEntityManager()
			->getRepository(Assignment::class);

		$assignments = $assignmentRepository->findAllWithParameters($client, $dateTimeFrom, $dateTimeTo,
			$assignmentState, $officeId, $departmentId);

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
	 * Get a single assignment by ID.
	 *
	 * @Operation(
	 *     tags={"Assignments"},
	 *     summary="Get Assignment by ID",
	 *     @SWG\Parameter(
	 *            name="Authorization",
	 *            in="header",
	 *            required=true,
	 *            type="string",
	 *            default="Bearer {jwt}",
	 *            description="Authorization"
	 *        ),
	 *     @SWG\Parameter(
	 *            name="assignmentId",
	 *            in="path",
	 *            required=true,
	 *            type="integer"
	 *        ),
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
	 * @param $assignmentId
	 * @Route("/{assignmentId}", requirements={"assignmentId" = "\d+"})
	 * @Method("GET")
	 *
	 * @return Response
	 */
	public function getAssignment($assignmentId)
	{
		$client = $this->getUser();

		/** @var AssignmentRepository $assignmentRepository */
		$assignmentRepository = $this->getEntityManager()
			->getRepository(Assignment::class);

		$assignment = $assignmentRepository->findOneByEmployee($assignmentId, $client->getEmployee()->getId());

		if (!$assignment) {
			return ResponseUtil::HTTP_NOT_FOUND();
		}

		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();
		$result = $serializer->normalize($assignment, Constants::JSON_SERIALIZATON_FORMAT, array(
			'groups' => array(
				SerializerGroup::ASSIGNMENTS,
			),
		));

		return ResponseUtil::HTTP_OK($result);
	}

	/**
	 * Get employees who are eligible for an Assignment by Assignment ID.
	 *
	 * @Operation(
	 *     tags={"Assignments"},
	 *     summary="Get employees who are eligible for an Assignment by Assignment ID",
	 *     @SWG\Parameter(
	 *            name="Authorization",
	 *            in="header",
	 *            required=true,
	 *            type="string",
	 *            default="Bearer {jwt}",
	 *            description="Authorization"
	 *        ),
	 *     @SWG\Parameter(
	 *            name="assignmentId",
	 *            in="path",
	 *            required=true,
	 *            type="integer"
	 *        ),
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
	 * @param $assignmentId
	 * @Route("/{assignmentId}/eligible_employees", requirements={"assignmentId" = "\d+"})
	 * @Method("GET")
	 *
	 * @return Response
	 */
	public function getEligibleEmployeesForAssignment($assignmentId)
	{
		$client = $this->getUser();

		/** @var EmployeeRepository $employeeRepository */
		$employeeRepository = $this->getEntityManager()
			->getRepository(Employee::class);

		$employees = $employeeRepository->findEligibleForAssignment($assignmentId);

		if (!$employees) {
			return ResponseUtil::HTTP_NOT_FOUND();
		}

		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();
		$result = $serializer->normalize($employees, Constants::JSON_SERIALIZATON_FORMAT, array(
			'groups' => array(
				SerializerGroup::ELIGIBLE_EMPLOYEES,
			),
		));

		return ResponseUtil::HTTP_OK($result);
	}
}
