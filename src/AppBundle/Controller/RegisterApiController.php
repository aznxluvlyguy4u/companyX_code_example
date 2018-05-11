<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Assignment;
use AppBundle\Entity\Department;
use AppBundle\Entity\Employee;
use AppBundle\Entity\Register;
use AppBundle\Entity\Client;
use AppBundle\Entity\SystemConfig;
use AppBundle\Enumerator\JsonResponseMessage;
use AppBundle\Enumerator\SerializerGroup;
use AppBundle\Repository\RegisterRepository;
use AppBundle\Repository\SystemConfigRepository;
use AppBundle\Util\Constants;
use AppBundle\Util\ResponseUtil;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use Symfony\Component\HttpFoundation\Request;
use Nelmio\ApiDocBundle\Annotation\Operation;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Serializer;
use AppBundle\Enumerator\RegisterStatus;
use Symfony\Component\Validator\ConstraintViolation;

/**
 * Class RegisterApiController.
 *
 * @Route("/api/v1/registers")
 */
class RegisterApiController extends BaseApiController
{
	/**
	 * Get all Registers of the client
	 * If both dateFrom and dateTo query parameters are given, registers within the interval dateFrom and dateTo are returned
	 * If only dateFrom and no dateTo given, registers are returned from the dateFrom value to the end of that month.
	 * If only dateTo and no dateFrom given, registers are returned from the start of the month of the dateFrom to the dateFrom value.
	 * If no dateFrom and no dateTo given, registers are returned of the current month.
	 *
	 * @Operation(
	 *     tags={"Registers"},
	 *     summary="Get all registers the clients are allowed to see",
	 *     @SWG\Parameter(
	 *     		name="Authorization",
	 *     		in="header",
	 *     		required=true,
	 *     		type="string",
	 *     		default="Bearer {jwt}",
	 *     		description="Authorization"
	 * 		),
	 *     @SWG\Parameter(
	 *         name="dateFrom",
	 *         in="query",
	 *         description="Provide a start date query parameter to fetch registers of an Employee from that specific date",
	 *         required=false,
	 *         type="string",
	 *         format="date"
	 *     ),
	 *     @SWG\Parameter(
	 *         name="dateTo",
	 *         in="query",
	 *         description="Provide a end date query parameter to fetch registers of an Employee to that specific date",
	 *         required=false,
	 *         type="string",
	 *         format="date"
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
	 * @QueryParam(name="dateFrom", requirements="%iso_date_regex%", default=null, description="start of the date range")
	 * @QueryParam(name="dateTo", requirements="%iso_date_regex%", default=null, description="end of the date range")
	 *
	 * @Route("")
	 * @Method("GET")
	 *
	 * @return Response
	 */
	public function getRegisters($dateFrom, $dateTo)
	{
		/** @var Client $client */
		$client = $this->getUser();
		$employee = $client->getEmployee();

		if (!$employee) {
			return ResponseUtil::HTTP_NOT_FOUND();
		}

		$dateTimeFrom = $dateFrom ? new DateTime($dateFrom) : null;
		$dateTimeTo = $dateTo ? new DateTime($dateTo) : null;

		/** @var RegisterRepository $registerRepository */
		$registerRepository = $this->getEntityManager()
			->getRepository(Register::class);

		$registers = $registerRepository->findAllWithDateParameters($dateTimeFrom, $dateTimeTo);

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
	 * Create one or multiple new Register(s).
	 *
	 * @Operation(
	 *     tags={"Registers"},
	 *     summary="Create one or multiple new Register(s)",
	 *     @SWG\Parameter(
	 *     		name="Authorization",
	 *     		in="header",
	 *     		required=true,
	 *     		type="string",
	 *     		default="Bearer {jwt}",
	 *     		description="Authorization"
	 * 		),
	 * 		@SWG\Parameter(
	 *         name="body",
	 *         in="body",
	 *         required=true,
	 *         type="string",
	 *     	   @SWG\Schema(
	 *            	type="object",
	 *     			required={"start_date", "end_date", "type"},
	 *      		@SWG\Property(
	 *     				property="start_date",
	 *     				description="Start datetime of a Register",
	 *     				type="string",
	 *     				example="2018-01-01"
	 *	 			),
	 *              @SWG\Property(
	 *     				property="end_date",
	 *					description="End datetime of a Register",
	 *     				type="string",
	 *     				example="2018-01-01"
	 *	 			),
	 *              @SWG\Property(
	 *     				property="type",
	 *					description="Type of the register",
	 *     				type="string",
	 *     				example="WORK",
	 *    	 			enum={"WORK", "AVAILABLE", "UNAVAILABLE", "PREFERENCE", "VACATION", "VACATION_DAYS", "OVERTIME", "SICK", "SICK_LEAVE", "REMARK", "LEAVE_HOLIDAY", "SICK_WAIT_DAY"}
	 *	 			),
	 *              @SWG\Property(
	 *     				property="remark",
	 *					description="Optional remark for the to be created Register",
	 *     				type="string",
	 *     				example="I worked today from 9 till 5"
	 *	 			),
	 *              @SWG\Property(
	 *     				property="break_duration",
	 *					description="Break duration in minutes for the to be created Register",
	 *     				type="integer",
	 *     				example="30"
	 *	 			),
	 *              @SWG\Property(
	 *     				property="meal",
	 *					description="Meal component of the to be created Register, only taken into account if DOR_REGISTRATION_COMPONENT_MEALCHECKBOX is true",
	 *     				type="boolean",
	 *     				example=true
	 *	 			),
	 *              @SWG\Property(
	 *     				property="kilometers",
	 *					description="Kilometers value in 2 decimal float for the to be created Register",
	 *     				type="number",
	 *     				example=12.34
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
	 *     				property="assignment",
	 *					description="The associated Assignment by ID of the to be created Register",
	 *     				type="array",
	 *     				@SWG\Items(
	 *     					type="object",
	 *     					@SWG\Property(
	 *     						property="id",
	 *     						type="integer",
	 *     						example=34
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
	 *
	 * @Route("")
	 * @Method("POST")
	 *
	 * @return Response
	 */
	public function createRegisters(Request $request)
	{
		/** @var EntityManager $entityManager */
		$entityManager = $this->getEntityManager();

		// Check for systemConfig DOR_FTE_MW_HOURREGISTRATION setting
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = $entityManager->getRepository(SystemConfig::class);
		$disableRegisterRegistration = $systemConfigRepo->getDisableRegisterRegistration();

		if ($disableRegisterRegistration) {
			return ResponseUtil::HTTP_FORBIDDEN();
		}

		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();
		try {
			$postedRegisters = $serializer->deserialize($request->getContent(), Register::class.'[]',
				Constants::JSON_SERIALIZATON_FORMAT);
		} catch (UnexpectedValueException $exception) {
			return ResponseUtil::HTTP_PRECONDITION_FAILED();
		}

		foreach ($postedRegisters as $postedRegister) {
			/** @var Register $postedRegister */
			// construct Employee object and set to posted Register
			if ($postedRegister->getEmployee()) {
				$employee = $entityManager->find(Employee::class, $postedRegister->getEmployee()->getId());
				if (!$employee) {
					return ResponseUtil::HTTP_NOT_FOUND();
				}
				$postedRegister->setEmployee($employee);
			}

			// construct Department object and set to posted Register
			if ($postedRegister->getDepartment()) {
				$department = $entityManager->find(Department::class, $postedRegister->getDepartment()->getId());
				if (!$department) {
					return ResponseUtil::HTTP_NOT_FOUND();
				}
				$postedRegister->setDepartment($department);
			}

			// construct Assignment object and set to posted Register
			if ($postedRegister->getAssignment()) {
				$assignment = $entityManager->find(Assignment::class, $postedRegister->getAssignment()->getId());
				if (!$assignment) {
					return ResponseUtil::HTTP_NOT_FOUND();
				}
				$postedRegister->setAssignment($assignment);
			}

			// Important to persist before validation check so that prePersist eventlisteners can kick in
			$entityManager->persist($postedRegister);

			$validator = $this->container->get('validator');
			$errors = $validator->validate($postedRegister);

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
		}

		$entityManager->flush();

		foreach ($postedRegisters as $postedRegister) {
			$entityManager->refresh($postedRegister);
		}

		$result = $serializer->normalize($postedRegisters, Constants::JSON_SERIALIZATON_FORMAT, array(
			'groups' => array(
				SerializerGroup::REGISTERS,
			),
		));

		return ResponseUtil::HTTP_OK($result);
	}

	/**
	 * Update one or multiple existing Register(s).
	 *
	 * @Operation(
	 *     tags={"Registers"},
	 *     summary="Update one or multiple existing Register(s)",
	 *     @SWG\Parameter(
	 *     		name="Authorization",
	 *     		in="header",
	 *     		required=true,
	 *     		type="string",
	 *     		default="Bearer {jwt}",
	 *     		description="Authorization"
	 * 		),
	 * 		@SWG\Parameter(
	 *         name="body",
	 *         in="body",
	 *         required=true,
	 *         type="string",
	 *     	   @SWG\Schema(
	 *            	type="object",
	 *     			required={"id", "start_date", "end_date", "type"},
	 *	 			@SWG\Property(
	 *     				property="id",
	 *     				description="Unique id of an Register",
	 *     				type="string",
	 *     				example="321"
	 *	 			),
	 *      		@SWG\Property(
	 *     				property="start_date",
	 *     				description="Start datetime of a Register",
	 *     				type="string",
	 *     				example="2018-01-01"
	 *	 			),
	 *              @SWG\Property(
	 *     				property="end_date",
	 *					description="End datetime of a Register",
	 *     				type="string",
	 *     				example="2018-01-01"
	 *	 			),
	 *              @SWG\Property(
	 *     				property="type",
	 *					description="Type of the register",
	 *     				type="string",
	 *     				example="WORK",
	 *    	 			enum={"WORK", "AVAILABLE", "UNAVAILABLE", "PREFERENCE", "VACATION", "VACATION_DAYS", "OVERTIME", "SICK", "SICK_LEAVE", "REMARK", "LEAVE_HOLIDAY", "SICK_WAIT_DAY"}
	 *	 			),
	 *              @SWG\Property(
	 *     				property="remark",
	 *					description="Optional remark for the to be created Register",
	 *     				type="string",
	 *     				example="I worked today from 9 till 5"
	 *	 			),
	 *              @SWG\Property(
	 *     				property="break_duration",
	 *					description="Break duration in minutes for the to be created Register",
	 *     				type="integer",
	 *     				example="30"
	 *	 			),
	 *              @SWG\Property(
	 *     				property="meal",
	 *					description="Meal component of the to be created Register, only taken into account if DOR_REGISTRATION_COMPONENT_MEALCHECKBOX is true",
	 *     				type="boolean",
	 *     				example=true
	 *	 			),
	 *              @SWG\Property(
	 *     				property="kilometers",
	 *					description="Kilometers value in 2 decimal float for the to be created Register",
	 *     				type="number",
	 *     				example=12.34
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
	 *     				property="assignment",
	 *					description="The associated Assignment by ID of the to be created Register",
	 *     				type="array",
	 *     				@SWG\Items(
	 *     					type="object",
	 *     					@SWG\Property(
	 *     						property="id",
	 *     						type="integer",
	 *     						example=34
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
	 *
	 * @Route("")
	 * @Method("PUT")
	 *
	 * @return Response
	 */
	public function updateRegisters(Request $request)
	{
		/** @var EntityManager $entityManager */
		$entityManager = $this->getEntityManager();

		// Check for systemConfig DOR_FTE_MW_HOURREGISTRATION setting
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = $entityManager->getRepository(SystemConfig::class);
		$disableRegisterRegistration = $systemConfigRepo->getDisableRegisterRegistration();

		if ($disableRegisterRegistration) {
			return ResponseUtil::HTTP_FORBIDDEN();
		}

		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();

		try {
			$jsonPostedRegisters = $serializer->decode($request->getContent(), Constants::JSON_SERIALIZATON_FORMAT);
			if (!array_key_exists('0', $jsonPostedRegisters)) {
				return ResponseUtil::HTTP_PRECONDITION_FAILED();
			}
		} catch (UnexpectedValueException $exception) {
			return ResponseUtil::HTTP_PRECONDITION_FAILED();
		}

		// Deserialize PUT json object into existing Register objects for edit
		$editedRegisters = new ArrayCollection();
		foreach ($jsonPostedRegisters as $jsonPostedRegister) {
			$existingRegister = $entityManager->getRepository(Register::class)->find($jsonPostedRegister['id']);
			if (!$existingRegister) {
				return ResponseUtil::HTTP_NOT_FOUND();
			}

			try {
				$editedRegister = $serializer->deserialize($serializer->encode($jsonPostedRegister,
					Constants::JSON_SERIALIZATON_FORMAT), Register::class, Constants::JSON_SERIALIZATON_FORMAT, array(
					'object_to_populate' => $existingRegister,
				));
			} catch (UnexpectedValueException $exception) {
				return ResponseUtil::HTTP_PRECONDITION_FAILED();
			}

			// construct Employee object and set to posted Register
			if ($editedRegister->getEmployee()) {
				$employee = $entityManager->find(Employee::class, $editedRegister->getEmployee()->getId());
				if (!$employee) {
					return ResponseUtil::HTTP_NOT_FOUND();
				}
				$editedRegister->setEmployee($employee);
			}

			// construct Department object and set to edited Register
			if ($editedRegister->getDepartment()) {
				$department = $entityManager->find(Department::class, $editedRegister->getDepartment()->getId());
				if (!$department) {
					return ResponseUtil::HTTP_NOT_FOUND();
				}
				$editedRegister->setDepartment($department);
			}

			// construct Assignment object and set to edited Register
			if ($editedRegister->getAssignment()) {
				$assignment = $entityManager->find(Assignment::class, $editedRegister->getAssignment()->getId());
				if (!$assignment) {
					return ResponseUtil::HTTP_NOT_FOUND();
				}
				$editedRegister->setAssignment($assignment);
			}

			$validator = $this->container->get('validator');
			$errors = $validator->validate($editedRegister);

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
			$editedRegisters->add($editedRegister);
		}

		$entityManager->flush();

		foreach ($editedRegisters as $editedRegister) {
			$entityManager->refresh($editedRegister);
		}

		$result = $serializer->normalize($editedRegisters, Constants::JSON_SERIALIZATON_FORMAT, array(
			'groups' => array(
				SerializerGroup::REGISTERS,
			),
		));

		return ResponseUtil::HTTP_OK($result);
	}

	/**
	 * Delete one or multiple existing Register(s) by Register ID.
	 *
	 * @Operation(
	 *     tags={"Registers"},
	 *     summary="Delete one or multiple existing Register(s) by Register ID",
	 *     @SWG\Parameter(
	 *     		name="Authorization",
	 *     		in="header",
	 *     		required=true,
	 *     		type="string",
	 *     		default="Bearer {jwt}",
	 *     		description="Authorization"
	 * 		),
	 * 		@SWG\Parameter(
	 *         name="body",
	 *         in="body",
	 *         required=true,
	 *         type="string",
	 *     	   @SWG\Schema(
	 *            	type="object",
	 *     			required={"id"},
	 *	 			@SWG\Property(
	 *     				property="id",
	 *     				description="Unique id of an Register",
	 *     				type="string",
	 *     				example="321"
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
	 *
	 * @Route("")
	 * @Method("DELETE")
	 *
	 * @return Response
	 */
	public function deleteRegisters(Request $request)
	{
		/** @var Client $client */
		$client = $this->getUser();
		/** @var EntityManager $entityManager */
		$entityManager = $this->getEntityManager();

		// Check for systemConfig DOR_FTE_MW_HOURREGISTRATION setting
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = $entityManager->getRepository(SystemConfig::class);
		$disableRegisterRegistration = $systemConfigRepo->getDisableRegisterRegistration();

		if ($disableRegisterRegistration) {
			return ResponseUtil::HTTP_FORBIDDEN();
		}

		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();
		try {
			$postedRegisters = $serializer->deserialize($request->getContent(), Register::class.'[]',
				Constants::JSON_SERIALIZATON_FORMAT);
		} catch (UnexpectedValueException $exception) {
			return ResponseUtil::HTTP_PRECONDITION_FAILED();
		}

		$deletedRegisters = array();
		foreach ($postedRegisters as $postedRegister) {
			/** @var Register $postedRegister */
			/** @var Register $existingRegister */
			$existingRegister = $entityManager->find(Register::class, $postedRegister->getId());
			if (!$existingRegister) {
				return ResponseUtil::HTTP_NOT_FOUND();
			}

			// Deny deletion of Registers that are already approved/processed
			if (RegisterStatus::UNPROCESSED !== $existingRegister->getStatus()) {
				return ResponseUtil::HTTP_FORBIDDEN();
			}

			$entityManager->remove($existingRegister);
			$deletedRegisters[] = ['id' => $existingRegister->getId()];
		}

		$entityManager->flush();

		return ResponseUtil::HTTP_OK($deletedRegisters);
	}

	/**
	 * Delete one Register by ID.
	 *
	 * @Operation(
	 *     tags={"Registers"},
	 *     summary="Delete one Register by ID",
	 *     @SWG\Parameter(
	 *     		name="Authorization",
	 *     		in="header",
	 *     		required=true,
	 *     		type="string",
	 *     		default="Bearer {jwt}",
	 *     		description="Authorization"
	 * 		),
	 *     @SWG\Parameter(
	 *         name="registerId",
	 *         in="path",
	 *         description="ID of register",
	 *         required=false,
	 *         type="integer"
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
	 * @param $registerId
	 * @Route("/{registerId}", requirements={"registerId" = "\d+"})
	 * @Method("DELETE")
	 *
	 * @return Response
	 */
	public function deleteRegister($registerId)
	{
		/** @var Client $client */
		$client = $this->getUser();
		/** @var EntityManager $entityManager */
		$entityManager = $this->getEntityManager();

		// Check for systemConfig DOR_FTE_MW_HOURREGISTRATION setting
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = $entityManager->getRepository(SystemConfig::class);
		$disableRegisterRegistration = $systemConfigRepo->getDisableRegisterRegistration();

		if ($disableRegisterRegistration) {
			return ResponseUtil::HTTP_FORBIDDEN();
		}

		/** @var Register $existingRegister */
		$existingRegister = $entityManager->find(Register::class, $registerId);
		if (!$existingRegister) {
			return ResponseUtil::HTTP_NOT_FOUND();
		}

		// Deny deletion of Registers that are already approved/processed
		if (RegisterStatus::UNPROCESSED !== $existingRegister->getStatus()) {
			return ResponseUtil::HTTP_FORBIDDEN();
		}

		$entityManager->remove($existingRegister);
		$deletedRegister = clone $existingRegister;
		$entityManager->flush();

		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();
		$result = $serializer->normalize($deletedRegister, Constants::JSON_SERIALIZATON_FORMAT, array(
			'groups' => array(
				SerializerGroup::REGISTERS,
			),
		));

		return ResponseUtil::HTTP_OK($result);
	}
}
