<?php

namespace AppBundle\Entity\Legacy;

use Doctrine\ORM\Mapping as ORM;

// TODO Remove this later
/**
 * Class LegacyClockMoment.
 */
class LegacyClockMoment
{
	// TODO many to one to a physical device, which is currently not used. Map it but don't use it
	/**
	 * @var int
	 * @ORM\Column(name="device_id", type="integer", nullable=true)
	 */
	protected $device;

	// TODO many to one to a Person entity, which is the person object who uses the device and is currently not used. Map it but don't use it
	/**
	 * @var int
	 * @ORM\Column(name="person_id", type="integer", nullable=true)
	 */
	protected $person;

	/**
	 * TODO Temporary virtual property to get related register because in DB 0 values exist and therefore invalid foreignkey constraint
	 * TODO Remove this and use the original $register instead after DB switch.
	 *
	 * @var int
	 * @ORM\Column(name="register_id", type="integer", nullable=true)
	 */
	protected $registerId;

	/**
	 * TODO Temporary virtual property to get related clockInterval because in DB 0 values exist and therefore invalid foreignkey constraint
	 * TODO Remove this and use the original $clockInterval instead after DB switch.
	 *
	 * @var int
	 * @ORM\Column(name="interval_id", type="integer", nullable=true)
	 */
	protected $clockIntervalId;

	/**
	 * TODO Temporary virtual property to get related department because in DB 0 values exist and therefore invalid foreignkey constraint
	 * TODO Remove this and use the original $department instead after DB switch.
	 *
	 * @var int
	 * @ORM\Column(name="object_id", type="integer", nullable=true)
	 */
	protected $departmentId;

	/**
	 * TODO Mapped not used.
	 *
	 * Set device.
	 *
	 * @param int $device
	 *
	 * @return LegacyClockMoment
	 */
	public function setDevice($device)
	{
		$this->device = $device;

		return $this;
	}

	/**
	 * TODO Mapped not used
	 * Get device.
	 *
	 * @return int
	 */
	public function getDevice()
	{
		return $this->device;
	}

	/**
	 * TODO Mapped not used
	 * Set person.
	 *
	 * @param int $person
	 *
	 * @return LegacyClockMoment
	 */
	public function setPerson($person)
	{
		$this->person = $person;

		return $this;
	}

	/**
	 * TODO Mapped not used
	 * Get person.
	 *
	 * @return int
	 */
	public function getPerson()
	{
		return $this->person;
	}

	/**
	 * TODO Temporary setter, remove this during DB switch
	 * Set registerId.
	 *
	 * @param int $registerId
	 *
	 * @return LegacyClockMoment
	 */
	public function setRegisterId($registerId)
	{
		$this->registerId = $registerId;

		return $this;
	}

	/**
	 * TODO Temporary getter, remove this during DB switch
	 * Get registerId.
	 *
	 * @return int
	 */
	public function getRegisterId()
	{
		return $this->registerId;
	}

	/**
	 * TODO Temporary setter, remove this during DB switch
	 * Set clockIntervalId.
	 *
	 * @param int $clockIntervalId
	 *
	 * @return LegacyClockMoment
	 */
	public function setClockIntervalId($clockIntervalId)
	{
		$this->clockIntervalId = $clockIntervalId;

		return $this;
	}

	/**
	 * TODO Temporary getter, remove this during DB switch
	 * Get clockIntervalId.
	 *
	 * @return int
	 */
	public function getClockIntervalId()
	{
		return $this->clockIntervalId;
	}

	/**
	 * TODO Temporary setter, remove this during DB switch
	 * Set departmentId.
	 *
	 * @param int $departmentId
	 *
	 * @return LegacyClockMoment
	 */
	public function setDepartmentId($departmentId)
	{
		$this->departmentId = $departmentId;

		return $this;
	}

	/**
	 * TODO Temporary getter, remove this during DB switch
	 * Get departmentId.
	 *
	 * @return int
	 */
	public function getDepartmentId()
	{
		return $this->departmentId;
	}
}
