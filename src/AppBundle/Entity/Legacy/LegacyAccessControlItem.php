<?php

namespace AppBundle\Entity\Legacy;

use Doctrine\ORM\Mapping as ORM;

// TODO Remove this later
/**
 * Class LegacyAccessControlItem.
 */
class LegacyAccessControlItem
{
	// TODO Temporary virtual property to get related department because in DB 0 and * values exist and therefore invalid foreignkey constraint
	// TODO Remove this and use the original $department instead after DB switch.
	/**
	 * @var string
	 * @ORM\Column(name="aco_id", type="string", nullable=true)
	 */
	protected $departmentId;

	//TODO Temporary setter, remove this during DB switch

	/**
	 * Set departmentId.
	 *
	 * @param int $departmentId
	 *
	 * @return LegacyAccessControlItem
	 */
	public function setDepartmentId($departmentId)
	{
		$this->departmentId = $departmentId;

		return $this;
	}

	// TODO Temporary getter, remove this during DB switch

	/**
	 * Get departmentId.
	 *
	 * @return int
	 */
	public function getDepartmentId()
	{
		return $this->departmentId;
	}
}
