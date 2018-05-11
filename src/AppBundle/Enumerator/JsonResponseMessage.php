<?php

namespace AppBundle\Enumerator;

/**
 * Class JsonResponseMessage.
 */
abstract class JsonResponseMessage
{
	const UNAUTHORIZED = 'Unauthorized';
	const NOT_FOUND = 'Not found';
	const BAD_REQUEST = 'Bad request';
	const PRECONDITION_FAILED = 'Precondition failed';
	const FORBIDDEN = 'Forbidden';
	const TOO_MANY_REQUESTS = 'Too many requests';
	const INTERNAL_SERVER_ERROR = 'Internal server error';
}
