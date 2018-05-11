<?php

namespace AppBundle\Entity;

/**
 * Interface BaseLogInterface.
 *
 * All new entityLog classes must extends BaseLog which implements this interface
 */
interface BaseLogInterface
{
	public function setId($id);

	public function getId();

	public function setChangedField($changedField);

	public function getChangedField();

	public function setDate($date);

	public function getDate();

	public function setTime($time);

	public function getTime();

	public function setNewValue($newValue);

	public function getNewValue();

	public function setOldValue($oldValue);

	public function getOldValue();

	public function setSessionId($sessionId);

	public function getSessionId();

	public function setLargeDataLog(LargeDataLog $largeDataLog = null);

	public function getLargeDataLog();

	public function setPrimaryKey($primaryKey);

	public function getPrimaryKey();
}
