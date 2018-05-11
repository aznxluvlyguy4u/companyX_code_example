<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Assignment;
use AppBundle\Entity\Employee;
use AppBundle\Entity\ShiftSwapRequest;
use AppBundle\Entity\Client;
use AppBundle\Entity\SystemConfig;
use AppBundle\Enumerator\JsonResponseMessage;
use AppBundle\Enumerator\SerializerGroup;
use AppBundle\Repository\ShiftSwapRequestRepository;
use AppBundle\Repository\SystemConfigRepository;
use AppBundle\Service\ShiftSwapRequestNotifierService;
use AppBundle\Util\Constants;
use AppBundle\Util\ResponseUtil;
use DateTime;
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

/**
 * Class ShiftSwapRequestApiController.
 *
 * @Route("/api/v1/shift_swap_requests")
 */
class ShiftSwapRequestApiController extends BaseApiController
{
	/**
	 * Get all ShiftSwapRequests of the client
	 * If both dateFrom and dateTo query parameters are given, shiftSwapRequests within the interval dateFrom and dateTo are returned
	 * If only dateFrom and no dateTo given, shiftSwapRequests are returned from the dateFrom value to the end of that month.
	 * If only dateTo and no dateFrom given, shiftSwapRequests are returned from the start of the month of the dateFrom to the dateFrom value.
	 * If no dateFrom and no dateTo given, shiftSwapRequests are returned of the current month.
	 *
	 * @Operation(
	 *     tags={"ShiftSwapRequests"},
	 *     summary="Get all shiftSwapRequests the clients are allowed to see",
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
	 *     @SWG\Parameter(
	 *         name="hash",
	 *         in="query",
	 *         description="hash of a shiftSwapRequest",
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
	 * @param $dateFrom
	 * @param $dateTo
	 * @param $hash
	 *
	 * @return Response
	 * @QueryParam(name="dateFrom", requirements="%iso_date_regex%", default=null, description="start of the date range")
	 * @QueryParam(name="dateTo", requirements="%iso_date_regex%", default=null, description="end of the date range")
	 * @QueryParam(name="hash", requirements="%sha1_regex%", default=null, description="hash of a shiftSwapRequest")
	 *
	 * @Route("")
	 * @Method("GET")
	 */
	public function getShiftSwapRequests($dateFrom, $dateTo, $hash)
	{
		/** @var Client $client */
		$client = $this->getUser();
		$employee = $client->getEmployee();
		$entityManager = $this->getEntityManager();

		if (!$employee) {
			return ResponseUtil::HTTP_NOT_FOUND();
		}

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

		$shiftSwapRequests = $shiftSwapRequestRepository->findForEmployeeByParameters($employee->getId(), $dateTimeFrom,
			$dateTimeTo, $hash);

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
	 * Get a single shiftSwapRequest by ID.
	 *
	 * @Operation(
	 *     tags={"ShiftSwapRequests"},
	 *     summary="Get ShiftSwapRequest by ID",
	 *     @SWG\Parameter(
	 *            name="Authorization",
	 *            in="header",
	 *            required=true,
	 *            type="string",
	 *            default="Bearer {jwt}",
	 *            description="Authorization"
	 *        ),
	 *     @SWG\Parameter(
	 *         name="shiftSwapRequestId",
	 *         in="path",
	 *         description="ShiftSwapRequest id",
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
	 *         response="500",
	 *         description="Internal Server Error"
	 *     )
	 * )
	 *
	 * @param $shiftSwapRequestId
	 * @Route("/{shiftSwapRequestId}", requirements={"shiftSwapRequestId" = "\d+"})
	 * @Method("GET")
	 *
	 * @return Response
	 */
	public function getShiftSwapRequest($shiftSwapRequestId)
	{
		$client = $this->getUser();
		$entityManager = $this->getEntityManager();

		// Check for systemConfig DOR_FEATURES_SHIFT_SWAP_REQUEST setting
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = $entityManager->getRepository(SystemConfig::class);
		$enableShiftSwapRequest = $systemConfigRepo->getEnableShiftSwapRequest();

		if (!$enableShiftSwapRequest) {
			return ResponseUtil::HTTP_FORBIDDEN();
		}

		/** @var ShiftSwapRequestRepository $shiftSwapRequestRepository */
		$shiftSwapRequestRepository = $entityManager->getRepository(ShiftSwapRequest::class);

		$shiftSwapRequest = $shiftSwapRequestRepository->findOneByEmployeeWithRestrictionCheck($shiftSwapRequestId,
			$client->getEmployee()->getId());

		if (!$shiftSwapRequest) {
			return ResponseUtil::HTTP_NOT_FOUND();
		}

		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();
		$result = $serializer->normalize($shiftSwapRequest, Constants::JSON_SERIALIZATON_FORMAT, array(
			'groups' => array(
				SerializerGroup::SHIFT_SWAP_REQUESTS,
			),
		));

		return ResponseUtil::HTTP_OK($result);
	}

	/**
	 * Create one new ShiftSwapRequest.
	 *
	 * @Operation(
	 *     tags={"ShiftSwapRequests"},
	 *     summary="Create one ShiftSwapRequest",
	 *     @SWG\Parameter(
	 *            name="Authorization",
	 *            in="header",
	 *            required=true,
	 *            type="string",
	 *            default="Bearer {jwt}",
	 *            description="Authorization"
	 *        ),
	 *         @SWG\Parameter(
	 *         name="body",
	 *         in="body",
	 *         required=true,
	 *         type="string",
	 *     	   @SWG\Schema(
	 *            	type="object",
	 *     			required={"receiver", "assignment"},
	 *     			@SWG\Property(
	 *     				property="receiver",
	 *					description="The targeted receiver(Employee) by ID of the to be created ShiftSwapRequest",
	 *     				type="array",
	 *     				@SWG\Items(
	 *     					type="object",
	 *     					@SWG\Property(
	 *     						property="id",
	 *     						type="integer",
	 *     						example=19
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
	 *     						example=12345
	 *     					)
	 * 					)
	 *	 			),
	 *              @SWG\Property(
	 *     				property="applicant_message",
	 *					description="Optional message from the applicant(Employee) for the to be created ShiftSwapRequest",
	 *     				type="string",
	 *     				example="Hey Thomas, want to swap?"
	 *	 			),
	 *              @SWG\Property(
	 *     				property="remark",
	 *					description="Optional remark for the to be created Register",
	 *     				type="string",
	 *     				example="I want it really bad"
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
	 * @param Request                         $request
	 * @param ShiftSwapRequestNotifierService $shiftSwapRequestNotifierService
	 *
	 * @return Response
	 * @Route("")
	 * @Method("POST")
	 */
	public function createShiftSwapRequest(
		Request $request,
		ShiftSwapRequestNotifierService $shiftSwapRequestNotifierService
	) {
		/** @var Client $client */
		$client = $this->getUser();
		/** @var EntityManager $entityManager */
		$entityManager = $this->getEntityManager();

		// Check for systemConfig DOR_FEATURES_SHIFT_SWAP_REQUEST setting
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = $entityManager->getRepository(SystemConfig::class);
		$enableShiftSwapRequest = $systemConfigRepo->getEnableShiftSwapRequest();

		if (!$enableShiftSwapRequest) {
			return ResponseUtil::HTTP_FORBIDDEN();
		}

		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();
		try {
			$postedShiftSwapRequest = $serializer->deserialize($request->getContent(), ShiftSwapRequest::class,
				Constants::JSON_SERIALIZATON_FORMAT);
		} catch (UnexpectedValueException $exception) {
			return ResponseUtil::HTTP_PRECONDITION_FAILED();
		}

		/** @var ShiftSwapRequest $postedShiftSwapRequest */
		// construct Receiver object and set to posted ShiftSwapRequest
		if ($postedShiftSwapRequest->getReceiver()) {
			$receiverEmployee = $entityManager->find(Employee::class, $postedShiftSwapRequest->getReceiver()->getId());
			$postedShiftSwapRequest->setReceiver($receiverEmployee ?? null);
		}

		// construct Assignment object and set to posted ShiftSwapRequest
		if ($postedShiftSwapRequest->getAssignment()) {
			$assignment = $entityManager->find(Assignment::class, $postedShiftSwapRequest->getAssignment()->getId());
			$postedShiftSwapRequest->setAssignment($assignment ?? null);
		}

		// Important to persist before validation check so that prePersist eventlisteners can kick in
		$entityManager->persist($postedShiftSwapRequest);

		$validator = $this->container->get('validator');
		$errors = $validator->validate($postedShiftSwapRequest);

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

		$entityManager->refresh($postedShiftSwapRequest);

		// Send email
		$shiftSwapRequestNotifierService->notifyConcernedForShiftSwapRequest($postedShiftSwapRequest);

		$result = $serializer->normalize($postedShiftSwapRequest, Constants::JSON_SERIALIZATON_FORMAT, array(
			'groups' => array(
				SerializerGroup::SHIFT_SWAP_REQUESTS,
			),
		));

		return ResponseUtil::HTTP_OK($result);
	}

	/**
	 * Update one existing ShiftSwapRequest.
	 *
	 * @Operation(
	 *     tags={"ShiftSwapRequests"},
	 *     summary="Update one existing ShiftSwapRequest",
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
	 *     			required={"id", "status"},
	 *              @SWG\Property(
	 *     				property="id",
	 *					description="Unique id of an ShiftSwapRequest",
	 *     				type="integer",
	 *     				example=9999
	 *	 			),
	 *     			@SWG\Property(
	 *     				property="status",
	 *					description="Status of the ShiftSwapRequest: 0 = UNPROCESSED_BY_RECEIVER, 1 = GRANTED_BY_RECEIVER, 2 = DENIED_BY_RECEIVER, 3 = GRANTED_BY_PLANNER, 4 = DENIED_BY_PLANNER, 5 = WITHDRAWN_BY_APPLICANT, 6 = WITHDRAWN_BY_RECEIVER",
	 *     				type="enum",
	 *     				enum={1,2,3,4,5,6}
	 *	 			),
	 *              @SWG\Property(
	 *     				property="applicant_withdrawal_message",
	 *					description="Optional withdrawal message from the applicant(Employee) for the ShiftSwapRequest",
	 *     				type="string",
	 *     				example="Not necessary anymore, thanks anyway!"
	 *	 			),
	 *              @SWG\Property(
	 *     				property="receiver_message",
	 *					description="Optional message from the receiver(Employee) for the ShiftSwapRequest",
	 *     				type="string",
	 *     				example="No problem!"
	 *	 			),
	 *	 			@SWG\Property(
	 *     				property="receiver_withdrawal_message",
	 *					description="Optional withdrawal message from the receiver(Employee) for the ShiftSwapRequest",
	 *     				type="string",
	 *     				example="Sorry I forgot that I have an appointment planned already!"
	 *	 			),
	 *     			@SWG\Property(
	 *     				property="planner_message",
	 *					description="Optional message from the planner(Employee) for the ShiftSwapRequest",
	 *     				type="string",
	 *     				example="Granted!"
	 *	 			),
	 *              @SWG\Property(
	 *     				property="remark",
	 *					description="Optional remark for the to be created Register",
	 *     				type="string",
	 *     				example="I want it really bad"
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
	 * @param Request                         $request
	 * @param ShiftSwapRequestNotifierService $shiftSwapRequestNotifierService
	 *
	 * @return Response
	 * @Route("")
	 * @Method("PATCH")
	 */
	public function updateShiftSwapRequests(Request $request, ShiftSwapRequestNotifierService $shiftSwapRequestNotifierService)
	{
		/** @var Client $client */
		$client = $this->getUser();
		/** @var EntityManager $entityManager */
		$entityManager = $this->getEntityManager();

		// Check for systemConfig DOR_FEATURES_SHIFT_SWAP_REQUEST setting
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = $entityManager->getRepository(SystemConfig::class);
		$enableShiftSwapRequest = $systemConfigRepo->getEnableShiftSwapRequest();

		if (!$enableShiftSwapRequest) {
			return ResponseUtil::HTTP_FORBIDDEN();
		}

		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();

		try {
			$jsonPostedShiftSwapRequest = $serializer->decode($request->getContent(),
				Constants::JSON_SERIALIZATON_FORMAT);
		} catch (UnexpectedValueException $exception) {
			return ResponseUtil::HTTP_PRECONDITION_FAILED();
		}

		/** @var ShiftSwapRequestRepository $shiftSwapRequestRepo */
		$shiftSwapRequestRepo = $entityManager->getRepository(ShiftSwapRequest::class);
		$existingShiftSwapRequest = $shiftSwapRequestRepo->findOneByEmployeeWithRestrictionCheck($jsonPostedShiftSwapRequest['id'],
			$client->getEmployee()->getId());
		if (!$existingShiftSwapRequest) {
			return ResponseUtil::HTTP_NOT_FOUND();
		}

		try {
			$editedShiftSwapRequest = $serializer->deserialize($serializer->encode($jsonPostedShiftSwapRequest,
				Constants::JSON_SERIALIZATON_FORMAT), ShiftSwapRequest::class, Constants::JSON_SERIALIZATON_FORMAT,
				array(
					'object_to_populate' => $existingShiftSwapRequest,
				));
		} catch (UnexpectedValueException $exception) {
			return ResponseUtil::HTTP_PRECONDITION_FAILED();
		}

		/** @var ShiftSwapRequest $editedShiftSwapRequest */
		// construct Receiver object and set to posted ShiftSwapRequest
		if ($editedShiftSwapRequest->getReceiver()) {
			$receiverEmployee = $entityManager->find(Employee::class, $editedShiftSwapRequest->getReceiver()->getId());
			$editedShiftSwapRequest->setReceiver($receiverEmployee ?? null);
		}

		// construct Department object and set to posted ShiftSwapRequest
		if ($editedShiftSwapRequest->getAssignment()) {
			$assignment = $entityManager->find(Assignment::class, $editedShiftSwapRequest->getAssignment()->getId());
			$editedShiftSwapRequest->setAssignment($assignment ?? null);
		}

		$validator = $this->container->get('validator');
		$errors = $validator->validate($editedShiftSwapRequest);

		if (count($errors) > 0) {
			$errorMessage = JsonResponseMessage::PRECONDITION_FAILED;

			// Check response.show_error_messages setting
			$showErrorMessages = $this->getParameter('response.show_error_messages');
			if ($showErrorMessages) {
				// Prepare error message string
				foreach ($errors as $index => $error) {
					/* @var ConstraintViolation $error */
					$errorMessage .= '. '.($index + 1).': '.$error->getMessage();
				}
			}

			return ResponseUtil::HTTP_PRECONDITION_FAILED($errorMessage);
		}

		$entityManager->flush();

		$entityManager->refresh($editedShiftSwapRequest);

		// Send email
		$shiftSwapRequestNotifierService->notifyConcernedForShiftSwapRequest($editedShiftSwapRequest);

		$result = $serializer->normalize($editedShiftSwapRequest, Constants::JSON_SERIALIZATON_FORMAT, array(
			'groups' => array(
				SerializerGroup::SHIFT_SWAP_REQUESTS,
			),
		));

		return ResponseUtil::HTTP_OK($result);
	}

	// TODO not in use right now, maybe later
	/*
	 * Delete one ShiftSwapRequest by ID
	 *
	 * @ApiDoc(
	 *   section = "ShiftSwapRequests",
	 *   requirements={
	 *     {
	 *       "name"="Authorization",
	 *       "dataType"="string",
	 *       "requirement"="Bearer {jwt}",
	 *       "description"="A valid non expired access token"
	 *     }
	 *   },
	 *   statusCodes = {
	 *      200 = "OK",
	 *      401 = "Unauthorized",
	 *      403 = "Forbidden",
	 *      404 = "Not Found",
	 *      412 = "Precondition failed",
	 *      500 = "Internal Server Error"
	 *   },
	 *   resource = true,
	 *   description = "Delete one ShiftSwapRequest by ID",
	 *   output = "AppBundle\Entity\ShiftSwapRequest[]"
	 * )
	 *
	 * @param $shiftSwapRequestId
	 * @Route("/{shiftSwapRequestId}", requirements={"shiftSwapRequestId" = "\d+"})
	 * @Method("DELETE")
	 *
	 * @return Response
	 */
//    public function deleteShiftSwapRequest($shiftSwapRequestId)
//    {
//        /** @var Client $client */
//        $client = $this->getUser();
//        /** @var EntityManager $entityManager */
//        $entityManager = $this->getEntityManager();
//
//        /** @var ShiftSwapRequest $existingShiftSwapRequest */
//        $existingShiftSwapRequest = $entityManager->find(ShiftSwapRequest::class, $shiftSwapRequestId);
//        if (!$existingShiftSwapRequest) {
//            return new Response(JSend::error(
//                JsonResponseMessage::NOT_FOUND,
//                Response::HTTP_NOT_FOUND, []),
//                Response::HTTP_NOT_FOUND
//            );
//        }
//
//        $entityManager->remove($existingShiftSwapRequest);
//        $deletedShiftSwapRequest = clone $existingShiftSwapRequest;
//        $entityManager->flush();
//
//        /** @var Serializer $serializer */
//        $serializer = $this->getSerializerService();
//        $result = $serializer->normalize($deletedShiftSwapRequest, Constants::JSON_SERIALIZATON_FORMAT, array(
//            'groups' => array(
//                SerializerGroup::SHIFT_SWAP_REQUESTS
//            )
//        ));
//
//        return ResponseUtil::HTTP_OK($result);
//    }
}
