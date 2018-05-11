<?php

namespace AppBundle\Doctrine;

use AppBundle\Entity\Assignment;
use AppBundle\Entity\Department;
use AppBundle\Entity\Register;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

class DepartmentFilter extends SQLFilter
{
	/** @var string */
	private $allowedDepartments;

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
		// If target Entity is Department, show only department IDS thats are in the allowed department set
		if (Department::class == $targetEntity->getReflectionClass()->name) {
			return sprintf('%s.id IN(%s)',
				$targetTableAlias,
				$this->allowedDepartments
			);
		}

		if (Assignment::class == $targetEntity->getReflectionClass()->name) {
			$departmentJoinColumnName = $targetEntity->getSingleAssociationJoinColumnName('department');

			return sprintf('%s.%s IN(%s)',
				$targetTableAlias,
				$departmentJoinColumnName,
				$this->allowedDepartments
			);
		}

		// If target Entity is Register, show only Registers with department IDS thats are in the allowed department set OR 0 OR = null
		if (Register::class == $targetEntity->getReflectionClass()->name) {
			$departmentJoinColumnName = $targetEntity->getSingleAssociationJoinColumnName('department');

			return sprintf('%s.%s IN(%s, 0) OR %s.%s IS NULL',
				$targetTableAlias,
				$departmentJoinColumnName,
				$this->allowedDepartments,
				$targetTableAlias,
				$departmentJoinColumnName
			);
		}

		return '';
	}

	/**
	 * @param array $allowedDepartments
	 */
	public function setAllowedDepartments(array $allowedDepartments)
	{
		foreach ($allowedDepartments as $allowedDepartment) {
			if (!is_int($allowedDepartment)) {
				throw new \InvalidArgumentException('Input must be an array of integer values');
			}
		}

		$this->allowedDepartments = implode(',', $allowedDepartments);
	}
}
