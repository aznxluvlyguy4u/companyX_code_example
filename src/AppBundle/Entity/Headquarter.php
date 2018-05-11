<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

//TODO Headquarter is currently a virtual entity as a standalone headquarter table doesn't exist. It refers to a record in the Department (dor_object) table. Fix this during DB switch
/**
 * Headquarter.
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\HeadquarterRepository")
 */
class Headquarter
{
	/**
	 * @var int
	 *
	 * @Groups({"headquarters"})
	 *
	 * @ORM\Column(name="id", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="AUTO")
	 */
	private $id;

	/**
	 * @var string
	 * @Groups({"headquarters"})
	 */
	private $name;

	/**
	 * One Headquarter has One Department Rootnode.
	 *
	 * @ORM\OneToOne(targetEntity="Department")
	 * @ORM\JoinColumn(name="department_root_id", referencedColumnName="id")
	 */
	private $departmentRoot;

	/**
	 * One Headquarter has Many Offices.
	 *
	 * @ORM\OneToMany(targetEntity="Office", mappedBy="headquarter")
	 */
	private $offices;

	/**
	 * Office constructor.
	 */
	public function __construct()
	{
		$this->offices = new ArrayCollection();
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
	 * Set departmentRoot.
	 *
	 * @param Department $departmentRoot
	 *
	 * @return Headquarter
	 */
	public function setDepartmentRoot(Department $departmentRoot = null)
	{
		$this->departmentRoot = $departmentRoot;

		return $this;
	}

	/**
	 * Get departmentRoot.
	 *
	 * @return Department
	 */
	public function getDepartmentRoot()
	{
		return $this->departmentRoot;
	}

	/**
	 * Add office.
	 *
	 * @param Office $office
	 *
	 * @return Headquarter
	 */
	public function addOffice(Office $office)
	{
		$this->offices[] = $office;

		return $this;
	}

	/**
	 * Remove office.
	 *
	 * @param Office $office
	 */
	public function removeOffice(Office $office)
	{
		$this->offices->removeElement($office);
	}

	/**
	 * Get offices.
	 *
	 * @return ArrayCollection
	 */
	public function getOffices()
	{
		return $this->offices;
	}
}
