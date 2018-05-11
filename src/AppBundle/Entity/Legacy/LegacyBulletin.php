<?php

namespace AppBundle\Entity\Legacy;

use Doctrine\ORM\Mapping as ORM;

// TODO Remove this later
/**
 * Class LegacyBulletin.
 */
class LegacyBulletin
{
	// TODO This property is according to Maarten no longer needed as it has never been used. Remove this when confirmed
	/**
	 * @var int
	 *
	 * @ORM\Column(name="priority", type="integer", nullable=true)
	 */
	protected $priority;

	// TODO Temporary setter, remove this during DB switch

	/**
	 * Set priority.
	 *
	 * @param int $priority
	 *
	 * @return LegacyBulletin
	 */
	public function setPriority($priority)
	{
		$this->priority = $priority;

		return $this;
	}

	// TODO Temporary getter, remove this during DB switch

	/**
	 * Get priority.
	 *
	 * @return int
	 */
	public function getPriority()
	{
		return $this->priority;
	}
}
