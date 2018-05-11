<?php

namespace AppBundle\Service;

use AppBundle\Entity\Client;
use AppBundle\Entity\Employee;
use AppBundle\Entity\SystemConfig;
use AppBundle\Repository\SystemConfigRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Class EmployeePrivacyService.
 */
class EmployeePrivacyService
{
	/** @var Client */
	private $client;

	/** @var EntityManager */
	private $em;

	/** @var SystemConfigRepository */
	private $systemConfigRepo;

	/**
	 * EmployeePrivacyModeService constructor.
	 *
	 * @param ContainerInterface    $container
	 * @param TokenStorageInterface $tokenStorage
	 */
	public function __construct(ContainerInterface $container, TokenStorageInterface $tokenStorage)
	{
		$this->client = $tokenStorage->getToken()->getUser();
		$this->em = $container->get('doctrine')->getManager('customer');
		$this->systemConfigRepo = $this->em->getRepository(SystemConfig::class);
	}

	/**
	 * Hide/show phone number and email address of the employees depending on access right and DOR_EMPLOYEES_PRIVACYMODE.
	 *
	 * @param Employee[]|Employee $employees
	 *
	 * @return Employee[]|Employee
	 */
	public function privacyModeCheck($employees)
	{
		if (is_array($employees)) {
			foreach ($employees as $employee) {
				$this->hidePhoneNumber($employee);
				$this->hideEmailAddress($employee);
			}
		} else {
			$this->hidePhoneNumber($employees);
			$this->hideEmailAddress($employees);
		}

		return $employees;
	}

	/**
	 * @param Employee $employee
	 */
	private function hidePhoneNumber(Employee $employee)
	{
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = $this->em->getRepository(SystemConfig::class);
		$hidePhoneNumber = $systemConfigRepo->getHidePhoneNumber($this->client);

		if ($hidePhoneNumber) {
			$employee->setPhoneNumber(null);
		}
	}

	/**
	 * @param Employee $employee
	 */
	private function hideEmailAddress(Employee $employee)
	{
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = $this->em->getRepository(SystemConfig::class);
		$hideEmailAddress = $systemConfigRepo->getHideEmailAddress($this->client);

		if ($hideEmailAddress) {
			$employee->setEmailAddress(null);
		}
	}
}
