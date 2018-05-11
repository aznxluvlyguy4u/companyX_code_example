<?php

namespace AppBundle\Tests\Controller;

use AppBundle\Enumerator\HttpHeader;
use AppBundle\Repository\BulletinRepository;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use AppBundle\Util\Constants;

class BulletinApiControllerTest extends WebTestCase
{
	/** @var string */
	private static $token;

	/**
	 * @var string
	 */
	private static $nonAdminToken;

	/**
	 * Sets up a request to retrieve a token to be used in testcases.
	 */
	public static function setUpBeforeClass()
	{
		//The company to identify
		$companyName = 'companydemo';
		$contentString = '{"'.Constants::QUERY_PARAM_COMPANY_NAME.'": "'.$companyName.'"}';

		//Authorization - Basic authentication header
		$headers = array(
			'PHP_AUTH_USER' => 'pieter', //Test user
			'PHP_AUTH_PW' => 'XGthJU1234#',
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/auth',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		//Deserialize response
		$tokenDetails = json_decode($response->getContent(), true);

		if (sizeof($tokenDetails) > 0) {
			self::$token = $tokenDetails['data']['token'];
		}

		// Set up nonAdminToken
		//Authorization - Basic authentication header
		$headers = array(
			'PHP_AUTH_USER' => 'chrisbos', //Test user
			'PHP_AUTH_PW' => 'XGthJU1234#',
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/auth',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		//Deserialize response
		$tokenDetails = json_decode($response->getContent(), true);

		if (sizeof($tokenDetails) > 0) {
			self::$nonAdminToken = $tokenDetails['data']['token'];
		}

		// Bulletin classes to use mock clock
		ClockMock::register(__CLASS__);
		ClockMock::register(BulletinRepository::class);
	}

	public static function tearDownAfterClass()
	{
		ClockMock::withClockMock(false);
	}

	/**
	 * Tests the retrieval of bulletins with full date range that user without manager roles is allowed to see.
	 */
	public function testGetBulletinsWithDateRangeWithNonManagerRestrictionCheck()
	{
		$dateFrom = '2014-01-01';
		$dateTo = '2017-12-01';
		$officeId = 55;

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$nonAdminToken,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/bulletins?dateFrom='.$dateFrom.'&dateTo='.$dateTo.'&officeId='.$officeId,
			array(),
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is OK
		$this->assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

		// Test that response is not empty
		$this->assertNotEmpty($response->getContent());

		// Test if Content-Type is valid application/json
		$this->assertSame(HttpHeader::APPLICATION_JSON, $response->headers->get(HttpHeader::CONTENT_TYPE));

		//Deserialize response
		$bulletins = json_decode($response->getContent(), true);

		//Test that the association array contains data
		$this->assertArrayHasKey('data', $bulletins);

		//Test if exactly 4 bulletins are returned
		$this->assertCount(4, $bulletins['data'], 'Retrieved number of bulletins does not match expected');

		//Test that each bulletin has at minimum the below values
		foreach ($bulletins['data'] as $bulletin) {
			$this->assertArrayHasKey('id', $bulletin);
			$this->assertArrayHasKey('start_date', $bulletin);
			$this->assertArrayHasKey('end_date', $bulletin);
			$this->assertArrayHasKey('department', $bulletin);
			$this->assertArrayHasKey('title', $bulletin);
			$this->assertArrayHasKey('id', $bulletin['department']);
			$this->assertArrayHasKey('name', $bulletin['department']);
		}
	}

	/**
	 * Tests the retrieval of bulletins with date range that current logged in client is allowed to see.
	 */
	public function testGetBulletinsWithDateRangeWithRestrictionCheck()
	{
		$dateFrom = '2014-01-01';
		$dateTo = '2016-12-01';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/bulletins?dateFrom='.$dateFrom.'&dateTo='.$dateTo,
			array(),
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is OK
		$this->assertSame(Response::HTTP_OK, $response->getStatusCode());

		// Test that response is not empty
		$this->assertNotEmpty($response->getContent());

		// Test if Content-Type is valid application/json
		$this->assertSame(HttpHeader::APPLICATION_JSON, $response->headers->get(HttpHeader::CONTENT_TYPE));

		//Deserialize response
		$bulletins = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($bulletins) > 0);

		//Test if exactly 6 bulletins are returned
		$this->assertTrue(1 === count($bulletins['data']));

		//Test that each bulletin has at minimum the below values
		foreach ($bulletins['data'] as $bulletin) {
			$this->assertArrayHasKey('id', $bulletin);
			$this->assertArrayHasKey('start_date', $bulletin);
			$this->assertArrayHasKey('end_date', $bulletin);
			$this->assertArrayHasKey('department', $bulletin);
			$this->assertArrayHasKey('title', $bulletin);
			$this->assertArrayHasKey('id', $bulletin['department']);
			$this->assertArrayHasKey('name', $bulletin['department']);
		}
	}

	/**
	 * Tests the retrieval of bulletins with only dateFrom query parameter that current logged in client is allowed to see.
	 */
	public function testGetBulletinsWithOnlyDateFromWithRestrictionCheck()
	{
		$dateFrom = '2014-03-01';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/bulletins?dateFrom='.$dateFrom,
			array(),
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is OK
		$this->assertSame(Response::HTTP_OK, $response->getStatusCode());

		// Test that response is not empty
		$this->assertNotEmpty($response->getContent());

		// Test if Content-Type is valid application/json
		$this->assertSame(HttpHeader::APPLICATION_JSON, $response->headers->get(HttpHeader::CONTENT_TYPE));

		//Deserialize response
		$bulletins = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($bulletins) > 0);

		//Test if exactly 3 bulletins are returned
		$this->assertTrue(1 === count($bulletins['data']));

		//Test that each bulletin has at minimum the below values
		foreach ($bulletins['data'] as $bulletin) {
			$this->assertArrayHasKey('id', $bulletin);
			$this->assertArrayHasKey('start_date', $bulletin);
			$this->assertArrayHasKey('end_date', $bulletin);
			$this->assertArrayHasKey('department', $bulletin);
			$this->assertArrayHasKey('title', $bulletin);
			$this->assertArrayHasKey('id', $bulletin['department']);
			$this->assertArrayHasKey('name', $bulletin['department']);
		}
	}

	/**
	 * Tests the retrieval of bulletins with only dateTo query parameter that current logged in client is allowed to see.
	 */
	public function testGetBulletinsWithOnlyDateToWithRestrictionCheck()
	{
		$dateTo = '2014-03-29';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/bulletins?dateTo='.$dateTo,
			array(),
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is OK
		$this->assertSame(Response::HTTP_OK, $response->getStatusCode());

		// Test that response is not empty
		$this->assertNotEmpty($response->getContent());

		// Test if Content-Type is valid application/json
		$this->assertSame(HttpHeader::APPLICATION_JSON, $response->headers->get(HttpHeader::CONTENT_TYPE));

		//Deserialize response
		$bulletins = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($bulletins) > 0);

		//Test if exactly 3 bulletins are returned
		$this->assertTrue(1 === count($bulletins['data']));

		//Test that each bulletin has at minimum the below values
		foreach ($bulletins['data'] as $bulletin) {
			$this->assertArrayHasKey('id', $bulletin);
			$this->assertArrayHasKey('start_date', $bulletin);
			$this->assertArrayHasKey('end_date', $bulletin);
			$this->assertArrayHasKey('department', $bulletin);
			$this->assertArrayHasKey('title', $bulletin);
			$this->assertArrayHasKey('id', $bulletin['department']);
			$this->assertArrayHasKey('name', $bulletin['department']);
		}
	}

	/**
	 * Tests the retrieval of bulletins without date params that current logged in client is allowed to see, which defaults at bulletins of current month.
	 *
	 * @group time-sensitive
	 */
	public function testGetBulletinsWithoutDateQueryParamsWithRestrictionCheck()
	{
		//mock current time in registered classes
		ClockMock::withClockMock(strtotime('2014-03-05 01:00:00'));

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/bulletins',
			array(),
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is OK
		$this->assertSame(Response::HTTP_OK, $response->getStatusCode());

		// Test that response is not empty
		$this->assertNotEmpty($response->getContent());

		// Test if Content-Type is valid application/json
		$this->assertSame(HttpHeader::APPLICATION_JSON, $response->headers->get(HttpHeader::CONTENT_TYPE));

		//Deserialize response
		$bulletins = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($bulletins) > 0);

		//Test if exactly 3 bulletins are returned
		$this->assertTrue(1 === count($bulletins['data']));

		//Test that each bulletin has at minimum the below values
		foreach ($bulletins['data'] as $bulletin) {
			$this->assertArrayHasKey('id', $bulletin);
			$this->assertArrayHasKey('start_date', $bulletin);
			$this->assertArrayHasKey('end_date', $bulletin);
			$this->assertArrayHasKey('department', $bulletin);
			$this->assertArrayHasKey('title', $bulletin);
			$this->assertArrayHasKey('id', $bulletin['department']);
			$this->assertArrayHasKey('name', $bulletin['department']);
		}
	}

	/**
	 * Test Get single Bulletin by ID.
	 */
	public function testGetSingleBulletinWithRestrictionCheck()
	{
		$bulletinId = 7;
		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/bulletins/'.$bulletinId,
			array(),
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is OK
		$this->assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

		// Test that response is not empty
		$this->assertNotEmpty($response->getContent());

		// Test if Content-Type is valid application/json
		$this->assertSame(HttpHeader::APPLICATION_JSON, $response->headers->get(HttpHeader::CONTENT_TYPE));

		//Deserialize response
		$bulletin = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($bulletin) > 0);

		$bulletin = $bulletin['data'];
		//Test that each bulletin has at minimum the below values
		$this->assertArrayHasKey('id', $bulletin);
		$this->assertEquals($bulletinId, $bulletin['id']);
		$this->assertArrayHasKey('start_date', $bulletin);
		$this->assertArrayHasKey('end_date', $bulletin);
		$this->assertArrayHasKey('department', $bulletin);
		$this->assertArrayHasKey('title', $bulletin);
		$this->assertArrayHasKey('description', $bulletin);
		$this->assertArrayHasKey('id', $bulletin['department']);
		$this->assertArrayHasKey('name', $bulletin['department']);
	}

	/**
	 * Test fail get single Bulletin with unknown ID.
	 */
	public function testFailedGetSingleBulletinNotFound()
	{
		$bulletinId = 99999999999;
		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/bulletins/'.$bulletinId,
			array(),
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is NOT FOUND 404
		$this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode(), $response->getContent());
	}
}
