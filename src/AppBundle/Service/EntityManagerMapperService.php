<?php

namespace AppBundle\Service;

use Doctrine\Bundle\DoctrineBundle\ManagerConfigurator;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;

/**
 * Class EntityManagerMapperService.
 */
class EntityManagerMapperService
{
	/**
	 * @var Container
	 */
	private $container;

	/**
	 * @var ManagerConfigurator
	 */
	private $managerConfigurator;

	/** @var array */
	private $customerDbCredentials;
	/**
	 * @var EntityManager
	 */
	private $customerEntityManager;

	/**
	 * EntityManagerMapperService constructor.
	 *
	 * @param $customerDbCredentials
	 * @param Container           $container
	 * @param ManagerConfigurator $managerConfigurator
	 * @param EntityManager       $customerEntityManager
	 */
	public function __construct($customerDbCredentials, Container $container, ManagerConfigurator $managerConfigurator, EntityManager $customerEntityManager)
	{
		$this->container = $container;
		$this->customerDbCredentials = $customerDbCredentials;
		$this->managerConfigurator = $managerConfigurator;
		$this->customerEntityManager = $customerEntityManager;
	}

	/**
	 * @param $clientIdentifier
	 *
	 * @return mixed
	 *
	 * @throws ConnectionException
	 * @throws \Doctrine\ORM\ORMException
	 */
	public function getEntityManager($clientIdentifier)
	{
		if (!array_key_exists($clientIdentifier, $this->customerDbCredentials)) {
			throw new ConnectionException('Invalid customer name');
		}

		// Manually override current connection parameters with the ones that belongs to the given clientIdentifier
		$connectionParams = $this->customerEntityManager->getConnection()->getParams();

		if (
			$this->customerDbCredentials[$clientIdentifier]['database_host'] !== $connectionParams['host'] ||
			$this->customerDbCredentials[$clientIdentifier]['database_port'] !== $connectionParams['port'] ||
			$this->customerDbCredentials[$clientIdentifier]['database_user'] !== $connectionParams['user'] ||
			$this->customerDbCredentials[$clientIdentifier]['database_password'] !== $connectionParams['password'] ||
			$this->customerDbCredentials[$clientIdentifier]['database_name'] !== $connectionParams['dbname']
		) {
			$connectionParams['host'] = $this->customerDbCredentials[$clientIdentifier]['database_host'];
			$connectionParams['port'] = $this->customerDbCredentials[$clientIdentifier]['database_port'];
			$connectionParams['user'] = $this->customerDbCredentials[$clientIdentifier]['database_user'];
			$connectionParams['password'] = $this->customerDbCredentials[$clientIdentifier]['database_password'];
			$connectionParams['dbname'] = $this->customerDbCredentials[$clientIdentifier]['database_name'];

			/** @var EntityManager $newCustomerEntityManager */
			$newCustomerEntityManager = $this->customerEntityManager->create(
				$connectionParams,
				$this->customerEntityManager->getConfiguration(),
				$this->customerEntityManager->getEventManager()
			);

			$this->managerConfigurator->configure($newCustomerEntityManager);
			$this->container->set('doctrine.orm.customer_entity_manager', $newCustomerEntityManager);
			$this->customerEntityManager = $newCustomerEntityManager;
		}

		return $this->customerEntityManager;
	}
}
