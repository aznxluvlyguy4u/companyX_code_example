<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Legacy\LegacyRegister;
use AppBundle\Enumerator\RegisterStatusLabel;
use AppBundle\Enumerator\RegisterStatus;
use AppBundle\Enumerator\RegisterType;
use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\Mapping\Annotation as Gedmo;
use Doctrine\ORM\Mapping as ORM;
use ReflectionClass;
use Symfony\Component\Validator\Constraints\DateTime;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use AppBundle\Validator\Constraints as AppAssert;
use AppBundle\Validator\Constraints\RegisterConstraints as RegisterAssert;

/**
 * TODO Rename column to default names during DB switch
 * Register.
 *
 * @ORM\Table(name="dor_register")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\RegisterRepository")
 * @ORM\EntityListeners({"AppBundle\EventListener\RegisterListener"})
 * @AppAssert\StartDateNotGreaterThenEndDate
 * @RegisterAssert\VacationTimeout
 * @RegisterAssert\AvailabilityBlockPlanned
 * @RegisterAssert\InvalidUpdateField
 * @RegisterAssert\InvalidPostField
 */
class Register extends LegacyRegister
{
	/**
	 * @var int
	 *
	 * @Groups({"employees"})
	 * @Groups({"registers"})
	 * @Groups({"registerLog"})
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
	 * @Groups({"employees"})
	 * @Groups({"registers"})
	 * @Groups({"registerLog"})
	 *
	 * @ORM\Column(name="start_date", type="datetime")
	 * @Assert\DateTime()
	 * @Assert\NotBlank()
	 */
	private $startDate;

	/**
	 * @var \DateTime
	 *
	 * @Groups({"employees"})
	 * @Groups({"registers"})
	 * @Groups({"registerLog"})
	 *
	 * @ORM\Column(name="end_date", type="datetime")
	 * @Assert\DateTime()
	 * @Assert\NotBlank()
	 */
	private $endDate;

	/**
	 * TODO When new DB structure is set, consider apply subclasses for different types
	 * TODO Abstract base class with children classes that have only the properties thats associated with it.
	 *
	 * @var string
	 *
	 * @Groups({"employees"})
	 * @Groups({"registers"})
	 * @Groups({"registerLog"})
	 *
	 * @ORM\Column(name="type", type="string")
	 * @Assert\Choice(callback = "getAllowedRegisterTypes", strict=true)
	 * @Assert\NotBlank()
	 * @Assert\Type("string")
	 */
	private $type;

	/**
	 * TODO Consider defining a standalone status entity with label property when new DB is in place.
	 *
	 * @var int
	 *
	 * @Groups({"employees"})
	 * @Groups({"registers"})
	 * @Groups({"registerLog"})
	 *
	 * @ORM\Column(name="status", type="integer")
	 * @Assert\Type("integer")
	 */
	private $status = 0;

	/**
	 * TODO When new DB structure is set, this property is not necessary to be mapped anymore
	 * TODO as it can be calculated using virtual property with custom logic in getter setter.
	 *
	 * @var float
	 *
	 * @Groups({"employees"})
	 * @Groups({"registers"})
	 * @Groups({"registerLog"})
	 *
	 * @ORM\Column(name="value", type="float", nullable=true)
	 * @Assert\Type("float")
	 */
	private $workDuration;

	/**
	 * @var float
	 *
	 * @Groups({"employees"})
	 * @Groups({"registers"})
	 * @Assert\Type("float")
	 */
	private $calculatedWorkDuration;

	/**
	 * Virtual property to show values stored in Original Remark depending on Client's role.
	 *
	 * @var string
	 *
	 * @Groups({"employees"})
	 * @Groups({"registers"})
	 */
	private $remark = null;

	/**
	 * Real remark stored in the DB.
	 *
	 * @var string
	 *
	 * @Groups({"registerLog"})
	 *
	 * @ORM\Column(name="opmerking", type="string", nullable=true)
	 * @Assert\Type("string")
	 */
	private $originalRemark;

	/**
	 * @var bool
	 *
	 * @Groups({"registerLog"})
	 *
	 * @ORM\Column(name="locked", type="boolean")
	 * @Assert\Type("bool")
	 */
	private $locked = false;

	/**
	 * @var \DateTime
	 *
	 * @Groups({"registerLog"})
	 *
	 * @ORM\Column(name="verloond", type="datetime", nullable=true)
	 * @Assert\DateTime()
	 */
	private $payedDate;

	/**
	 * TODO Convert this to DateInterval during DB switch, kept as int for current backward compatibility
	 * NOTE: break duration in minutes.
	 *
	 * @var int
	 *
	 * @Groups({"employees"})
	 * @Groups({"registers"})
	 * @Groups({"registerLog"})
	 *
	 * @ORM\Column(name="pauze", type="integer", nullable=true)
	 * @Assert\Type("integer")
	 */
	private $breakDuration;

	/**
	 * TODO Reevaluate other options to save features during DB switch.
	 *
	 * NOTE: binaire waardes, 2,4,8,16 etc, dan de opgetelde waardes opslaan als 'features'
	 * Dus checkbox met value 2, en checkbox 8, wordt waarde 10.
	 *
	 * @var int
	 *
	 * @Groups({"registerLog"})
	 *
	 * @ORM\Column(name="features", type="integer", nullable=true)
	 * @Assert\Type("integer")
	 */
	private $features;

	// TODO Reevaluate if feature component properties such as this can be stored as seperate column
	/**
	 * Virtual property to handle if setting DOR_REGISTRATION_COMPONENT_MEALCHECKBOX is set to true.
	 *
	 * @var bool|null
	 *
	 * @Groups({"employees"})
	 * @Groups({"registers"})
	 * @Assert\Type("boolean")
	 */
	private $meal = null;

	/**
	 * @var float
	 *
	 * @Groups({"employees"})
	 * @Groups({"registers"})
	 * @Groups({"registerLog"})
	 *
	 * @ORM\Column(name="kilometers", type="float", nullable=true)
	 * @Assert\Type("float")
	 */
	private $kilometers = 0.00;

	/**
	 * TODO Currently session id comes from log in supplied by CompanyX, reevaluate if this is still needed after DB switch.
	 *
	 * @var int
	 *
	 * @Groups({"registerLog"})
	 *
	 * @ORM\Column(name="session_id", type="integer", nullable=true)
	 */
	private $sessionId;

	/**
	 * @var \DateTime
	 *
	 * @Groups({"registerLog"})
	 *
	 * @Gedmo\Timestampable(on="create")
	 * @ORM\Column(name="created", type="datetime")
	 * @Assert\NotBlank()
	 * @Assert\DateTime()
	 */
	private $created;

	/**
	 * @var \DateTime
	 *
	 * @Groups({"registerLog"})
	 *
	 * @Gedmo\Timestampable(on="update")
	 * @ORM\Column(name="modified", type="datetime")
	 * @Assert\NotBlank()
	 * @Assert\DateTime()
	 */
	private $modified;

	/**
	 * @var Client
	 *
	 * @Groups({"registerLog"})
	 *
	 * @ORM\OneToOne(targetEntity="Client")
	 * @ORM\JoinColumn(name="admin_id", referencedColumnName="id")
	 * @Assert\Valid
	 */
	private $modifiedBy;

	/**
	 * TODO Currently defaults 0 in CompanyX DB, which is not valid as foreignKey to Client
	 * TODO But we keep default at null and it can be saved, see if error occur with CompanyX code, if so temporarily change default to 0
	 * Many Registers belong to One Client.
	 *
	 * @var Client
	 *
	 * @Groups({"registerLog"})
	 *
	 * @ORM\ManyToOne(targetEntity="Client", inversedBy="registers")
	 * @ORM\JoinColumn(name="profile_id", referencedColumnName="id", nullable=true)
	 * @Assert\Valid
	 */
	private $client;

	/**
	 * TODO Rename "user_id" to "employee_id" during DB switch
	 * Many Registers belong to One Employee.
	 *
	 * @var Employee
	 *
	 * @Groups({"registers"})
	 * @Groups({"registerLog"})
	 *
	 * @ORM\ManyToOne(targetEntity="Employee", inversedBy="registers")
	 * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
	 * @Assert\NotBlank()
	 * @Assert\Valid
	 */
	private $employee;

	/**
	 * Many Registers belong to One Assignment.
	 *
	 * @var Assignment
	 *
	 * @Groups({"employees"})
	 * @Groups({"registers"})
	 * @Groups({"registerLog"})
	 *
	 * @ORM\ManyToOne(targetEntity="Assignment", inversedBy="registers")
	 * @ORM\JoinColumn(name="assignment_id", referencedColumnName="id", nullable=true)
	 * @Assert\Valid
	 */
	private $assignment;

	/**
	 * One Register has One Department.
	 *
	 * @var Department
	 *
	 * @Groups({"registers"})
	 * @Groups({"registerLog"})
	 *
	 * @ORM\OneToOne(targetEntity="Department")
	 * @ORM\JoinColumn(name="object_id", referencedColumnName="id")
	 * @Assert\Valid
	 */
	private $department;

	/**
	 * One Register has Many ClockMoments.
	 *
	 * @ORM\OneToMany(targetEntity="ClockMoment", mappedBy="register")
	 */
	private $clockMoments;

	/**
	 * Register constructor.
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
	 * @return Register
	 */
	public function setId($id)
	{
		$this->id = $id;

		return $this;
	}

	/**
	 * Set startDate.
	 *
	 * @param \DateTime $startDate
	 *
	 * @return Register
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
	 * @return Register
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
	 * Set employee.
	 *
	 * @param Employee $employee
	 *
	 * @return Register
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
	 * TODO Currently hardcoded to return converted values in enum RegisterType, may be deleted later on
	 * Set type.
	 *
	 * @param string $type
	 *
	 * @return Register
	 */
	public function setType($type)
	{
		$matchTable = $this->getTypeMatchTable();

		$typeValue = null;
		if (array_key_exists($type, $matchTable)) {
			$typeValue = $matchTable[$type];
		}

		$this->type = $typeValue;

		return $this;
	}

	/**
	 * TODO Currently hardcoded to return converted values in enum RegisterType, may be deleted later on
	 * Get type.
	 *
	 * @return string
	 */
	public function getType()
	{
		$matchTable = $this->getTypeMatchTable();
		$matchTable = array_flip($matchTable);

		return $matchTable[$this->type] ?? null;
	}

	/**
	 * TODO Currently hardcoded to return converted values in enum RegisterType, may be deleted later on
	 * Get type value name.
	 *
	 * @return string|null
	 */
	public function getTypeValueName()
	{
		return $this->type ?? null;
	}

	/**
	 * Set remark.
	 *
	 * @param string $remark
	 *
	 * @return Register
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
	 * Set originalRemark.
	 *
	 * @param string $originalRemark
	 *
	 * @return Register
	 */
	public function setOriginalRemark($originalRemark)
	{
		$this->originalRemark = $originalRemark;

		return $this;
	}

	/**
	 * Get originalRemark.
	 *
	 * @return string
	 */
	public function getOriginalRemark()
	{
		return $this->originalRemark;
	}

	/**
	 * Set status.
	 *
	 * @param int $status
	 *
	 * @return Register
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
	 * TODO for now the type value is hardcoded, replace by either enum or child class object property name
	 * TODO same apply for the status string
	 * Get string associated with status.
	 *
	 * @Groups({"employees"})
	 * @Groups({"registers"})
	 *
	 * @return int
	 */
	public function getStatusString()
	{
		$statusString = $this->getStatusStringMatchTable()[$this->getType()][$this->getStatus()] ?? null;

		return $statusString;
	}

	/**
	 * Set workDuration.
	 *
	 * @param \double $workDuration
	 *
	 * @return Register
	 */
	public function setWorkDuration($workDuration)
	{
		$this->workDuration = $workDuration;

		return $this;
	}

	/**
	 * Get workDuration.
	 *
	 * @return \double
	 */
	public function getWorkDuration()
	{
		return $this->workDuration;
	}

	/**
	 * Set calculatedWorkDuration.
	 *
	 * @param \double $calculatedWorkDuration
	 *
	 * @return Register
	 */
	public function setCalculatedWorkDuration($calculatedWorkDuration)
	{
		$this->calculatedWorkDuration = $calculatedWorkDuration;

		return $this;
	}

	/**
	 * Get calculatedWorkDuration.
	 *
	 * @return \double
	 */
	public function getCalculatedWorkDuration()
	{
		return $this->calculatedWorkDuration;
	}

	/**
	 * Set assignment.
	 *
	 * @param Assignment $assignment
	 *
	 * @return Register
	 */
	public function setAssignment(Assignment $assignment = null)
	{
		$this->assignment = $assignment;

		return $this;
	}

	/**
	 * Get assignment.
	 *
	 * @return Assignment
	 */
	public function getAssignment()
	{
		return $this->assignment;
	}

	/**
	 * Set created.
	 *
	 * @param \DateTime $created
	 *
	 * @return Register
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
	 * Set modified.
	 *
	 * @param \DateTime $modified
	 *
	 * @return Register
	 */
	public function setModified($modified)
	{
		$this->modified = $modified;

		return $this;
	}

	/**
	 * Get modified.
	 *
	 * @return \DateTime
	 */
	public function getModified()
	{
		return $this->modified;
	}

	/**
	 * Set locked.
	 *
	 * @param bool $locked
	 *
	 * @return Register
	 */
	public function setLocked($locked)
	{
		$this->locked = $locked;

		return $this;
	}

	/**
	 * Get locked.
	 *
	 * @return bool
	 */
	public function getLocked()
	{
		return $this->locked;
	}

	/**
	 * Set payedDate.
	 *
	 * @param \DateTime $payedDate
	 *
	 * @return Register
	 */
	public function setPayedDate($payedDate)
	{
		$this->payedDate = $payedDate;

		return $this;
	}

	/**
	 * Get payedDate.
	 *
	 * @return \DateTime
	 */
	public function getPayedDate()
	{
		return $this->payedDate;
	}

	/**
	 * Set breakDuration.
	 *
	 * @param int $breakDuration
	 *
	 * @return Register
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
	 * Set features.
	 *
	 * @param int $features
	 *
	 * @return Register
	 */
	public function setFeatures($features)
	{
		$this->features = $features;

		return $this;
	}

	/**
	 * Get features.
	 *
	 * @return int
	 */
	public function getFeatures()
	{
		return $this->features;
	}

	/**
	 * Set meal.
	 *
	 * @param bool $meal
	 *
	 * @return Register
	 */
	public function setMeal($meal)
	{
		$this->meal = $meal;

		return $this;
	}

	/**
	 * Get meal.
	 *
	 * @return bool
	 */
	public function getMeal()
	{
		return $this->meal;
	}

	/**
	 * Set kilometers.
	 *
	 * @param float $kilometers
	 *
	 * @return Register
	 */
	public function setKilometers($kilometers)
	{
		$this->kilometers = $kilometers;

		return $this;
	}

	/**
	 * Get kilometers.
	 *
	 * @return float
	 */
	public function getKilometers()
	{
		return $this->kilometers;
	}

	/**
	 * Set modifiedBy.
	 *
	 * @param Client $modifiedBy
	 *
	 * @return Register
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
	 * Set client.
	 *
	 * @param Client $client
	 *
	 * @return Register
	 */
	public function setClient(Client $client = null)
	{
		$this->client = $client;

		return $this;
	}

	/**
	 * Get client.
	 *
	 * @return Client
	 */
	public function getClient()
	{
		return $this->client;
	}

	/**
	 * Set department.
	 *
	 * @param Department $department
	 *
	 * @return Register
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

	// TODO Remove this after DB switch

	/**
	 * Get the type key => value names conversion matchtable.
	 *
	 * @return array
	 */
	public function getTypeMatchTable()
	{
		$registerTypeClass = new ReflectionClass(RegisterType::class);
		$matchTable = $registerTypeClass->getConstants();

		return $matchTable;
	}

	// TODO Remove this after DB switch

	/**
	 * Temporary helper method to match status string to each Register type.
	 *
	 * @return array
	 */
	public function getStatusStringMatchTable()
	{
		$typeMatchTable = $this->getTypeMatchTable();
		$typeMatchTable = array_flip($typeMatchTable);
		$statusStringMatchTable = [
			$typeMatchTable[RegisterType::WORK] => [RegisterStatus::UNPROCESSED => RegisterStatusLabel::WORK_UNPROCESSED, RegisterStatus::DENIED => RegisterStatusLabel::WORK_DENIED, RegisterStatus::GRANTED => RegisterStatusLabel::WORK_GRANTED],
			$typeMatchTable[RegisterType::AVAILABLE] => [RegisterStatus::UNPROCESSED => RegisterStatusLabel::AVAILABLE_UNPROCESSED, RegisterStatus::DENIED => RegisterStatusLabel::AVAILABLE_DENIED, RegisterStatus::GRANTED => RegisterStatusLabel::AVAILABLE_GRANTED],
			$typeMatchTable[RegisterType::UNAVAILABLE] => [RegisterStatus::UNPROCESSED => RegisterStatusLabel::UNAVAILABLE_UNPROCESSED, RegisterStatus::DENIED => RegisterStatusLabel::UNAVAILABLE_DENIED, RegisterStatus::GRANTED => RegisterStatusLabel::UNAVAILABLE_GRANTED],
			$typeMatchTable[RegisterType::PREFERENCE] => [RegisterStatus::UNPROCESSED => RegisterStatusLabel::PREFERENCE_UNPROCESSED, RegisterStatus::DENIED => RegisterStatusLabel::PREFERENCE_DENIED, RegisterStatus::GRANTED => RegisterStatusLabel::PREFERENCE_GRANTED],
			$typeMatchTable[RegisterType::VACATION] => [RegisterStatus::UNPROCESSED => RegisterStatusLabel::VACATION_UNPROCESSED, RegisterStatus::DENIED => RegisterStatusLabel::VACATION_DENIED, RegisterStatus::GRANTED => RegisterStatusLabel::VACATION_OK],
		];

		return $statusStringMatchTable;
	}

	/**
	 * Get allowed type values for choice restriction.
	 *
	 * @return array
	 */
	public function getAllowedRegisterTypes()
	{
		$registerTypeClass = new ReflectionClass(RegisterType::class);
		$matchTable = $registerTypeClass->getConstants();
		$allowedRegisterTypes = array_values($matchTable);

		return $allowedRegisterTypes;
	}

	/**
	 * Set sessionId.
	 *
	 * @param int $sessionId
	 *
	 * @return Register
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
	 * Add clockMoment.
	 *
	 * @param ClockMoment $clockMoment
	 *
	 * @return Register
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
}
