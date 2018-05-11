<?php

namespace AppBundle\Entity\Legacy;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

// TODO Remove this later
/**
 * Class LegacyShiftSwapRequest.
 */
class LegacyShiftSwapRequest
{
	/**
	 * TODO Temporary virtual property to get related planner(Employee) because in DB 0 values exist and therefore invalid foreignkey constraint
	 * TODO Remove this and use the original $planner instead after DB switch.
	 *
	 * @var int
	 * @ORM\Column(name="planner_id", type="integer", nullable=true)
	 */
	protected $plannerId;

	// TODO Useless property can be calculated from Assignment. Delete after DB switch
	/**
	 * @var string
	 *
	 * @Groups({"shiftSwapRequests"})
	 * @ORM\Column(name="dienst", type="string")
	 * @Assert\Type("string")
	 */
	protected $departmentName;

	//TODO Reevaluate what this is, delete during DB switch
	/**
	 * @var int
	 *
	 * @ORM\Column(name="aanvragen_id", type="integer")
	 */
	protected $requestsId;

	/**
	 * TODO Useless setter, remove this during DB switch
	 * Set departmentName.
	 *
	 * @param string $departmentName
	 *
	 * @return LegacyShiftSwapRequest
	 */
	public function setDepartmentName($departmentName)
	{
		$this->departmentName = $departmentName;

		return $this;
	}

	/**
	 * TODO Useless getter, remove this during DB switch
	 * Get departmentName.
	 *
	 * @return string
	 */
	public function getDepartmentName()
	{
		return $this->departmentName;
	}

	/**
	 * TODO Temporary setter, remove this during DB switch
	 * Set requestsId.
	 *
	 * @param int $requestsId
	 *
	 * @return LegacyShiftSwapRequest
	 */
	public function setRequestsId($requestsId)
	{
		$this->requestsId = $requestsId;

		return $this;
	}

	/**
	 * TODO Temporary getter, remove this during DB switch
	 * Get requestsId.
	 *
	 * @return int
	 */
	public function getRequestsId()
	{
		return $this->requestsId;
	}

	/**
	 * TODO Temporary setter, remove this during DB switch
	 * Set plannerId.
	 *
	 * @param int $plannerId
	 *
	 * @return LegacyShiftSwapRequest
	 */
	public function setPlannerId($plannerId)
	{
		$this->plannerId = $plannerId;

		return $this;
	}

	/**
	 * TODO Temporary getter, remove this during DB switch
	 * Get plannerId.
	 *
	 * @return int
	 */
	public function getPlannerId()
	{
		return $this->plannerId;
	}
}
