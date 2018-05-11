<?php

namespace AppBundle\Repository;

use AppBundle\Entity\Bulletin;
use AppBundle\Entity\Client;
use AppBundle\Entity\Office;
use AppBundle\Util\Constants;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityRepository;

/**
 * BulletinRepository.
 */
class BulletinRepository extends EntityRepository
{
	/**
	 * Find all Bulletins with date parameters that an authenticated client is allowed to see, returns bulletins of current month by default.
	 *
	 * @param DateTime|null $dateTimeFrom
	 * @param DateTime|null $dateTimeTo
	 * @param null          $officeId
	 * @param Client|null   $client
	 *
	 * @return Bulletin[] []
	 */
	public function findAllWithParametersWithRestrictionCheck(
		\DateTime $dateTimeFrom = null,
		\DateTime $dateTimeTo = null,
		$officeId = null,
		Client $client = null
	) {
		$qb = $this->createQueryBuilder('bulletin');
		$qb
			->addCriteria($this->createAllowedOfficeCriteria($client, $officeId))
			->addCriteria($this->createDateRangeCriteria($dateTimeFrom, $dateTimeTo))
			->addCriteria($this->createUserRoleCriteria($client))
			->orderBy('bulletin.startDate', Criteria::ASC);

		$bulletins = $qb->getQuery()->getResult();

		return $bulletins;
	}

	/**
	 * Find one Bulletin by ID that the authenticated client is allowed to see.
	 *
	 * @param $bulletinId
	 * @param Client $client
	 *
	 * @return Bulletin|null
	 */
	public function findOneWithRestriction($bulletinId, Client $client)
	{
		$qb = $this->getEntityManager()->createQueryBuilder();
		$qb
			->select('bulletin')
			->from(Bulletin::class, 'bulletin')
			->addCriteria($this->createBulletinIdCriteria($bulletinId))
			->addCriteria($this->createAllowedOfficeCriteria($client))
			->addCriteria($this->createUserRoleCriteria($client));

		$bulletin = $qb->getQuery()->getOneOrNullResult();

		return $bulletin;
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
		$expression = null;
		$expr = Criteria::expr();

		// if no dateTimeFrom and no dateTimeTo provided, default fetch bulletins of current month
		if (!$dateTimeFrom && !$dateTimeTo) {
			// Must use this instead of new DateTime() to be able to mock current time in phpunit tests
			$currentDate = DateTime::createFromFormat('U', time());
			$beginOfTheMonth = (new DateTime($currentDate->format(Constants::DateFormatString)))->modify('first day of this month');
			$firstDayNextMonth = (new DateTime($currentDate->format(Constants::DateFormatString)))->modify('first day of next month');

			$expression = $expr->andX(
				$expr->gte('startDate', $beginOfTheMonth),
				$expr->lt('startDate', $firstDayNextMonth)
			);
		}

		// If both dateTimeFrom and dateTimeTo query parameters are given, bulletins within that interval are returned.
		if ($dateTimeFrom && $dateTimeTo) {
			$newDateTimeTo = (new DateTime($dateTimeTo->format(Constants::DateFormatString)))->modify('+1 day');

			$expression = $expr->andX(
				$expr->gte('startDate', $dateTimeFrom->format(Constants::DateFormatString)),
				$expr->lt('startDate', $newDateTimeTo->format(Constants::DateFormatString))
			);
		}

		// if only dateTimeFrom and no dateTimeTo provided, return bulletins from the dateTimeFrom value to the end of that month
		if ($dateTimeFrom && !$dateTimeTo) {
			$firstDayNextMonth = (new DateTime($dateTimeFrom->format(Constants::DateFormatString)))->modify('first day of next month')->format(Constants::DateFormatString);

			$expression = $expr->andX(
				$expr->gte('startDate', $dateTimeFrom->format(Constants::DateFormatString)),
				$expr->lt('startDate', $firstDayNextMonth)
			);
		}

		// if only dateTimeTo and no dateTimeFrom provided, return bulletins from the start of the month of the dateTimeFrom to the dateTimeFrom value
		if (!$dateTimeFrom && $dateTimeTo) {
			$beginOfTheMonth = (new DateTime($dateTimeTo->format(Constants::DateFormatString)))->modify('first day of this month');
			$newDateTimeTo = (new DateTime($dateTimeTo->format(Constants::DateFormatString)))->modify('+1 day');

			$expression = $expr->andX(
				$expr->gte('startDate', $beginOfTheMonth),
				$expr->lt('startDate', $newDateTimeTo)
			);
		}

		return Criteria::create()->andWhere($expression);
	}

	/**
	 * Show bulletins depending on user roles:
	 *  - MANAGEMENT_DASHBOARD and CREATING_SCHEDULES and ALL_RIGHTS may see all
	 *  - Other users only priority 2 (normal users) and null.
	 *
	 * @param Client $client
	 *
	 * @return Criteria
	 */
	private function createUserRoleCriteria(Client $client)
	{
		$criteria = Criteria::create();
		$expr = Criteria::expr();

		if ($client->canViewBulletinsFromOtherEmployees()) {
			// do not filter results
			return $criteria;
		}

		// defined priorities are:
		//	- 0 for administrators and management with dashboard rights
		// 	- 2 for normal users
		$restrictedPriorities = [0];

		return $criteria->andWhere(
			$expr->orX(
				$expr->notIn('bulletin.priority', $restrictedPriorities),
				$expr->isNull('bulletin.priority')
			)
		);
	}

	/**
	 * @param Client $client
	 * @param int    $officeId
	 *
	 * @return Criteria
	 */
	public function createAllowedOfficeCriteria(Client $client, int $officeId = null)
	{
		$expr = Criteria::expr();
		$criteria = Criteria::create();
		/** @var OfficeRepository $officeRepository */
		$officeRepository = $this->getEntityManager()->getRepository(Office::class);
		$allowedOffices = $officeRepository->findByEmployee($client->getEmployee()->getId())->toArray();

		// Get bulletins of offices the authenticated client is a member of
		if ($officeId) {
			$expression = $expr->andX(
				$expr->in('bulletin.department', $allowedOffices),
				$expr->eq('bulletin.department', $officeId));
		} else {
			$expression = $expr->in('bulletin.department', $allowedOffices);
		}

		return $criteria->andWhere($expression);
	}

	/**
	 * @param int $bulletinId
	 *
	 * @return Criteria
	 */
	public function createBulletinIdCriteria(int $bulletinId)
	{
		$criteria = Criteria::create();
		$expr = Criteria::expr();

		return $criteria->andWhere($expr->eq('bulletin.id', $bulletinId));
	}
}
