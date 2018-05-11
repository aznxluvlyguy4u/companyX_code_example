<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * TODO Rename all column names AND reevaluate the necessity of this
 * Class UserRole.
 *
 * @Gedmo\Tree(type="nested")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\UserRoleRepository")
 * @ORM\Table(name="auth_permissions")
 */
class UserRole
{
	/**
	 * @var int
	 *
	 * @Groups({"clients"})
	 *
	 * @ORM\Column(name="id", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="IDENTITY")
	 */
	private $id;

	/**
	 * @ORM\Column(name="name", type="string")
	 * @Groups({"clients"})
	 */
	private $name;

	/**
	 * @ORM\Column(name="task", type="string")
	 */
	private $task;

	//TODO Rename column to default during DB switch
	/**
	 * @var string
	 * @ORM\Column(name="object", type="string")
	 */
	private $identifierString;

	/**
	 * @ORM\Column(name="parent_id", type="integer")
	 */
	private $parent_id;

	/**
	 * @ORM\Column(name="system", type="string")
	 */
	private $system;

	/**
	 * One UserRole has Many PagePermissions.
	 *
	 * @ORM\OneToMany(targetEntity="PagePermission", mappedBy="userRole")
	 */
	private $pagePermissions;

	/**
	 * Many Groups have Many Users.
	 *
	 * @ORM\ManyToMany(targetEntity="Client", mappedBy="userroles")
	 */
	private $clients;

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
	 * @ORM\ManyToOne(targetEntity="UserRole")
	 * @ORM\JoinColumn(name="root", referencedColumnName="id", onDelete="CASCADE")
	 */
	private $root;

	/**
	 * @Gedmo\TreeParent
	 * @ORM\ManyToOne(targetEntity="UserRole", inversedBy="children")
	 * @ORM\JoinColumn(name="proper_parent_id", referencedColumnName="id", onDelete="CASCADE")
	 */
	private $parent;

	/**
	 * @ORM\OneToMany(targetEntity="UserRole", mappedBy="parent")
	 * @ORM\OrderBy({"lft" = "ASC"})
	 */
	private $children;

	public function __construct()
	{
		$this->pagePermissions = new ArrayCollection();
		$this->clients = new ArrayCollection();
		$this->children = new ArrayCollection();
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
	 * Set name.
	 *
	 * @param string $name
	 *
	 * @return UserRole
	 */
	public function setName($name)
	{
		$this->name = $name;

		return $this;
	}

	/**
	 * Get name.
	 *
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Set task.
	 *
	 * @param string $task
	 *
	 * @return UserRole
	 */
	public function setTask($task)
	{
		$this->task = $task;

		return $this;
	}

	/**
	 * Get task.
	 *
	 * @return string
	 */
	public function getTask()
	{
		return $this->task;
	}

	/**
	 * Set identifierString.
	 *
	 * @param string $identifierString
	 *
	 * @return UserRole
	 */
	public function setIdentifierString($identifierString)
	{
		$this->identifierString = $identifierString;

		return $this;
	}

	/**
	 * Get identifierString.
	 *
	 * @return string
	 */
	public function getIdentifierString()
	{
		return $this->identifierString;
	}

	/**
	 * Set parentId.
	 *
	 * @param int $parentId
	 *
	 * @return UserRole
	 */
	public function setParentId($parentId)
	{
		$this->parent_id = $parentId;

		return $this;
	}

	/**
	 * Get parentId.
	 *
	 * @return int
	 */
	public function getParentId()
	{
		return $this->parent_id;
	}

	/**
	 * Set system.
	 *
	 * @param string $system
	 *
	 * @return UserRole
	 */
	public function setSystem($system)
	{
		$this->system = $system;

		return $this;
	}

	/**
	 * Get system.
	 *
	 * @return string
	 */
	public function getSystem()
	{
		return $this->system;
	}

	/**
	 * Add pagePermission.
	 *
	 * @param PagePermission $pagePermission
	 *
	 * @return UserRole
	 */
	public function addPagePermission(PagePermission $pagePermission)
	{
		$this->pagePermissions[] = $pagePermission;

		return $this;
	}

	/**
	 * Remove pagePermission.
	 *
	 * @param PagePermission $pagePermission
	 */
	public function removePagePermission(PagePermission $pagePermission)
	{
		$this->pagePermissions->removeElement($pagePermission);
	}

	/**
	 * Get pagePermissions.
	 *
	 * @return ArrayCollection
	 */
	public function getPagePermissions()
	{
		return $this->pagePermissions;
	}

	/**
	 * Add client.
	 *
	 * @param Client $client
	 *
	 * @return UserRole
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
	 * Get clients.
	 *
	 * @return ArrayCollection
	 */
	public function getClients()
	{
		return $this->clients;
	}

	/**
	 * Set lft.
	 *
	 * @param int $lft
	 *
	 * @return UserRole
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
	 * @return UserRole
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
	 * @return UserRole
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
	 * @param UserRole $root
	 *
	 * @return UserRole
	 */
	public function setRoot(self $root = null)
	{
		$this->root = $root;

		return $this;
	}

	/**
	 * Get root.
	 *
	 * @return UserRole
	 */
	public function getRoot()
	{
		return $this->root;
	}

	/**
	 * Set parent.
	 *
	 * @param UserRole $parent
	 *
	 * @return UserRole
	 */
	public function setParent(self $parent = null)
	{
		$this->parent = $parent;

		return $this;
	}

	/**
	 * Get parent.
	 *
	 * @return UserRole
	 */
	public function getParent()
	{
		return $this->parent;
	}

	/**
	 * Add child.
	 *
	 * @param UserRole $child
	 *
	 * @return UserRole
	 */
	public function addChild(self $child)
	{
		$this->children[] = $child;

		return $this;
	}

	/**
	 * Remove child.
	 *
	 * @param UserRole $child
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
}
