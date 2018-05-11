<?php

namespace AppBundle\Entity\Legacy;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

// TODO Remove this later
/**
 * Class LegacyClockInterval.
 */
class LegacyClockInterval
{
	// TODO this is not necessary as it can deduced from startDate, remove during DB switch
	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(name="date", type="date")
	 * @Assert\DateTime()
	 * @Assert\NotBlank()
	 */
	protected $date;

	// TODO Temporary setter, remove this during DB switch

	/**
	 * Set date.
	 *
	 * @param \DateTime $date
	 *
	 * @return LegacyClockInterval
	 */
	public function setDate($date)
	{
		$this->date = $date;

		return $this;
	}

	//TODO Temporary setter, remove this during DB switch

	/**
	 * Get date.
	 *
	 * @return \DateTime
	 */
	public function getDate()
	{
		return $this->date;
	}
}
