<?php

namespace AppBundle\Entity\Legacy;

use Doctrine\ORM\Mapping as ORM;

// TODO Remove this later
/**
 * Class LegacyAssignment.
 */
class LegacyAssignment
{
	// TODO Temporary virtual property to get related employee because in DB 0 values exist and therefore invalid foreignkey constraint
	// TODO Remove this and use the original $employee instead after DB switch
	/**
	 * @var int
	 * @ORM\Column(name="user_id", type="integer", nullable=true)
	 */
	protected $employeeId;

	/**
	 * TODO Temporary setter, remove this during DB switch
	 * Set employeeId.
	 *
	 * @param int $employeeId
	 *
	 * @return LegacyAssignment
	 */
	public function setEmployeeId($employeeId)
	{
		$this->employeeId = $employeeId;

		return $this;
	}

	/**
	 * TODO Temporary getter, remove this during DB switch
	 * Get employeeId.
	 *
	 * @return int
	 */
	public function getEmployeeId()
	{
		return $this->employeeId;
	}
}
