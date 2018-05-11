<?php

namespace AppBundle\Repository;

use AppBundle\Entity\ClockMoment;
use AppBundle\Entity\Employee;
use AppBundle\Util\Constants;
use DateTime;
use Doctrine\ORM\EntityRepository;

/**
 * ClockMomentRepository.
 */
class ClockMomentRepository extends EntityRepository
{
	/**
	 * @param $employeeId
	 *
	 * @return ClockMoment|null
	 */
	public function findLastTwoClockMomentsOfEmployee($employeeId)
	{
		$qb = $this->createQueryBuilder('clockMoment');
		$qb
			->where($qb->expr()->eq('clockMoment.employee', ':employeeId'))
			->orderBy('clockMoment.id', 'DESC')
			->setMaxResults(1)
			->setParameter('employeeId', $employeeId);

		$result = $qb->getQuery()->getOneOrNullResult();

		return $result;
	}

	/**
	 * @param $employeeId
	 * @param DateTime|null $dateTimeFrom
	 * @param DateTime|null $dateTimeTo
	 *
	 * @return ClockMoment[]|[]
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

		// if no dateTimeFrom and no dateTimeTo provided, default fetch clockMoments of current month
		if (!$dateTimeFrom && !$dateTimeTo) {
			$intervalQuery = $qb->expr()->andX(
				$qb->expr()->eq('year(clockMoment.timeStamp)', $currentDate->format('Y')),
				$qb->expr()->eq('month(clockMoment.timeStamp)', $currentDate->format('m'))
			);
		}

		// If both dateTimeFrom and dateTimeTo query parameters are given, clockMoment within that interval are returned.
		if ($dateTimeFrom && $dateTimeTo) {
			$intervalQuery = $qb->expr()->andX(
				$qb->expr()->gte("date_format(clockMoment.timeStamp, '%Y-%m-%d')", ':dateTimeFrom'),
				$qb->expr()->lte("date_format(clockMoment.timeStamp, '%Y-%m-%d')", ':dateTimeTo')
			);
			$parameters['dateTimeFrom'] = $dateTimeFrom->format(Constants::DateFormatString);
			$parameters['dateTimeTo'] = $dateTimeTo->format(Constants::DateFormatString);
		}

		// if only dateTimeFrom and no dateTimeTo provided, return clockMoment from the dateTimeFrom value to the end of that month
		if ($dateTimeFrom && !$dateTimeTo) {
			$intervalQuery = $qb->expr()->andX(
				$qb->expr()->gte("date_format(clockMoment.timeStamp, '%Y-%m-%d')", ':dateTimeFrom'),
				$qb->expr()->lte("date_format(clockMoment.timeStamp, '%Y-%m')", ':defaultDateTimeTo')
			);
			$parameters['dateTimeFrom'] = $dateTimeFrom->format(Constants::DateFormatString);
			$parameters['defaultDateTimeTo'] = $dateTimeFrom->format('Y-m');
		}

		// if only dateTimeTo and no dateTimeFrom provided, return clockMoment from the start of the month of the dateTimeFrom to the dateTimeFrom value
		if (!$dateTimeFrom && $dateTimeTo) {
			$intervalQuery = $qb->expr()->andX(
				$qb->expr()->gte("date_format(clockMoment.timeStamp, '%Y-%m')", ':defaultDateTimeFrom'),
				$qb->expr()->lte("date_format(clockMoment.timeStamp, '%Y-%m-%d')", ':dateTimeTo')
			);
			$parameters['defaultDateTimeFrom'] = $dateTimeTo->format('Y-m');
			$parameters['dateTimeTo'] = $dateTimeTo->format(Constants::DateFormatString);
		}

		$qb = $this->getEntityManager()->createQueryBuilder();
		$qb
			->select('clockMoment')
			->from(ClockMoment::class, 'clockMoment')
			->where($qb->expr()->eq('clockMoment.employee', ':employeeId'))
			->andWhere($intervalQuery)
			->orderBy('clockMoment.timeStamp', 'ASC')
			->setParameters($parameters)
			->setParameter('employeeId', $employeeId);

		$clockMoments = $qb->getQuery()->getResult();

		return $clockMoments;
	}
}
