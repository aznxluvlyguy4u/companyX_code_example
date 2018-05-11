<?php

namespace AppBundle\Doctrine;

use AppBundle\Entity\Assignment;
use AppBundle\Entity\Client;
use AppBundle\Entity\Department;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

class AssignmentFilter extends SQLFilter
{
	/** @var Client */
	private $client;

	/** @var string */
	private $allowedDepartments;

	/** @var string */
	private $extraAllowedAssignments;

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
		if (Assignment::class == $targetEntity->getReflectionClass()->name) {
			$employeeColumnName = $targetEntity->getSingleAssociationJoinColumnName('employee');
			$departmentColumnName = $targetEntity->getSingleAssociationJoinColumnName('department');
			// Show only assignments that belong to the employee ID associated with the authenticated client
			// AND unassigned assignments (assignments with employee id = 0 and assigned to the same department the client is an employee of)
			// AND assignments that are associated with the client in ShiftSwapRequests
			return sprintf('%s.%s = %d OR (%s.%s = 0 AND %s.%s IN(%s)) OR (%s.id IN (%s))',
				$targetTableAlias,
				$employeeColumnName,
				$this->client->getEmployee()->getId(),
				$targetTableAlias,
				$employeeColumnName,
				$targetTableAlias,
				$departmentColumnName,
				$this->allowedDepartments,
				$targetTableAlias,
				$this->extraAllowedAssignments
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

		// Check if the client is an employee of the given departmentId or officeId
		$allowedDepartments = array_map(
			function ($department) {/* @var Department $department */ return $department->getId(); },
			$client->getDepartments()->toArray()
		);

		$this->allowedDepartments = implode(',', $allowedDepartments);
	}

	/**
	 * @param array $extraAllowedAssignments
	 */
	public function setExtraAllowedAssignments(array $extraAllowedAssignments)
	{
		foreach ($extraAllowedAssignments as $extraAllowedAssignment) {
			if (!is_int($extraAllowedAssignment)) {
				throw new \InvalidArgumentException('Input must be an array of integer values');
			}
		}

		$this->extraAllowedAssignments = implode(',', $extraAllowedAssignments);
	}
}
