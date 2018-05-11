<?php

namespace AppBundle\Component\Serializer\Normalizer;

use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class NullValueNormalizer extends ObjectNormalizer
{
	public function __construct(ClassMetadataFactoryInterface $classMetadataFactory = null, NameConverterInterface $nameConverter = null, PropertyAccessorInterface $propertyAccessor = null, PropertyTypeExtractorInterface $propertyTypeExtractor = null)
	{
		$nameConverter = $nameConverter ?: new CamelCaseToSnakeCaseNameConverter();

		parent::__construct($classMetadataFactory, $nameConverter, $propertyTypeExtractor);
	}

	/**
	 * Filter out all properties with null values.
	 *
	 * {@inheritdoc}
	 */
	public function normalize($object, $format = null, array $context = [])
	{
		$data = parent::normalize($object, $format, $context);

		return array_filter($data, function ($value) {
			return null !== $value && '' !== $value;
		});
	}

	/**
	 * Do not denormalize with this normalizer.
	 *
	 * @param mixed  $data
	 * @param string $type
	 * @param null   $format
	 *
	 * @return bool
	 */
	public function supportsDenormalization($data, $type, $format = null)
	{
		return false;
	}
}
