<?php

namespace AppBundle\Entity;

use AppBundle\Util\Constants;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class Employee.
 *
 * TODO Rename table name = dysc_users to "employee" matching the entity name during DB switch
 * TODO Rename column names to default names during DB switch
 *
 * @ORM\Table(name="dysc_users")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\EmployeeRepository")
 * @ORM\HasLifecycleCallbacks
 */
class Employee
{
	/**
	 * @var int
	 *
	 * @Groups({"employees"})
	 * @Groups({"eligibleEmployees"})
	 * @Groups({"clients"})
	 * @Groups({"registers"})
	 * @Groups({"assignments"})
	 * @Groups({"shiftSwapRequests"})
	 * @Groups({"clockMoments"})
	 * @Groups({"registerLog"})
	 * @Groups({"assignmentLog"})
	 * @Groups({"employeeLog"})
	 * @Groups({"clockMomentLog"})
	 *
	 * @ORM\Column(name="id", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="AUTO")
	 */
	private $id;

	/**
	 * @var string
	 *
	 * @Groups({"employees"})
	 * @Groups({"eligibleEmployees"})
	 * @Groups({"shiftSwapRequests"})
	 * @Groups({"assignments"})
	 * @Groups({"clockMoments"})
	 * @Groups({"employeeLog"})
	 *
	 * @ORM\Column(name="voornaam", type="string")
	 */
	private $firstname;

	/**
	 * @var string
	 *
	 * @Groups({"employees"})
	 * @Groups({"eligibleEmployees"})
	 * @Groups({"shiftSwapRequests"})
	 * @Groups({"assignments"})
	 * @Groups({"clockMoments"})
	 * @Groups({"employeeLog"})
	 *
	 * @ORM\Column(name="geboortenaam", type="string")
	 */
	private $lastname;

	/**
	 * @var string
	 *
	 * @Groups({"employees"})
	 * @Groups({"eligibleEmployees"})
	 * @Groups({"shiftSwapRequests"})
	 * @Groups({"assignments"})
	 * @Groups({"clockMoments"})
	 * @Groups({"employeeLog"})
	 *
	 * @ORM\Column(name="tussenvoegsel", type="string")
	 */
	private $insertion;

	/**
	 * Temporary property used in the solution to fetch the avatar
	 * TODO Reevaluate if this can be removed later on.
	 *
	 * @var int
	 *
	 * @Groups({"employees"})
	 * @Groups({"employeeLog"})
	 *
	 * @ORM\Column(name="profile_id", type="integer")
	 */
	private $profileId;

	/**
	 * @var string
	 *
	 * @Groups({"employees"})
	 * @Groups({"clients"})
	 * @Groups({"employeeLog"})
	 *
	 * @ORM\Column(name="email", type="string")
	 */
	private $emailAddress;

	/**
	 * @var string
	 *
	 * @Groups({"employees"})
	 * @Groups({"clients"})
	 * @Groups({"employeeLog"})
	 *
	 * @ORM\Column(name="telefoon", type="string")
	 */
	private $phoneNumber;

	/**
	 * @var bool
	 *
	 * @Groups({"employeeLog"})
	 *
	 * @ORM\Column(name="active", type="boolean")
	 */
	private $active;

	/**
	 * @var \DateTime
	 *
	 * @Groups({"employeeLog"})
	 *
	 * @ORM\Column(name="contract_start", type="datetime")
	 */
	private $contractStart;

	/**
	 * @var \DateTime
	 *
	 * @Groups({"employeeLog"})
	 *
	 * @ORM\Column(name="contract_eind", type="datetime")
	 */
	private $contractEnd;

	/**
	 * @var \DateInterval
	 */
	private $contractDuration;

	/**
	 * One Employee has Many Assignments.
	 *
	 * @Groups({"employees"})
	 * @ORM\OneToMany(targetEntity="Assignment", mappedBy="employee")
	 */
	private $assignments;

	/**
	 * One Employee has Many Registers.
	 *
	 * @Groups({"employees"})
	 * @ORM\OneToMany(targetEntity="Register", mappedBy="employee")
	 */
	private $registers;

	/**
	 * Many Employees have Many Departments.
	 *
	 * @ORM\ManyToMany(targetEntity="Department", inversedBy="employees")
	 * @ORM\JoinTable(name="offices_users",
	 *      joinColumns={@ORM\JoinColumn(name="user_id", referencedColumnName="id")},
	 *      inverseJoinColumns={@ORM\JoinColumn(name="office_id", referencedColumnName="id", unique=true)})
	 */
	private $departments;

	/**
	 * One Employee has One Client.
	 *
	 * @Groups({"employeeLog"})
	 *
	 * @ORM\OneToOne(targetEntity="Client", mappedBy="employee")
	 */
	private $client;

	/**
	 * One Employee has Many ShiftSwapRequestApplicants.
	 *
	 * @ORM\OneToMany(targetEntity="ShiftSwapRequest", mappedBy="applicant")
	 */
	private $shiftSwapRequestApplicants;

	/**
	 * One Employee has Many ShiftSwapRequestReceivers.
	 *
	 * @ORM\OneToMany(targetEntity="ShiftSwapRequest", mappedBy="receiver")
	 */
	private $shiftSwapRequestReceivers;

	/**
	 * One Employee has Many ShiftSwapRequestPlanners.
	 *
	 * @ORM\OneToMany(targetEntity="ShiftSwapRequest", mappedBy="planner")
	 */
	private $shiftSwapRequestPlanners;

	/**
	 * One Employee has Many ClockMoments.
	 *
	 * @ORM\OneToMany(targetEntity="ClockMoment", mappedBy="employee")
	 */
	private $clockMoments;

	/**
	 * One Employee has Many ClockIntervals.
	 *
	 * @ORM\OneToMany(targetEntity="ClockInterval", mappedBy="employee")
	 */
	private $clockIntervals;

	/**
	 * One Employee has Many AccessControlItems.
	 *
	 * @ORM\OneToMany(targetEntity="AccessControlItem", mappedBy="employee")
	 */
	private $accessControlItems;

	/**
	 * Employee constructor.
	 */
	public function __construct()
	{
		$this->departments = new ArrayCollection();
		$this->assignments = new ArrayCollection();
		$this->registers = new ArrayCollection();
		$this->shiftSwapRequestApplicants = new ArrayCollection();
		$this->shiftSwapRequestReceivers = new ArrayCollection();
		$this->shiftSwapRequestPlanners = new ArrayCollection();
		$this->clockMoments = new ArrayCollection();
		$this->clockIntervals = new ArrayCollection();
		$this->accessControlItems = new ArrayCollection();
	}

	/**
	 * @return int
	 */
	public function getId()
	{
		return $this->id;
	}

	/**
	 * @param int $id
	 */
	public function setId($id)
	{
		$this->id = $id;
	}

	/**
	 * @return string
	 */
	public function getFirstname()
	{
		return $this->firstname;
	}

	/**
	 * @param string $firstname
	 */
	public function setFirstname($firstname)
	{
		$this->firstname = $firstname;
	}

	/**
	 * @return string
	 */
	public function getLastname()
	{
		return $this->lastname;
	}

	/**
	 * @param string $lastname
	 */
	public function setLastname($lastname)
	{
		$this->lastname = $lastname;
	}

	/**
	 * @return string
	 */
	public function getEmailAddress()
	{
		return $this->emailAddress;
	}

	/**
	 * @param string $emailAddress
	 */
	public function setEmailAddress($emailAddress)
	{
		$this->emailAddress = $emailAddress;
	}

	/**
	 * @return string
	 */
	public function getPhoneNumber()
	{
		return $this->phoneNumber;
	}

	/**
	 * @param string $phoneNumber
	 */
	public function setPhoneNumber($phoneNumber)
	{
		$this->phoneNumber = $phoneNumber;
	}

	/**
	 * @return \DateTime
	 */
	public function getContractStart()
	{
		return $this->contractStart;
	}

	/**
	 * @param \DateTime $contractStart
	 */
	public function setContractStart($contractStart)
	{
		$this->contractStart = $contractStart;
	}

	/**
	 * @return \DateTime
	 */
	public function getContractEnd()
	{
		return $this->contractEnd;
	}

	/**
	 * @param \DateTime $contractEnd
	 */
	public function setContractEnd($contractEnd)
	{
		$this->contractEnd = $contractEnd;
	}

	/**
	 * @return string
	 */
	public function getContractDuration()
	{
		return $this->contractDuration;
	}

	/**
	 * @param string $contractDuration
	 */
	public function setContractDuration($contractDuration)
	{
		$this->contractDuration = $contractDuration;
	}

	/**
	 * TODO Remove the zero date converter.
	 *
	 * @ORM\PostLoad()
	 */
	public function postLoadSetContractDuration()
	{
		$this->contractDuration = $this->calculateContractDuration();
	}

	/**
	 * @return null|string
	 */
	public function calculateContractDuration()
	{
		$duration = null;

		if ($this->contractStart) {
			$startDate = $this->contractStart->format(Constants::DateFormatString);
			$startDate = '-0001-11-30' === $startDate ? null : $startDate;
			if ($startDate) {
				/** @var \DateInterval $duration */
				$duration = $this->contractStart->diff(new \DateTime());
			}
		}

		return $duration;
	}

	/**
	 * Set active.
	 *
	 * @param bool $active
	 *
	 * @return Employee
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
	 * Add assignment.
	 *
	 * @param Assignment $assignment
	 *
	 * @return Employee
	 */
	public function addAssignment(Assignment $assignment)
	{
		$this->assignments[] = $assignment;

		return $this;
	}

	/**
	 * Remove assignment.
	 *
	 * @param Assignment $assignment
	 */
	public function removeAssignment(Assignment $assignment)
	{
		$this->assignments->removeElement($assignment);
	}

	/**
	 * Get assignments.
	 *
	 * @return ArrayCollection
	 */
	public function getAssignments()
	{
		return $this->assignments;
	}

	/**
	 * Add register.
	 *
	 * @param Register $register
	 *
	 * @return Employee
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
	 * Set insertion.
	 *
	 * @param string $insertion
	 *
	 * @return Employee
	 */
	public function setInsertion($insertion)
	{
		$this->insertion = $insertion;

		return $this;
	}

	/**
	 * Get insertion.
	 *
	 * @return string
	 */
	public function getInsertion()
	{
		return $this->insertion;
	}

	/**
	 * Set profileId.
	 *
	 * @param int $profileId
	 *
	 * @return Employee
	 */
	public function setProfileId($profileId)
	{
		$this->profileId = $profileId;

		return $this;
	}

	/**
	 * Get profileId.
	 *
	 * @return int
	 */
	public function getProfileId()
	{
		return $this->profileId;
	}

	/**
	 * @return mixed
	 */
	public function getClient()
	{
		return $this->client;
	}

	/**
	 * @param mixed $client
	 */
	public function setClient($client)
	{
		$this->client = $client;
	}

	/**
	 * Add department.
	 *
	 * @param Department $department
	 *
	 * @return Employee
	 */
	public function addDepartment(Department $department)
	{
		$this->departments[] = $department;

		return $this;
	}

	/**
	 * Remove department.
	 *
	 * @param Department $department
	 */
	public function removeDepartment(Department $department)
	{
		$this->departments->removeElement($department);
	}

	/**
	 * Get departments.
	 *
	 * @return ArrayCollection
	 */
	public function getDepartments()
	{
		return $this->departments;
	}

	/**
	 * Add shiftSwapRequestApplicant.
	 *
	 * @param ShiftSwapRequest $shiftSwapRequestApplicant
	 *
	 * @return Employee
	 */
	public function addShiftSwapRequestApplicant(ShiftSwapRequest $shiftSwapRequestApplicant)
	{
		$this->shiftSwapRequestApplicants[] = $shiftSwapRequestApplicant;

		return $this;
	}

	/**
	 * Remove shiftSwapRequestApplicant.
	 *
	 * @param ShiftSwapRequest $shiftSwapRequestApplicant
	 */
	public function removeShiftSwapRequestApplicant(ShiftSwapRequest $shiftSwapRequestApplicant)
	{
		$this->shiftSwapRequestApplicants->removeElement($shiftSwapRequestApplicant);
	}

	/**
	 * Get shiftSwapRequestApplicants.
	 *
	 * @return ArrayCollection
	 */
	public function getShiftSwapRequestApplicants()
	{
		return $this->shiftSwapRequestApplicants;
	}

	/**
	 * Add shiftSwapRequestReceiver.
	 *
	 * @param ShiftSwapRequest $shiftSwapRequestReceiver
	 *
	 * @return Employee
	 */
	public function addShiftSwapRequestReceiver(ShiftSwapRequest $shiftSwapRequestReceiver)
	{
		$this->shiftSwapRequestReceivers[] = $shiftSwapRequestReceiver;

		return $this;
	}

	/**
	 * Remove shiftSwapRequestReceiver.
	 *
	 * @param ShiftSwapRequest $shiftSwapRequestReceiver
	 */
	public function removeShiftSwapRequestReceiver(ShiftSwapRequest $shiftSwapRequestReceiver)
	{
		$this->shiftSwapRequestReceivers->removeElement($shiftSwapRequestReceiver);
	}

	/**
	 * Get shiftSwapRequestReceivers.
	 *
	 * @return ArrayCollection
	 */
	public function getShiftSwapRequestReceivers()
	{
		return $this->shiftSwapRequestReceivers;
	}

	/**
	 * Add shiftSwapRequestPlanner.
	 *
	 * @param ShiftSwapRequest $shiftSwapRequestPlanner
	 *
	 * @return Employee
	 */
	public function addShiftSwapRequestPlanner(ShiftSwapRequest $shiftSwapRequestPlanner)
	{
		$this->shiftSwapRequestPlanners[] = $shiftSwapRequestPlanner;

		return $this;
	}

	/**
	 * Remove shiftSwapRequestPlanner.
	 *
	 * @param ShiftSwapRequest $shiftSwapRequestPlanner
	 */
	public function removeShiftSwapRequestPlanner(ShiftSwapRequest $shiftSwapRequestPlanner)
	{
		$this->shiftSwapRequestPlanners->removeElement($shiftSwapRequestPlanner);
	}

	/**
	 * Get shiftSwapRequestPlanners.
	 *
	 * @return ArrayCollection
	 */
	public function getShiftSwapRequestPlanners()
	{
		return $this->shiftSwapRequestPlanners;
	}

	/**
	 * Virtual getter to get compiled full name.
	 *
	 * @return string
	 */
	public function getFullName()
	{
		$insertion = $this->getInsertion() ? ' '.$this->getInsertion() : null;
		$fullName = $this->getFirstname().$insertion.' '.$this->getLastname();

		return $fullName;
	}

	/**
	 * Add clockMoment.
	 *
	 * @param ClockMoment $clockMoment
	 *
	 * @return Employee
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
	 * Add clockInterval.
	 *
	 * @param ClockInterval $clockInterval
	 *
	 * @return Employee
	 */
	public function addClockInterval(ClockInterval $clockInterval)
	{
		$this->clockIntervals[] = $clockInterval;

		return $this;
	}

	/**
	 * Remove clockInterval.
	 *
	 * @param ClockInterval $clockInterval
	 */
	public function removeClockInterval(ClockInterval $clockInterval)
	{
		$this->clockIntervals->removeElement($clockInterval);
	}

	/**
	 * Get clockIntervals.
	 *
	 * @return ArrayCollection
	 */
	public function getClockIntervals()
	{
		return $this->clockIntervals;
	}

	/**
	 * Add accessControlItem.
	 *
	 * @param AccessControlItem $accessControlItem
	 *
	 * @return Employee
	 */
	public function addAccessControlItem(AccessControlItem $accessControlItem)
	{
		$this->accessControlItems[] = $accessControlItem;

		return $this;
	}

	/**
	 * Remove accessControlItem.
	 *
	 * @param AccessControlItem $accessControlItem
	 */
	public function removeAccessControlItem(AccessControlItem $accessControlItem)
	{
		$this->accessControlItems->removeElement($accessControlItem);
	}

	/**
	 * Get accessControlItems.
	 *
	 * @return ArrayCollection
	 */
	public function getAccessControlItems()
	{
		return $this->accessControlItems;
	}
}
