<?php

namespace AppBundle\Repository;

use AppBundle\Entity\Register;
use AppBundle\Util\Constants;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityRepository;

/**
 * RegisterRepository.
 */
class RegisterRepository extends EntityRepository
{
	/**
	 * Find all Registers with date parameters that a client is allowed to see, return registers of current month by default.
	 *
	 * @param DateTime|null $dateTimeFrom
	 * @param DateTime|null $dateTimeTo
	 *
	 * @return array
	 */
	public function findAllWithDateParameters(\DateTime $dateTimeFrom = null, \DateTime $dateTimeTo = null)
	{
		$qb = $this->createQueryBuilder('register');
		$qb
			->addCriteria($this->createDateRangeCriteria($dateTimeFrom, $dateTimeTo))
			->orderBy('register.startDate', 'ASC');

		$registers = $qb->getQuery()->getResult();

		return $registers;
	}

	/**
	 * Find Registers of a specific Employee with date parameters, return registers of current month by default.
	 *
	 * @param $employeeId
	 * @param DateTime|null $dateTimeFrom
	 * @param DateTime|null $dateTimeTo
	 *
	 * @return array
	 */
	public function findByEmployeeWithDateParameters($employeeId, \DateTime $dateTimeFrom = null, \DateTime $dateTimeTo = null)
	{
		$qb = $this->createQueryBuilder('register');
		$qb
			->addCriteria($this->createDateRangeCriteria($dateTimeFrom, $dateTimeTo))
			->andWhere('register.employee = :employeeId')
			->orderBy('register.startDate', 'ASC')
			->setParameter('employeeId', $employeeId);

		$registers = $qb->getQuery()->getResult();

		return $registers;
	}

	/**
	 * Check if a given Register has date overlapping with an existing one.
	 *
	 * @param Register $register
	 *
	 * @return bool
	 */
	public function hasOverlappingRegister(Register $register)
	{
		$qb = $this->createQueryBuilder('register');
		$qb
			->where($qb->expr()->lt('register.startDate', ':referenceEndDate'))
			->andWhere($qb->expr()->gt('register.endDate', ':referenceStartDate'))
			->andWhere($qb->expr()->eq('register.type', ':referenceType'))
			->setParameter('referenceEndDate', $register->getEndDate())
			->setParameter('referenceStartDate', $register->getStartDate())
			->setParameter('referenceType', $register->getTypeValueName());

		// During PUT/PATCH make sure don't check against itself
		if ($register->getId()) {
			$qb
				->andWhere($qb->expr()->neq('register.id', ':referenceId'))
				->setParameter('referenceId', $register->getId());
		}

		$overlappingRegisters = $qb->getQuery()->getResult();

		return !empty($overlappingRegisters);
	}

	/**
	 * Creates select within date range criteria.
	 *
	 * @param DateTime|null $dateTimeFrom
	 * @param DateTime|null $dateTimeTo
	 *
	 * @return Criteria
	 */
	private function createDateRangeCriteria(\DateTime $dateTimeFrom = null, \DateTime $dateTimeTo = null)
	{
		// Must use this instead of new DateTime() to be able to mock current time in phpunit tests
		$currentDate = DateTime::createFromFormat('U', time());
		$intervalQuery = null;

		// if no dateTimeFrom and no dateTimeTo provided, default fetch registers of current month
		if (!$dateTimeFrom && !$dateTimeTo) {
			$beginOfTheMonth = (new DateTime($currentDate->format(Constants::DateFormatString)))->modify('first day of this month');
			$endOfTheMonth = (new DateTime($currentDate->format(Constants::DateFormatString)))->modify('first day of next month');

			$intervalQuery = Criteria::expr()->andX(
				Criteria::expr()->gte('startDate', $beginOfTheMonth),
				Criteria::expr()->lt('startDate', $endOfTheMonth)
			);
		}

		// If both dateTimeFrom and dateTimeTo query parameters are given, registers within that interval are returned.
		if ($dateTimeFrom && $dateTimeTo) {
			$newDateTimeTo = (new DateTime($dateTimeTo->format(Constants::DateFormatString)))->modify('+1 day');

			$intervalQuery = Criteria::expr()->andX(
				Criteria::expr()->gte('startDate', $dateTimeFrom->format(Constants::DateFormatString)),
				Criteria::expr()->lt('startDate', $newDateTimeTo)
			);
		}

		// if only dateTimeFrom and no dateTimeTo provided, return registers from the dateTimeFrom value to the end of that month
		if ($dateTimeFrom && !$dateTimeTo) {
			$endOfTheMonth = (new DateTime($dateTimeFrom->format(Constants::DateFormatString)))->modify('first day of next month');

			$intervalQuery = Criteria::expr()->andX(
				Criteria::expr()->gte('startDate', $dateTimeFrom->format(Constants::DateFormatString)),
				Criteria::expr()->lt('startDate', $endOfTheMonth)
			);
		}

		// if only dateTimeTo and no dateTimeFrom provided, return registers from the start of the month of the dateTimeFrom to the dateTimeFrom value
		if (!$dateTimeFrom && $dateTimeTo) {
			$beginOfTheMonth = (new DateTime($dateTimeTo->format(Constants::DateFormatString)))->modify('first day of this month');
			$newDateTimeTo = (new DateTime($dateTimeTo->format(Constants::DateFormatString)))->modify('+1 day');

			$intervalQuery = Criteria::expr()->andX(
				Criteria::expr()->gte('startDate', $beginOfTheMonth),
				Criteria::expr()->lt('startDate', $newDateTimeTo)
			);
		}

		return Criteria::create()->andWhere($intervalQuery);
	}

	/**
	 * Find corresponding register by employee and assignment.
	 *
	 * @param Register $register
	 *
	 * @return Register|null
	 */
	public function findByEmployeeAndAssignment(Register $register)
	{
		/** @var Register $register */
		$register = $this->findOneBy(
			[
				'employee' => $register->getEmployee()->getId(),
				'assignment' => $register->getAssignment()->getId(),
			]
		);

		return $register;
	}
}
