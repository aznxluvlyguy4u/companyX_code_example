<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

//TODO Rename table name = logsch_large_data to "large_data_log" matching the entity name during DB switch
/**
 * LargeDataLog.
 *
 * @ORM\Table(name="logsh_large_data")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\LargeDataLogRepository")
 */
class LargeDataLog
{
	/**
	 * @var int
	 *
	 * @ORM\Column(name="id", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="AUTO")
	 */
	private $id;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="data", type="string")
	 */
	private $data;

	/**
	 * Get id.
	 *
	 * @return int
	 */
	public function getId()
	{
		return $this->id;
	}

	/**
	 * Set data.
	 *
	 * @param string $data
	 *
	 * @return LargeDataLog
	 */
	public function setData($data)
	{
		$this->data = $data;

		return $this;
	}

	/**
	 * Get data.
	 *
	 * @return string
	 */
	public function getData()
	{
		return $this->data;
	}
}
