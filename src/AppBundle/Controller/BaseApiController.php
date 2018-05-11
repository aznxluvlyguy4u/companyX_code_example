<?php

namespace AppBundle\Controller;

use AppBundle\Service\EntityManagerMapperService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class BaseApiController.
 */
class BaseApiController extends Controller
{
	/**
	 * @var EntityManagerMapperService
	 */
	private $entityManagerMapperService;

	/**
	 * @var SerializerInterface
	 */
	private $serializerService;

	/**
	 * BaseApiController constructor.
	 *
	 * @param EntityManagerMapperService $entityManagerMapperService
	 * @param SerializerInterface        $serializerService
	 */
	public function __construct(EntityManagerMapperService $entityManagerMapperService,
								SerializerInterface $serializerService)
	{
		$this->entityManagerMapperService = $entityManagerMapperService;
		$this->serializerService = $serializerService;
	}

	/**
	 * Returns the Entity manager of the current authenticated Client.
	 *
	 * @return mixed
	 */
	public function getEntityManager()
	{
		return $this->get('doctrine.orm.customer_entity_manager');
	}

	/**
	 * Returns the Entity manager based on a Client's clientIdentifier.
	 *
	 * @param $clientIdentifier
	 *
	 * @return mixed
	 */
	public function getEntityManagerForClient($clientIdentifier)
	{
		return $this->entityManagerMapperService->getEntityManager($clientIdentifier);
	}

	/**
	 * @return EntityManagerMapperService
	 */
	public function getEntityManagerMapperService()
	{
		return $this->entityManagerMapperService;
	}

	/**
	 * @return SerializerInterface
	 */
	public function getSerializerService()
	{
		return $this->serializerService;
	}
}
