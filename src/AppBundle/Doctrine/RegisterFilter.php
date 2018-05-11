<?php

namespace AppBundle\Doctrine;

use AppBundle\Entity\Client;
use AppBundle\Entity\Register;
use AppBundle\Enumerator\CompanyXUserRole;
use AppBundle\Enumerator\RegisterType;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

class RegisterFilter extends SQLFilter
{
	/** @var Client */
	private $client;

	/**
	 * Gets the SQL query part to add to a query.
	 *
	 * @param ClassMetaData $targetEntity
	 * @param string        $targetTableAlias
	 *
	 * @return string the constraint SQL if there is available, empty string otherwise
	 */
	public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias)
	{
		if (Register::class == $targetEntity->getReflectionClass()->name) {
			// If client has role TELEPHONELIST_ACCESS
			// Also show Registers of the types UNAVAILABLE, AVAILABLE, VACATION of other employees and hide the rest
			$otherPeoplesRegister = null;
			if ($this->client->hasRole(CompanyXUserRole::TELEPHONELIST_ACCESS)) {
				$typeColumnName = $targetEntity->getColumnName('type');
				$otherPeoplesRegister = sprintf('OR %s.%s IN (\'%s\', \'%s\', \'%s\')',
					$targetTableAlias,
					$typeColumnName,
					RegisterType::UNAVAILABLE,
					RegisterType::AVAILABLE,
					RegisterType::VACATION
				);
			}

			$employeeColumnName = $targetEntity->getSingleAssociationJoinColumnName('employee');
			$typeColumnName = $targetEntity->getColumnName('type');

			// Hide Registers that don't belong to the authenticated Client
			// AND hide Registers with type = SICK
			return sprintf('%s.%s = %d AND %s.%s <> \'%s\''.$otherPeoplesRegister,
				$targetTableAlias,
				$employeeColumnName,
				$this->client->getEmployee()->getId(),
				$targetTableAlias,
				$typeColumnName,
				RegisterType::SICK
			);
		}

		return '';
	}

	/**
	 * @param Client $client
	 */
	public function setClient(Client $client)
	{
		$this->client = $client;
	}
}
