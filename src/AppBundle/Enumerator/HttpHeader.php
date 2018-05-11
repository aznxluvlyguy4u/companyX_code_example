<?php

namespace AppBundle\Enumerator;

/**
 * Class HttpHeader.
 */
abstract class HttpHeader
{
	const AUTHORIZATION = 'Authorization';
	const BEARER = 'Bearer';
	const CONTENT_TYPE = 'Content-Type';
	const APPLICATION_JSON = 'application/json';
}
