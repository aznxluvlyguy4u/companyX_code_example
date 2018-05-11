<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Legacy\LegacyAccessControlItem;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;
use Doctrine\Common\Collections\ArrayCollection;

// TODO change table name to default after DB switch
// TODO change all column names to default after DB switch
/**
 * AccessControlItem.
 *
 * @ORM\Table(name="auth_acl")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\AccessControlItemRepository")
 * @ORM\EntityListeners({"AppBundle\EventListener\AccessControlItemListener"})
 */
class AccessControlItem extends LegacyAccessControlItem
{
	/**
	 * @var int
	 *
	 * @Groups({"clients"})
	 *
	 * @ORM\Column(name="id", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="AUTO")
	 */
	private $id;

	/**
	 * @var \DateTime
	 *
	 * @Gedmo\Timestampable(on="create")
	 * @ORM\Column(name="created", type="datetime")
	 * @Assert\NotBlank()
	 * @Assert\DateTime()
	 */
	private $created;

	//TODO Rename column to default during DB switch
	/**
	 * Many AccessControlItems belong to One Employee.
	 *
	 * @var Employee
	 *
	 * @ORM\ManyToOne(targetEntity="Employee", inversedBy="accessControlItems")
	 * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
	 * @Assert\NotBlank()
	 * @Assert\Valid
	 */
	private $employee;

	//TODO Rename column to default during DB switch
	//TODO Many accessControlItems belong to One UserRole relation ship exist, but the current CompanyX DB uses a unique string as foreign key which Doctrine doesn't support.
	//TODO AS THIS CAN EASILY USE A NORMAL FOREIGN KEY ID INSTEAD WTF?
	//TODO Ask if its possible to use UserRole Id as foreignkey instead and add column to table auth_acl
	/**
	 * Many accessControlItems belong to One UserRole.
	 *
	 * @var string
	 *
	 * @ORM\Column(name="aco", type="string")
	 * @Assert\NotBlank()
	 */
	private $userRoleIdentifierString;

	//TODO Rename column to default during DB switch
	/**
	 * Many accessControlItems belong to One Department.
	 *
	 * @var Department
	 *
	 * @Groups({"clients"})
	 *
	 * @ORM\ManyToOne(targetEntity="Department", inversedBy="accessControlItems")
	 * @ORM\JoinColumn(name="aco_id", referencedColumnName="id")
	 * @Assert\NotBlank()
	 * @Assert\Valid
	 */
	private $department;

	/**
	 * Many accessControlItems belong to One Client.
	 *
	 * @var Client
	 *
	 * @ORM\ManyToOne(targetEntity="Client", inversedBy="accessControlItems")
	 * @ORM\JoinColumn(name="created_by", referencedColumnName="id")
	 * @Assert\Valid
	 */
	private $createdBy;

	//TODO Ask CompanyX team to change the way access to all departments to save seperate record to each department in auth_acl
	/**
	 * Virtual property to keep track of all departments if department = *
	 * One AccessControlItem has Many Departments.
	 */
	private $departments;

	public function __construct()
	{
		$this->departments = new ArrayCollection();
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
	 * Set created.
	 *
	 * @param \DateTime $created
	 *
	 * @return AccessControlItem
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
	 * @return AccessControlItem
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
	 * @return AccessControlItem
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

	/**
	 * Set createdBy.
	 *
	 * @param Client $createdBy
	 *
	 * @return AccessControlItem
	 */
	public function setCreatedBy(Client $createdBy = null)
	{
		$this->createdBy = $createdBy;

		return $this;
	}

	/**
	 * Get createdBy.
	 *
	 * @return Client
	 */
	public function getCreatedBy()
	{
		return $this->createdBy;
	}

	/**
	 * Set userRoleIdentifierString.
	 *
	 * @param string $userRoleIdentifierString
	 *
	 * @return AccessControlItem
	 */
	public function setUserRoleIdentifierString($userRoleIdentifierString = null)
	{
		$this->userRoleIdentifierString = $userRoleIdentifierString;

		return $this;
	}

	/**
	 * Get userRoleIdentifierString.
	 *
	 * @return string
	 */
	public function getUserRoleIdentifierString()
	{
		return $this->userRoleIdentifierString;
	}

	/**
	 * Add department.
	 *
	 * @param Department $department
	 *
	 * @return AccessControlItem
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
	 * Set departments.
	 *
	 * @param $departments
	 *
	 * @return AccessControlItem
	 */
	public function setDepartments($departments)
	{
		$this->departments = $departments;

		return $this;
	}
}
