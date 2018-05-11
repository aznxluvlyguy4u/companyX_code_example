<?php

namespace AppBundle\Enumerator;

/**
 * Class SerializerGroup.
 */
abstract class SerializerGroup
{
	const BULLETINS = 'bulletins';
	const EMPLOYEES = 'employees';
	const ELIGIBLE_EMPLOYEES = 'eligibleEmployees';
	const HEADQUARTERS = 'headquarters';
	const OFFICES = 'offices';
	const OFFICE = 'offices/*';
	const DEPARTMENTS = 'departments';
	const CLIENTS = 'clients';
	const REGISTERS = 'registers';
	const ASSIGNMENTS = 'assignments';
	const SHIFT_SWAP_REQUESTS = 'shiftSwapRequests';
	const SYSTEMCONFIGS = 'systemConfigs';
	const CLOCKMOMENTS = 'clockMoments';
	const CLOCKINTERVALS = 'clockIntervals';
	const REGISTER_LOG = 'registerLog';
	const ASSIGNMENT_LOG = 'assignmentLog';
	const EMPLOYEE_LOG = 'employeeLog';
	const CLOCKMOMENT_LOG = 'clockMomentLog';
	const SYSTEMCONFIG_LOG = 'systemConfigLog';
}
