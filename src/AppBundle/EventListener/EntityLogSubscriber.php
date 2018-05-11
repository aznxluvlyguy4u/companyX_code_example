<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Assignment;
use AppBundle\Entity\AssignmentLog;
use AppBundle\Entity\BaseLogInterface;
use AppBundle\Entity\ClockMoment;
use AppBundle\Entity\Employee;
use AppBundle\Entity\LargeDataLog;
use AppBundle\Entity\Register;
use AppBundle\Entity\SystemConfig;
use AppBundle\Enumerator\SerializerGroup;
use AppBundle\Util\Constants;
use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\UnitOfWork;
use ReflectionClass;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Subscriber for to log changes to entities in 'EntityLog'.
 */
class EntityLogSubscriber implements EventSubscriber
{
	/** @var TokenStorageInterface */
	private $tokenStorage;

	/** @var array */
	private $entitiesToLog;

	/** @var Serializer */
	private $serializer;

	/**
	 * OnFlushEntityLogListener constructor.
	 *
	 * @param TokenStorageInterface $tokenStorage
	 * @param SerializerInterface   $serializerService
	 */
	public function __construct(TokenStorageInterface $tokenStorage, SerializerInterface $serializerService)
	{
		$this->tokenStorage = $tokenStorage;
		$this->serializer = $serializerService;

		// Define entities that need to be logged in this array
		$this->entitiesToLog = array(
			Register::class,
			Assignment::class,
			Employee::class,
			ClockMoment::class,
			SystemConfig::class,
		);
	}

	/**
	 * Returns an array of event names this subscriber wants to listen to.
	 *
	 * The array keys are event names and the value can be:
	 *
	 *  * The method name to call (priority defaults to 0)
	 *  * An array composed of the method name to call and the priority
	 *  * An array of arrays composed of the method names to call and respective
	 *    priorities, or 0 if unset
	 *
	 * For instance:
	 *
	 *  * array('eventName' => 'methodName')
	 *  * array('eventName' => array('methodName', $priority))
	 *  * array('eventName' => array(array('methodName1', $priority), array('methodName2')))
	 *
	 * @return array The event names to listen to
	 */
	public function getSubscribedEvents()
	{
		return array(
			Events::onFlush => 'onFlush',
		);
	}

	/**
	 * @param OnFlushEventArgs $eventArgs
	 */
	public function onFlush(OnFlushEventArgs $eventArgs)
	{
		$em = $eventArgs->getEntityManager();
		$unitOfWork = $em->getUnitOfWork();

		/**
		 * First temporarily disable this onFlush listener to prevent infinite loop
		 * because we call flush inside this class.
		 * see https://blog.liplex.de/doctrine-flush-listener-with-flush-in-it/.
		 */
		$eventManager = $em->getEventManager();
		if ($eventManager) {
			$eventManager->removeEventListener(
				[Events::onFlush],
				$this
			);
		}

		$this->handleEntityInsertions($em, $unitOfWork, $unitOfWork->getScheduledEntityInsertions());

		$this->handleEntityUpdates($em, $unitOfWork, $unitOfWork->getScheduledEntityUpdates());

		$this->handleEntityDeletions($em, $unitOfWork, $unitOfWork->getScheduledEntityDeletions());

		// Add listener back again
		if ($eventManager) {
			$eventManager->addEventListener(
				[Events::onFlush],
				$this
			);
		}
	}

	/**
	 * Handles persistence of related (Entity)Log according to the to be inserted entities.
	 *
	 * @param EntityManager $em
	 * @param UnitOfWork    $unitOfWork
	 * @param array         $toBeInsertedEntities
	 */
	private function handleEntityInsertions(EntityManager $em, UnitOfWork $unitOfWork, array $toBeInsertedEntities)
	{
		$client = $this->tokenStorage->getToken() ? $this->tokenStorage->getToken()->getUser() : null;

		// Log actions to perform during insertions
		foreach ($toBeInsertedEntities as $entity) {
			if (in_array(get_class($entity), $this->entitiesToLog)) {
				// Generalize the Log class to instantiate and the setter and adder method names based on $entity being flushed
				$entityClassName = get_class($entity);
				$entityClassLogName = $entityClassName.'Log';

				// Instantiate new Log depending on the $entity being flushed and set the matching properties
				/** @var BaseLogInterface $entityLog */
				$entityLog = new $entityClassLogName();
				$entityLog->setChangedField('created');
				$entityLog->setDate(new \DateTime());
				$entityLog->setTime(new \DateTime());

				// Call getCreated to NewValue if it exist on the entity
				if (method_exists($entity, 'getCreated')) {
					$entityLog->setNewValue($entity->getCreated()->format(Constants::DATE_TIME_FORMAT_STRING));
				} else {
					$entityLog->setNewValue((new \DateTime())->format(Constants::DATE_TIME_FORMAT_STRING));
				}

				if ($client && $client->getSessionId()) {
					$entityLog->setSessionId($client->getSessionId());
				}

				// TODO Test this specific snippet after CREATE and UPDATE and DELETE Assignment endpoint
				// set AssignmentLog specific fields during insert
				if (AssignmentLog::class == $entityClassLogName) {
					/* @var AssignmentLog $entityLog */
					$entityLog->setNewStartDate($entity->getStartDate());
					$entityLog->setOldEmployeeId($entity->getEmployee()->getId());
				}

				// Equivalent to flush
				$em->persist($entityLog);
				$unitOfWork->computeChangeSet($em->getClassMetadata(get_class($entityLog)), $entityLog);
				$em->flush();

				// Attach newly created entity's id to PrimaryKey field
				$entityLog->setPrimaryKey($entity->getId());
				$unitOfWork->computeChangeSet($em->getClassMetadata(get_class($entityLog)), $entityLog);
			}
		}
	}

	/**
	 * Handles persistence of related (Entity)Log according to the to be updated entities.
	 *
	 * @param EntityManager $em
	 * @param UnitOfWork    $unitOfWork
	 * @param array         $toBeUpdatedEntities
	 */
	private function handleEntityUpdates(EntityManager $em, UnitOfWork $unitOfWork, array $toBeUpdatedEntities)
	{
		$client = $this->tokenStorage->getToken() ? $this->tokenStorage->getToken()->getUser() : null;

		// Log actions to perform during updates
		foreach ($toBeUpdatedEntities as $entity) {
			if (in_array(get_class($entity), $this->entitiesToLog)) {
				$changeSet = $unitOfWork->getEntityChangeSet($entity);

				foreach ($changeSet as $changedField => $values) {
					// Generalize the Log class to instantiate and the setter and adder method names based on $entity being flushed
					$classMetadata = $em->getClassMetadata(get_class($entity));
					$entityClassName = get_class($entity);
					$entityClassLogName = $entityClassName.'Log';

					// TODO we log the current column name instead of the property name defined in the entity class for backward compatibility
					// TODO use correct naming during DB Switch
					$changedFieldColumnName = $classMetadata->getColumnName($changedField);
					// If changedField is an association, log the JoinColumnName instead
					if ($classMetadata->hasAssociation($changedField)) {
						$changedFieldColumnName = $classMetadata->getSingleAssociationJoinColumnName($changedField);
					}

					// Convert old and new values to string
					foreach ($values as $index => $value) {
						// Stringify DateTime values
						if ($value instanceof \DateTime) {
							$values[$index] = $value->format(Constants::DATE_TIME_FORMAT_STRING);
						}

						// If the field and the changed values is an associated Entity, log the ID (foreignKey)
						if ($classMetadata->hasAssociation($changedField)) {
							$values[$index] = (string) $value->getId();
						}

						// Stringify Boolean values
						if (Type::BOOLEAN === $classMetadata->getTypeOfField($changedField)) {
							$values[$index] = (int) $value;
						}
					}

					$oldValue = $values[0];
					$newValue = $values[1];

					// Instantiate new Log depending on the $entity being flushed and set the matching properties
					/** @var BaseLogInterface $entityLog */
					$entityLog = new $entityClassLogName();
					$entityLog->setPrimaryKey($entity->getId());
					$entityLog->setChangedField($changedFieldColumnName);
					$entityLog->setDate(new \DateTime());
					$entityLog->setTime(new \DateTime());
					$entityLog->setNewValue($newValue);
					$entityLog->setOldValue($oldValue);
					if ($client && $client->getSessionId()) {
						$entityLog->setSessionId($client->getSessionId());
					}

					// TODO Test this specific snippet after CREATE and UPDATE and DELETE Assignment endpoint
					// set AssignmentLog specific fields during update
					if (AssignmentLog::class == $entityClassLogName) {
						/* @var AssignmentLog $entityLog */
						$entityLog->setNewStartDate($entity->getStartDate());
						$originalEntityData = $unitOfWork->getOriginalEntityData($entity);
						/** @var Employee $oldEmployee */
						$oldEmployee = $originalEntityData['employee'];
						$entityLog->setOldEmployeeId($oldEmployee->getId());
					}

					// Instead of $em->flush() because we are already in flush process
					$em->persist($entityLog);
					$unitOfWork->computeChangeSet($em->getClassMetadata(get_class($entityLog)), $entityLog);
				}
			}
		}
	}

	/**
	 * Handles persistence of related (Entity)Log according to the to be deleted entities.
	 *
	 * @param EntityManager $em
	 * @param UnitOfWork    $unitOfWork
	 * @param array         $toBeDeletedEntities
	 */
	private function handleEntityDeletions(EntityManager $em, UnitOfWork $unitOfWork, array $toBeDeletedEntities)
	{
		$client = $this->tokenStorage->getToken() ? $this->tokenStorage->getToken()->getUser() : null;

		// Log actions to perform during deletes
		foreach ($toBeDeletedEntities as $entity) {
			if (in_array(get_class($entity), $this->entitiesToLog)) {
				// Generalize the Log class to instantiate and the setter and adder method names based on $entity being flushed
				$entityClassName = get_class($entity);
				$entityClassLogName = $entityClassName.'Log';
				$serializerGroupName = strtoupper((new \ReflectionClass($entity))->getShortName().'_log');
				$serializerGroup = (new ReflectionClass(SerializerGroup::class))->getConstant($serializerGroupName);

				// Call setModifiedBy to current Client is it exist on the entity
				if (method_exists($entity, 'setModifiedBy')) {
					$entity->setModifiedBy($client);
				}

				// Serialize the deleted object into JSON string to save as newValue
				$newValue = $this->serializer->serialize($entity, Constants::JSON_SERIALIZATON_FORMAT, array(
					'groups' => array(
						$serializerGroup,
					),
				));

				// Instantiate new Log depending on the $entity being flushed and set the matching properties
				// Log a the serialized object json string
				/** @var BaseLogInterface $entityLog */
				$entityLog = new $entityClassLogName();
				$entityLog->setPrimaryKey($entity->getId());
				$entityLog->setChangedField('deleted');
				$entityLog->setDate(new \DateTime());
				$entityLog->setTime(new \DateTime());
				$entityLog->setNewValue($newValue);
				if ($client && $client->getSessionId()) {
					$entityLog->setSessionId($client->getSessionId());
				}

				// TODO Test this specific snippet after CREATE and UPDATE and DELETE Assignment endpoint
				// set AssignmentLog specific fields during delete
				if (AssignmentLog::class == $entityClassLogName) {
					/* @var AssignmentLog $entityLog */
					$entityLog->setNewStartDate($entity->getStartDate());
					$entityLog->setOldEmployeeId($entity->getEmployee()->getId());
				}

				// Check if newValue needs to be logged to LargeDataLog
				$this->isLargeDataLog($em, $unitOfWork, $entityLog, $newValue);

				// Instead of $em->flush() because we are already in flush process
				$em->persist($entityLog);
				$unitOfWork->computeChangeSet($em->getClassMetadata(get_class($entityLog)), $entityLog);
			}
		}
	}

	/**
	 * Handles persistence of LargeDataLog if needed. A JSON object string larger than 127 characters is
	 * considered to be LargeData in current CompanyX code.
	 *
	 * @param EntityManager    $em
	 * @param UnitOfWork       $unitOfWork
	 * @param BaseLogInterface $entityLog
	 * @param $data
	 */
	private function isLargeDataLog(EntityManager $em, UnitOfWork $unitOfWork, BaseLogInterface $entityLog, $data)
	{
		if (strlen($data) > 127) {
			$largeDataLog = new LargeDataLog();
			$largeDataLog->setData($data);
			$entityLog->setLargeDataLog($largeDataLog);

			$em->persist($largeDataLog);
			$unitOfWork->computeChangeSet($em->getClassMetadata(get_class($largeDataLog)), $largeDataLog);
		}
	}
}
