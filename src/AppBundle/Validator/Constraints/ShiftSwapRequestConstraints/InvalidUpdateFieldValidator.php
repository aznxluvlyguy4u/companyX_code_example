<?php

namespace AppBundle\Validator\Constraints\ShiftSwapRequestConstraints;

use AppBundle\Entity\Assignment;
use AppBundle\Entity\Client;
use AppBundle\Entity\Employee;
use AppBundle\Entity\ShiftSwapRequest;
use AppBundle\Enumerator\ShiftSwapRequestRequestRole;
use AppBundle\Repository\AssignmentRepository;
use AppBundle\Repository\EmployeeRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface as TokenStorage;
use AppBundle\Enumerator\ShiftSwapRequestStatus;

/**
 * Class VacationTimeoutValidator
 * Validator for Class InvalidUpdateField.
 */
class InvalidUpdateFieldValidator extends ConstraintValidator
{
	/**
	 * @var EntityManager
	 */
	private $em;

	/**
	 * @var Client
	 */
	private $client;

	/**
	 * @var PropertyAccessorInterface
	 */
	private $propertyAccessor;

	/**
	 * @var string
	 */
	private $currentRequestMethod;

	/**
	 * Property 'applicant' of a ShiftSwapRequest object.
	 */
	const APPLICANT_FIELD = 'applicant';

	/**
	 * Property 'receiver' of a ShiftSwapRequest object.
	 */
	const RECEIVER_FIELD = 'receiver';

	/**
	 * Property 'planner' of a ShiftSwapRequest object.
	 */
	const PLANNER_FIELD = 'planner';

	/**
	 * Property 'assignment' of a ShiftSwapRequest object.
	 */
	const ASSIGNMENT_FIELD = 'assignment';

	/**
	 * Property 'applicantMessage' of a ShiftSwapRequest object.
	 */
	const APPLICANT_MESSAGE_FIELD = 'applicantMessage';

	/**
	 * Property 'applicantWithdrawalMessage' of a ShiftSwapRequest object.
	 */
	const APPLICANT_WITHDRAWAL_MESSAGE_FIELD = 'applicantWithdrawalMessage';

	/**
	 * Property 'receiverMessage' of a ShiftSwapRequest object.
	 */
	const RECEIVER_MESSAGE_FIELD = 'receiverMessage';

	/**
	 * Property 'receiverWithdrawalMessage' of a ShiftSwapRequest object.
	 */
	const RECEIVER_WITHDRAWAL_MESSAGE_FIELD = 'receiverWithdrawalMessage';

	/**
	 * Property 'plannerMessage' of a ShiftSwapRequest object.
	 */
	const PLANNER_MESSAGE_FIELD = 'plannerMessage';

	/**
	 * Property 'hash' of a ShiftSwapRequest object.
	 */
	const HASH_FIELD = 'hash';

	/**
	 * Property 'status' of a ShiftSwapRequest object.
	 */
	const STATUS_FIELD = 'status';

	/**
	 * Property 'startDate' of a ShiftSwapRequest object.
	 */
	const START_DATE_FIELD = 'startDate';

	/**
	 * Property 'endDate' of a ShiftSwapRequest object.
	 */
	const END_DATE_FIELD = 'endDate';

	/**
	 * InvalidUpdateFieldValidator constructor.
	 *
	 * @param EntityManager             $em
	 * @param TokenStorage              $tokenStorage
	 * @param PropertyAccessorInterface $propertyAccessor
	 * @param RequestStack              $requestStack
	 */
	public function __construct(EntityManager $em, TokenStorage $tokenStorage, PropertyAccessorInterface $propertyAccessor, RequestStack $requestStack)
	{
		$client = $tokenStorage->getToken()->getUser();
		$this->client = $client;
		$this->em = $em;
		$this->propertyAccessor = $propertyAccessor;
		$this->currentRequestMethod = $requestStack->getCurrentRequest()->getMethod();
	}

	/**
	 * @param ShiftSwapRequest $shiftSwapRequest
	 * @param Constraint       $constraint
	 */
	public function validate($shiftSwapRequest, Constraint $constraint)
	{
		// Prepare unit of work
		$unitOfWork = $this->em->getUnitOfWork();
		$entityState = $unitOfWork->getEntityState($shiftSwapRequest);

		// Check for constraint violations during a POST ShiftSwapRequest request
		if (Request::METHOD_POST === $this->currentRequestMethod) {
			// Stop checking for constraint violations when the 'applicant', 'receiver' or 'assignment' field of the ShiftSwapRequest is empty
			if (!$shiftSwapRequest->getApplicant() || !$shiftSwapRequest->getReceiver() || !$shiftSwapRequest->getAssignment()) {
				return;
			}

			// Raise constraint violation for when Applicant and receiver are the same
			$this->sameApplicantAndReceiverViolationCheck($shiftSwapRequest, $constraint);

			// Raise constraint violation for when the requested assignment for swap does not belong to the current user
			$this->invalidAssignmentViolationCheck($shiftSwapRequest, $constraint);

			// Raise constraint violation for when the requested assignment for swap is in the past
			$this->assignmentDateInThePastViolationCheck($shiftSwapRequest, $constraint);

			// Raise constraint violation for when the given assignment already as an associated shiftSwapRequest
			$this->shiftSwapRequestAlreadyIssuedViolationCheck($shiftSwapRequest, $constraint);

			// Raise constraint violation for when the given receiver is not eligible for this swap
			$this->ineligibleReceiverViolationCheck($shiftSwapRequest, $constraint);

			// Raise constraint violation for when certain properties of the ShiftSwapRequest are sent that are not allowed during a POST request
			$this->forbiddenFieldsForPOSTViolationCheck($shiftSwapRequest, $constraint);

			// Raise constraint violation for when the status is anything other than 0 during a POST request
			$this->invalidStatusDuringPOSTViolationCheck($shiftSwapRequest, $constraint);
		}

		// Check for constraint violations during a PATCH / PUT ShiftSwapRequest request
		if (Request::METHOD_PATCH === $this->currentRequestMethod) {
			// Merge the detached ShiftSwapRequest object to be able to calculate unitOfWork
			/** @var ShiftSwapRequest $shiftSwapRequest */
			$shiftSwapRequest = $this->em->merge($shiftSwapRequest);

			// Calculate changed fields during PATCH / PUT
			$originalData = $unitOfWork->getOriginalEntityData($shiftSwapRequest);
			$unitOfWork->computeChangeSets();
			$changeSet = $unitOfWork->getEntityChangeSet($shiftSwapRequest);

			// Check for constraint violations for ShiftSwapRequest with status 0
			$this->shiftSwapRequestWithStatus0ViolationCheck($shiftSwapRequest, $constraint, $originalData, $changeSet);

			// Check for constraint violations for ShiftSwapRequest with status 1
			$this->shiftSwapRequestWithStatus1ViolationCheck($shiftSwapRequest, $constraint, $originalData, $changeSet);

			/*
			 * Raise constraint violation for when the fields are changed
			 * and the status of the ShiftSwapRequest is 2 3 4 5 6 during a PATCH / PUT request
			 */
			$this->notAllowedToUpdateViolationCheck($shiftSwapRequest, $constraint, $originalData, $changeSet);
		}
	}

	/**
	 * Constraint violation for when Applicant and receiver are the same.
	 *
	 * @param ShiftSwapRequest $shiftSwapRequest
	 * @param Constraint       $constraint
	 */
	private function sameApplicantAndReceiverViolationCheck(ShiftSwapRequest $shiftSwapRequest, Constraint $constraint)
	{
		// Applicant and receiver cannot be the same
		if ($shiftSwapRequest->getApplicant()->getId() === $shiftSwapRequest->getReceiver()->getId()) {
			$this->context->buildViolation($constraint->message)
				->setParameters(array(
					'{{ errorMessage }}' => 'applicant and receiver cannot be the same',
				))
				->addViolation();
		}
	}

	/**
	 * Constraint violation for when the requested assignment for swap does not belong to the current user.
	 *
	 * @param ShiftSwapRequest $shiftSwapRequest
	 * @param Constraint       $constraint
	 */
	private function invalidAssignmentViolationCheck(ShiftSwapRequest $shiftSwapRequest, Constraint $constraint)
	{
		if ($shiftSwapRequest->getAssignment()->getEmployee()->getId() !== $this->client->getEmployee()->getId()) {
			$this->context->buildViolation($constraint->message)
				->setParameters(array(
					'{{ errorMessage }}' => 'the requested assignment for swap does not belong to the current user',
				))
				->addViolation();
		}
	}

	/**
	 * Constraint violation for when the requested assignment for swap is in the past.
	 *
	 * @param ShiftSwapRequest $shiftSwapRequest
	 * @param Constraint       $constraint
	 */
	private function assignmentDateInThePastViolationCheck(ShiftSwapRequest $shiftSwapRequest, Constraint $constraint)
	{
		// the requested assignment for swap is in the past
		if ($shiftSwapRequest->getAssignment()->getStartDate() <= \DateTime::createFromFormat('U', time())) {
			$this->context->buildViolation($constraint->message)
				->setParameters(array(
					'{{ errorMessage }}' => 'the startDate of the requested assignment for swap is in the past',
				))
				->addViolation();
		}
	}

	/**
	 * Constraint violation for when the given assignment already as an associated shiftSwapRequest.
	 *
	 * @param ShiftSwapRequest $shiftSwapRequest
	 * @param Constraint       $constraint
	 */
	private function shiftSwapRequestAlreadyIssuedViolationCheck(ShiftSwapRequest $shiftSwapRequest, Constraint $constraint)
	{
		/** @var AssignmentRepository $assignmentRepository */
		$assignmentRepository = $this->em->getRepository(Assignment::class);
		$existingAssignment = $assignmentRepository->findOneByEmployee($shiftSwapRequest->getAssignment()->getId(), $this->client->getEmployee()->getId());
		if ($existingAssignment && count($existingAssignment->getShiftSwapRequests()) > 0) {
			$this->context->buildViolation($constraint->message)
				->setParameters(array(
					'{{ errorMessage }}' => 'there is already a shift swap request issued for this assignment',
				))
				->addViolation();
		}
	}

	/**
	 * Constraint violation for when the given receiver is not eligible for this swap.
	 *
	 * @param ShiftSwapRequest $shiftSwapRequest
	 * @param Constraint       $constraint
	 */
	private function ineligibleReceiverViolationCheck(ShiftSwapRequest $shiftSwapRequest, Constraint $constraint)
	{
		/** @var EmployeeRepository $employeeRepository */
		$employeeRepository = $this->em->getRepository(Employee::class);
		$eligibleEmployeesIds = $employeeRepository->findByDepartment($shiftSwapRequest->getAssignment()->getDepartment()->getId(), true);

		if (!in_array($shiftSwapRequest->getReceiver()->getId(), $eligibleEmployeesIds)) {
			$this->context->buildViolation($constraint->message)
				->setParameters(array(
					'{{ errorMessage }}' => 'the given receiver is not eligible for this assignment swap',
				))
				->addViolation();
		}
	}

	/**
	 * Constraint violation for when certain properties of the ShiftSwapRequest are sent that are not allowed during a POST request.
	 *
	 * @param ShiftSwapRequest $shiftSwapRequest
	 * @param Constraint       $constraint
	 */
	private function forbiddenFieldsForPOSTViolationCheck(ShiftSwapRequest $shiftSwapRequest, Constraint $constraint)
	{
		$forbiddenFields = array(
			self::APPLICANT_WITHDRAWAL_MESSAGE_FIELD,
			self::RECEIVER_MESSAGE_FIELD,
			self::RECEIVER_WITHDRAWAL_MESSAGE_FIELD,
			self::PLANNER_MESSAGE_FIELD,
		);

		foreach ($forbiddenFields as $field) {
			if (null !== $this->propertyAccessor->getValue($shiftSwapRequest, $field)) {
				$this->context->buildViolation($constraint->message)
					->setParameters(array(
						'{{ errorMessage }}' => "Not allowed to change $field",
					))
					->addViolation();
			}
		}
	}

	/**
	 * Constraint violation for when the status is anything other than 0 during a POST request.
	 *
	 * @param ShiftSwapRequest $shiftSwapRequest
	 * @param Constraint       $constraint
	 */
	private function invalidStatusDuringPOSTViolationCheck(ShiftSwapRequest $shiftSwapRequest, Constraint $constraint)
	{
		if (ShiftSwapRequestStatus::UNPROCESSED_BY_RECEIVER !== $shiftSwapRequest->getStatus()) {
			$this->context->buildViolation($constraint->message)
				->setParameters(array(
					'{{ errorMessage }}' => 'Invalid status',
				))
				->addViolation();
		}
	}

	/**
	 * Check and raise constraint violations for ShiftSwapRequest with status 0 during PATCH / PUT.
	 *
	 * @param ShiftSwapRequest $shiftSwapRequest
	 * @param Constraint       $constraint
	 * @param $originalData
	 * @param $changeSet
	 */
	private function shiftSwapRequestWithStatus0ViolationCheck(ShiftSwapRequest $shiftSwapRequest, Constraint $constraint, $originalData, $changeSet)
	{
		$originalStatus = $originalData['status'];
		$newStatus = $shiftSwapRequest->getStatus();
		$currentRole = $shiftSwapRequest->getCurrentRole();

		// If the status was 0 (newly created)
		if (ShiftSwapRequestStatus::UNPROCESSED_BY_RECEIVER === $originalStatus) {
			// Allowed status numbers to be changed from status 0 by the 'applicant' of a ShiftSwapRequest during a PATCH / PUT request
			$allowedStatusesForApplicant = array(
				ShiftSwapRequestStatus::WITHDRAWN_BY_APPLICANT,
			);

			// Allowed status numbers to be changed from status 0 by the 'receiver' of a ShiftSwapRequest during a PATCH / PUT request
			$allowedStatusesForReceiver = array(
				ShiftSwapRequestStatus::GRANTED_BY_RECEIVER,
				ShiftSwapRequestStatus::DENIED_BY_RECEIVER,
			);

			// Raise constraint violation for when the status number is not allowed for the current role of the ShiftSwapRequest during a PATCH / PUT request
			if ((ShiftSwapRequestRequestRole::APPLICANT === $currentRole && !in_array($newStatus, $allowedStatusesForApplicant))
				|| (ShiftSwapRequestRequestRole::RECEIVER === $currentRole && !in_array($newStatus, $allowedStatusesForReceiver))) {
				$this->context->buildViolation($constraint->message)
					->setParameters(array(
						'{{ errorMessage }}' => "Not allowed to change status from $originalStatus to $newStatus by the current user",
					))
					->addViolation();
			}

			// Properties that are NOT allowed to be changed by the 'applicant' of a ShiftSwapRequest during a PATCH / PUT request
			$forbiddenFieldsForApplicant = array(
				self::APPLICANT_FIELD,
				self::RECEIVER_FIELD,
				self::ASSIGNMENT_FIELD,
				self::APPLICANT_MESSAGE_FIELD,
				self::RECEIVER_MESSAGE_FIELD,
				self::RECEIVER_WITHDRAWAL_MESSAGE_FIELD,
				self::PLANNER_MESSAGE_FIELD,
			);

			/**
			 * Raise constraint violation for when the fields are changed that are not allowed
			 * by the 'applicant' of the ShiftSwapRequest during a PATCH / PUT request.
			 */
			$invalidEditedFields = array_intersect_key($changeSet, array_flip($forbiddenFieldsForApplicant));
			if (ShiftSwapRequestRequestRole::APPLICANT === $currentRole && $invalidEditedFields) {
				foreach ($invalidEditedFields as $invalidEditedField => $changedValue) {
					if ((self::APPLICANT_FIELD === $invalidEditedField || self::RECEIVER_FIELD === $invalidEditedField)
						&& ($changedValue[0]->getId() === $changedValue[1]->getId())
					) {
						continue;
					}
					$this->context->buildViolation($constraint->message)
						->setParameters(array(
							'{{ errorMessage }}' => "Not allowed to change $invalidEditedField",
						))
						->addViolation();
				}
			}

			// Properties that are NOT allowed to be changed by the 'receiver' of a ShiftSwapRequest during a PATCH / PUT request
			$forbiddenFieldsForReceiver = array(
				self::APPLICANT_FIELD,
				self::RECEIVER_FIELD,
				self::ASSIGNMENT_FIELD,
				self::APPLICANT_MESSAGE_FIELD,
				self::APPLICANT_WITHDRAWAL_MESSAGE_FIELD,
				self::RECEIVER_WITHDRAWAL_MESSAGE_FIELD,
				self::PLANNER_MESSAGE_FIELD,
			);

			/**
			 * Raise constraint violation for when the fields are changed that are not allowed
			 * by the 'receiver' of the ShiftSwapRequest during a PATCH / PUT request.
			 */
			$invalidEditedFields = array_intersect_key($changeSet, array_flip($forbiddenFieldsForReceiver));
			if (ShiftSwapRequestRequestRole::RECEIVER === $currentRole && $invalidEditedFields) {
				foreach ($invalidEditedFields as $invalidEditedField => $changedValue) {
					if ((self::APPLICANT_FIELD === $invalidEditedField || self::RECEIVER_FIELD === $invalidEditedField)
						&& ($changedValue[0]->getId() === $changedValue[1]->getId())
					) {
						continue;
					}
					$this->context->buildViolation($constraint->message)
						->setParameters(array(
							'{{ errorMessage }}' => "Not allowed to change $invalidEditedField",
						))
						->addViolation();
				}
			}
		}
	}

	/**
	 * Check and raise constraint violations for ShiftSwapRequest with status 1 during PATCH / PUT.
	 *
	 * @param ShiftSwapRequest $shiftSwapRequest
	 * @param Constraint       $constraint
	 * @param $originalData
	 * @param $changeSet
	 */
	private function shiftSwapRequestWithStatus1ViolationCheck(ShiftSwapRequest $shiftSwapRequest, Constraint $constraint, $originalData, $changeSet)
	{
		$originalStatus = $originalData['status'];
		$newStatus = $shiftSwapRequest->getStatus();
		$currentRole = $shiftSwapRequest->getCurrentRole();

		// If the status was 1 (granted by receiver)
		if (ShiftSwapRequestStatus::GRANTED_BY_RECEIVER === $originalStatus) {
			// Allowed status numbers to be changed from status 0 by the 'applicant' of a ShiftSwapRequest during a PATCH / PUT request
			$allowedStatusesForApplicant = array(
				ShiftSwapRequestStatus::WITHDRAWN_BY_APPLICANT,
			);

			// Allowed status numbers to be changed from status 0 by the 'receiver' of a ShiftSwapRequest during a PATCH / PUT request
			$allowedStatusesForReceiver = array(
				ShiftSwapRequestStatus::WITHDRAWN_BY_RECEIVER,
			);

			// Raise constraint violation for when the status number is not allowed for the current role of the ShiftSwapRequest during a PATCH / PUT request
			if ((ShiftSwapRequestRequestRole::APPLICANT === $currentRole && !in_array($newStatus, $allowedStatusesForApplicant))
				|| (ShiftSwapRequestRequestRole::RECEIVER === $currentRole && !in_array($newStatus, $allowedStatusesForReceiver))) {
				$this->context->buildViolation($constraint->message)
					->setParameters(array(
						'{{ errorMessage }}' => "Not allowed to change status from $originalStatus to $newStatus by the current user",
					))
					->addViolation();
			}

			// Properties that are NOT allowed to be changed by the 'applicant' of a ShiftSwapRequest during a PATCH / PUT request
			$forbiddenFieldsForApplicant = array(
				self::APPLICANT_FIELD,
				self::RECEIVER_FIELD,
				self::ASSIGNMENT_FIELD,
				self::APPLICANT_MESSAGE_FIELD,
				self::RECEIVER_MESSAGE_FIELD,
				self::RECEIVER_WITHDRAWAL_MESSAGE_FIELD,
				self::PLANNER_MESSAGE_FIELD,
			);

			/**
			 * Raise constraint violation for when the fields are changed that are not allowed
			 * by the 'applicant' of the ShiftSwapRequest during a PATCH / PUT request.
			 */
			$invalidEditedFields = array_intersect_key($changeSet, array_flip($forbiddenFieldsForApplicant));
			if (ShiftSwapRequestRequestRole::APPLICANT === $currentRole && $invalidEditedFields) {
				foreach ($invalidEditedFields as $invalidEditedField => $changedValue) {
					if ((self::APPLICANT_FIELD === $invalidEditedField || self::RECEIVER_FIELD === $invalidEditedField)
						&& ($changedValue[0]->getId() === $changedValue[1]->getId())
					) {
						continue;
					}
					$this->context->buildViolation($constraint->message)
						->setParameters(array(
							'{{ errorMessage }}' => "Not allowed to change $invalidEditedField",
						))
						->addViolation();
				}
			}

			// Properties that are NOT allowed to be changed by the 'receiver' of a ShiftSwapRequest during a PATCH / PUT request
			$forbiddenFieldsForReceiver = array(
				self::APPLICANT_FIELD,
				self::RECEIVER_FIELD,
				self::ASSIGNMENT_FIELD,
				self::APPLICANT_MESSAGE_FIELD,
				self::APPLICANT_WITHDRAWAL_MESSAGE_FIELD,
				self::RECEIVER_MESSAGE_FIELD,
				self::PLANNER_MESSAGE_FIELD,
			);

			/**
			 * Raise constraint violation for when the fields are changed that are not allowed
			 * by the 'receiver' of the ShiftSwapRequest during a PATCH / PUT request.
			 */
			$invalidEditedFields = array_intersect_key($changeSet, array_flip($forbiddenFieldsForReceiver));
			if (ShiftSwapRequestRequestRole::RECEIVER === $currentRole && $invalidEditedFields) {
				foreach ($invalidEditedFields as $invalidEditedField => $changedValue) {
					if ((self::APPLICANT_FIELD === $invalidEditedField || self::RECEIVER_FIELD === $invalidEditedField)
						&& ($changedValue[0]->getId() === $changedValue[1]->getId())
					) {
						continue;
					}
					$this->context->buildViolation($constraint->message)
						->setParameters(array(
							'{{ errorMessage }}' => "Not allowed to change $invalidEditedField",
						))
						->addViolation();
				}
			}
		}
	}

	/**
	 * Constraint violation for when fields are changed when the original status of the ShiftSwapRequest is 2 3 4 5 6 during a PATCH / PUT request.
	 *
	 * @param ShiftSwapRequest $shiftSwapRequest
	 * @param Constraint       $constraint
	 * @param $originalData
	 * @param $changeSet
	 */
	private function notAllowedToUpdateViolationCheck(ShiftSwapRequest $shiftSwapRequest, Constraint $constraint, $originalData, $changeSet)
	{
		$originalStatus = $originalData['status'];
		$currentRole = $shiftSwapRequest->getCurrentRole();

		if (in_array($originalStatus, array(
				ShiftSwapRequestStatus::DENIED_BY_RECEIVER,
				ShiftSwapRequestStatus::GRANTED_BY_PLANNER,
				ShiftSwapRequestStatus::DENIED_BY_PLANNER,
				ShiftSwapRequestStatus::WITHDRAWN_BY_APPLICANT,
				ShiftSwapRequestStatus::WITHDRAWN_BY_RECEIVER,
			)) && (ShiftSwapRequestRequestRole::APPLICANT === $currentRole || ShiftSwapRequestRequestRole::RECEIVER === $currentRole)
			&& $changeSet
		) {
			$this->context->buildViolation($constraint->message)
				->setParameters(array(
					'{{ errorMessage }}' => 'Not allowed to update record by current user',
				))
				->addViolation();
		}
	}
}
