<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;

//TODO Office is currently a virtual entity as a standalone office table doesn't exist. It refers to a record in the Department (dor_object) table. Fix this during DB switch
/**
 * Class Office.
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\OfficeRepository")
 */
class Office
{
	/**
	 * @var int
	 *
	 * @Groups({"offices"})
	 * @Groups({"departments"})
	 * @Groups({"clients"})
	 * @ORM\Column(name="id", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="IDENTITY")
	 */
	private $id;

	/**
	 * @var string
	 *
	 * @Groups({"offices"})
	 * @Groups({"departments"})
	 * @Groups({"clients"})
	 */
	private $name;

	/**
	 * Many Offices have One Headqurter.
	 *
	 * @ORM\ManyToOne(targetEntity="Headquarter", inversedBy="offices")
	 * @ORM\JoinColumn(name="headquarter_id", referencedColumnName="id")
	 */
	private $headquarter;

	/**
	 * Virtual property that indicates whether or not an Office is a Headquarter.
	 *
	 * @var bool
	 *
	 * @Groups({"offices"})
	 * @Groups({"departments"})
	 */
	private $isHeadquarter;

	/**
	 * One Office has One Department Rootnode.
	 *
	 * @var Department
	 * @ORM\OneToOne(targetEntity="Department")
	 * @ORM\JoinColumn(name="department_root_id", referencedColumnName="id")
	 */
	private $departmentRoot;

	/**
	 * One Office has Many Departments.
	 *
	 * @MaxDepth(1)
	 * @Groups({"departments"})
	 * @Groups({"offices/*"})
	 * TODO ORM\OneToMany(targetEntity="Department", mappedBy="office")
	 */
	private $departments;

	/**
	 * Office constructor.
	 */
	public function __construct()
	{
		$this->departments = new ArrayCollection();
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
	 * @return mixed
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * @param mixed $name
	 */
	public function setName($name)
	{
		$this->name = $name;
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
	 * Add department.
	 *
	 * @param Department $department
	 *
	 * @return $this
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

	//TODO This is only to make top level  possible

	/**
	 * Add office.
	 *
	 * @param Office $office
	 *
	 * @return $this
	 */
	public function addOffice(self $office)
	{
		$this->departments[] = $office;

		return $this;
	}

	//TODO This is only to make top level  possible

	/**
	 * Remove office.
	 *
	 * @param Office $office
	 */
	public function removeOffice(self $office)
	{
		$this->departments->removeElement($office);
	}

	/**
	 * Set departmentRoot.
	 *
	 * @param Department $departmentRoot
	 *
	 * @return Office
	 */
	public function setDepartmentRoot(Department $departmentRoot = null)
	{
		$this->departmentRoot = $departmentRoot;

		return $this;
	}

	/**
	 * Get departmentRoot.
	 *
	 * @return \AppBundle\Entity\Department
	 */
	public function getDepartmentRoot()
	{
		return $this->departmentRoot;
	}

	/**
	 * Set headquarter.
	 *
	 * @param Headquarter $headquarter
	 *
	 * @return Office
	 */
	public function setHeadquarter(Headquarter $headquarter = null)
	{
		$this->headquarter = $headquarter;

		return $this;
	}

	/**
	 * Get headquarter.
	 *
	 * @return Headquarter
	 */
	public function getHeadquarter()
	{
		return $this->headquarter;
	}

	/**
	 * Set isHeadquarter.
	 *
	 * @param bool $isHeadquarter
	 *
	 * @return Office
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
		return $this->departmentRoot->getIsHeadquarter();
	}
}
