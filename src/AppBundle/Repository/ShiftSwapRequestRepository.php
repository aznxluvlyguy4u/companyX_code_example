<?php

namespace AppBundle\Repository;

use AppBundle\Entity\Employee;
use AppBundle\Entity\ShiftSwapRequest;
use AppBundle\Enumerator\ShiftSwapRequestStatus;
use AppBundle\Util\Constants;
use DateTime;
use Doctrine\ORM\EntityRepository;

/**
 * ShiftSwapRequestRepository.
 */
class ShiftSwapRequestRepository extends EntityRepository
{
	/**
	 * Find ShiftSwapRequests for an Employee with dateFrom dateTo parameters that an Employee is allowed to see , returns ShiftSwapRequests of current month by default.
	 *
	 * @param $employeeId
	 * @param DateTime|null $dateTimeFrom
	 * @param DateTime|null $dateTimeTo
	 *
	 * @return array
	 */
	public function findForEmployeeByParameters($employeeId, \DateTime $dateTimeFrom = null, \DateTime $dateTimeTo = null, $hash = null)
	{
		// Must use this instead of new DateTime() to be able to mock current time in phpunit tests
		$currentDate = DateTime::createFromFormat('U', time());
		$qb = $this->createQueryBuilder('shiftSwapRequest');

		$intervalQuery = null;
		$checkHashQuery = null;
		$parameters = array(
			'employeeId' => $employeeId,
		);

		// if no dateTimeFrom and no dateTimeTo provided, default fetch shiftSwapRequests of current month
		if (!$dateTimeFrom && !$dateTimeTo) {
			$intervalQuery = $qb->expr()->andX(
				$qb->expr()->eq('year(shiftSwapRequest.startDate)', $currentDate->format('Y')),
				$qb->expr()->eq('month(shiftSwapRequest.startDate)', $currentDate->format('m'))
			);
		}

		// If both dateTimeFrom and dateTimeTo query parameters are given, shiftSwapRequests within that interval are returned.
		if ($dateTimeFrom && $dateTimeTo) {
			$intervalQuery = $qb->expr()->andX(
				$qb->expr()->gte("date_format(shiftSwapRequest.startDate, '%Y-%m-%d')", ':dateTimeFrom'),
				$qb->expr()->lte("date_format(shiftSwapRequest.startDate, '%Y-%m-%d')", ':dateTimeTo')
			);
			$parameters['dateTimeFrom'] = $dateTimeFrom->format(Constants::DateFormatString);
			$parameters['dateTimeTo'] = $dateTimeTo->format(Constants::DateFormatString);
		}

		// if only dateTimeFrom and no dateTimeTo provided, return shiftSwapRequests from the dateTimeFrom value to the end of that month
		if ($dateTimeFrom && !$dateTimeTo) {
			$intervalQuery = $qb->expr()->andX(
				$qb->expr()->gte("date_format(shiftSwapRequest.startDate, '%Y-%m-%d')", ':dateTimeFrom'),
				$qb->expr()->lte("date_format(shiftSwapRequest.startDate, '%Y-%m')", ':defaultDateTimeTo')
			);
			$parameters['dateTimeFrom'] = $dateTimeFrom->format(Constants::DateFormatString);
			$parameters['defaultDateTimeTo'] = $dateTimeFrom->format('Y-m');
		}

		// if only dateTimeTo and no dateTimeFrom provided, return shiftSwapRequests from the start of the month of the dateTimeFrom to the dateTimeFrom value
		if (!$dateTimeFrom && $dateTimeTo) {
			$intervalQuery = $qb->expr()->andX(
				$qb->expr()->gte("date_format(shiftSwapRequest.startDate, '%Y-%m')", ':defaultDateTimeFrom'),
				$qb->expr()->lte("date_format(shiftSwapRequest.startDate, '%Y-%m-%d')", ':dateTimeTo')
			);
			$parameters['defaultDateTimeFrom'] = $dateTimeTo->format('Y-m');
			$parameters['dateTimeTo'] = $dateTimeTo->format(Constants::DateFormatString);
		}

		if ($hash) {
			$checkHashQuery = $qb->expr()->andX(
				$qb->expr()->eq('shiftSwapRequest.receiver', ':employeeId'),
				$qb->expr()->eq('shiftSwapRequest.hash', ':hash'),
				$qb->expr()->eq('shiftSwapRequest.status', ShiftSwapRequestStatus::UNPROCESSED_BY_RECEIVER),
				$qb->expr()->gt("date_format(shiftSwapRequest.expireDate, '%Y-%m-%d')", ':currentDate')
			);

			if (!$dateTimeFrom && !$dateTimeTo) {
				$intervalQuery = null;
			}

			$parameters['hash'] = $hash;
		}

		$qb
			->where($qb->expr()->orX(
				'shiftSwapRequest.applicant = :employeeId',
				'shiftSwapRequest.receiver = :employeeId'
			))
			->andWhere($intervalQuery)
			->andWhere($qb->expr()->in('shiftSwapRequest.status', array(
				ShiftSwapRequestStatus::UNPROCESSED_BY_RECEIVER,
				ShiftSwapRequestStatus::GRANTED_BY_RECEIVER,
				ShiftSwapRequestStatus::GRANTED_BY_PLANNER,
			)))
			->andWhere($qb->expr()->gt("date_format(shiftSwapRequest.startDate, '%Y-%m-%d')", ':currentDate'))
			->andWhere($checkHashQuery)
			->orderBy('shiftSwapRequest.startDate', 'ASC')
			->setParameters($parameters)
			->setParameter('currentDate', $currentDate->format(Constants::DateFormatString));

		$shiftSwapRequests = $qb->getQuery()->getResult();

		return $shiftSwapRequests;
	}

	// TODO Check if employee is a 'Planner' in which case he/she can get any ShiftSwapRequest

	/**
	 * Find one ShiftSwapRequest by ID that an Employee is allowed to see.
	 *
	 * @param $shiftSwapRequestId
	 * @param $employeeId
	 *
	 * @return ShiftSwapRequest|null
	 */
	public function findOneByEmployeeWithRestrictionCheck($shiftSwapRequestId, $employeeId)
	{
		// Must use this instead of new DateTime() to be able to mock current time in phpunit tests
		$currentDate = DateTime::createFromFormat('U', time());

		/** @var Employee $employee */
		$employee = $this->getEntityManager()->find(Employee::class, $employeeId);

		if (!$employee) {
			return null;
		}

		$qb = $this->createQueryBuilder('shiftSwapRequest');
		$qb
			->where('shiftSwapRequest.id = :shiftSwapRequestId')
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('shiftSwapRequest.applicant', ':employeeId'),
				$qb->expr()->eq('shiftSwapRequest.receiver', ':employeeId')
			))
			->andWhere($qb->expr()->gt("date_format(shiftSwapRequest.startDate, '%Y-%m-%d')", ':currentDate'))
			->setParameters(array(
				'shiftSwapRequestId' => $shiftSwapRequestId,
				'employeeId' => $employeeId,
				'currentDate' => $currentDate->format(Constants::DateFormatString), )
			);

		$shiftSwapRequest = $qb->getQuery()->getOneOrNullResult();

		return $shiftSwapRequest;
	}
}
