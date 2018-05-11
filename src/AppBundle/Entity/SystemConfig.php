<?php

namespace AppBundle\Entity;

use AppBundle\Enumerator\SystemConfigKey;
use AppBundle\Enumerator\SystemConfigValueType;
use Doctrine\ORM\Mapping as ORM;
use ReflectionClass;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * TODO Rename all column names AND reevaluate the necessity of this.
 *
 * Class SystemConfig.
 *
 * @ORM\Table(name="sc_lib")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\SystemConfigRepository")
 * @ORM\EntityListeners({"AppBundle\EventListener\SystemConfigListener"})
 */
class SystemConfig
{
	/**
	 * @var int
	 *
	 * @Groups({"systemConfigLog"})
	 *
	 * @ORM\Column(name="i", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="IDENTITY")
	 */
	private $id;

	/**
	 * @var string
	 *
	 * @Groups({"systemConfigs"})
	 * @Groups({"systemConfigLog"})
	 *
	 * @ORM\Column(name="k", type="string")
	 */
	private $key;

	/**
	 * @var string
	 *
	 * @Groups({"systemConfigLog"})
	 *
	 * @ORM\Column(name="v", type="string")
	 */
	private $value;

	/**
	 * Virtual property to convert the value stored in sc_libs table into a Symfony friendly value depending on 'valueType'.
	 *
	 * @Groups({"systemConfigs"})
	 *
	 * @var mixed
	 */
	private $normalizedValue;

	// TODO Temporary virtual property to specify which type the systemConfig value 'v' is
	// TODO Currently no type column is in the sc_libs table, which makes it impossible to guess what the value 'v' really means
	// TODO Consider make an extra column value_type to store that information
	/**
	 * @var mixed
	 */
	private $valueType;

	/**
	 * @var \DateTime
	 *
	 * @Groups({"systemConfigLog"})
	 *
	 * @ORM\Column(name="m", type="datetime")
	 */
	private $modified;

	/**
	 * @var int
	 *
	 * @Groups({"systemConfigLog"})
	 *
	 * @ORM\Column(name="object_id", type="integer")
	 */
	private $objectId;

	/**
	 * Employee constructor.
	 */
	public function __construct()
	{
	}

	/**
	 * Get id.
	 *
	 * @return int
	 */
	public function getId()
	{
		return $this->id;
	}

	/**
	 * Set key.
	 *
	 * @param string $key
	 *
	 * @return SystemConfig
	 */
	public function setKey($key)
	{
		$this->key = $key;

		return $this;
	}

	/**
	 * Get key.
	 *
	 * @return string
	 */
	public function getKey()
	{
		$matchTable = $this->getKeyMatchTable();
		$matchTable = array_flip($matchTable);

		return $matchTable[$this->key] ?? null;
	}

	/**
	 * Set value.
	 *
	 * @param string $value
	 *
	 * @return SystemConfig
	 */
	public function setValue($value)
	{
		$this->value = $value;

		return $this;
	}

	/**
	 * Get value.
	 *
	 * @return string
	 */
	public function getValue()
	{
		return $this->value;
	}

	/**
	 * Set normalizedValue.
	 *
	 * @param mixed $normalizedValue
	 *
	 * @return SystemConfig
	 */
	public function setNormalizedValue($normalizedValue)
	{
		$this->normalizedValue = $normalizedValue;

		return $this;
	}

	/**
	 * Get normalizedValue.
	 *
	 * @return mixed
	 */
	public function getNormalizedValue()
	{
		return $this->normalizedValue;
	}

	/**
	 * Set valueType.
	 *
	 * @param string $valueType
	 *
	 * @return SystemConfig
	 */
	public function setValueType($valueType)
	{
		$this->valueType = $valueType;

		return $this;
	}

	/**
	 * Get valueType.
	 *
	 * @return string
	 */
	public function getValueType()
	{
		return $this->valueType;
	}

	/**
	 * Set modified.
	 *
	 * @param \DateTime $modified
	 *
	 * @return SystemConfig
	 */
	public function setModified($modified)
	{
		$this->modified = $modified;

		return $this;
	}

	/**
	 * Get modified.
	 *
	 * @return \DateTime
	 */
	public function getModified()
	{
		return $this->modified;
	}

	/**
	 * Set objectId.
	 *
	 * @param int $objectId
	 *
	 * @return SystemConfig
	 */
	public function setObjectId($objectId)
	{
		$this->objectId = $objectId;

		return $this;
	}

	/**
	 * Get objectId.
	 *
	 * @return int
	 */
	public function getObjectId()
	{
		return $this->objectId;
	}

	/**
	 * Get the type key => value names conversion matchtable.
	 *
	 * @return array
	 */
	private function getKeyMatchTable()
	{
		$systemConfigKeyClass = new ReflectionClass(SystemConfigKey::class);
		$matchTable = $systemConfigKeyClass->getConstants();

		return $matchTable;
	}

	// TODO temporary solution to determine what type the systemConfig value is
	// TODO Index all currently known system config value types HERE!
	/*
	 * Get hardcoded array with each systemConfig currently known by key name and its type
	 */
	public function getValueTypeMatchTable()
	{
		$keyMatchTable = $this->getKeyMatchTable();
		$keyMatchTable = array_flip($keyMatchTable);
		$valueTypeArray = [
			$keyMatchTable[SystemConfigKey::DOR_EMPLOYEES_RESTRICT] => SystemConfigValueType::BOOLEAN,
			$keyMatchTable[SystemConfigKey::DOR_EMPLOYEES_PRIVACYMODE] => SystemConfigValueType::INTEGER,
			$keyMatchTable[SystemConfigKey::DOR_PHONELIST_RESTRICT] => SystemConfigValueType::BOOLEAN,
			$keyMatchTable[SystemConfigKey::DOR_SCHEDULE_NO_ENDTIMES] => SystemConfigValueType::BOOLEAN,
			$keyMatchTable[SystemConfigKey::DOR_SCHEDULE_HIDE_BREAKS] => SystemConfigValueType::BOOLEAN,
			$keyMatchTable[SystemConfigKey::DOR_SCHEDULE_LOCK_APPROVED] => SystemConfigValueType::BOOLEAN,
			$keyMatchTable[SystemConfigKey::DOR_SCHEDULE_USE_VACATION_TIMEOUT_FOR_UNAVAILABILITY] => SystemConfigValueType::BOOLEAN,
			$keyMatchTable[SystemConfigKey::DOR_SCHEDULE_VACATION_MESSAGE] => SystemConfigValueType::STRING,
			$keyMatchTable[SystemConfigKey::DOR_SCHEDULE_VACATION_TIMEOUT] => SystemConfigValueType::DATEINTERVAL,
			$keyMatchTable[SystemConfigKey::DOR_SCHEDULE_AVAILABILITY_BLOCK_PLANNED] => SystemConfigValueType::INTEGER,
			$keyMatchTable[SystemConfigKey::DOR_SCHEDULE_HOURS_OVERVIEW_SHOW_SALDI] => SystemConfigValueType::BOOLEAN,
			$keyMatchTable[SystemConfigKey::DOR_SCHEDULE_HOURS_OVERVIEW_EXTENDED] => SystemConfigValueType::BOOLEAN,
			$keyMatchTable[SystemConfigKey::DOR_REGISTRATION_FEATURES] => SystemConfigValueType::INTEGER,
			$keyMatchTable[SystemConfigKey::DOR_REGISTRATION_ENABLE_BREAK] => SystemConfigValueType::INTEGER,
			$keyMatchTable[SystemConfigKey::DOR_REGISTRATION_COMPONENTS_KILOMETER_ALLOWENCE] => SystemConfigValueType::BOOLEAN,
			$keyMatchTable[SystemConfigKey::DOR_REGISTRATION_COMPONENT_MEALCHECKBOX] => SystemConfigValueType::BOOLEAN,
			$keyMatchTable[SystemConfigKey::DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS] => SystemConfigValueType::BOOLEAN,
			$keyMatchTable[SystemConfigKey::DOR_OPENSHIFTS_ALWAYS_SHOW_OPEN_SHIFTS] => SystemConfigValueType::BOOLEAN,
			$keyMatchTable[SystemConfigKey::DOR_OPENSHIFTS_SHOW_IF_SCHEDULED] => SystemConfigValueType::BOOLEAN,
			$keyMatchTable[SystemConfigKey::DOR_FTE_MW_HOURREGISTRATION] => SystemConfigValueType::INTEGER,
			$keyMatchTable[SystemConfigKey::DOR_HOUR_REGISTRATION_AUTOFILL] => SystemConfigValueType::BOOLEAN,
			$keyMatchTable[SystemConfigKey::DOR_FEATURES_SHIFT_SWAP_REQUEST] => SystemConfigValueType::BOOLEAN,
			$keyMatchTable[SystemConfigKey::DOR_THEME_DISABLED_MENU_ITEMS] => SystemConfigValueType::STRING,
			$keyMatchTable[SystemConfigKey::DOR_HEADQUARTER_ID] => SystemConfigValueType::INTEGER,
		];

		return $valueTypeArray;
	}
}
