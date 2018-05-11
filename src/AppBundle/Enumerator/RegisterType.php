<?php

namespace AppBundle\Enumerator;

/**
 * TODO This enum is supposedly temporary as it is now impossible to alter their current database structure
 * TODO consider delete this and apply proper sub type class with proper naming in the future durinb DB switch
 * Class RegisterType.
 */
abstract class RegisterType
{
	const WORK = 'werk';
	const PREFERENCE = 'voorkeur';
	const AVAILABLE = 'beschikbaar';
	const UNAVAILABLE = 'onbeschikbaar';
	const OVERTIME = 'overwerk';
	const SICK = 'ziek';
	const SICK_LEAVE = 'ziekte_dagen';
	const VACATION = 'vakantie';
	const VACATION_DAYS = 'vakantie_dagen';
	const REMARK = 'opmerking';
	const LEAVE_HOLIDAY = 'verlof_feest';
	const SICK_WAIT_DAY = 'ziekte_wachtdag';
}
