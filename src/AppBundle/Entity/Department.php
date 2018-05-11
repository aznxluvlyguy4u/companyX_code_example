<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Serializer\Annotation\Groups;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Class Department.
 *
 * @Gedmo\Tree(type="nested")
 * TODO Rename table name = dor_objects to "department" matching the entity name during DB switch
 * TODO Rename all mapped column names to proper default names
 * @ORM\Table(name="dor_objects")
 * @ORM\EntityListeners({"AppBundle\EventListener\DepartmentListener"})
 * @ORM\Entity(repositoryClass="AppBundle\Repository\DepartmentRepository")
 */
class Department
{
	/**
	 * @var int
	 *
	 * @Groups({"employees", "offices/*", "departments"})
	 * @Groups({"assignments"})
	 * @Groups({"registers"})
	 * @Groups({"bulletins"})
	 * @Groups({"shiftSwapRequests"})
	 * @Groups({"clockMoments"})
	 * @Groups({"clients"})
	 * @Groups({"registerLog"})
	 * @Groups({"assignmentLog"})
	 * @Groups({"clockMomentLog"})
	 *
	 * @ORM\Column(name="id", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="IDENTITY")
	 */
	private $id;

	/**
	 * @var string
	 *
	 * @Groups({"employees", "offices/*", "departments"})
	 * @Groups({"assignments"})
	 * @Groups({"registers"})
	 * @Groups({"bulletins"})
	 * @Groups({"shiftSwapRequests"})
	 * @Groups({"clockMoments"})
	 * @Groups({"clients"})
	 *
	 * @ORM\Column(name="name", type="string")
	 */
	private $name;

	/**
	 * Virtual property that stores the flattened department hierarchy name.
	 *
	 * @var string
	 */
	private $flattenedName = null;

	/**
	 * Many Departments have One Office.
	 * TODO activate ORM\ManyToOne(targetEntity="Office", inversedBy="departments")
	 * TODO activate ORM\JoinColumn(name="office_id", referencedColumnName="id").
	 */
	private $office;

	/**
	 * Virtual property that indicates whether or not an Department/Office is a Headquarter.
	 *
	 * @var bool
	 */
	private $isHeadquarter = false;

	/**
	 * @Gedmo\TreeLeft
	 * @ORM\Column(name="lft", type="integer")
	 */
	private $lft;

	/**
	 * @Gedmo\TreeLevel
	 * @ORM\Column(name="lvl", type="integer")
	 */
	private $lvl;

	/**
	 * @Gedmo\TreeRight
	 * @ORM\Column(name="rgt", type="integer")
	 */
	private $rgt;

	/**
	 * @Gedmo\TreeRoot
	 * @ORM\ManyToOne(targetEntity="Department")
	 * @ORM\JoinColumn(name="root", referencedColumnName="id", onDelete="CASCADE")
	 */
	private $root;

	/**
	 * @MaxDepth(1)
	 * @Groups({"employees"})
	 * @Groups({"registers"})
	 * @Groups({"assignments"})
	 *
	 * @Gedmo\TreeParent
	 * @ORM\ManyToOne(targetEntity="Department", inversedBy="children")
	 * @ORM\JoinColumn(name="proper_parent_id", referencedColumnName="id", onDelete="CASCADE")
	 */
	private $parent;

	/**
	 * @Groups({"departments"})
	 * @MaxDepth(10)
	 * @ORM\OneToMany(targetEntity="Department", mappedBy="parent")
	 * @ORM\OrderBy({"lft" = "ASC"})
	 */
	private $children;

	/**
	 * One Department has Many Assignments.
	 *
	 * @ORM\OneToMany(targetEntity="Assignment", mappedBy="department")
	 */
	private $assignments;

	/**
	 * One Department has Many Bulletins.
	 *
	 * @ORM\OneToMany(targetEntity="Bulletin", mappedBy="department")
	 */
	private $bulletins;

	/**
	 * One Department has Many ClockMoments.
	 *
	 * @ORM\OneToMany(targetEntity="ClockMoment", mappedBy="department")
	 */
	private $clockMoments;

	/**
	 * One Department has Many ClockIntervals.
	 *
	 * @ORM\OneToMany(targetEntity="ClockInterval", mappedBy="department")
	 */
	private $clockIntervals;

	/**
	 * One Department has Many AccessControlItems.
	 *
	 * @ORM\OneToMany(targetEntity="AccessControlItem", mappedBy="department")
	 */
	private $accessControlItems;

	/**
	 * Many Departments have Many Clients.
	 *
	 * @ORM\ManyToMany(targetEntity="Client", mappedBy="departments")
	 */
	private $clients;

	/**
	 * Many Departments have Many Employees.
	 *
	 * @ORM\ManyToMany(targetEntity="Employee", mappedBy="departments")
	 */
	private $employees;

	/**
	 * Many Departments have Many Clients.
	 *
	 * @ORM\ManyToMany(targetEntity="Client", mappedBy="departments_secondment")
	 */
	private $clientsSecondment;

	/**
	 * Department constructor.
	 */
	public function __construct()
	{
		$this->children = new ArrayCollection();
		$this->assignments = new ArrayCollection();
		$this->clients = new ArrayCollection();
		$this->clientsSecondment = new ArrayCollection();
		$this->employees = new ArrayCollection();
		$this->bulletins = new ArrayCollection();
		$this->clockMoments = new ArrayCollection();
		$this->clockIntervals = new ArrayCollection();
		$this->accessControlItems = new ArrayCollection();
	}

	/**
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * @param string $name
	 */
	public function setName($name)
	{
		$this->name = $name;
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
	 * @param int $id
	 */
	public function setId($id)
	{
		$this->id = $id;
	}

	/**
	 * Set lft.
	 *
	 * @param int $lft
	 *
	 * @return Department
	 */
	public function setLft($lft)
	{
		$this->lft = $lft;

		return $this;
	}

	/**
	 * Get lft.
	 *
	 * @return int
	 */
	public function getLft()
	{
		return $this->lft;
	}

	/**
	 * Set lvl.
	 *
	 * @param int $lvl
	 *
	 * @return Department
	 */
	public function setLvl($lvl)
	{
		$this->lvl = $lvl;

		return $this;
	}

	/**
	 * Get lvl.
	 *
	 * @return int
	 */
	public function getLvl()
	{
		return $this->lvl;
	}

	/**
	 * Set rgt.
	 *
	 * @param int $rgt
	 *
	 * @return Department
	 */
	public function setRgt($rgt)
	{
		$this->rgt = $rgt;

		return $this;
	}

	/**
	 * Get rgt.
	 *
	 * @return int
	 */
	public function getRgt()
	{
		return $this->rgt;
	}

	/**
	 * Set root.
	 *
	 * @param \AppBundle\Entity\Department $root
	 *
	 * @return Department
	 */
	public function setRoot(self $root = null)
	{
		$this->root = $root;

		return $this;
	}

	/**
	 * Get root.
	 *
	 * @return \AppBundle\Entity\Department
	 */
	public function getRoot()
	{
		return $this->root;
	}

	/**
	 * Set parent.
	 *
	 * @param \AppBundle\Entity\Department $parent
	 *
	 * @return Department
	 */
	public function setParent(self $parent = null)
	{
		$this->parent = $parent;

		return $this;
	}

	/**
	 * Get parent.
	 *
	 * @return \AppBundle\Entity\Department
	 */
	public function getParent()
	{
		return $this->parent;
	}

	/**
	 * Add child.
	 *
	 * @param \AppBundle\Entity\Department $child
	 *
	 * @return Department
	 */
	public function addChild(self $child)
	{
		$this->children[] = $child;

		return $this;
	}

	/**
	 * Remove child.
	 *
	 * @param \AppBundle\Entity\Department $child
	 */
	public function removeChild(self $child)
	{
		$this->children->removeElement($child);
	}

	/**
	 * Get children.
	 *
	 * @return ArrayCollection
	 */
	public function getChildren()
	{
		return $this->children;
	}

	/**
	 * Set office.
	 *
	 * @param \AppBundle\Entity\Office $office
	 *
	 * @return Department
	 */
	public function setOffice(Office $office = null)
	{
		$this->office = $office;

		return $this;
	}

	/**
	 * Get office.
	 *
	 * @return \AppBundle\Entity\Office
	 */
	public function getOffice()
	{
		return $this->office;
	}

	/**
	 * Add assignment.
	 *
	 * @param \AppBundle\Entity\Assignment $assignment
	 *
	 * @return Department
	 */
	public function addAssignment(Assignment $assignment)
	{
		$this->assignments[] = $assignment;

		return $this;
	}

	/**
	 * Remove assignment.
	 *
	 * @param \AppBundle\Entity\Assignment $assignment
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
	 * @return mixed
	 */
	public function getClients()
	{
		return $this->clients;
	}

	/**
	 * @return mixed
	 */
	public function getClientsSecondment()
	{
		return $this->clientsSecondment;
	}

	/**
	 * @param mixed $clientsSecondment
	 */
	public function setClientsSecondment($clientsSecondment)
	{
		$this->clientsSecondment = $clientsSecondment;
	}

	/**
	 * Add client.
	 *
	 * @param Client $client
	 *
	 * @return Department
	 */
	public function addClient(Client $client)
	{
		$this->clients[] = $client;

		return $this;
	}

	/**
	 * Remove client.
	 *
	 * @param Client $client
	 */
	public function removeClient(Client $client)
	{
		$this->clients->removeElement($client);
	}

	/**
	 * Add employee.
	 *
	 * @param Employee $employee
	 *
	 * @return Department
	 */
	public function addEmployee(Employee $employee)
	{
		$this->employees[] = $employee;

		return $this;
	}

	/**
	 * Remove employee.
	 *
	 * @param Employee $employee
	 */
	public function removeEmployee(Employee $employee)
	{
		$this->employees->removeElement($employee);
	}

	/**
	 * Get employees.
	 *
	 * @return ArrayCollection
	 */
	public function getEmployees()
	{
		return $this->employees;
	}

	/**
	 * Add clientsSecondment.
	 *
	 * @param Client $clientsSecondment
	 *
	 * @return Department
	 */
	public function addClientsSecondment(Client $clientsSecondment)
	{
		$this->clientsSecondment[] = $clientsSecondment;

		return $this;
	}

	/**
	 * Remove clientsSecondment.
	 *
	 * @param Client $clientsSecondment
	 */
	public function removeClientsSecondment(Client $clientsSecondment)
	{
		$this->clientsSecondment->removeElement($clientsSecondment);
	}

	/**
	 * Add bulletin.
	 *
	 * @param Bulletin $bulletin
	 *
	 * @return Department
	 */
	public function addBulletin(Bulletin $bulletin)
	{
		$this->bulletins[] = $bulletin;

		return $this;
	}

	/**
	 * Remove bulletin.
	 *
	 * @param Bulletin $bulletin
	 */
	public function removeBulletin(Bulletin $bulletin)
	{
		$this->bulletins->removeElement($bulletin);
	}

	/**
	 * Get bulletins.
	 *
	 * @return ArrayCollection
	 */
	public function getBulletins()
	{
		return $this->bulletins;
	}

	/**
	 * Add clockMoment.
	 *
	 * @param ClockMoment $clockMoment
	 *
	 * @return Department
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
	 * @return Department
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
	 * @return Department
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

	/**
	 * Set isHeadquarter.
	 *
	 * @param bool $isHeadquarter
	 *
	 * @return Department
	 */
	public function setIsHeadquarter($isHeadquarter)
	{
		$this->isHeadquarter = $isHeadquarter;

		return $this;
	}

	/**
	 * Get isHeadquarter.
	 *
	 * @return bool
	 */
	public function getIsHeadquarter()
	{
		return $this->isHeadquarter;
	}

	/**
	 * Set flattenedName.
	 *
	 * @param string $flattenedName
	 *
	 * @return Department
	 */
	public function setFlattenedName($flattenedName)
	{
		$this->flattenedName = $flattenedName;

		return $this;
	}

	/**
	 * Get flattenedName.
	 *
	 * @Groups({"employees", "offices/*", "departments"})
	 * @Groups({"assignments"})
	 * @Groups({"registers"})
	 * @Groups({"bulletins"})
	 * @Groups({"shiftSwapRequests"})
	 * @Groups({"clockMoments"})
	 * @Groups({"clients"})
	 *
	 * @return string
	 */
	public function getFlattenedName()
	{
		// TODO TEST and see if set Department to null gives conflict with current CompanyX code base during live testing
		$departmentParent = $this->getParent();
		$flattenedNameString = $this->getName();
		while ($departmentParent) {
			$flattenedNameString = $departmentParent->getName().' > '.$flattenedNameString;
			$departmentParent = $departmentParent->getParent();
		}

		return $flattenedNameString;
	}

	/**
	 * Get flattenedName without office name.
	 *
	 * @Groups({"assignments"})
	 * @Groups({"clients"})
	 *
	 * @return string
	 */
	public function getFlattenedNameWithoutOffice()
	{
		$departmentParent = $this->getParent();
		$flattenedNameString = $this->getName();
		while ($departmentParent) {
			if ($departmentParent->getParent()) {
				$flattenedNameString = $departmentParent->getName().' > '.$flattenedNameString;
			}
			$departmentParent = $departmentParent->getParent();
		}

		return $flattenedNameString;
	}

	/**
	 * Get the ID of the root department.
	 *
	 * @Groups({"clients"})
	 * @Groups({"assignments"})
	 *
	 * @return int
	 */
	public function getOfficeId()
	{
		return $this->getRoot()->getId();
	}
}
