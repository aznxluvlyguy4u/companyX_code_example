<?php

namespace AppBundle\Enumerator;

// TODO This enum is supposedly temporary as it is now impossible to alter their current database structure
// TODO consider rename system config key values with uniform english names during DB Switch
/**
 * Class SystemConfigKey.
 */
abstract class SystemConfigKey
{
	const DOR_EMPLOYEES_RESTRICT = 'DOR.Employees.Restrict';
	const DOR_EMPLOYEES_PRIVACYMODE = 'DOR.Employees.PrivacyMode';
	const DOR_PHONELIST_RESTRICT = 'DOR.Telefoonlijst.Restrict';
	const DOR_SCHEDULE_NO_ENDTIMES = 'DOR.Rooster.GeenEindtijden';
	const DOR_SCHEDULE_HIDE_BREAKS = 'DOR.Rooster.VerbergGeplandePauzes';
	const DOR_SCHEDULE_LOCK_APPROVED = 'DOR.Rooster.VergrendelGoedgekeurd';
	const DOR_SCHEDULE_USE_VACATION_TIMEOUT_FOR_UNAVAILABILITY = 'DOR.Vakantie.OokVoorOnbeschikbaar';
	const DOR_SCHEDULE_VACATION_MESSAGE = 'DOR.Vakantie.Message';
	const DOR_SCHEDULE_VACATION_TIMEOUT = 'DOR.Vakantie.Timeout';
	const DOR_SCHEDULE_AVAILABILITY_BLOCK_PLANNED = 'DOR.Beschikbaarheid.BlokkeerIngepland';
	const DOR_SCHEDULE_HOURS_OVERVIEW_SHOW_SALDI = 'DOR.Rooster.UrenOverzicht.ToonSaldi';
	const DOR_SCHEDULE_HOURS_OVERVIEW_EXTENDED = 'DOR.Rooster.UrenOverzicht.ToonUitgebreid';
	const DOR_REGISTRATION_FEATURES = 'DOR.Urenregistratie.Features';
	const DOR_REGISTRATION_ENABLE_BREAK = 'DOR.Urenregistratie.Pauzes.Enable';
	const DOR_REGISTRATION_COMPONENTS_KILOMETER_ALLOWENCE = 'DOR.Urenregistratie.AddComponents.Kilometervergoeding';
	const DOR_REGISTRATION_COMPONENT_MEALCHECKBOX = 'DOR.Urenregistratie.AddComponents.MaaltijdVinkje';
	const DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS = 'DOR.Rooster.GatenWeergeven';
	const DOR_OPENSHIFTS_ALWAYS_SHOW_OPEN_SHIFTS = 'DOR.Rooster.StandaardGaten';
	const DOR_OPENSHIFTS_SHOW_IF_SCHEDULED = 'DOR.GatenTonenAlsIngeroosterd';
	const DOR_FTE_MW_HOURREGISTRATION = 'DOR.FTE.MW_Urenregistratie';
	const DOR_HOUR_REGISTRATION_AUTOFILL = 'DOR.Urenregistratie.AutoFill';
	const DOR_FEATURES_SHIFT_SWAP_REQUEST = 'DOR.Features.Availibility.Exchange';
	const DOR_THEME_DISABLED_MENU_ITEMS = 'DOR.Theme.MenuDisabledItems';
	const DOR_HEADQUARTER_ID = 'DOR.HeadOffice.ID';
}
