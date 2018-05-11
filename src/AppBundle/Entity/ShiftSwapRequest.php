<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Legacy\LegacyShiftSwapRequest;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;
use AppBundle\Validator\Constraints as AppAssert;
use AppBundle\Validator\Constraints\ShiftSwapRequestConstraints as ShiftSwapRequestAssert;

// TODO change table name to default after DB switch
// TODO change all column names to default after DB switch
/**
 * ShiftSwapRequest.
 *
 * @ORM\Table(name="dor_exchange_emp")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\ShiftSwapRequestRepository")
 * @ORM\EntityListeners({"AppBundle\EventListener\ShiftSwapRequestListener"})
 * @AppAssert\StartDateNotGreaterThenEndDate
 * @ShiftSwapRequestAssert\InvalidUpdateField
 */
class ShiftSwapRequest extends LegacyShiftSwapRequest
{
	/**
	 * @var int
	 *
	 * @Groups({"shiftSwapRequests"})
	 * @Groups({"assignments"})
	 * @ORM\Column(name="id", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="AUTO")
	 */
	private $id;

	/**
	 * Many ShiftSwapRequests have one Employee as applicant.
	 *
	 * @var Employee
	 *
	 * @Groups({"shiftSwapRequests"})
	 * @Groups({"assignments"})
	 * @ORM\ManyToOne(targetEntity="Employee", inversedBy="shiftSwapRequestApplicants")
	 * @ORM\JoinColumn(name="aanvrager_id", referencedColumnName="id")
	 * @Assert\NotBlank
	 * @Assert\Valid
	 */
	private $applicant;

	/**
	 * Many ShiftSwapRequests have one Employee as receiver.
	 *
	 * @var Employee
	 *
	 * @Groups({"shiftSwapRequests"})
	 * @Groups({"assignments"})
	 * @ORM\ManyToOne(targetEntity="Employee", inversedBy="shiftSwapRequestReceivers")
	 * @ORM\JoinColumn(name="vervanger_id", referencedColumnName="id")
	 * @Assert\NotBlank
	 * @Assert\Valid
	 */
	private $receiver;

	/**
	 * Many ShiftSwapRequests have one Employee as planner.
	 *
	 * @var Employee
	 *
	 * @ORM\ManyToOne(targetEntity="Employee", inversedBy="shiftSwapRequestPlanners")
	 * @ORM\JoinColumn(name="planner_id", referencedColumnName="id", nullable=true)
	 * @Assert\Valid
	 */
	private $planner;

	/**
	 * Many ShiftSwapRequests have one Assignment.
	 *
	 * @var Assignment
	 *
	 * @Groups({"shiftSwapRequests"})
	 * @ORM\ManyToOne(targetEntity="Assignment", inversedBy="shiftSwapRequests")
	 * @ORM\JoinColumn(name="assignment_id", referencedColumnName="id")
	 * @Assert\NotBlank
	 * @Assert\Valid
	 */
	private $assignment;

	/**
	 * @var string
	 *
	 * @Groups({"shiftSwapRequests"})
	 * @ORM\Column(name="toelichting", type="string", nullable=true)
	 * @Assert\Type("string")
	 */
	private $applicantMessage;

	/**
	 * @var string
	 *
	 * @Groups({"shiftSwapRequests"})
	 * @ORM\Column(name="toelichtingVervanger", type="string", nullable=true)
	 * @Assert\Type("string")
	 */
	private $receiverMessage;

	/**
	 * @var string
	 *
	 * @Groups({"shiftSwapRequests"})
	 * @ORM\Column(name="toelichtingPlanner", type="string", nullable=true)
	 * @Assert\Type("string")
	 */
	private $plannerMessage;

	/**
	 * @var string
	 *
	 * @Groups({"shiftSwapRequests"})
	 * @ORM\Column(name="toelichtingIntrekkenAanvrager", type="string", nullable=true)
	 * @Assert\Type("string")
	 */
	private $applicantWithdrawalMessage;

	/**
	 * @var string
	 *
	 * @Groups({"shiftSwapRequests"})
	 * @ORM\Column(name="toelichtingIntrekkenVervanger", type="string", nullable=true)
	 * @Assert\Type("string")
	 */
	private $receiverWithdrawalMessage;

	/**
	 * @var string
	 *
	 * @Groups({"shiftSwapRequests"})
	 * @ORM\Column(name="opmerking", type="string", nullable=true)
	 * @Assert\Type("string")
	 */
	private $remark;

	/**
	 * @var \datetime
	 *
	 * @ORM\Column(name="duration", type="datetime")
	 * @Assert\DateTime()
	 * @Assert\NotBlank()
	 */
	private $expireDate;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="hash", type="string")
	 * @Assert\NotBlank()
	 * @Assert\Type("string")
	 */
	private $hash;

	/**
	 * @var int
	 *
	 * @Groups({"shiftSwapRequests"})
	 * @ORM\Column(name="status", type="integer")
	 * @Assert\NotBlank()
	 * @Assert\Type("integer")
	 */
	private $status = 0;

	/**
	 * @var \DateTime
	 *
	 * @Groups({"shiftSwapRequests"})
	 * @ORM\Column(name="start_date", type="datetime")
	 * @Assert\DateTime()
	 * @Assert\NotBlank()
	 */
	private $startDate;

	/**
	 * @var \DateTime
	 *
	 * @Groups({"shiftSwapRequests"})
	 * @ORM\Column(name="end_date", type="datetime")
	 * @Assert\DateTime()
	 * @Assert\NotBlank()
	 */
	private $endDate;

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
	 * @var bool
	 *
	 * @ORM\Column(name="changed", type="boolean")
	 */
	private $changed = 0;

	/**
	 * Virtual helper property to determine from whom the request is coming from.
	 *
	 * @var string
	 */
	private $currentRole = null;

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
	 * @return ShiftSwapRequest
	 */
	public function setId($id)
	{
		$this->id = $id;

		return $this;
	}

	/**
	 * Set applicantMessage.
	 *
	 * @param string $applicantMessage
	 *
	 * @return ShiftSwapRequest
	 */
	public function setApplicantMessage($applicantMessage)
	{
		$this->applicantMessage = $applicantMessage;

		return $this;
	}

	/**
	 * Get applicantMessage.
	 *
	 * @return string
	 */
	public function getApplicantMessage()
	{
		return $this->applicantMessage;
	}

	/**
	 * Set receiverMessage.
	 *
	 * @param string $receiverMessage
	 *
	 * @return ShiftSwapRequest
	 */
	public function setReceiverMessage($receiverMessage)
	{
		$this->receiverMessage = $receiverMessage;

		return $this;
	}

	/**
	 * Get receiverMessage.
	 *
	 * @return string
	 */
	public function getReceiverMessage()
	{
		return $this->receiverMessage;
	}

	/**
	 * Set plannerMessage.
	 *
	 * @param string $plannerMessage
	 *
	 * @return ShiftSwapRequest
	 */
	public function setPlannerMessage($plannerMessage)
	{
		$this->plannerMessage = $plannerMessage;

		return $this;
	}

	/**
	 * Get plannerMessage.
	 *
	 * @return string
	 */
	public function getPlannerMessage()
	{
		return $this->plannerMessage;
	}

	/**
	 * Set applicantWithdrawalMessage.
	 *
	 * @param string $applicantWithdrawalMessage
	 *
	 * @return ShiftSwapRequest
	 */
	public function setApplicantWithdrawalMessage($applicantWithdrawalMessage)
	{
		$this->applicantWithdrawalMessage = $applicantWithdrawalMessage;

		return $this;
	}

	/**
	 * Get applicantWithdrawalMessage.
	 *
	 * @return string
	 */
	public function getApplicantWithdrawalMessage()
	{
		return $this->applicantWithdrawalMessage;
	}

	/**
	 * Set receiverWithdrawalMessage.
	 *
	 * @param string $receiverWithdrawalMessage
	 *
	 * @return ShiftSwapRequest
	 */
	public function setReceiverWithdrawalMessage($receiverWithdrawalMessage)
	{
		$this->receiverWithdrawalMessage = $receiverWithdrawalMessage;

		return $this;
	}

	/**
	 * Get receiverWithdrawalMessage.
	 *
	 * @return string
	 */
	public function getReceiverWithdrawalMessage()
	{
		return $this->receiverWithdrawalMessage;
	}

	/**
	 * Set remark.
	 *
	 * @param string $remark
	 *
	 * @return ShiftSwapRequest
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
	 * Set expireDate.
	 *
	 * @param \DateTime $expireDate
	 *
	 * @return ShiftSwapRequest
	 */
	public function setExpireDate($expireDate)
	{
		$this->expireDate = $expireDate;

		return $this;
	}

	/**
	 * Get expireDate.
	 *
	 * @return \DateTime
	 */
	public function getExpireDate()
	{
		return $this->expireDate;
	}

	/**
	 * Set hash.
	 *
	 * @param string $hash
	 *
	 * @return ShiftSwapRequest
	 */
	public function setHash($hash)
	{
		$this->hash = $hash;

		return $this;
	}

	/**
	 * Get hash.
	 *
	 * @return string
	 */
	public function getHash()
	{
		return $this->hash;
	}

	/**
	 * Set status.
	 *
	 * @param int $status
	 *
	 * @return ShiftSwapRequest
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
	 * Set startDate.
	 *
	 * @param \DateTime $startDate
	 *
	 * @return ShiftSwapRequest
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
	 * @return ShiftSwapRequest
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
	 * Set created.
	 *
	 * @param \DateTime $created
	 *
	 * @return ShiftSwapRequest
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
	 * Set changed.
	 *
	 * @param bool $changed
	 *
	 * @return ShiftSwapRequest
	 */
	public function setChanged($changed)
	{
		$this->changed = $changed;

		return $this;
	}

	/**
	 * Get changed.
	 *
	 * @return bool
	 */
	public function getChanged()
	{
		return $this->changed;
	}

	/**
	 * Set applicant.
	 *
	 * @param Employee $applicant
	 *
	 * @return ShiftSwapRequest
	 */
	public function setApplicant(Employee $applicant = null)
	{
		$this->applicant = $applicant;

		return $this;
	}

	/**
	 * Get applicant.
	 *
	 * @return Employee
	 */
	public function getApplicant()
	{
		return $this->applicant;
	}

	/**
	 * Set receiver.
	 *
	 * @param Employee $receiver
	 *
	 * @return ShiftSwapRequest
	 */
	public function setReceiver(Employee $receiver = null)
	{
		$this->receiver = $receiver;

		return $this;
	}

	/**
	 * Get receiver.
	 *
	 * @return Employee
	 */
	public function getReceiver()
	{
		return $this->receiver;
	}

	/**
	 * Set planner.
	 *
	 * @param Employee $planner
	 *
	 * @return ShiftSwapRequest
	 */
	public function setPlanner(Employee $planner = null)
	{
		$this->planner = $planner;

		return $this;
	}

	/**
	 * Get planner.
	 *
	 * @return Employee
	 */
	public function getPlanner()
	{
		return $this->planner;
	}

	/**
	 * Set assignment.
	 *
	 * @param Assignment $assignment
	 *
	 * @return ShiftSwapRequest
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
	 * Set currentRole.
	 *
	 * @param string $currentRole
	 *
	 * @return ShiftSwapRequest
	 */
	public function setCurrentRole($currentRole)
	{
		$this->currentRole = $currentRole;

		return $this;
	}

	/**
	 * Get currentRole.
	 *
	 * @return string
	 */
	public function getCurrentRole()
	{
		return $this->currentRole;
	}
}
