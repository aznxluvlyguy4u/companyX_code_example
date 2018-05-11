<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Legacy\LegacyClockInterval;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

// TODO Rename table name to default during DB switch
// TODO Rename all columns where necessary to default names during DB switch
/**
 * ClockInterval.
 *
 * @ORM\Table(name="iclock_intervals")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\ClockIntervalRepository")
 */
class ClockInterval extends LegacyClockInterval
{
	/**
	 * @var int
	 *
	 * @Groups({"clockMoments"})
	 * @Groups({"clockIntervals"})
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
	 * @Groups({"clockIntervals"})
	 *
	 * @ORM\Column(name="start_date", type="datetime")
	 * @Assert\DateTime()
	 * @Assert\NotBlank()
	 */
	private $startDate;

	/**
	 * @var \DateTime
	 *
	 * @Groups({"clockMoments"})
	 * @Groups({"clockIntervals"})
	 *
	 * @ORM\Column(name="end_date", type="datetime")
	 * @Assert\DateTime()
	 * @Assert\NotBlank()
	 */
	private $endDate;

	/**
	 * Break duration in minutes.
	 *
	 * @var int
	 *
	 * @Groups({"clockMoments"})
	 * @Groups({"clockIntervals"})
	 *
	 * @ORM\Column(name="pauze", type="integer")
	 * @Assert\NotBlank()
	 * @Assert\Type("integer")
	 */
	private $breakDuration = false;

	/**
	 * @var bool
	 *
	 * @Groups({"clockMoments"})
	 * @Groups({"clockIntervals"})
	 *
	 * @ORM\Column(name="problem", type="boolean")
	 * @Assert\NotBlank()
	 * @Assert\Type("boolean")
	 */
	private $problem = false;

	/**
	 * @var \DateTime
	 *
	 * @Gedmo\Timestampable(on="create")
	 * @ORM\Column(name="created", type="datetime")
	 * @Assert\NotBlank()
	 * @Assert\DateTime()
	 */
	private $created;

	/**
	 * Many ClockIntervals belong to One Employee.
	 *
	 * @var Employee
	 *
	 * @ORM\ManyToOne(targetEntity="Employee", inversedBy="clockIntervals")
	 * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
	 * @Assert\NotBlank()
	 * @Assert\Valid
	 */
	private $employee;

	// TODO rename account_id during DB switch
	/**
	 * Many ClockIntervals have One Department.
	 *
	 * @var Department
	 *
	 * @ORM\ManyToOne(targetEntity="Department", inversedBy="clockIntervals")
	 * @ORM\JoinColumn(name="account_id", referencedColumnName="id")
	 * @Assert\NotBlank()
	 * @Assert\Valid
	 */
	private $department;

	/**
	 * One ClockInterval has Many ClockMoments.
	 *
	 * @ORM\OneToMany(targetEntity="ClockMoment", mappedBy="clockInterval")
	 */
	private $clockMoments;

	/**
	 * ClockInterval constructor.
	 */
	public function __construct()
	{
		$this->clockMoments = new ArrayCollection();
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
	 * Set id.
	 *
	 * @param int $id
	 *
	 * @return ClockInterval
	 */
	public function setId($id)
	{
		$this->id = $id;

		return $this;
	}

	/**
	 * Add clockMoment.
	 *
	 * @param ClockMoment $clockMoment
	 *
	 * @return ClockInterval
	 */
	public function addClockMoment(ClockMoment $clockMoment)
	{
		$this->clockMoments[] = $clockMoment;

		return $this;
	}

	/**
	 * Remove clockMoment.
	 *
	 * @param ClockMoment $clockMoment
	 */
	public function removeClockMoment(ClockMoment $clockMoment)
	{
		$this->clockMoments->removeElement($clockMoment);
	}

	/**
	 * Get clockMoments.
	 *
	 * @return ArrayCollection
	 */
	public function getClockMoments()
	{
		return $this->clockMoments;
	}

	/**
	 * Set startDate.
	 *
	 * @param \DateTime $startDate
	 *
	 * @return ClockInterval
	 */
	public function setStartDate($startDate)
	{
		$this->startDate = $startDate;

		return $this;
	}

	/**
	 * Get startDate.
	 *
	 * @return \DateTime
	 */
	public function getStartDate()
	{
		return $this->startDate;
	}

	/**
	 * Set endDate.
	 *
	 * @param \DateTime $endDate
	 *
	 * @return ClockInterval
	 */
	public function setEndDate($endDate)
	{
		$this->endDate = $endDate;

		return $this;
	}

	/**
	 * Get endDate.
	 *
	 * @return \DateTime
	 */
	public function getEndDate()
	{
		return $this->endDate;
	}

	/**
	 * Set breakDuration.
	 *
	 * @param int $breakDuration
	 *
	 * @return ClockInterval
	 */
	public function setBreakDuration($breakDuration)
	{
		$this->breakDuration = $breakDuration;

		return $this;
	}

	/**
	 * Get breakDuration.
	 *
	 * @return int
	 */
	public function getBreakDuration()
	{
		return $this->breakDuration;
	}

	/**
	 * Set problem.
	 *
	 * @param bool $problem
	 *
	 * @return ClockInterval
	 */
	public function setProblem($problem)
	{
		$this->problem = $problem;

		return $this;
	}

	/**
	 * Get problem.
	 *
	 * @return bool
	 */
	public function getProblem()
	{
		return $this->problem;
	}

	/**
	 * Set created.
	 *
	 * @param \DateTime $created
	 *
	 * @return ClockInterval
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
	 * Set employee.
	 *
	 * @param Employee $employee
	 *
	 * @return ClockInterval
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
	 * Set department.
	 *
	 * @param Department $department
	 *
	 * @return ClockInterval
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
