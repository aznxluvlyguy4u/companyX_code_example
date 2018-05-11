<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Legacy\LegacyAssignment;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints\DateTime;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class Assignment.
 *
 * TODO Rename table name = dor_assignment to "assignment" matching the entity name during DB switch
 * TODO Rename column names to default names during DB switch
 *
 * @ORM\Table(name="dor_assignments")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\AssignmentRepository")
 * @ORM\EntityListeners({"AppBundle\EventListener\AssignmentListener"})
 */
class Assignment extends LegacyAssignment
{
	/**
	 * @var int
	 *
	 * @Groups({"employees"})
	 * @Groups({"registers"})
	 * @Groups({"assignments"})
	 * @Groups({"shiftSwapRequests"})
	 * @Groups({"registerLog"})
	 * @Groups({"assignmentLog"})
	 *
	 * @ORM\Column(name="id", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="AUTO")
	 */
	private $id;

	/**
	 * @var \DateTime
	 *
	 * @Groups({"employees"})
	 * @Groups({"registers"})
	 * @Groups({"assignments"})
	 * @Groups({"shiftSwapRequests"})
	 * @Groups({"assignmentLog"})
	 *
	 * @ORM\Column(name="start_date", type="datetime")
	 */
	private $startDate;

	/**
	 * @var \DateTime
	 *
	 * @Groups({"employees"})
	 * @Groups({"registers"})
	 * @Groups({"assignments"})
	 * @Groups({"shiftSwapRequests"})
	 * @Groups({"assignmentLog"})
	 *
	 * @ORM\Column(name="end_date", type="datetime")
	 */
	private $endDate;

	/**
	 * @var string
	 *
	 * @Groups({"employees"})
	 * @Groups({"registers"})
	 * @Groups({"assignments"})
	 * @Groups({"shiftSwapRequests"})
	 * @Groups({"assignmentLog"})
	 *
	 * @ORM\Column(name="c_start", type="string", nullable=true)
	 */
	private $clientDefinedStartDate;

	/**
	 * @var string
	 *
	 * @Groups({"employees"})
	 * @Groups({"registers"})
	 * @Groups({"assignments"})
	 * @Groups({"shiftSwapRequests"})
	 * @Groups({"assignmentLog"})
	 *
	 * @ORM\Column(name="c_end", type="string", nullable=true)
	 */
	private $clientDefinedEndDate;

	/**
	 * @var string
	 *
	 * @Groups({"assignmentLog"})
	 *
	 * TODO consult for proper property naming
	 * @ORM\Column(name="publish", type="boolean")
	 */
	private $published;

	/**
	 * @var string
	 *
	 * @Groups({"employees"})
	 * @Groups({"assignments"})
	 * @Groups({"shiftSwapRequests"})
	 * @Groups({"assignmentLog"})
	 *
	 * @ORM\Column(name="opmerking", type="string", nullable=true)
	 */
	private $remark;

	/**
	 * Virtual property to show values stored in Original break duration
	 * depending on Client's role and/or system configuration.
	 *
	 * @Groups({"employees"})
	 * @Groups({"assignments"})
	 * @Groups({"assignmentLog"})
	 */
	private $breakDuration;

	/**
	 * Real breakDuration stored in the DB.
	 *
	 * @var int
	 *
	 * @ORM\Column(name="pauze", type="integer", nullable=true)
	 */
	private $originalBreakDuration;

	/**
	 * Many Assignments have One Employee.
	 *
	 * @var Employee
	 *
	 * @Groups({"assignments"})
	 * @Groups({"assignmentLog"})
	 *
	 * @ORM\ManyToOne(targetEntity="Employee", inversedBy="assignments")
	 * TODO Rename "user_id" to "employee_id" during DB switch
	 * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
	 */
	private $employee;

	/**
	 * Many Assignments have One Department.
	 *
	 * @Groups({"employees"})
	 * @Groups({"registers"})
	 * @Groups({"assignments"})
	 * @Groups({"shiftSwapRequests"})
	 * @Groups({"assignmentLog"})
	 *
	 * @ORM\ManyToOne(targetEntity="Department", inversedBy="assignments")
	 * TODO Rename "object_id" to "department_id" during DB switch
	 * @ORM\JoinColumn(name="object_id", referencedColumnName="id")
	 */
	private $department;

	/**
	 * One Assignment has Many Registers.
	 *
	 * @ORM\OneToMany(targetEntity="Register", mappedBy="assignment")
	 */
	private $registers;

	/**
	 * One Assignment has Many ShiftSwapRequests.
	 *
	 * @Groups({"assignments"})
	 * @ORM\OneToMany(targetEntity="ShiftSwapRequest", mappedBy="assignment")
	 */
	private $shiftSwapRequests;

	/**
	 * Assignment constructor.
	 */
	public function __construct()
	{
		$this->registers = new ArrayCollection();
		$this->shiftSwapRequests = new ArrayCollection();
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
	 * @param $id
	 *
	 * @return $this
	 */
	public function setId($id)
	{
		$this->id = $id;

		return $this;
	}

	/**
	 * Set employee.
	 *
	 * @param Employee $employee
	 *
	 * @return Assignment
	 */
	public function setEmployee(Employee $employee = null)
	{
		$this->employee = $employee;

		return $this;
	}

	/**
	 * Get employee.
	 *
	 * @return \AppBundle\Entity\Employee
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
	 * @return Assignment
	 */
	public function setDepartment(Department $department = null)
	{
		$this->department = $department;

		return $this;
	}

	/**
	 * Get department.
	 *
	 * @return \AppBundle\Entity\Department
	 */
	public function getDepartment()
	{
		return $this->department;
	}

	/**
	 * Set startDate.
	 *
	 * @param \DateTime $startDate
	 *
	 * @return Assignment
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
	 * @return Assignment
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
	 * Set clientDefinedStartDate.
	 *
	 * @param string $clientDefinedStartDate
	 *
	 * @return Assignment
	 */
	public function setClientDefinedStartDate($clientDefinedStartDate)
	{
		$this->clientDefinedStartDate = $clientDefinedStartDate;

		return $this;
	}

	/**
	 * Get clientDefinedStartDate.
	 *
	 * @return string
	 */
	public function getClientDefinedStartDate()
	{
		return $this->clientDefinedStartDate;
	}

	/**
	 * Set clientDefinedEndDate.
	 *
	 * @param string $clientDefinedEndDate
	 *
	 * @return Assignment
	 */
	public function setClientDefinedEndDate($clientDefinedEndDate)
	{
		$this->clientDefinedEndDate = $clientDefinedEndDate;

		return $this;
	}

	/**
	 * Get clientDefinedEndDate.
	 *
	 * @return string
	 */
	public function getClientDefinedEndDate()
	{
		return $this->clientDefinedEndDate;
	}

	/**
	 * Set original breakDuration for db.
	 *
	 * @param int $originalBreakDuration
	 *
	 * @return Assignment
	 */
	public function setOriginalBreakDuration(int $originalBreakDuration)
	{
		$this->originalBreakDuration = $originalBreakDuration;

		return $this;
	}

	/**
	 * Get original breakDuration from db.
	 *
	 * @return int
	 */
	public function getOriginalBreakDuration()
	{
		return $this->originalBreakDuration;
	}

	/**
	 * Set breakDuration.
	 *
	 * @param int $breakDuration
	 *
	 * @return Assignment
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
	 * Set published.
	 *
	 * @param bool $published
	 *
	 * @return Assignment
	 */
	public function setPublished($published)
	{
		$this->published = $published;

		return $this;
	}

	/**
	 * Get published.
	 *
	 * @return bool
	 */
	public function getPublished()
	{
		return $this->published;
	}

	/**
	 * Add register.
	 *
	 * @param Register $register
	 *
	 * @return Assignment
	 */
	public function addRegister(Register $register)
	{
		$this->registers[] = $register;

		return $this;
	}

	/**
	 * Remove register.
	 *
	 * @param Register $register
	 */
	public function removeRegister(Register $register)
	{
		$this->registers->removeElement($register);
	}

	/**
	 * Get registers.
	 *
	 * @return ArrayCollection
	 */
	public function getRegisters()
	{
		return $this->registers;
	}

	/**
	 * Add shiftSwapRequest.
	 *
	 * @param ShiftSwapRequest $shiftSwapRequest
	 *
	 * @return Assignment
	 */
	public function addShiftSwapRequest(ShiftSwapRequest $shiftSwapRequest)
	{
		$this->shiftSwapRequests[] = $shiftSwapRequest;

		return $this;
	}

	/**
	 * Remove shiftSwapRequest.
	 *
	 * @param ShiftSwapRequest $shiftSwapRequest
	 */
	public function removeShiftSwapRequest(ShiftSwapRequest $shiftSwapRequest)
	{
		$this->shiftSwapRequests->removeElement($shiftSwapRequest);
	}

	/**
	 * Get shiftSwapRequests.
	 *
	 * @return ArrayCollection
	 */
	public function getShiftSwapRequests()
	{
		return $this->shiftSwapRequests;
	}

	/**
	 * Set remark.
	 *
	 * @param string $remark
	 *
	 * @return Assignment
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
}
