<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\SystemConfig;
use AppBundle\Enumerator\SystemConfigValueType;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\PostLoad;
use Doctrine\ORM\Mapping\PrePersist;
use Doctrine\ORM\Mapping\PreUpdate;

/**
 * Entity listener for SystemConfig.
 */
class SystemConfigListener
{
	/**
	 * Temporary valueType setter to specify which type the current systemConfig value belongs to.
	 *
	 * @PostLoad
	 * @PrePersist
	 * @PreUpdate
	 *
	 * @param SystemConfig       $systemConfig
	 * @param LifecycleEventArgs $event
	 */
	public function prePersistPostLoadSetValueType(SystemConfig $systemConfig, LifecycleEventArgs $event)
	{
		$valueType = $systemConfig->getValueTypeMatchTable()[$systemConfig->getKey()] ?? null;
		$systemConfig->setValueType($valueType);
	}

	/**
	 * Temporary value converter to transform proper value for each systemConfig key.
	 *
	 * @PostLoad
	 *
	 * @param SystemConfig       $systemConfig
	 * @param LifecycleEventArgs $event
	 */
	public function postLoadSetNormalizedValue(SystemConfig $systemConfig, LifecycleEventArgs $event)
	{
		$valueType = $systemConfig->getValueType();
		$value = $systemConfig->getValue();

		// Uniformly transform 'yes' or 1 values in boolean true and 'no' or 0 values in boolean false, default false
		if (SystemConfigValueType::BOOLEAN === $valueType) {
			$systemConfig->setNormalizedValue('yes' === $value || '1' === $value);
		}

		// Uniformly transform datetime strings values in datetime object
		if (SystemConfigValueType::DATETIME === $valueType && isset($value)) {
			$dateTime = new \DateTime($value);
			$systemConfig->setNormalizedValue($dateTime);
		}

		// Uniformly transform date_interval strings values in dateInterval object
		if (SystemConfigValueType::DATE_INTERVAL === $valueType && isset($value)) {
			$dateInterval = new \DateInterval($value);
			$systemConfig->setNormalizedValue($dateInterval);
		}

		// Uniformly transform integer string values in integer
		if (SystemConfigValueType::INTEGER === $valueType && isset($value)) {
			$systemConfig->setNormalizedValue((int) $value);
		}

		// Uniformly transform string values in string
		if (SystemConfigValueType::STRING === $valueType && isset($value)) {
			$systemConfig->setNormalizedValue((string) $value);
		}

		// Uniformly transform relative timestring in normalized interval array
		if (SystemConfigValueType::DATEINTERVAL === $valueType && isset($value)) {
			$interval = \DateInterval::createFromDateString($value);
			$labels = [
				'year' => 'y',
				'month' => 'm',
				'day' => 'd',
			];
			$normalized = [];
			foreach ($labels as $label => $value) {
				if ($interval->$value > 0) {
					$normalized[$label] = $interval->$value;
				}
			}
			if (!empty($normalized['day']) && $normalized['day'] > 0 && $normalized['day'] % 7 == 0) {
				$normalized['week'] = $normalized['day'] / 7;
				unset($normalized['day']);
			}
			$systemConfig->setNormalizedValue($normalized);
		}
	}

	// TODO Evaluate if this is still needed during DB switch

	/**
	 * Convert boolean to 'yes' 'no' if systemConfigValue type is boolean.
	 *
	 * @PrePersist
	 * @PreUpdate
	 *
	 * @param SystemConfig       $systemConfig
	 * @param LifecycleEventArgs $event
	 */
	public function prePersistSetValue(SystemConfig $systemConfig, LifecycleEventArgs $event)
	{
		$valueType = $systemConfig->getValueType();
		$value = $systemConfig->getValue();

		// Uniformly transform boolean values in 'yes' or 'no' before saving to DB for backward compatibility
		if (SystemConfigValueType::BOOLEAN === $valueType) {
			if (true === $value) {
				$systemConfig->setValue('yes');
			} elseif (false === $value) {
				$systemConfig->setValue('no');
			}
		}
	}
}
