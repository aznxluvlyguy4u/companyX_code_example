<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Legacy\LegacyClockMoment;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

// TODO Rename table name to default during DB switch
// TODO Rename all columns where necessary to default names during DB switch
/**
 * ClockMoment.
 *
 * @ORM\Table(name="iclock_attendence")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\ClockMomentRepository")
 * @ORM\EntityListeners({"AppBundle\EventListener\ClockMomentListener"})
 */
class ClockMoment extends LegacyClockMoment
{
	/**
	 * @var int
	 *
	 * @Groups({"clockMoments"})
	 * @Groups({"clockMomentLog"})
	 *
	 * @ORM\Column(name="id", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="AUTO")
	 */
	private $id;

	/**
	 * @var \DateTime
	 *
	 * @Groups({"clockMoments"})
	 * @Groups({"clockMomentLog"})
	 *
	 * @ORM\Column(name="time", type="datetime")
	 * @Assert\DateTime()
	 * @Assert\NotBlank()
	 */
	private $timeStamp;

	/**
	 * @var \DateTime
	 *
	 * @Groups({"clockMomentLog"})
	 *
	 * @ORM\Column(name="time_original", type="datetime", nullable=true)
	 * @Assert\DateTime()
	 */
	private $originalTimeStamp;

	// TODO Rename column during DB switch
	/**
	 * @var string
	 *
	 * @Groups({"clockMoments"})
	 * @Groups({"clockMomentLog"})
	 *
	 * @ORM\Column(name="admin_comment", type="string", nullable=true)
	 * @Assert\Type("string")
	 */
	private $remark;

	/**
	 * @var int
	 *
	 * @Groups({"clockMoments"})
	 * @Groups({"clockMomentLog"})
	 *
	 * @ORM\Column(name="status", type="integer")
	 * @Assert\NotBlank()
	 * @Assert\Type("integer")
	 * @Assert\Choice(choices={0, 1, 4}, strict=true)
	 */
	private $status;

	/**
	 * @var bool
	 *
	 * @Groups({"clockMoments"})
	 * @Groups({"clockMomentLog"})
	 *
	 * @ORM\Column(name="active", type="boolean")
	 * @Assert\Type("boolean")
	 */
	private $active = true;

	/**
	 * Many ClockMoments belong to One Client.
	 *
	 * @var Client
	 *
	 * @Groups({"clockMoments"})
	 * @Groups({"clockMomentLog"})
	 *
	 * @ORM\ManyToOne(targetEntity="Client", inversedBy="clockMoments")
	 * @ORM\JoinColumn(name="admin_id", referencedColumnName="id")
	 * @Assert\Valid
	 */
	private $modifiedBy;

	/**
	 * Many ClockMoments belong to One Employee.
	 *
	 * @var Employee
	 *
	 * @Groups({"clockMoments"})
	 * @Groups({"clockMomentLog"})
	 *
	 * @ORM\ManyToOne(targetEntity="Employee", inversedBy="clockMoments")
	 * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
	 * @Assert\NotBlank()
	 * @Assert\Valid
	 */
	private $employee;

	/**
	 * Many ClockMoments belong to One Register.
	 *
	 * @var Register
	 *
	 * @Groups({"clockMoments"})
	 * @Groups({"clockMomentLog"})
	 *
	 * @ORM\ManyToOne(targetEntity="Register", inversedBy="clockMoments")
	 * @ORM\JoinColumn(name="register_id", referencedColumnName="id", nullable=true)
	 * @Assert\Valid
	 */
	private $register;

	/**
	 * Many ClockMoments belong to One ClockInterval.
	 *
	 * @var ClockInterval
	 *
	 * @Groups({"clockMoments"})
	 * @Groups({"clockMomentLog"})
	 *
	 * @ORM\ManyToOne(targetEntity="ClockInterval", inversedBy="clockMoments")
	 * @ORM\JoinColumn(name="interval_id", referencedColumnName="id", nullable=true)
	 * @Assert\Valid
	 */
	private $clockInterval;

	/**
	 * Many ClockMoments have One Department.
	 *
	 * @var Department
	 *
	 * @Groups({"clockMoments"})
	 * @Groups({"clockMomentLog"})
	 *
	 * @ORM\ManyToOne(targetEntity="Department", inversedBy="clockMoments")
	 * @ORM\JoinColumn(name="object_id", referencedColumnName="id")
	 * @Assert\NotBlank()
	 * @Assert\Valid
	 */
	private $department;

	/**
	 * @var \DateTime
	 *
	 * @Groups({"clockMomentLog"})
	 *
	 * @Gedmo\Timestampable(on="create")
	 * @ORM\Column(name="created", type="datetime")
	 * @Assert\NotBlank()
	 * @Assert\DateTime()
	 */
	private $created;

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
	 * Set id.
	 *
	 * @param int $id
	 *
	 * @return ClockMoment
	 */
	public function setId($id)
	{
		$this->id = $id;

		return $this;
	}

	/**
	 * Set timeStamp.
	 *
	 * @param \DateTime $timeStamp
	 *
	 * @return ClockMoment
	 */
	public function setTimeStamp($timeStamp)
	{
		$this->timeStamp = $timeStamp;

		return $this;
	}

	/**
	 * Get timeStamp.
	 *
	 * @return \DateTime
	 */
	public function getTimeStamp()
	{
		return $this->timeStamp;
	}

	/**
	 * Set originalTimeStamp.
	 *
	 * @param \DateTime $originalTimeStamp
	 *
	 * @return ClockMoment
	 */
	public function setOriginalTimeStamp($originalTimeStamp)
	{
		$this->originalTimeStamp = $originalTimeStamp;

		return $this;
	}

	/**
	 * Get originalTimeStamp.
	 *
	 * @return \DateTime
	 */
	public function getOriginalTimeStamp()
	{
		return $this->originalTimeStamp;
	}

	/**
	 * Set remark.
	 *
	 * @param string $remark
	 *
	 * @return ClockMoment
	 */
	public function setRemark($remark)
	{
		$this->remark = $remark;

		return $this;
	}

	/**
	 * Get remark.
	 *
	 * @return string
	 */
	public function getRemark()
	{
		return $this->remark;
	}

	/**
	 * Set status.
	 *
	 * @param int $status
	 *
	 * @return ClockMoment
	 */
	public function setStatus($status)
	{
		$this->status = $status;

		return $this;
	}

	/**
	 * Get status.
	 *
	 * @return int
	 */
	public function getStatus()
	{
		return $this->status;
	}

	/**
	 * Set active.
	 *
	 * @param bool $active
	 *
	 * @return ClockMoment
	 */
	public function setActive($active)
	{
		$this->active = $active;

		return $this;
	}

	/**
	 * Get active.
	 *
	 * @return bool
	 */
	public function getActive()
	{
		return $this->active;
	}

	/**
	 * Set created.
	 *
	 * @param \DateTime $created
	 *
	 * @return ClockMoment
	 */
	public function setCreated($created)
	{
		$this->created = $created;

		return $this;
	}

	/**
	 * Get created.
	 *
	 * @return \DateTime
	 */
	public function getCreated()
	{
		return $this->created;
	}

	/**
	 * Set modifiedBy.
	 *
	 * @param Client $modifiedBy
	 *
	 * @return ClockMoment
	 */
	public function setModifiedBy(Client $modifiedBy = null)
	{
		$this->modifiedBy = $modifiedBy;

		return $this;
	}

	/**
	 * Get modifiedBy.
	 *
	 * @return Client
	 */
	public function getModifiedBy()
	{
		return $this->modifiedBy;
	}

	/**
	 * Set employee.
	 *
	 * @param Employee $employee
	 *
	 * @return ClockMoment
	 */
	public function setEmployee(Employee $employee = null)
	{
		$this->employee = $employee;

		return $this;
	}

	/**
	 * Get employee.
	 *
	 * @return Employee
	 */
	public function getEmployee()
	{
		return $this->employee;
	}

	/**
	 * Set register.
	 *
	 * @param Register $register
	 *
	 * @return ClockMoment
	 */
	public function setRegister(Register $register = null)
	{
		$this->register = $register;

		return $this;
	}

	/**
	 * Get register.
	 *
	 * @return Register
	 */
	public function getRegister()
	{
		return $this->register;
	}

	/**
	 * Set clockInterval.
	 *
	 * @param ClockInterval $clockInterval
	 *
	 * @return ClockMoment
	 */
	public function setClockInterval(ClockInterval $clockInterval = null)
	{
		$this->clockInterval = $clockInterval;

		return $this;
	}

	/**
	 * Get clockInterval.
	 *
	 * @return ClockInterval
	 */
	public function getClockInterval()
	{
		return $this->clockInterval;
	}

	/**
	 * Set department.
	 *
	 * @param Department $department
	 *
	 * @return ClockMoment
	 */
	public function setDepartment(Department $department = null)
	{
		$this->department = $department;

		return $this;
	}

	/**
	 * Get department.
	 *
	 * @return Department
	 */
	public function getDepartment()
	{
		return $this->department;
	}
}
