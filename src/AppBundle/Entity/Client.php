<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Security\Core\User\UserInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use AppBundle\Enumerator\CompanyXUserRole;

/**
 * Class Client.
 *
 * TODO Rename column names to default names during DB switch
 *
 * @ORM\Table(name="dor_user_profile")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\ClientRepository")
 * @ORM\HasLifecycleCallbacks
 * @ORM\EntityListeners({"AppBundle\EventListener\ClientListener"})
 */
class Client implements UserInterface
{
	/**
	 * @Groups({"clients"})
	 * @Groups({"clockMoments"})
	 * @Groups({"registerLog"})
	 * @Groups({"employeeLog"})
	 * @Groups({"clockMomentLog"})
	 *
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="AUTO")
	 * @ORM\Column(type="integer")
	 */
	private $id;

	/**
	 * @var string
	 *
	 * @Groups({"clients"})
	 * TODO: rename database column to user_name. This will happen when we auto generate the database.
	 * @ORM\Column(name="username", type="string")
	 * @ORM\Column(type="string", unique=true)
	 */
	private $username;

	/**
	 * @ORM\Column(type="string", length=64, nullable=false)
	 */
	private $password;

	/**
	 * @var string
	 *
	 * @Groups({"clients"})
	 * @Groups({"clockMoments"})
	 *
	 * @ORM\Column(name="voornaam", type="string")
	 */
	private $firstname;

	/**
	 * @var string
	 *
	 * @Groups({"clients"})
	 * @Groups({"clockMoments"})
	 *
	 * @ORM\Column(name="geboortenaam", type="string")
	 */
	private $lastname;

	/**
	 * @var string
	 *
	 * @Groups({"clients"})
	 * @Groups({"clockMoments"})
	 *
	 * @ORM\Column(name="tussenvoegsel", type="string")
	 */
	private $insertion;

	/**
	 * Temporary property used in the solution to fetch the avatar
	 * TODO Reevaluate if this can be removed later on.
	 *
	 * @var int
	 * @ORM\Column(name="user_id", type="integer")
	 */
	private $userId;

	/** @var string */
	private $entityManagerIdentifier;

	// TODO: Temporary solution to store the CompanyX session ID
	/** @var int */
	private $sessionId = null;

	/**
	 * Many Clients have Many Departments.
	 *
	 * @ORM\ManyToMany(targetEntity="Department", inversedBy="clients")
	 * @ORM\JoinTable(name="offices_users",
	 *      joinColumns={@ORM\JoinColumn(name="user_id", referencedColumnName="user_id")},
	 *      inverseJoinColumns={@ORM\JoinColumn(name="office_id", referencedColumnName="id", unique=true)})
	 */
	private $departments;

	/**
	 * Many Clients have Many Departments .
	 *
	 * @ORM\ManyToMany(targetEntity="Department", inversedBy="clients_secondment")
	 * @ORM\JoinTable(name="offices_users_detached",
	 *      joinColumns={@ORM\JoinColumn(name="user_id", referencedColumnName="user_id")},
	 *      inverseJoinColumns={@ORM\JoinColumn(name="office_id", referencedColumnName="id")})
	 */
	private $departments_secondment;

	/**
	 * One Client has One Employee.
	 *
	 * @var Employee
	 *
	 * @Groups({"clients"})
	 * @ORM\OneToOne(targetEntity="Employee")
	 * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
	 */
	private $employee;

	/**
	 * Many Clients have Many UserRoles.
	 *
	 * @ORM\ManyToMany(targetEntity="UserRole", inversedBy="clients")
	 * @ORM\JoinTable(name="auth_users_groups_perms",
	 *      joinColumns={@ORM\JoinColumn(name="foreign_key", referencedColumnName="user_id")},
	 *      inverseJoinColumns={@ORM\JoinColumn(name="perm_id", referencedColumnName="id")})
	 */
	private $userRoles;

	/**
	 * Virtual property to keep track of the roles conforming the Symfony security naming standards from converted UserRoles.
	 *
	 * @var array
	 */
	private $roles;

	/**
	 * @var string
	 */
	protected $apiKey;

	/**
	 * One Client has Many Registers.
	 *
	 * @ORM\OneToMany(targetEntity="Register", mappedBy="modifiedBy")
	 */
	private $registers;

	/**
	 * One Client has Many Bulletins.
	 *
	 * @ORM\OneToMany(targetEntity="Bulletin", mappedBy="modifiedBy")
	 */
	private $bulletins;

	/**
	 * One Client has Many ClockMoments.
	 *
	 * @ORM\OneToMany(targetEntity="ClockMoment", mappedBy="modifiedBy")
	 */
	private $clockMoments;

	/**
	 * One Client has Many AccessControlItems.
	 *
	 * @ORM\OneToMany(targetEntity="AccessControlItem", mappedBy="createdBy")
	 */
	private $accessControlItems;

	/**
	 * @Groups({"clients"})
	 *
	 * Virtual Property accessControlList to compile a list of roles and departments
	 *
	 * @var array
	 */
	private $accessControlList;

	public function __construct()
	{
		$this->departments = new ArrayCollection();
		$this->departments_secondment = new ArrayCollection();
		$this->registers = new ArrayCollection();
		$this->userRoles = new ArrayCollection();
		$this->bulletins = new ArrayCollection();
		$this->clockMoments = new ArrayCollection();
		$this->accessControlItems = new ArrayCollection();
	}

	/**
	 * @Groups({"clients"})
	 * Returns the roles granted to the user.
	 *
	 * <code>
	 * public function getRoles()
	 * {
	 *     return array('ROLE_USER');
	 * }
	 * </code>
	 *
	 * Alternatively, the roles might be stored on a ``roles`` property,
	 * and populated in any number of different ways when the user object
	 * is created.
	 *
	 * @return array (CompanyXUserRole|string)[] The user roles
	 */
	public function getRoles()
	{
		return $this->roles;
	}

	/**
	 * Set roles.
	 *
	 * @param array $roles
	 *
	 * @return $this
	 */
	public function setRoles(array $roles)
	{
		$this->roles = $roles;

		return $this;
	}

	/**
	 * Check if user has a certain role.
	 *
	 * @param string $role
	 *
	 * @return bool
	 */
	public function hasRole($role)
	{
		return in_array($role, $this->getRoles());
	}

	/**
	 * Returns the salt that was originally used to encode the password.
	 *
	 * This can return null if the password was not encoded using a salt.
	 *
	 * @return string|null The salt
	 */
	public function getSalt()
	{
		return null;
	}

	/**
	 * Removes sensitive data from the user.
	 *
	 * This is important if, at any given point, sensitive information like
	 * the plain-text password is stored on this object.
	 */
	public function eraseCredentials()
	{
		return null;
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
	 * @return Client
	 */
	public function setId($id)
	{
		$this->id = $id;

		return $this;
	}

	/**
	 * Returns the username used to authenticate the user.
	 *
	 * @return string The username
	 */
	public function getUsername()
	{
		return $this->username;
	}

	/**
	 * Set username.
	 *
	 * @param string $username
	 *
	 * @return Client
	 */
	public function setUsername($username)
	{
		$this->username = $username;

		return $this;
	}

	/**
	 * Returns the password used to authenticate the user.
	 *
	 * This should be the encoded password. On authentication, a plain-text
	 * password will be salted, encoded, and then compared to this value.
	 *
	 * @return string The password
	 */
	public function getPassword()
	{
		return $this->password;
	}

	/**
	 * Set password.
	 *
	 * @param string $password
	 *
	 * @return Client
	 */
	public function setPassword($password)
	{
		$this->password = $password;

		return $this;
	}

	/**
	 * Set firstname.
	 *
	 * @param string $firstname
	 *
	 * @return Client
	 */
	public function setFirstname($firstname)
	{
		$this->firstname = $firstname;

		return $this;
	}

	/**
	 * Get firstname.
	 *
	 * @return string
	 */
	public function getFirstname()
	{
		return $this->firstname;
	}

	/**
	 * Set lastname.
	 *
	 * @param string $lastname
	 *
	 * @return Client
	 */
	public function setLastname($lastname)
	{
		$this->lastname = $lastname;

		return $this;
	}

	/**
	 * Get lastname.
	 *
	 * @return string
	 */
	public function getLastname()
	{
		return $this->lastname;
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
	 * Set sessionId.
	 *
	 * @param int $sessionId
	 *
	 * @return Client
	 */
	public function setSessionId(int $sessionId)
	{
		$this->sessionId = $sessionId;

		return $this;
	}

	/**
	 * Set insertion.
	 *
	 * @param string $insertion
	 *
	 * @return Client
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
	 * Set userId.
	 *
	 * @param int $userId
	 *
	 * @return Client
	 */
	public function setUserId($userId)
	{
		$this->userId = $userId;

		return $this;
	}

	/**
	 * Get userId.
	 *
	 * @return int
	 */
	public function getUserId()
	{
		return $this->userId;
	}

	/**
	 * Add department.
	 *
	 * @param Department $department
	 *
	 * @return Client
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
	 * Add departmentsSecondment.
	 *
	 * @param Department $departmentsSecondment
	 *
	 * @return Client
	 */
	public function addDepartmentsSecondment(Department $departmentsSecondment)
	{
		$this->departments_secondment[] = $departmentsSecondment;

		return $this;
	}

	/**
	 * Remove departmentsSecondment.
	 *
	 * @param Department $departmentsSecondment
	 */
	public function removeDepartmentsSecondment(Department $departmentsSecondment)
	{
		$this->departments_secondment->removeElement($departmentsSecondment);
	}

	/**
	 * Get departmentsSecondment.
	 *
	 * @return ArrayCollection
	 */
	public function getDepartmentsSecondment()
	{
		return $this->departments_secondment;
	}

	/**
	 * Set employee.
	 *
	 * @param Employee $employee
	 *
	 * @return Client
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
	 * Add userRole.
	 *
	 * @param UserRole $userRole
	 *
	 * @return Client
	 */
	public function addUserRole(UserRole $userRole)
	{
		$this->userRoles[] = $userRole;

		return $this;
	}

	/**
	 * Remove userRole.
	 *
	 * @param UserRole $userRole
	 */
	public function removeUserRole(UserRole $userRole)
	{
		$this->userRoles->removeElement($userRole);
	}

	/**
	 * Get userRoles.
	 *
	 * @return ArrayCollection
	 */
	public function getUserRoles()
	{
		return $this->userRoles;
	}

	/**
	 * Add register.
	 *
	 * @param Register $register
	 *
	 * @return Client
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
	 * Get entityManagerIdentifier.
	 *
	 * @return string
	 */
	public function getEntityManagerIdentifier()
	{
		return $this->entityManagerIdentifier;
	}

	/**
	 * Set entityManagerIdentifier.
	 *
	 * @param string $entityManagerIdentifier
	 *
	 * @return $this
	 */
	public function setEntityManagerIdentifier($entityManagerIdentifier)
	{
		$this->entityManagerIdentifier = $entityManagerIdentifier;

		return $this;
	}

	/**
	 * Add bulletin.
	 *
	 * @param Bulletin $bulletin
	 *
	 * @return Client
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
	 * Return naming conversion match table for CompanyX UserRoles to symfony Roles.
	 *
	 * @return array
	 */
	public function getUserRolesMatchTable()
	{
		return [
			'transdb.perm.administrators' => CompanyXUserRole::ADMINISTRATORS,
			'transdb.perm.communication-centre' => CompanyXUserRole::COMMUNICATION_CENTRE,
			'transdb.perm.creating-schedules' => CompanyXUserRole::CREATING_SCHEDULES,
			'transdb.perm.all-rights' => CompanyXUserRole::ALL_RIGHTS,
			'transdb.perm.newsletter-creation' => CompanyXUserRole::NEWSLETTER_CREATION,
			'transdb.perm.hours' => CompanyXUserRole::HOURS,
			'transdb.perm.register' => CompanyXUserRole::HOURS_REGISTER,
			'transdb.perm.accord' => CompanyXUserRole::HOURS_ACCORD,
			'transdb.perm.employees' => CompanyXUserRole::EMPLOYEES,
			'transdb.perm.looking-not-editing' => CompanyXUserRole::EMPLOYEES_LOOKING_NOT_EDITING,
			'transdb.perm.control' => CompanyXUserRole::EMPLOYEES_CONTROL,
			'transdb.perm.management' => CompanyXUserRole::MANAGEMENT,
			'transdb.perm.dashboard' => CompanyXUserRole::MANAGEMENT_DASHBOARD,
			'transdb.perm.diary' => CompanyXUserRole::MANAGEMENT_DIARY,
			'transdb.perm.agenda' => CompanyXUserRole::MANAGEMENT_AGENDA,
			'transdb.perm.forecast' => CompanyXUserRole::MANAGEMENT_FORECAST,
			'transdb.perm.everything' => CompanyXUserRole::MANAGEMENT_EVERYTHING,
			'transdb.perm.reports' => CompanyXUserRole::REPORTS,
			'transdb.perm.creating-permissions' => CompanyXUserRole::CREATING_PERMISSIONS,
			'transdb.perm.show-salaries' => CompanyXUserRole::SHOW_SALARIES,
			'transdb.perm.group-permissions' => CompanyXUserRole::GROUP_PERMISSIONS,
			'transdb.perm.perm-for-employees' => CompanyXUserRole::GROUP_PERMISSIONS_FOR_EMPLOYEES,
			'transdb.perm.perm-for-visitor' => CompanyXUserRole::GROUP_PERMISSIONS_FOR_VISITOR,
			'transdb.perm.perm-for-everyone' => CompanyXUserRole::GROUP_PERMISSIONS_FOR_EVERYONE,
			'transdb.perm.pre-member' => CompanyXUserRole::GROUP_PERMISSIONS_FOR_PRE_MEMBER,
			'transdb.perm.super-user' => CompanyXUserRole::SUPER_USER,

			// This one doesn't exist in current DB, but should
			'transdb.perm.telephonelist-access' => CompanyXUserRole::TELEPHONELIST_ACCESS,

			// There are a few inconsistencies among databases, that still contain dutch translations.
			'Verlof behandelen' => CompanyXUserRole::VACATION_MANAGEMENT,
			'Salarisgegevens verbergen' => CompanyXUserRole::HIDE_SALARIES,
		];
	}

	/**
	 * Add clockMoment.
	 *
	 * @param ClockMoment $clockMoment
	 *
	 * @return Client
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
	 * Add accessControlItem.
	 *
	 * @param AccessControlItem $accessControlItem
	 *
	 * @return Client
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
	 * Set accessControlList.
	 *
	 * @param array $accessControlList
	 *
	 * @return Client
	 */
	public function setAccessControlList($accessControlList)
	{
		$this->accessControlList = $accessControlList;

		return $this;
	}

	/**
	 * Get accessControlList.
	 *
	 * @return array
	 */
	public function getAccessControlList()
	{
		return $this->accessControlList;
	}

	/**
	 * Check if current client has access to the employee data via assigned roles list, normal users should not have
	 * access to sensitive employee data.
	 *
	 * @return bool returns true if the client has access to sensitive employee data
	 */
	public function hasAccessToEmployeeData()
	{
		// These are the roles that have access to employee data via one way or another
		if ($this->hasRole(CompanyXUserRole::ALL_RIGHTS)
			|| $this->hasRole(CompanyXUserRole::EMPLOYEES)
			|| $this->hasRole(CompanyXUserRole::COMMUNICATION_CENTRE)) {
			return true;
		}

		return false;
	}

	/**
	 * Check if current client can POST/PUT/DELETE Assignments for other employees via assigned roles list, normal users should not
	 * be able POST/PUT/DELETE Assignments for other employees but their own Assigments.
	 *
	 * @return bool
	 */
	public function canAssignForOtherEmployees()
	{
		// These are the roles that have access to employee data via one way or another
		return $this->hasRole(CompanyXUserRole::ALL_RIGHTS)
			|| ($this->hasRole(CompanyXUserRole::ADMINISTRATORS) && $this->hasRole(CompanyXUserRole::CREATING_SCHEDULES));
	}

	/**
	 * Check if current client can POST/PUT/DELETE Registers for other employees via assigned roles list, normal users should not
	 * be able POST/PUT/DELETE Registers for other employees but their own Registers.
	 *
	 * @return bool returns true if the client can POST/PUT/DELETE Registers for other employees
	 */
	public function canRegisterForOtherEmployees()
	{
		return $this->hasRole(CompanyXUserRole::ALL_RIGHTS)
			|| ($this->hasRole(CompanyXUserRole::ADMINISTRATORS) && $this->hasRole(CompanyXUserRole::HOURS_REGISTER));
	}

	/**
	 * Check if current client can view bulletins from other employees via assigned roles list, normal users should not
	 * be able to view those, except their own Registers.
	 *
	 * @return bool returns true if the client can view  for other employees
	 */
	public function canViewBulletinsFromOtherEmployees()
	{
		// MANAGEMENT_DASHBOARD always has MANAGEMENT and vice versa
		// ADMINISTRATORS and TELEPHONELIST_ACCESS are also always granted with MANAGEMENT_DASHBOARD

		return
			$this->hasRole(CompanyXUserRole::ALL_RIGHTS) ||
			$this->hasRole(CompanyXUserRole::MANAGEMENT_DASHBOARD) ||
			$this->hasRole(CompanyXUserRole::CREATING_SCHEDULES);
	}
}
