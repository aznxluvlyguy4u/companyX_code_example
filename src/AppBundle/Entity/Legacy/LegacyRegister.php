<?php

namespace AppBundle\Entity\Legacy;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

// TODO Remove this later
/**
 * Class LegacyRegister.
 */
class LegacyRegister
{
	/**
	 * TODO Remove this property during DB switch, no longer supported, but needs to be mapped
	 * TODO for backward compatibility insert.
	 *
	 * @var string
	 *
	 * @ORM\Column(name="old_value", type="string", nullable=true)
	 */
	protected $oldValue;

	/**
	 * TODO Temporary virtual property to get related modifiedBy(client) because in DB 0 values exist and therefore invalid foreignkey constraint
	 * TODO Remove this and use the original $modifiedBy instead after DB switch.
	 *
	 * @var int
	 * @ORM\Column(name="admin_id", type="integer", nullable=true)
	 */
	protected $modifiedById;

	/**
	 * TODO Temporary property mapped to the same column as $assignment to get related assignment ids because in DB 0 values exist and therefore invalid foreignkey constraint
	 * TODO Remove this and use the original $assignment instead after DB switch.
	 *
	 * @var int
	 *
	 * @ORM\Column(name="assignment_id", type="integer", nullable=true)
	 */
	protected $assignmentId;

	/**
	 * TODO Temporary property mapped to the same column as $department to get related department ids because in DB 0 values exist and therefore invalid foreignkey constraint
	 * TODO Remove this and use the original $department instead after DB switch.
	 *
	 * @var int
	 *
	 * @ORM\Column(name="object_id", type="integer", nullable=true)
	 */
	protected $departmentId;

	/**
	 * TODO Remove this property during DB switch, it can be calculated by department id alone.
	 *
	 * @var string
	 * @ORM\Column(name="activity", type="string", nullable=true)
	 * @Assert\Type("string")
	 */
	protected $activity;

	/**
	 * TODO Remove this property during DB switch, it can be calculated by department id alone.
	 *
	 * @var string
	 * @ORM\Column(name="location", type="string", nullable=true)
	 * @Assert\Type("string")
	 */
	protected $location;

	/**
	 * TODO Remove this property during DB switch, no longer needed.
	 *
	 * @var int
	 * @ORM\Column(name="period_id", type="integer", nullable=true)
	 * @Assert\Type("integer")
	 */
	protected $period = 0;

	/**
	 * TODO Reevaluate if this property is necessary during DB switch, temporary keep for backward compatibility.
	 *
	 * @var string
	 *
	 * @ORM\Column(name="dirty", type="string", nullable=true)
	 * @Assert\Type("string")
	 */
	protected $dirty;

	// TODO Remove this property during DB switch

	/**
	 * Set oldValue.
	 *
	 * @param string $oldValue
	 *
	 * @return LegacyRegister
	 */
	public function setOldValue($oldValue)
	{
		$this->oldValue = $oldValue;

		return $this;
	}

	// TODO Remove this property during DB switch

	/**
	 * Get oldValue.
	 *
	 * @return string
	 */
	public function getOldValue()
	{
		return $this->oldValue;
	}

	// TODO Remove this property during DB switch

	/**
	 * Set activity.
	 *
	 * @param string $activity
	 *
	 * @return LegacyRegister
	 */
	public function setActivity($activity)
	{
		$this->activity = $activity;

		return $this;
	}

	// TODO Remove this property during DB switch

	/**
	 * Get activity.
	 *
	 * @return string
	 */
	public function getActivity()
	{
		return $this->activity;
	}

	// TODO Remove this property during DB switch

	/**
	 * Set location.
	 *
	 * @param string $location
	 *
	 * @return LegacyRegister
	 */
	public function setLocation($location)
	{
		$this->location = $location;

		return $this;
	}

	// TODO Remove this property during DB switch

	/**
	 * Get location.
	 *
	 * @return string
	 */
	public function getLocation()
	{
		return $this->location;
	}

	// TODO Remove this property during DB switch

	/**
	 * Set period.
	 *
	 * @param int $period
	 *
	 * @return LegacyRegister
	 */
	public function setPeriod($period)
	{
		$this->period = $period;

		return $this;
	}

	// TODO Remove this property during DB switch

	/**
	 * Get period.
	 *
	 * @return int
	 */
	public function getPeriod()
	{
		return $this->period;
	}

	// TODO Remove this property during DB switch

	/**
	 * Set dirty.
	 *
	 * @param string $dirty
	 *
	 * @return LegacyRegister
	 */
	public function setDirty($dirty)
	{
		$this->dirty = $dirty;

		return $this;
	}

	// TODO Remove this property during DB switch

	/**
	 * Get dirty.
	 *
	 * @return string
	 */
	public function getDirty()
	{
		return $this->dirty;
	}

	/**
	 * TODO Temporary setter, remove this during DB switch
	 * Set assignmentId.
	 *
	 * @param int $assignmentId
	 *
	 * @return LegacyRegister
	 */
	public function setAssignmentId($assignmentId)
	{
		$this->assignmentId = $assignmentId;

		return $this;
	}

	/**
	 * TODO Temporary getter, remove this during DB switch
	 * Get assignmentId.
	 *
	 * @return int
	 */
	public function getAssignmentId()
	{
		return $this->assignmentId;
	}

	/**
	 * TODO Temporary setter, remove this during DB switch
	 * Set modifiedById.
	 *
	 * @param int $modifiedById
	 *
	 * @return LegacyRegister
	 */
	public function setModifiedById($modifiedById)
	{
		$this->modifiedById = $modifiedById;

		return $this;
	}

	/**
	 * TODO Temporary getter, remove this during DB switch
	 * Get modifiedById.
	 *
	 * @return int
	 */
	public function getModifiedById()
	{
		return $this->modifiedById;
	}

	// TODO Temporary setter, remove this during DB switch

	/**
	 * Set departmentId.
	 *
	 * @param int $departmentId
	 *
	 * @return LegacyRegister
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
