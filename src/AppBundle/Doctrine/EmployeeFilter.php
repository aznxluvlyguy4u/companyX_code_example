<?php

namespace AppBundle\Doctrine;

use AppBundle\Entity\Assignment;
use AppBundle\Entity\Employee;
use AppBundle\Entity\Register;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

class EmployeeFilter extends SQLFilter
{
	/** @var string */
	private $directColleagueIds;

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
		// Hide Employees that the current authenticated Client is not allowed to see
		if (Employee::class == $targetEntity->getReflectionClass()->name) {
			return sprintf('%s.id IN(%s)',
				$targetTableAlias,
				$this->directColleagueIds
			);
		}

		// Hide Assignments that the current authenticated Client is not allowed to see
		if (Assignment::class == $targetEntity->getReflectionClass()->name) {
			$employeeColumnName = $targetEntity->getSingleAssociationJoinColumnName('employee');

			// TODO 0 values exist as foreignKey to Employees in dor_assignments for Open Assignments.
			// TODO this may be fixed in the future, in that case, keep in mind that the 0 in this SQL
			// TODO needs to be changed as well.
			return sprintf('%s.%s IN(%s, 0)',
				$targetTableAlias,
				$employeeColumnName,
				$this->directColleagueIds
			);
		}

		// Hide Registers that the current authenticated Client is not allowed to see
		if (Register::class == $targetEntity->getReflectionClass()->name) {
			$employeeColumnName = $targetEntity->getSingleAssociationJoinColumnName('employee');

			return sprintf('%s.%s IN(%s)',
				$targetTableAlias,
				$employeeColumnName,
				$this->directColleagueIds
			);
		}

		return '';
	}

	/**
	 * @param $directColleagueIds
	 */
	public function setDirectColleagueIds(array $directColleagueIds)
	{
		foreach ($directColleagueIds as $directColleagueId) {
			if (!is_int($directColleagueId)) {
				throw new \InvalidArgumentException('Input must be an array of integer values');
			}
		}

		$this->directColleagueIds = implode(',', $directColleagueIds);
	}
}
