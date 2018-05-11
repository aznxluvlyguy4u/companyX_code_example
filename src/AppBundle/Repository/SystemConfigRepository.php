<?php

namespace AppBundle\Repository;

use AppBundle\Entity\Client;
use AppBundle\Entity\SystemConfig;
use AppBundle\Enumerator\CompanyXRoute;
use AppBundle\Enumerator\SystemConfigKey;
use Doctrine\ORM\EntityRepository;

/**
 * Class SystemConfigRepository.
 */
class SystemConfigRepository extends EntityRepository
{
	/**
	 * Convert bitwise value to boolean values for key Dor.Employees.PrivacyMode to determine if phoneNumber of Employee is hidden.
	 *
	 * @param Client $client
	 *
	 * @return bool
	 */
	public function getHidePhoneNumber(Client $client)
	{
		// Do not hide phone number when client does has access to employee data anyway.
		if ($client->hasAccessToEmployeeData()) {
			return false;
		}

		// Else if the user telephone list is hidden, normal users should not have access
		if ($this->isTelephoneListHidden()) {
			return true;
		}

		// Last but not least, we check the privacy settings.
		/** @var SystemConfig $result */
		$result = $this->findOneByKey(SystemConfigKey::DOR_EMPLOYEES_PRIVACYMODE);
		if (!$result) {
			return false;
		}

		return $result->getValue() & 1;
	}

	/**
	 * Convert bitwise value to boolean values for key Dor.Employees.PrivacyMode to determine if emailAddress of Employee is hidden.
	 *
	 * @param Client $client
	 *
	 * @return bool
	 */
	public function getHideEmailAddress(Client $client)
	{
		// Do not hide phone number when client does has access to employee data anyway.
		if ($client->hasAccessToEmployeeData()) {
			return false;
		}

		// Else if the user telephone list is hidden, normal users should not have access
		if ($this->isTelephoneListHidden()) {
			return true;
		}

		// Last but not least, we check the privacy settings.
		/** @var SystemConfig $result */
		$result = $this->findOneByKey(SystemConfigKey::DOR_EMPLOYEES_PRIVACYMODE);
		if (!$result) {
			return false;
		}

		return $result->getValue() & 2;
	}

	/**
	 * Convert stored values to boolean values for key DOR.FTE.MW_Urenregistratie to determine if client is allowed to perform CREATE EDIT DELETE operation for Register.
	 *
	 * @return bool
	 */
	public function getDisableRegisterRegistration()
	{
		/** @var SystemConfig $result */
		$result = $this->findOneByKey(SystemConfigKey::DOR_FTE_MW_HOURREGISTRATION);

		if (!$result) {
			return false;
		}

		return 0 === $result->getNormalizedValue();
	}

	/**
	 * Convert stored values to boolean values for key DOR_FEATURES_SHIFT_SWAP_REQUEST/DOR.Features.Availibility.Exchange to determine if ShiftSwapRequests can be used in My Schedule.
	 *
	 * @return bool
	 */
	public function getEnableShiftSwapRequest()
	{
		/** @var SystemConfig $result */
		$result = $this->findOneByKey(SystemConfigKey::DOR_FEATURES_SHIFT_SWAP_REQUEST);

		if (!$result) {
			return false;
		}

		return $result->getNormalizedValue();
	}

	/**
	 * Get an array of disabled menu items stored in key DOR_THEME_DISABLED_MENU_ITEMS / DOR.Theme.MenuDisabledItems.
	 *
	 * @return array|null
	 */
	public function getDisabledMenuItems()
	{
		/** @var SystemConfig $result */
		$result = $this->findOneByKey(SystemConfigKey::DOR_THEME_DISABLED_MENU_ITEMS);

		if (!$result) {
			return null;
		}

		$items = explode(',', strtolower($result->getValue()));

		return array_map('trim', $items);
	}

	// TODO This is purely for backward compatibility and reads table from admin_shared.shpages table which is not currently mapped.
	// TODO figure out if this is still necessary in the future

	/**
	 * Get an array of disabled menu items in legacy CompanyX code stored in admin_shared.shpages.
	 *
	 * @return array
	 */
	public function getLegacyDisabledMenuItems()
	{
		$sql = /* @lang text */
			'SELECT url FROM admin_shared.shpages shpage INNER JOIN auth_navigation an ON shpage.id = an.shpage_id AND an.active = 0';
		$connection = $this->getEntityManager()->getConnection();
		$statement = $connection->prepare($sql);
		$statement->execute();

		$disabledMenuItems = $statement->fetchAll();

		return array_column($disabledMenuItems, 'url');
	}

	// TODO This is purely for backward compatibility and reads table from admin_shared.shpages table which is not currently mapped.
	// TODO figure out if this is still necessary in the future

	/**
	 * Get an array of combined disabled menu items in key DOR_THEME_DISABLED_MENU_ITEMS / DOR.Theme.MenuDisabledItems AND in legacy CompanyX code stored in admin_shared.shpages.
	 *
	 * @return array
	 */
	public function getCombinedDisabledMenuItems()
	{
		$newDisabledMenuItems = $this->getDisabledMenuItems();
		$legacyDisabledMenuItems = $this->getLegacyDisabledMenuItems();

		return array_merge($newDisabledMenuItems, $legacyDisabledMenuItems);
	}

	/**
	 * Get the setting value for system config key DOR_PHONELIST_RESTRICT / DOR.Telefoonlijst.Restrict.
	 *
	 * @return bool
	 */
	public function getPhonelistRestriction()
	{
		/** @var SystemConfig $dorPhonelistRestrict */
		$dorPhonelistRestrict = $this->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
		$restriction = $dorPhonelistRestrict ? $dorPhonelistRestrict->getNormalizedValue() : false;

		return $restriction;
	}

	/**
	 * Check if the telephone list routes are in the hidden menu items.
	 *
	 * @return bool true if the phone list is hidden, false if it is not
	 */
	public function isTelephoneListHidden()
	{
		// Hide telephone list when telephone list is hidden
		$combinedDisabledMenuItems = $this->getCombinedDisabledMenuItems();
		if (in_array(CompanyXRoute::DASHBOARD_TELEFOONLIJST, $combinedDisabledMenuItems)
			|| in_array(CompanyXRoute::BTOOLS_TELEFOONLIJST, $combinedDisabledMenuItems)
		) {
			return true;
		}

		// No condition met, don't hide.
		return false;
	}

	/**
	 * Check DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS setting to determine if unassigned assignments can be shown.
	 *
	 * @return bool
	 */
	public function showUnassignedAssignments()
	{
		/** @var SystemConfig $dorOpenshiftsClickToShowShifts */
		$dorOpenshiftsClickToShowShifts = $this->findOneByKey(SystemConfigKey::DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS);
		$showUnassignedAssignments = $dorOpenshiftsClickToShowShifts ? $dorOpenshiftsClickToShowShifts->getNormalizedValue() : true;

		return $showUnassignedAssignments;
	}

	/**
	 * Check DOR_OPENSHIFTS_SHOW_IF_SCHEDULED setting to determine if scheduled unassigned assignments can be shown.
	 *
	 * @return bool
	 */
	public function showScheduledUnassignedAssignments()
	{
		/** @var SystemConfig $dorOpenshiftsShowIfScheduled */
		$dorOpenshiftsShowIfScheduled = $this->findOneByKey(SystemConfigKey::DOR_OPENSHIFTS_SHOW_IF_SCHEDULED);
		$showUnassignedAssignmentsIfScheduled = $dorOpenshiftsShowIfScheduled ? $dorOpenshiftsShowIfScheduled->getNormalizedValue() : false;

		return $showUnassignedAssignmentsIfScheduled;
	}

	/**
	 * Check DOR_SCHEDULE_HIDE_BREAKS setting to determine if breaks in schedule can be shown.
	 *
	 * @return bool
	 */
	public function showBreaksInSchedule()
	{
		/** @var SystemConfig $dorHideBreaksInSchedule */
		$dorHideBreaksInSchedule = $this->findOneByKey(SystemConfigKey::DOR_SCHEDULE_HIDE_BREAKS);
		$showBreaksInSchedule = $dorHideBreaksInSchedule ? !$dorHideBreaksInSchedule->getNormalizedValue() : true;

		return $showBreaksInSchedule;
	}

	/**
	 * @return array
	 *
	 * @throws \Doctrine\ORM\ORMException
	 */
	public function getVacationTimeoutValue()
	{
		$timeoutConfig = $this->findOneByKey(SystemConfigKey::DOR_SCHEDULE_VACATION_TIMEOUT);
		/* @var SystemConfig $dorScheduleVacationTimeout */
		return $timeoutConfig ? $timeoutConfig->getNormalizedValue() : [];
	}

	/**
	 * Check DOR_SCHEDULE_AVAILABILITY_BLOCK_PLANNED setting to determine whether or not a client
	 * is allowed PUT/PATCH/DELETE the Register of the type AVAILABLE/UNAVAILABLE.
	 *
	 * 0. A Client can always PUT/PATCH/DELETE his AVAILABLE/UNAVAILABLE Registers.
	 *    (This is default behaviour if the key doesn't exist in DB)
	 * 1. A Client cannot PUT/PATCH/DELETE his AVAILABLE/UNAVAILABLE Registers if he
	 *    already has an Assignment on that day that is already published
	 * 2. A Client cannot PUT/PATCH/DELETE his AVAILABLE/UNAVAILABLE Registers if he
	 *    already has an Assignment REGARDLESS whether or not it is published
	 *
	 * @return int
	 */
	public function getScheduleAvailabilityBlockPlannedSetting()
	{
		/** @var SystemConfig $dorScheduleAvailabilityBlockPlanned */
		$dorScheduleAvailabilityBlockPlanned = $this->findOneByKey(SystemConfigKey::DOR_SCHEDULE_AVAILABILITY_BLOCK_PLANNED);
		$blockPlannedSettingValue = $dorScheduleAvailabilityBlockPlanned ? $dorScheduleAvailabilityBlockPlanned->getNormalizedValue() : 0;

		return $blockPlannedSettingValue;
	}
}
