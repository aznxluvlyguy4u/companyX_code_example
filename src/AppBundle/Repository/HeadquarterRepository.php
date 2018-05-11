<?php

namespace AppBundle\Repository;

use AppBundle\Entity\Client;
use AppBundle\Entity\Department;
use AppBundle\Entity\Headquarter;
use AppBundle\Entity\Office;
use AppBundle\Entity\SystemConfig;
use AppBundle\Enumerator\CompanyXUserRole;
use AppBundle\Enumerator\SystemConfigKey;
use Doctrine\ORM\EntityRepository;

/**
 * HeadquarterRepository.
 */
class HeadquarterRepository extends EntityRepository
{
	/**
	 * Find the only headquarter if DOR_HEADQUARTER_ID is set, and if the client is a member of headquarter.
	 *
	 * @param Client $client
	 *
	 * @return Headquarter
	 */
	public function findHeadquarterWithRestrictionCheck(Client $client)
	{
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = $this->getEntityManager()->getRepository(SystemConfig::class);

		// Check if DOR_HEADQUARTER_ID is set
		/** @var SystemConfig $dorHeadquarterId */
		$dorHeadquarterId = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_HEADQUARTER_ID);
		$headquarterOfficeId = $dorHeadquarterId ? $dorHeadquarterId->getNormalizedValue() : null;

		// Check DOR_PHONELIST_RESTRICT setting
		/** @var SystemConfig $dorPhonelistRestrict */
		$dorPhonelistRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
		$restriction = $dorPhonelistRestrict ? $dorPhonelistRestrict->getNormalizedValue() : false;

		if (!$headquarterOfficeId) {
			return null;
		}

		//TODO as headquarter is currently a physical Department record, fetch it from DepartmentRepo. Fix during DB switch
		/** @var DepartmentRepository $departmentRepo */
		$departmentRepo = $this->getEntityManager()->getRepository(Department::class);
		$qb = $departmentRepo->getRootNodesQueryBuilder();
		$qb
			->leftJoin('node.employees', 'employee')
			->andWhere($qb->expr()->eq('node.id', $headquarterOfficeId))
			->andWhere('employee.id = :employeeId')
			->orderBy('node.id', 'ASC')
			->setParameters(array(
				'employeeId' => $client->getEmployee()->getId(),
			));

		/** @var Department $existingOffice */
		$existingOffice = $qb->getQuery()->getOneOrNullResult();

		if (!$existingOffice) {
			return null;
		}

		/** @var OfficeRepository $officeRepo */
		$officeRepo = $this->getEntityManager()->getRepository(Office::class);

		if ($restriction && !$client->hasRole(CompanyXUserRole::ALL_RIGHTS)) {
			$authorizedOffices = $officeRepo->findByEmployee($client->getEmployee()->getId());
		} else {
			$authorizedOffices = $officeRepo->findAll();
		}

		// Cast it to Headquarter object
		$headquarter = new Headquarter();
		$headquarter->setName($existingOffice->getName());
		$headquarter->setDepartmentRoot($existingOffice);
		$headquarter->setId($existingOffice->getId());
		foreach ($authorizedOffices as $authorizedOffice) {
			$headquarter->addOffice($authorizedOffice);
		}

		return $headquarter;
	}
}
