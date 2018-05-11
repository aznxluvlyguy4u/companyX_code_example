<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * TODO Rename all column names AND reevaluate the necessity of this
 * Class UserRole.
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\PagePermissionRepository")
 * @ORM\Table(name="auth_page_perm")
 */
class PagePermission
{
	/**
	 * @var int
	 *
	 * @ORM\Column(name="id", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="IDENTITY")
	 */
	private $id;

	/**
	 * @ORM\Column(name="shpage_id", type="integer")
	 */
	private $shpageId;

	/**
	 * @ORM\Column(name="shpage_mask", type="string")
	 */
	private $shpageMask;

	/**
	 * Many PagePermissions have One UserRole.
	 *
	 * @ORM\ManyToOne(targetEntity="UserRole", inversedBy="pagePermissions")
	 * @ORM\JoinColumn(name="perm_id", referencedColumnName="id")
	 */
	private $userRole;

	/**
	 * @ORM\Column(name="_original", type="string")
	 */
	private $original;

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
	 * Set shpageId.
	 *
	 * @param int $shpageId
	 *
	 * @return PagePermission
	 */
	public function setShpageId($shpageId)
	{
		$this->shpageId = $shpageId;

		return $this;
	}

	/**
	 * Get shpageId.
	 *
	 * @return int
	 */
	public function getShpageId()
	{
		return $this->shpageId;
	}

	/**
	 * Set shpageMask.
	 *
	 * @param string $shpageMask
	 *
	 * @return PagePermission
	 */
	public function setShpageMask($shpageMask)
	{
		$this->shpageMask = $shpageMask;

		return $this;
	}

	/**
	 * Get shpageMask.
	 *
	 * @return string
	 */
	public function getShpageMask()
	{
		return $this->shpageMask;
	}

	/**
	 * Set original.
	 *
	 * @param string $original
	 *
	 * @return PagePermission
	 */
	public function setOriginal($original)
	{
		$this->original = $original;

		return $this;
	}

	/**
	 * Get original.
	 *
	 * @return string
	 */
	public function getOriginal()
	{
		return $this->original;
	}

	/**
	 * Set userRole.
	 *
	 * @param UserRole $userRole
	 *
	 * @return PagePermission
	 */
	public function setUserRole(UserRole $userRole = null)
	{
		$this->userRole = $userRole;

		return $this;
	}

	/**
	 * Get userRole.
	 *
	 * @return \AppBundle\Entity\UserRole
	 */
	public function getUserRole()
	{
		return $this->userRole;
	}
}
