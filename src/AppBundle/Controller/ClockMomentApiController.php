<?php

namespace AppBundle\Controller;

use AppBundle\Entity\ClockMoment;
use AppBundle\Entity\Department;
use AppBundle\Entity\Employee;
use AppBundle\Entity\Register;
use AppBundle\Entity\Client;
use AppBundle\Enumerator\JsonResponseMessage;
use AppBundle\Enumerator\SerializerGroup;
use AppBundle\Util\Constants;
use AppBundle\Util\ResponseUtil;
use Doctrine\ORM\EntityManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use Symfony\Component\HttpFoundation\Request;
use Nelmio\ApiDocBundle\Annotation\Operation;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Validator\ConstraintViolation;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * Class ClockMomentApiController.
 *
 * @Route("/api/v1/clock_moments")
 */
class ClockMomentApiController extends BaseApiController
{
	// TODO determine proper GET behaviour before finishing this endpoint
	//    /**
	//     * @ApiDoc(
	//     *   section = "ClockMoments",
	//     *   parameters={
	//     *      {
	//     *        "name"="dateFrom",
	//     *        "dataType"="string",
	//     *        "required"=false,
	//     *        "description"="Provide a start date query parameter to fetch ClockMoments from that specific date",
	//     *        "format"="?dateFrom=yyyy-mm-dd"
	//     *      },
	//     *     {
	//     *        "name"="dateTo",
	//     *        "dataType"="string",
	//     *        "required"=false,
	//     *        "description"="Provide a end date query parameter to fetch ClockMoments to that specific date",
	//     *        "format"="?dateTo=yyyy-mm-dd"
	//     *      },
	//     *     {
	//     *        "name"="officeId",
	//     *        "dataType"="integer",
	//     *        "required"=false,
	//     *        "description"="ID of office",
	//     *        "format"="?officeId=1"
	//     *      },
	//     *     {
	//     *        "name"="departmentId",
	//     *        "dataType"="integer",
	//     *        "required"=false,
	//     *        "description"="ID of department",
	//     *        "format"="?departmentId=1"
	//     *      },
	//     *     {
	//     *        "name"="employeeId",
	//     *        "dataType"="integer",
	//     *        "required"=false,
	//     *        "description"="ID of an employee",
	//     *        "format"="?departmentId=1"
	//     *      }
	//     *   },
	//     *   resource = true,
	//     *   description = "Get all clockMoments the clients are allowed to see.",
	//     *   requirements={
	//     *     {
	//     *       "name"="Authorization",
	//     *       "dataType"="string",
	//     *       "requirement"="Bearer {jwt}",
	//     *       "description"="A valid non expired access token"
	//     *     }
	//     *   },
	//     *   output = "AppBundle\Entity\ClockMoment[]",
	//     *   statusCodes = {
	//     *      200 = "OK",
	//     *      401 = "Unauthorized",
	//     *      403 = "Forbidden",
	//     *      404 = "Not Found",
	//     *      500 = "Internal Server Error"
	//     *   },
	//     * )
	//     *
	//     * @QueryParam(name="dateFrom", requirements="%iso_date_regex%", default=null, description="start of the date range")
	//     * @QueryParam(name="dateTo", requirements="%iso_date_regex%", default=null, description="end of the date range")
	//     * @QueryParam(name="officeId", requirements="\d+", strict=true, nullable=true, default=null, description="ID of an office")
	//     * @QueryParam(name="departmentId", requirements="\d+", strict=true, nullable=true, default=null, description="ID of a department")
	//     * @QueryParam(name="employeeId", requirements="\d+", strict=true, nullable=true, default=null, description="ID of an employee")
	//     *
	//     * @Route("")
	//     * @Method("GET")
	//     *
	//     * @return Response
	//     * @Security("has_role('ROLE_HOURS_REGISTER')")
	//     */
	//    public function getClockMoments($dateFrom, $dateTo, $officeId, $departmentId, $employeeId)
	//    {
	//        $client = $this->getUser();
	//        /** @var EntityManager $entityManager */
	//        $entityManager = $this->getEntityManager();
//
	//        if (!$client) {
	//            return ResponseUtil::HTTP_NOT_FOUND();
	//        }
//
	//        $clockRepo = $entityManager->getRepository(ClockMoment::class);
	//        $clockMoment = $clockRepo->find(4564);
//
	//        /** @var Serializer $serializer */
	//        $serializer = $this->getSerializerService();
	//        $result = $serializer->normalize($clockMoment, Constants::JSON_SERIALIZATON_FORMAT, array(
	//            'groups' => array(
	//                SerializerGroup::CLOCK_MOMENTS
	//            )
	//        ));
//
	//        return ResponseUtil::HTTP_OK($result);
	//    }

	/**
	 * Create one new ClockMoment.
	 *
	 * @Operation(
	 *     tags={"ClockMoments"},
	 *     summary="Create one ClockMoment",
	 *     @SWG\Parameter(
	 *            name="Authorization",
	 *            in="header",
	 *            required=true,
	 *            type="string",
	 *            default="Bearer {jwt}",
	 *            description="Authorization"
	 *        ),
	 *     @SWG\Parameter(
	 *         name="body",
	 *         in="body",
	 *         required=true,
	 *         type="string",
	 *     	   @SWG\Schema(
	 *            	type="object",
	 *     			required={"time_stamp","status","employee","department"},
	 *      		@SWG\Property(
	 *     				property="time_stamp",
	 *     				description="DateTime of a Register",
	 *     				type="datetime",
	 *     				example="2019-09-10T14:00:00+00:00"
	 *	 			),
	 *              @SWG\Property(
	 *     				property="status",
	 *     				description="Status of the ClockMoment: 0 = CHECK_IN, 1 = CHECK_OUT, 4 = CHECK_OUT_ALTERNATIVE",
	 *     				type="integer",
	 *     				example="0"
	 *	 			),
	 *              @SWG\Property(
	 *     				property="employee",
	 *					description="The associated Employee by ID of the to be created Register",
	 *     				type="array",
	 *     				@SWG\Items(
	 *     					type="object",
	 *     					@SWG\Property(
	 *     						property="id",
	 *     						type="integer",
	 *     						example=12
	 *     					)
	 * 					)
	 *	 			),
	 *     			@SWG\Property(
	 *     				property="department",
	 *					description="The associated Department by ID of the to be created Register",
	 *     				type="array",
	 *     				@SWG\Items(
	 *     					type="object",
	 *     					@SWG\Property(
	 *     						property="id",
	 *     						type="integer",
	 *     						example=56
	 *     					)
	 * 					)
	 *	 			),
	 *              @SWG\Property(
	 *     				property="remark",
	 *					description="Optional remark for the to be created ClockMoment",
	 *     				type="string",
	 *     				example="I worked today from 9 till 5"
	 *	 			)
	 *         )
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
	 *         response="412",
	 *         description="Precondition failed"
	 *     ),
	 *     @SWG\Response(
	 *         response="500",
	 *         description="Internal Server Error"
	 *     )
	 * )
	 *
	 * @param Request $request
	 * @Route("")
	 * @Method("POST")
	 *
	 * @return Response
	 * @Security("has_role('ROLE_HOURS_REGISTER')")
	 */
	public function createClockMoment(Request $request)
	{
		/** @var Client $client */
		$client = $this->getUser();
		/** @var EntityManager $entityManager */
		$entityManager = $this->getEntityManager();

		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();
		try {
			$postedClockMoment = $serializer->deserialize(
				$request->getContent(),
				ClockMoment::class,
				Constants::JSON_SERIALIZATON_FORMAT
			);
		} catch (UnexpectedValueException $exception) {
			return ResponseUtil::HTTP_PRECONDITION_FAILED();
		}

		/** @var ClockMoment $postedClockMoment */
		// construct Employee object and set to posted ClockMoment
		if ($postedClockMoment->getEmployee()) {
			$employee = $entityManager->find(Employee::class, $postedClockMoment->getEmployee()->getId());
			$postedClockMoment->setEmployee($employee ?? null);
		}

		// construct Register object and set to posted ClockMoment
		if ($postedClockMoment->getRegister()) {
			$register = $entityManager->find(Register::class, $postedClockMoment->getRegister()->getId());
			$postedClockMoment->setRegister($register ?? null);
		}

		// construct Department object and set to posted ClockMoment
		if ($postedClockMoment->getDepartment()) {
			$department = $entityManager->find(Department::class, $postedClockMoment->getDepartment()->getId());
			$postedClockMoment->setDepartment($department ?? null);
		}

		// Important to persist before validation check so that prePersist eventlisteners can kick in
		$entityManager->persist($postedClockMoment);

		$validator = $this->container->get('validator');
		$errors = $validator->validate($postedClockMoment);

		if (count($errors) > 0) {
			$errorMessage = JsonResponseMessage::PRECONDITION_FAILED;

			// Check response.show_error_messages setting
			$showErrorMessages = $this->getParameter('response.show_error_messages');
			if ($showErrorMessages) {
				// Prepare error message string
				foreach ($errors as $index => $error) {
					/* @var ConstraintViolation $error */
					$errorMessage .= '. '.($index + 1).'. '.$error->getPropertyPath().': '.$error->getMessage();
				}
			}

			return ResponseUtil::HTTP_PRECONDITION_FAILED($errorMessage);
		}

		$entityManager->flush();

		$entityManager->refresh($postedClockMoment);

		$result = $serializer->normalize($postedClockMoment, Constants::JSON_SERIALIZATON_FORMAT, array(
			'groups' => array(
				SerializerGroup::CLOCKMOMENTS,
			),
		));

		return ResponseUtil::HTTP_OK($result);
	}

	/**
	 * Update one existing ClockMoment.
	 *
	 * @Operation(
	 *     tags={"ClockMoments"},
	 *     summary="Update one existing ClockMoment",
	 *     @SWG\Parameter(
	 *            name="Authorization",
	 *            in="header",
	 *            required=true,
	 *            type="string",
	 *            default="Bearer {jwt}",
	 *            description="Authorization"
	 *        ),
	 *     @SWG\Parameter(
	 *         name="body",
	 *         in="body",
	 *         required=true,
	 *         type="string",
	 *     	   @SWG\Schema(
	 *            	type="object",
	 *     			required={"id","time_stamp","status"},
	 *              @SWG\Property(
	 *     				property="id",
	 *     				description="Unique id of an ClockMoment",
	 *     				type="integer",
	 *     				example="0"
	 *	 			),
	 *      		@SWG\Property(
	 *     				property="time_stamp",
	 *     				description="DateTime of a Register",
	 *     				type="datetime",
	 *     				example="2019-09-10T14:00:00+00:00"
	 *	 			),
	 *              @SWG\Property(
	 *     				property="status",
	 *     				description="Status of the ClockMoment: 0 = CHECK_IN, 1 = CHECK_OUT, 4 = CHECK_OUT_ALTERNATIVE",
	 *     				type="integer",
	 *     				example="0"
	 *	 			),
	 *              @SWG\Property(
	 *     				property="active",
	 *     				description="Is the clockMoment active or not (soft delete)",
	 *     				type="boolean",
	 *     				example=false
	 *	 			),
	 *              @SWG\Property(
	 *     				property="remark",
	 *					description="Optional remark for the to be created ClockMoment",
	 *     				type="string",
	 *     				example="I worked today from 9 till 5"
	 *	 			)
	 *         )
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
	 *         response="412",
	 *         description="Precondition failed"
	 *     ),
	 *     @SWG\Response(
	 *         response="500",
	 *         description="Internal Server Error"
	 *     )
	 * )
	 *
	 * @param Request $request
	 * @Route("")
	 * @Method("PATCH")
	 *
	 * @return Response
	 * @Security("has_role('ROLE_HOURS_REGISTER')")
	 */
	public function updateClockMoments(Request $request)
	{
		/** @var Client $client */
		$client = $this->getUser();
		/** @var EntityManager $entityManager */
		$entityManager = $this->getEntityManager();

		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();

		try {
			$jsonPostedClockMoment = $serializer->decode($request->getContent(), Constants::JSON_SERIALIZATON_FORMAT);
		} catch (UnexpectedValueException $exception) {
			return ResponseUtil::HTTP_PRECONDITION_FAILED();
		}

		$existingClockMoment = $entityManager->find(ClockMoment::class, $jsonPostedClockMoment['id']);
		if (!$existingClockMoment) {
			return ResponseUtil::HTTP_NOT_FOUND();
		}

		try {
			$editedClockMoment = $serializer->deserialize($serializer->encode($jsonPostedClockMoment, Constants::JSON_SERIALIZATON_FORMAT), ClockMoment::class, Constants::JSON_SERIALIZATON_FORMAT, array(
				'object_to_populate' => $existingClockMoment,
			));
		} catch (UnexpectedValueException $exception) {
			return ResponseUtil::HTTP_PRECONDITION_FAILED();
		}

		/** @var ClockMoment $editedClockMoment */
		// construct Employee object and set to posted ClockMoment
		if ($editedClockMoment->getEmployee()) {
			$employee = $entityManager->find(Employee::class, $editedClockMoment->getEmployee()->getId());
			$editedClockMoment->setEmployee($employee ?? null);
		}

		// construct Register object and set to posted ClockMoment
		if ($editedClockMoment->getRegister()) {
			$register = $entityManager->find(Register::class, $editedClockMoment->getRegister()->getId());
			$editedClockMoment->setRegister($register ?? null);
		}

		// construct Department object and set to posted ClockMoment
		if ($editedClockMoment->getDepartment()) {
			$department = $entityManager->find(Department::class, $editedClockMoment->getDepartment()->getId());
			$editedClockMoment->setDepartment($department ?? null);
		}

		$validator = $this->container->get('validator');
		$errors = $validator->validate($editedClockMoment);

		if (count($errors) > 0) {
			$errorMessage = JsonResponseMessage::PRECONDITION_FAILED;

			// Check response.show_error_messages setting
			$showErrorMessages = $this->getParameter('response.show_error_messages');
			if ($showErrorMessages) {
				// Prepare error message string
				foreach ($errors as $index => $error) {
					/* @var ConstraintViolation $error */
					$errorMessage .= '. '.($index + 1).'. '.$error->getPropertyPath().': '.$error->getMessage();
				}
			}

			return ResponseUtil::HTTP_PRECONDITION_FAILED($errorMessage);
		}

		$entityManager->flush();

		$entityManager->refresh($editedClockMoment);

		$result = $serializer->normalize($editedClockMoment, Constants::JSON_SERIALIZATON_FORMAT, array(
			'groups' => array(
				SerializerGroup::CLOCKMOMENTS,
			),
		));

		return ResponseUtil::HTTP_OK($result);
	}
}
