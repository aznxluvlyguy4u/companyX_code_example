<?php

namespace AppBundle\Component\Serializer\Normalizer;

use DateTimeZone;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Normalizes an object implementing the {@see \DateTimeInterface} to a date string.
 * Denormalizes a date string to an instance of {@see \DateTime} or {@see \DateTimeImmutable} in timezone set in date_default_timezone_set().
 */
class DefaultTimezoneDateTimeNormalizer implements NormalizerInterface, DenormalizerInterface
{
	const FORMAT_KEY = 'datetime_format';

	/**
	 * @var string
	 */
	private $format;

	/**
	 * @param string $format
	 */
	public function __construct($format = \DateTime::RFC3339)
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
		if (!$object instanceof \DateTimeInterface) {
			throw new InvalidArgumentException('The object must implement the "\DateTimeInterface".');
		}

		$format = $context[self::FORMAT_KEY] ?? $this->format;

		return $object->format($format);
	}

	/**
	 * {@inheritdoc}
	 */
	public function supportsNormalization($data, $format = null)
	{
		return $data instanceof \DateTimeInterface;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @throws UnexpectedValueException
	 */
	public function denormalize($data, $class, $format = null, array $context = array())
	{
		$dateTimeFormat = $context[self::FORMAT_KEY] ?? null;

		if (null !== $dateTimeFormat) {
			$object = \DateTime::class === $class ? \DateTime::createFromFormat($dateTimeFormat, $data) : \DateTimeImmutable::createFromFormat($dateTimeFormat, $data);

			if (false !== $object) {
				return $object;
			}

			$dateTimeErrors = \DateTime::class === $class ? \DateTime::getLastErrors() : \DateTimeImmutable::getLastErrors();

			throw new UnexpectedValueException(sprintf(
				'Parsing datetime string "%s" using format "%s" resulted in %d errors:'."\n".'%s',
				$data,
				$dateTimeFormat,
				$dateTimeErrors['error_count'],
				implode("\n", $this->formatDateTimeErrors($dateTimeErrors['errors']))
			));
		}

		try {
			$dateTimeObject = \DateTime::class === $class ? new \DateTime($data) : new \DateTimeImmutable($data);
			$dateTimeObject->setTimezone(new DateTimeZone(date_default_timezone_get()));

			return $dateTimeObject;
		} catch (\Exception $e) {
			throw new UnexpectedValueException($e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function supportsDenormalization($data, $type, $format = null)
	{
		$supportedTypes = array(
			\DateTimeInterface::class => true,
			\DateTimeImmutable::class => true,
			\DateTime::class => true,
		);

		return isset($supportedTypes[$type]);
	}

	/**
	 * Formats datetime errors.
	 *
	 * @param array $errors
	 *
	 * @return string[]
	 */
	private function formatDateTimeErrors(array $errors)
	{
		$formattedErrors = array();

		foreach ($errors as $pos => $message) {
			$formattedErrors[] = sprintf('at position %d: %s', $pos, $message);
		}

		return $formattedErrors;
	}
}
