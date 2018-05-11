<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Department;
use AppBundle\Entity\Office;
use AppBundle\Enumerator\SerializerGroup;
use AppBundle\Repository\DepartmentRepository;
use AppBundle\Repository\OfficeRepository;
use AppBundle\Util\Constants;
use AppBundle\Util\ResponseUtil;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use Symfony\Component\HttpFoundation\Response;
use Nelmio\ApiDocBundle\Annotation\Operation;
use Swagger\Annotations as SWG;
use Symfony\Component\Serializer\Serializer;
use AppBundle\Enumerator\CompanyXUserRole;

/**
 * Class OfficeApiController.
 *
 * @Route("/api/v1/offices")
 */
class OfficeApiController extends BaseApiController
{
	/**
	 * @Operation(
	 *     tags={"Offices"},
	 *     summary="Returns all offices",
	 *     @SWG\Parameter(
	 *            name="Authorization",
	 *            in="header",
	 *            required=true,
	 *            type="string",
	 *            default="Bearer {jwt}",
	 *            description="Authorization"
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
	 * @Route("")
	 * @Method("GET")
	 *
	 * @return Response
	 */
	public function getOffices()
	{
		$client = $this->getUser();

		/** @var OfficeRepository $officeRepository */
		$officeRepository = $this->getEntityManager()
			->getRepository(Office::class);

		/** @var Office[]\ArrayCollection $offices */
		$offices = $officeRepository->findAllWithRestrictionCheck($client);

		if (!$offices) {
			return ResponseUtil::HTTP_NOT_FOUND();
		}

		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();
		$result = $serializer->normalize($offices, Constants::JSON_SERIALIZATON_FORMAT, array(
			'groups' => array(
				SerializerGroup::OFFICES,
			),
		));

		return ResponseUtil::HTTP_OK($result);
	}

	/**
	 * @Operation(
	 *     tags={"Offices"},
	 *     summary="Find office by ID",
	 *     @SWG\Parameter(
	 *            name="Authorization",
	 *            in="header",
	 *            required=true,
	 *            type="string",
	 *            default="Bearer {jwt}",
	 *            description="Authorization"
	 *        ),
	 *     @SWG\Parameter(
	 *            name="officeId",
	 *            in="path",
	 *            required=true,
	 *            type="integer",
	 *            description="Id of office"
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
	 * @param $officeId
	 *
	 * @Route("/{officeId}", requirements={"officeId" = "\d+"})
	 * @Method("GET")
	 *
	 * @return Response
	 */
	public function getOffice($officeId)
	{
		$client = $this->getUser();

		/** @var OfficeRepository $officeRepository */
		$officeRepository = $this->getEntityManager()
			->getRepository(Office::class);

		/** @var Office $office */
		$office = $officeRepository->findWithRestrictionCheck($officeId, $client);
		if (!$office) {
			return ResponseUtil::HTTP_NOT_FOUND();
		}

		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();
		$result = $serializer->normalize($office, Constants::JSON_SERIALIZATON_FORMAT, array(
			'groups' => array(
				SerializerGroup::OFFICES,
				SerializerGroup::OFFICE,
			),
		));

		return ResponseUtil::HTTP_OK($result);
	}

	/**
	 * @Operation(
	 *     tags={"Offices"},
	 *     summary="Returns all departments of an Office by ID",
	 *     @SWG\Parameter(
	 *            name="Authorization",
	 *            in="header",
	 *            required=true,
	 *            type="string",
	 *            default="Bearer {jwt}",
	 *            description="Authorization"
	 *        ),
	 *     @SWG\Parameter(
	 *            name="officeId",
	 *            in="path",
	 *            required=true,
	 *            type="integer",
	 *            description="Id of employee"
	 *        ),
	 *     @SWG\Parameter(
	 *         name="role",
	 *         in="query",
	 *         description="name of a role to query a list of Departments that the given role has access to",
	 *         required=false,
	 *         type="string",
	 *     	   enum={"CREATING_SCHEDULES","HOURS_REGISTER","HOURS_ACCORD","VACATION_MANAGEMENT","EMPLOYEES_CONTROL"}
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
	 * @param $officeId
	 * @param $role
	 *
	 * @QueryParam(name="role", requirements="(CREATING_SCHEDULES|HOURS_REGISTER|HOURS_ACCORD|VACATION_MANAGEMENT|EMPLOYEES_CONTROL)", default=null, nullable=true, allowBlank=false, description="name of a role to query a list of Departments that the given role has access to")
	 *
	 * @Route("/{officeId}/departments", requirements={"officeId" = "\d+"})
	 * @Method("GET")
	 *
	 * @return Response
	 */
	public function getDepartments($officeId, $role)
	{
		$client = $this->getUser();

		/** @var DepartmentRepository $departmentRepository */
		$departmentRepository = $this->getEntityManager()
			->getRepository(Department::class);

		if ($role) {
			$departments = $departmentRepository->findWriteAccessDepartmentsTreeByOfficeForClientAndRole($officeId, $client, constant("AppBundle\\Enumerator\\CompanyXUserRole::$role"));
		} else {
			$departments = $departmentRepository->findByOfficeWithRestrictionCheck($officeId, $client);
		}

		// Company user has access to all offices via id=0
		if (!$departments || !$client->hasRole(CompanyXUserRole::SUPER_USER) && 0 === intval($officeId)) {
			return ResponseUtil::HTTP_NOT_FOUND();
		}

		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();
		$result = $serializer->normalize($departments, Constants::JSON_SERIALIZATON_FORMAT, array(
			'groups' => array(
				SerializerGroup::DEPARTMENTS,
			),
		));

		return ResponseUtil::HTTP_OK($result);
	}

	/**
	 * @Operation(
	 *     tags={"Offices"},
	 *     summary="Returns a department by ID of an Office by ID",
	 *     @SWG\Parameter(
	 *            name="Authorization",
	 *            in="header",
	 *            required=true,
	 *            type="string",
	 *            default="Bearer {jwt}",
	 *            description="Authorization"
	 *        ),
	 *     @SWG\Parameter(
	 *            name="officeId",
	 *            in="path",
	 *            required=true,
	 *            type="integer",
	 *            description="Id of employee"
	 *        ),
	 *     @SWG\Parameter(
	 *            name="departmentId",
	 *            in="path",
	 *            required=true,
	 *            type="integer",
	 *            description="Id of department"
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
	 * @param $officeId
	 * @param $departmentId
	 *
	 * @Route("/{officeId}/departments/{departmentId}", requirements={"officeId" = "\d+", "departmentId" = "\d+"})
	 * @Method("GET")
	 *
	 * @return Response
	 */
	public function getDepartment($officeId, $departmentId)
	{
		$client = $this->getUser();

		/** @var DepartmentRepository $departmentRepository */
		$departmentRepository = $this->getEntityManager()
			->getRepository(Department::class);

		/** @var Department $department */
		$department = $departmentRepository->findOneByOfficeWithRestrictionCheck($officeId, $departmentId, $client);
		if (!$department) {
			return ResponseUtil::HTTP_NOT_FOUND();
		}

		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();
		$result = $serializer->normalize($department, Constants::JSON_SERIALIZATON_FORMAT, array(
			'groups' => array(
				SerializerGroup::DEPARTMENTS,
			),
		));

		return ResponseUtil::HTTP_OK($result);
	}
}
