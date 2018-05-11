<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

//TODO Rename table name = dor_assignments_logsh to "system_config_log" matching the entity name during DB switch
//TODO Rename column names to default names during DB switch
/**
 * AssignmentLog.
 *
 * @ORM\Table(name="dor_assignments_logsh")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\AssignmentLogRepository")
 */
class AssignmentLog extends BaseLog
{
	/**
	 * @var \DateTime
	 *
	 * TODO Rename "udf_date" to "newStartDate" during DB switch
	 * @ORM\Column(name="udf_date", type="datetime")
	 */
	private $newStartDate;

	/**
	 * @var int
	 *
	 * TODO Rename "udf_user_id" to "old_employee_id" during DB switch
	 * @ORM\Column(name="udf_user_id", type="integer")
	 */
	private $oldEmployeeId;

	/**
	 * Set newStartDate.
	 *
	 * @param \DateTime $newStartDate
	 *
	 * @return AssignmentLog
	 */
	public function setNewStartDate($newStartDate)
	{
		$this->newStartDate = $newStartDate;

		return $this;
	}

	/**
	 * Get newStartDate.
	 *
	 * @return \DateTime
	 */
	public function getNewStartDate()
	{
		return $this->newStartDate;
	}

	/**
	 * Set oldEmployeeId.
	 *
	 * @param int $oldEmployeeId
	 *
	 * @return AssignmentLog
	 */
	public function setOldEmployeeId($oldEmployeeId)
	{
		$this->oldEmployeeId = $oldEmployeeId;

		return $this;
	}

	/**
	 * Get oldEmployeeId.
	 *
	 * @return int
	 */
	public function getOldEmployeeId()
	{
		return $this->oldEmployeeId;
	}
}
