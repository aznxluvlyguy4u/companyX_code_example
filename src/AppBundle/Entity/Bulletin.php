<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Legacy\LegacyBulletin;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

// TODO Rename table and column names to default names during DB switch
/**
 * Class Bulletin.
 *
 * @ORM\Table(name="dor_calender")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\BulletinRepository")
 * @ORM\EntityListeners({"AppBundle\EventListener\BulletinListener"})
 */
class Bulletin extends LegacyBulletin
{
	/**
	 * @var int
	 *
	 * @Groups({"bulletins"})
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="AUTO")
	 * @ORM\Column(name="id", type="integer")
	 */
	private $id;

	/**
	 * @var \DateTime
	 *
	 * @Groups({"bulletins"})
	 * @ORM\Column(name="start_date", type="datetime")
	 * @Assert\DateTime()
	 * @Assert\NotBlank()
	 */
	private $startDate;

	/**
	 * @var \DateTime
	 *
	 * @Groups({"bulletins"})
	 * @ORM\Column(name="end_date", type="datetime")
	 * @Assert\DateTime()
	 * @Assert\NotBlank()
	 */
	private $endDate;

	// TODO Rename "model_id" to "department_id" during DB switch
	// TODO This is actually an Office, but it can't be fixed for now as Office is not an seperate table so use Department instead for now. Fix during DB Switch
	/**
	 * Many Bulletins have One Department.
	 *
	 * @var Department
	 *
	 * @Groups({"bulletins"})
	 * @ORM\ManyToOne(targetEntity="Department", inversedBy="bulletins")
	 * @ORM\JoinColumn(name="model_id", referencedColumnName="id")
	 * @Assert\Valid
	 */
	private $department;

	// TODO When new DB structure is set, consider apply subclasses for different types
	// TODO Abstract base class with children classes that have only the properties thats associated with it
	/**
	 * @var string
	 *
	 * @ORM\Column(name="type", type="string")
	 * @Assert\NotBlank()
	 * @Assert\Type("string")
	 */
	private $type;

	/**
	 * @var string
	 *
	 * @Groups({"bulletins"})
	 * @ORM\Column(name="title", type="string")
	 */
	private $title;

	/**
	 * @var string
	 *
	 * @Groups({"bulletins"})
	 * @ORM\Column(name="description", type="string", nullable=true)
	 */
	private $description;

	// TODO Reevaluate if data stored inside metaData unserialized array can be stored as physical seperate columns
	/**
	 * @var string
	 *
	 * @ORM\Column(name="meta", type="string")
	 */
	private $metaData;

	// TODO many to one relation to Client can't be mapped to virtual property, change this property to a real one during DB switch
	/**
	 * Virtual property for author_id inside metaData.
	 *
	 * @var Client
	 */
	private $modifiedBy;

	// TODO Reevaluate if this property can be stored as physical seperate column
	/**
	 * Virtual property for description_planning inside metaData.
	 *
	 * @var string
	 *
	 * @Groups({"bulletins"})
	 */
	private $remark;

	/**
	 * @var float
	 *
	 * @ORM\Column(name="value", type="decimal", precision=12, scale=4)
	 */
	private $value;

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
	 * @var \DateTime
	 *
	 * @Gedmo\Timestampable(on="update")
	 * @ORM\Column(name="modified", type="datetime")
	 * @Assert\NotBlank()
	 * @Assert\DateTime()
	 */
	private $modified;

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
	 * @return Bulletin
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
	 * @return Bulletin
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
	 * @return Bulletin
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
	 * Set department.
	 *
	 * @param \AppBundle\Entity\Department $department
	 *
	 * @return Bulletin
	 */
	public function setDepartment(\AppBundle\Entity\Department $department = null)
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
	 * Set type.
	 *
	 * @param string $type
	 *
	 * @return Bulletin
	 */
	public function setType($type)
	{
		$this->type = $type;

		return $this;
	}

	/**
	 * Get type.
	 *
	 * @return string
	 */
	public function getType()
	{
		return $this->type;
	}

	/**
	 * Set title.
	 *
	 * @param string $title
	 *
	 * @return Bulletin
	 */
	public function setTitle($title)
	{
		$this->title = $title;

		return $this;
	}

	/**
	 * Get title.
	 *
	 * @return string
	 */
	public function getTitle()
	{
		return $this->title;
	}

	/**
	 * Set description.
	 *
	 * @param string $description
	 *
	 * @return Bulletin
	 */
	public function setDescription($description)
	{
		$this->description = $description;

		return $this;
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public function getDescription()
	{
		return $this->description;
	}

	/**
	 * Set metaData.
	 *
	 * @param string $metaData
	 *
	 * @return Bulletin
	 */
	public function setMetaData($metaData)
	{
		$this->metaData = $metaData;

		return $this;
	}

	/**
	 * Get metaData.
	 *
	 * @return string
	 */
	public function getMetaData()
	{
		return $this->metaData;
	}

	/**
	 * Set value.
	 *
	 * @param string $value
	 *
	 * @return Bulletin
	 */
	public function setValue($value)
	{
		$this->value = $value;

		return $this;
	}

	/**
	 * Get value.
	 *
	 * @return string
	 */
	public function getValue()
	{
		return $this->value;
	}

	/**
	 * Set created.
	 *
	 * @param \DateTime $created
	 *
	 * @return Bulletin
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
	 * @return Bulletin
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
	 * Set modifiedBy.
	 *
	 * @param Client $modifiedBy
	 *
	 * @return Bulletin
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
	 * Set remark.
	 *
	 * @param string $remark
	 *
	 * @return Bulletin
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
