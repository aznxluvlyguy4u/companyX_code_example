<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

//TODO Rename column names to default names during DB switch
/**
 * Abstract Class BaseLog for other entityLog classes to extend from.
 */
class BaseLog implements BaseLogInterface
{
	/**
	 * @var int
	 *
	 * @ORM\Column(name="id", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="IDENTITY")
	 */
	protected $id;

	/**
	 * @var int
	 *
	 * @ORM\Column(name="pk", type="integer")
	 */
	protected $primaryKey = 0;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="attr", type="string")
	 */
	protected $changedField;

	/**
	 * @var \DateTime
	 * @ORM\Column(name="date", type="date")
	 */
	protected $date;

	/**
	 * @var \DateTime
	 * @ORM\Column(name="time", type="time")
	 */
	protected $time;

	/**
	 * @ORM\Column(name="value_new", type="string")
	 */
	protected $newValue;

	/**
	 * @ORM\Column(name="value_old", type="string")
	 */
	protected $oldValue;

	/**
	 * @var LargeDataLog
	 *
	 * @ORM\ManyToOne(targetEntity="LargeDataLog")
	 * @ORM\JoinColumn(name="ldata_id", referencedColumnName="id")
	 */
	protected $largeDataLog;

	/**
	 * TODO Currently session id comes from log in supplied by CompanyX, reevaluate if this is still needed after DB switch.
	 *
	 * @var int
	 *
	 * @ORM\Column(name="auth_id", type="integer", nullable=true)
	 */
	protected $sessionId = 0;

	/**
	 * Set id.
	 *
	 * @param int $id
	 *
	 * @return BaseLog
	 */
	public function setId($id)
	{
		$this->id = $id;

		return $this;
	}

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
	 * Set changedField.
	 *
	 * @param string $changedField
	 *
	 * @return BaseLog
	 */
	public function setChangedField($changedField)
	{
		$this->changedField = $changedField;

		return $this;
	}

	/**
	 * Get changedField.
	 *
	 * @return string
	 */
	public function getChangedField()
	{
		return $this->changedField;
	}

	/**
	 * Set date.
	 *
	 * @param \DateTime $date
	 *
	 * @return BaseLog
	 */
	public function setDate($date)
	{
		$this->date = $date;

		return $this;
	}

	/**
	 * Get date.
	 *
	 * @return \DateTime
	 */
	public function getDate()
	{
		return $this->date;
	}

	/**
	 * Set time.
	 *
	 * @param \DateTime $time
	 *
	 * @return BaseLog
	 */
	public function setTime($time)
	{
		$this->time = $time;

		return $this;
	}

	/**
	 * Get time.
	 *
	 * @return \DateTime
	 */
	public function getTime()
	{
		return $this->time;
	}

	/**
	 * Set newValue.
	 *
	 * @param string $newValue
	 *
	 * @return BaseLog
	 */
	public function setNewValue($newValue)
	{
		$this->newValue = $newValue;

		return $this;
	}

	/**
	 * Get newValue.
	 *
	 * @return string
	 */
	public function getNewValue()
	{
		return $this->newValue;
	}

	/**
	 * Set oldValue.
	 *
	 * @param string $oldValue
	 *
	 * @return BaseLog
	 */
	public function setOldValue($oldValue)
	{
		$this->oldValue = $oldValue;

		return $this;
	}

	/**
	 * Get oldValue.
	 *
	 * @return string
	 */
	public function getOldValue()
	{
		return $this->oldValue;
	}

	/**
	 * Set sessionId.
	 *
	 * @param int $sessionId
	 *
	 * @return BaseLog
	 */
	public function setSessionId($sessionId)
	{
		$this->sessionId = $sessionId;

		return $this;
	}

	/**
	 * Get sessionId.
	 *
	 * @return int
	 */
	public function getSessionId()
	{
		return $this->sessionId;
	}

	/**
	 * Set largeDataLog.
	 *
	 * @param LargeDataLog $largeDataLog
	 *
	 * @return BaseLog
	 */
	public function setLargeDataLog(LargeDataLog $largeDataLog = null)
	{
		$this->largeDataLog = $largeDataLog;

		return $this;
	}

	/**
	 * Get largeDataLog.
	 *
	 * @return LargeDataLog
	 */
	public function getLargeDataLog()
	{
		return $this->largeDataLog;
	}

	/**
	 * Set primaryKey.
	 *
	 * @param int $primaryKey
	 *
	 * @return BaseLog
	 */
	public function setPrimaryKey($primaryKey)
	{
		$this->primaryKey = $primaryKey;

		return $this;
	}

	/**
	 * Get primaryKey.
	 *
	 * @return int
	 */
	public function getPrimaryKey()
	{
		return $this->primaryKey;
	}
}
