<?php

namespace AppBundle\Enumerator;

/**
 * Class EntityState.
 */
abstract class EntityState
{
	/**
	 * An entity is in MANAGED state when its persistence is managed by an EntityManager.
	 */
	const ENTITY_STATE_MANAGED = 1;

	/**
	 * An entity is new if it has just been instantiated (i.e. using the "new" operator)
	 * and is not (yet) managed by an EntityManager.
	 */
	const ENTITY_STATE_NEW = 2;

	/**
	 * A detached entity is an instance with persistent state and identity that is not
	 * (or no longer) associated with an EntityManager (and a UnitOfWork).
	 */
	const ENTITY_STATE_DETACHED = 3;

	/**
	 * A removed entity instance is an instance with a persistent identity,
	 * associated with an EntityManager, whose persistent state will be deleted
	 * on commit.
	 */
	const ENTITY_STATE_REMOVED = 4;
}
