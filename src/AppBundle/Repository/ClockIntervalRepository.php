<?php

namespace AppBundle\Repository;

use AppBundle\Entity\ClockInterval;
use AppBundle\Entity\Employee;
use AppBundle\Util\Constants;
use Doctrine\ORM\EntityRepository;
use DateTime;

/**
 * ClockIntervalRepository.
 */
class ClockIntervalRepository extends EntityRepository
{
	/**
	 * @param $employeeId
	 * @param DateTime|null $dateTimeFrom
	 * @param DateTime|null $dateTimeTo
	 *
	 * @return ClockInterval[]|[]
	 */
	public function findByEmployeeWithDateParameters($employeeId, \DateTime $dateTimeFrom = null, \DateTime $dateTimeTo = null)
	{
		/** @var Employee $employee */
		$employee = $this->getEntityManager()->find(Employee::class, $employeeId);

		if (!$employee) {
			return [];
		}

		// Must use this instead of new DateTime() to be able to mock current time in phpunit tests
		$currentDate = DateTime::createFromFormat('U', time());
		$qb = $this->getEntityManager()->createQueryBuilder();

		$intervalQuery = null;
		$parameters = array();

		// if no dateTimeFrom and no dateTimeTo provided, default fetch clockIntervals of current month
		if (!$dateTimeFrom && !$dateTimeTo) {
			$intervalQuery = $qb->expr()->andX(
				$qb->expr()->eq('year(clockInterval.startDate)', $currentDate->format('Y')),
				$qb->expr()->eq('month(clockInterval.startDate)', $currentDate->format('m'))
			);
		}

		// If both dateTimeFrom and dateTimeTo query parameters are given, clockInterval within that interval are returned.
		if ($dateTimeFrom && $dateTimeTo) {
			$intervalQuery = $qb->expr()->andX(
				$qb->expr()->gte("date_format(clockInterval.startDate, '%Y-%m-%d')", ':dateTimeFrom'),
				$qb->expr()->lte("date_format(clockInterval.startDate, '%Y-%m-%d')", ':dateTimeTo')
			);
			$parameters['dateTimeFrom'] = $dateTimeFrom->format(Constants::DateFormatString);
			$parameters['dateTimeTo'] = $dateTimeTo->format(Constants::DateFormatString);
		}

		// if only dateTimeFrom and no dateTimeTo provided, return clockInterval from the dateTimeFrom value to the end of that month
		if ($dateTimeFrom && !$dateTimeTo) {
			$intervalQuery = $qb->expr()->andX(
				$qb->expr()->gte("date_format(clockInterval.startDate, '%Y-%m-%d')", ':dateTimeFrom'),
				$qb->expr()->lte("date_format(clockInterval.startDate, '%Y-%m')", ':defaultDateTimeTo')
			);
			$parameters['dateTimeFrom'] = $dateTimeFrom->format(Constants::DateFormatString);
			$parameters['defaultDateTimeTo'] = $dateTimeFrom->format('Y-m');
		}

		// if only dateTimeTo and no dateTimeFrom provided, return clockInterval from the start of the month of the dateTimeFrom to the dateTimeFrom value
		if (!$dateTimeFrom && $dateTimeTo) {
			$intervalQuery = $qb->expr()->andX(
				$qb->expr()->gte("date_format(clockInterval.startDate, '%Y-%m')", ':defaultDateTimeFrom'),
				$qb->expr()->lte("date_format(clockInterval.startDate, '%Y-%m-%d')", ':dateTimeTo')
			);
			$parameters['defaultDateTimeFrom'] = $dateTimeTo->format('Y-m');
			$parameters['dateTimeTo'] = $dateTimeTo->format(Constants::DateFormatString);
		}

		$qb = $this->getEntityManager()->createQueryBuilder();
		$qb
			->select('clockInterval')
			->from(ClockInterval::class, 'clockInterval')
			->where($qb->expr()->eq('clockInterval.employee', ':employeeId'))
			->andWhere($intervalQuery)
			->orderBy('clockInterval.startDate', 'ASC')
			->setParameters($parameters)
			->setParameter('employeeId', $employeeId);

		$clockIntervals = $qb->getQuery()->getResult();

		return $clockIntervals;
	}

	/**
	 * @param $employeeId
	 * @param DateTime|null $dateTimeFrom
	 * @param DateTime|null $dateTimeTo
	 *
	 * @return ClockInterval[]|[]
	 */
	public function findByClientWithDateParameters($employeeId, \DateTime $dateTimeFrom = null, \DateTime $dateTimeTo = null)
	{
		/** @var Employee $employee */
		$employee = $this->getEntityManager()->find(Employee::class, $employeeId);

		if (!$employee) {
			return [];
		}

		// Must use this instead of new DateTime() to be able to mock current time in phpunit tests
		$currentDate = DateTime::createFromFormat('U', time());
		$qb = $this->getEntityManager()->createQueryBuilder();

		$intervalQuery = null;
		$parameters = array();

		// if no dateTimeFrom and no dateTimeTo provided, default fetch clockIntervals of current month
		if (!$dateTimeFrom && !$dateTimeTo) {
			$intervalQuery = $qb->expr()->andX(
				$qb->expr()->eq('year(clockInterval.startDate)', $currentDate->format('Y')),
				$qb->expr()->eq('month(clockInterval.startDate)', $currentDate->format('m'))
			);
		}

		// If both dateTimeFrom and dateTimeTo query parameters are given, clockInterval within that interval are returned.
		if ($dateTimeFrom && $dateTimeTo) {
			$intervalQuery = $qb->expr()->andX(
				$qb->expr()->gte("date_format(clockInterval.startDate, '%Y-%m-%d')", ':dateTimeFrom'),
				$qb->expr()->lte("date_format(clockInterval.startDate, '%Y-%m-%d')", ':dateTimeTo')
			);
			$parameters['dateTimeFrom'] = $dateTimeFrom->format(Constants::DateFormatString);
			$parameters['dateTimeTo'] = $dateTimeTo->format(Constants::DateFormatString);
		}

		// if only dateTimeFrom and no dateTimeTo provided, return clockInterval from the dateTimeFrom value to the end of that month
		if ($dateTimeFrom && !$dateTimeTo) {
			$intervalQuery = $qb->expr()->andX(
				$qb->expr()->gte("date_format(clockInterval.startDate, '%Y-%m-%d')", ':dateTimeFrom'),
				$qb->expr()->lte("date_format(clockInterval.startDate, '%Y-%m')", ':defaultDateTimeTo')
			);
			$parameters['dateTimeFrom'] = $dateTimeFrom->format(Constants::DateFormatString);
			$parameters['defaultDateTimeTo'] = $dateTimeFrom->format('Y-m');
		}

		// if only dateTimeTo and no dateTimeFrom provided, return clockInterval from the start of the month of the dateTimeFrom to the dateTimeFrom value
		if (!$dateTimeFrom && $dateTimeTo) {
			$intervalQuery = $qb->expr()->andX(
				$qb->expr()->gte("date_format(clockInterval.startDate, '%Y-%m')", ':defaultDateTimeFrom'),
				$qb->expr()->lte("date_format(clockInterval.startDate, '%Y-%m-%d')", ':dateTimeTo')
			);
			$parameters['defaultDateTimeFrom'] = $dateTimeTo->format('Y-m');
			$parameters['dateTimeTo'] = $dateTimeTo->format(Constants::DateFormatString);
		}

		$qb = $this->getEntityManager()->createQueryBuilder();
		$qb
			->select('clockInterval')
			->from(ClockInterval::class, 'clockInterval')
			->where($qb->expr()->eq('clockInterval.employee', ':employeeId'))
			->andWhere($intervalQuery)
			->orderBy('clockInterval.startDate', 'ASC')
			->setParameters($parameters)
			->setParameter('employeeId', $employeeId);

		$clockIntervals = $qb->getQuery()->getResult();

		return $clockIntervals;
	}
}
