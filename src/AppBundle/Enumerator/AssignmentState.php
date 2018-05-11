<?php

namespace AppBundle\Enumerator;

/**
 * Class AssignmentState.
 */
abstract class AssignmentState
{
	const UNASSIGNED = 'unassigned';
	const ALL = 'all';
	const ASSIGNED_ASSIGNMENTS = 'assigned_assignments';
	const UNASSIGNED_ASSIGNMENTS = 'unassigned_assignments';
}
