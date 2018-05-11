<?php

namespace AppBundle\Component\Serializer\Normalizer;

use AppBundle\Util\Constants;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizes an object implementing the {@see \DateInterval} to a date interval string.
 */
class DateIntervalNormalizer implements NormalizerInterface
{
	const FORMAT_KEY = 'dateInterval_format';

	/**
	 * @var string
	 */
	private $format;

	/**
	 * @param string $format
	 */
	public function __construct($format = Constants::INTERVAL_ISO8601)
	{
		$this->format = $format;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @throws InvalidArgumentException
	 */
	public function normalize($object, $format = null, array $context = array())
	{
		if (!$object instanceof \DateInterval) {
			throw new InvalidArgumentException('The object must implement the "\DateInterval".');
		}

		$format = isset($context[self::FORMAT_KEY]) ? $context[self::FORMAT_KEY] : $this->format;

		return $object->format($format);
	}

	/**
	 * {@inheritdoc}
	 */
	public function supportsNormalization($data, $format = null)
	{
		return $data instanceof \DateInterval;
	}
}
