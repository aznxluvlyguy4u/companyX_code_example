<?php

namespace AppBundle\Tests\Controller;

use AppBundle\Entity\SystemConfig;
use AppBundle\Enumerator\EligibleEmployeeState;
use AppBundle\Enumerator\HttpHeader;
use AppBundle\Enumerator\SystemConfigKey;
use AppBundle\Repository\AssignmentRepository;
use AppBundle\Repository\RegisterRepository;
use AppBundle\Repository\SystemConfigRepository;
use AppBundle\Util\Constants;
use Doctrine\ORM\EntityManager;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AssignmentApiControllerTest.
 */
class AssignmentApiControllerTest extends WebTestCase
{
	/** @var string */
	private static $token;

	/** @var string */
	private static $nonAdminToken;

	/**
	 * @var EntityManager
	 */
	private static $em;

	/**
	 * @var SystemConfigRepository
	 */
	private static $systemConfigRepo;

	/** @var array */
	private static $originalSystemConfigValues = [];

	/** @var array */
	private static $createdSystemConfigs = [];

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

		// Assignment classes to use mock clock
		ClockMock::register(__CLASS__);
		ClockMock::register(AssignmentRepository::class);
		ClockMock::register(RegisterRepository::class);

		// set up entity manager
		self::bootKernel();
		self::$em = static::$kernel->getContainer()
			->get('doctrine')
			->getManager('customer');

		self::$systemConfigRepo = self::$em->getRepository(SystemConfig::class);

		/** @var SystemConfig $dorOpenshiftsClickToShowShifts */
		$dorOpenshiftsClickToShowShifts = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS);
		if ($dorOpenshiftsClickToShowShifts) {
			self::$originalSystemConfigValues[SystemConfigKey::DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS] = $dorOpenshiftsClickToShowShifts->getNormalizedValue();
		} else {
			// create the system config for test but delete it lateron
			self::$em->clear();
			$newDorOpenshiftsClockToShowShifts = new SystemConfig();
			$newDorOpenshiftsClockToShowShifts->setKey(SystemConfigKey::DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS);
			// Set default DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS true
			$newDorOpenshiftsClockToShowShifts->setValue('yes');
			self::$em->persist($newDorOpenshiftsClockToShowShifts);
			self::$em->flush();
			self::$em->refresh($newDorOpenshiftsClockToShowShifts);
			self::$originalSystemConfigValues[SystemConfigKey::DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS] = true;
			self::$createdSystemConfigs[] = $newDorOpenshiftsClockToShowShifts;
		}

		/** @var SystemConfig $dorOpenshiftsShowIfScheduled */
		$dorOpenshiftsShowIfScheduled = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_OPENSHIFTS_SHOW_IF_SCHEDULED);
		if ($dorOpenshiftsShowIfScheduled) {
			self::$originalSystemConfigValues[SystemConfigKey::DOR_OPENSHIFTS_SHOW_IF_SCHEDULED] = $dorOpenshiftsShowIfScheduled->getNormalizedValue();
		} else {
			// create the system config for test but delete it lateron
			self::$em->clear();
			$newDorOpenshiftsShowIfScheduled = new SystemConfig();
			$newDorOpenshiftsShowIfScheduled->setKey(SystemConfigKey::DOR_OPENSHIFTS_SHOW_IF_SCHEDULED);
			// Set default DOR_OPENSHIFTS_SHOW_IF_SCHEDULED true
			$newDorOpenshiftsShowIfScheduled->setValue(true);
			self::$em->persist($newDorOpenshiftsShowIfScheduled);
			self::$em->flush();
			self::$em->refresh($newDorOpenshiftsShowIfScheduled);
			self::$originalSystemConfigValues[SystemConfigKey::DOR_OPENSHIFTS_SHOW_IF_SCHEDULED] = true;
			self::$createdSystemConfigs[] = $newDorOpenshiftsShowIfScheduled;
		}

		self::$em->clear();
	}

	public static function tearDownAfterClass()
	{
		ClockMock::withClockMock(false);
		self::$em->clear();

		// Set all systemConfigs to original values
		foreach (self::$originalSystemConfigValues as $systemConfig => $value) {
			/** @var SystemConfig $alteredSystemConfig */
			$alteredSystemConfig = self::$systemConfigRepo->findOneByKey($systemConfig);
			$alteredSystemConfig->setValue($value);
		}

		// Delete created SystemConfigs for tests
		foreach (self::$createdSystemConfigs as $createdSystemConfig) {
			$toBeDeletedSystemConfig = self::$systemConfigRepo->find($createdSystemConfig->getId());
			self::$em->remove($toBeDeletedSystemConfig);
		}

		self::$em->flush();
		self::$em->close();
		self::$em = null; // avoid memory leaks
	}

	/**
	 * Tests the retrieval of assignments without breaks for a non admin client because DOR_SCHEDULE_HIDE_BREAKS is true.
	 */
	public function testGetAssignmentsWithDateRangeWithNonAdminTokenWithoutBreakDuration()
	{
		/** @var SystemConfig $dorScheduleHideBreaks */
		$dorScheduleHideBreaks = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_SCHEDULE_HIDE_BREAKS);

		// Set DOR_SCHEDULE_HIDE_BREAKS true
		$newDorScheduleHideBreaks = new SystemConfig();
		$newDorScheduleHideBreaks->setKey(SystemConfigKey::DOR_SCHEDULE_HIDE_BREAKS);
		$newDorScheduleHideBreaks->setValue(true);
		self::$em->clear();
		self::$em->persist($newDorScheduleHideBreaks);
		self::$em->flush();
		self::$em->refresh($newDorScheduleHideBreaks);

		$originalSystemConfigValues = [];
		$createdSystemConfigs = [];
		if ($dorScheduleHideBreaks) { // edit the system config for test but restore it later on
			$originalSystemConfigValues[SystemConfigKey::DOR_SCHEDULE_HIDE_BREAKS] = $dorScheduleHideBreaks->getNormalizedValue();
		} else {
			$createdSystemConfigs[SystemConfigKey::DOR_SCHEDULE_HIDE_BREAKS] = $newDorScheduleHideBreaks;
		}

		$dateFrom = '2017-04-01';
		$dateTo = '2017-04-20';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$nonAdminToken,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/assignments?dateFrom='.$dateFrom.'&dateTo='.$dateTo,
			array(),
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Set all systemConfigs to original values
		foreach ($originalSystemConfigValues as $systemConfig => $value) {
			/** @var SystemConfig $alteredSystemConfig */
			$alteredSystemConfig = self::$systemConfigRepo->findOneByKey($systemConfig);
			$alteredSystemConfig->setValue($value);
		}
		// Delete created SystemConfigs for tests
		foreach ($createdSystemConfigs as $createdSystemConfig) {
			$toBeDeletedSystemConfig = self::$systemConfigRepo->find($createdSystemConfig->getId());
			self::$em->remove($toBeDeletedSystemConfig);
		}
		self::$em->flush();

		// Test if response is OK
		$this->assertSame(Response::HTTP_OK, $response->getStatusCode());

		// Test that response is not empty
		$this->assertNotEmpty($response->getContent());

		// Test if Content-Type is valid application/json
		$this->assertSame(HttpHeader::APPLICATION_JSON, $response->headers->get(HttpHeader::CONTENT_TYPE));

		//Deserialize response
		$assignments = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($assignments) > 0);

		//Test if exactly 160 assignments are returned
		$this->assertTrue(160 === count($assignments['data']));

		foreach ($assignments['data'] as $assignment) {
			//Test that each assignment has at minimum the below values
			$this->assertArrayHasKey('id', $assignment);
			$this->assertArrayHasKey('start_date', $assignment);
			$this->assertArrayHasKey('department', $assignment);
			$this->assertArrayHasKey('employee', $assignment);
			$this->assertArrayHasKey('id', $assignment['employee']);
			//Test that break duration is not given
			$this->assertArrayNotHasKey('break_duration', $assignment);
		}
	}

	/**
	 * Tests the retrieval of assignments with date range that non admin client is allowed to see.
	 */
	public function testGetAssignmentsWithDateRangeWithNonAdminToken()
	{
		$dateFrom = '2017-04-01';
		$dateTo = '2017-04-20';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$nonAdminToken,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/assignments?dateFrom='.$dateFrom.'&dateTo='.$dateTo,
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
		$assignments = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($assignments) > 0);

		//Test if exactly 160 assignments are returned
		$this->assertTrue(160 === count($assignments['data']));

		//Test that each assignment has at minimum the below values
		foreach ($assignments['data'] as $assignment) {
			$this->assertArrayHasKey('id', $assignment);
			$this->assertArrayHasKey('start_date', $assignment);
			$this->assertArrayHasKey('break_duration', $assignment);
			$this->assertArrayHasKey('department', $assignment);
			$this->assertArrayHasKey('employee', $assignment);
			$this->assertArrayHasKey('id', $assignment['employee']);
		}
	}

	/**
	 * Tests the retrieval of assignments with officeId query paramter that a client that is assigned to multiple offices is allowed to see.
	 */
	public function testGetAssignmentsWithOfficeIdWithNonAdminToken()
	{
		$dateFrom = '2017-05-01';
		$officeId1 = 55;
		$officeId2 = 105;
		$invalidOfficeId = 99999999;

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$nonAdminToken,
		);

		/**
		 * Test get assignments of only office 55.
		 */
		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/assignments?dateFrom='.$dateFrom.'&officeId='.$officeId1,
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
		$assignments = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($assignments) > 0);

		//Test if exactly 29 assignments are returned
		$this->assertTrue(29 === count($assignments['data']));

		//Test that each assignment has at minimum the below values
		foreach ($assignments['data'] as $assignment) {
			$this->assertArrayHasKey('id', $assignment);
			$this->assertArrayHasKey('start_date', $assignment);
			$this->assertArrayHasKey('break_duration', $assignment);
			$this->assertArrayHasKey('department', $assignment);
			$this->assertArrayHasKey('employee', $assignment);
			$this->assertArrayHasKey('id', $assignment['employee']);
			$flattenedName = $assignment['department']['flattened_name'];
			// Test that all returned Assignments belong to the given office
			$this->assertStringStartsWith('Productie > Productie >', $flattenedName);
		}

		/**
		 * Test get assignments of only office 105.
		 */
		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/assignments?dateFrom='.$dateFrom.'&officeId='.$officeId2,
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
		$assignments = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($assignments) > 0);

		//Test if exactly 102 assignments are returned
		$this->assertTrue(102 === count($assignments['data']));

		//Test that each assignment has at minimum the below values
		foreach ($assignments['data'] as $assignment) {
			$this->assertArrayHasKey('id', $assignment);
			$this->assertArrayHasKey('start_date', $assignment);
			$this->assertArrayHasKey('break_duration', $assignment);
			$this->assertArrayHasKey('department', $assignment);
			$this->assertArrayHasKey('employee', $assignment);
			$this->assertArrayHasKey('id', $assignment['employee']);
			$flattenedName = $assignment['department']['flattened_name'];
			// Test that all returned Assignments belong to the given office
			$this->assertStringStartsWith('Algemeen > Afdeling A >', $flattenedName);
		}

		/**
		 * Test get assignments of an invalid office.
		 */
		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/assignments?dateFrom='.$dateFrom.'&officeId='.$invalidOfficeId,
			array(),
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is NOT FOUND 404
		$this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
	}

	/**
	 * Tests the retrieval of assignments with date range that admin client is allowed to see.
	 */
	public function testGetAssignmentsWithDateRangeWithAdminToken()
	{
		$dateFrom = '2017-04-01';
		$dateTo = '2017-04-20';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/assignments?dateFrom='.$dateFrom.'&dateTo='.$dateTo,
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
		$assignments = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($assignments) > 0);

		//Test if exactly 57 assignments are returned
		$this->assertTrue(57 === count($assignments['data']));

		//Test that each assignment has at minimum the below values
		foreach ($assignments['data'] as $assignment) {
			$this->assertArrayHasKey('id', $assignment);
			$this->assertArrayHasKey('start_date', $assignment);
			$this->assertArrayHasKey('break_duration', $assignment);
			$this->assertArrayHasKey('department', $assignment);
			$this->assertArrayHasKey('employee', $assignment);
			$this->assertArrayHasKey('id', $assignment['employee']);
		}
	}

	/**
	 * Tests the retrieval of assignments with only dateFrom query parameter that the non admin client is allowed to see.
	 */
	public function testGetAssignmentsWithOnlyDateFromWithNonAdminToken()
	{
		$dateFrom = '2017-04-15';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$nonAdminToken,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/assignments?dateFrom='.$dateFrom,
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
		$assignments = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($assignments) > 0);

		//Test if exactly 136 assignments are returned
		$this->assertTrue(136 === count($assignments['data']));

		//Test that each assignment has at minimum the below values
		foreach ($assignments['data'] as $assignment) {
			$this->assertArrayHasKey('id', $assignment);
			$this->assertArrayHasKey('start_date', $assignment);
			$this->assertArrayHasKey('break_duration', $assignment);
			$this->assertArrayHasKey('department', $assignment);
			$this->assertArrayHasKey('employee', $assignment);
			$this->assertArrayHasKey('id', $assignment['employee']);
		}
	}

	/**
	 * Tests the retrieval of assignments with only dateFrom query parameter that the admin client is allowed to see.
	 */
	public function testGetAssignmentsWithOnlyDateFromWithAdminToken()
	{
		$dateFrom = '2017-04-15';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/assignments?dateFrom='.$dateFrom,
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
		$assignments = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($assignments) > 0);

		//Test if exactly 62 assignments are returned
		$this->assertTrue(62 === count($assignments['data']));

		//Test that each assignment has at minimum the below values
		foreach ($assignments['data'] as $assignment) {
			$this->assertArrayHasKey('id', $assignment);
			$this->assertArrayHasKey('start_date', $assignment);
			$this->assertArrayHasKey('break_duration', $assignment);
			$this->assertArrayHasKey('department', $assignment);
			$this->assertArrayHasKey('employee', $assignment);
			$this->assertArrayHasKey('id', $assignment['employee']);
		}
	}

	/**
	 * Tests the retrieval of assignments with only dateTo query parameter that a non admin client is allowed to see.
	 */
	public function testGetAssignmentsWithOnlyDateToWithNonAdminToken()
	{
		$dateTo = '2017-04-15';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$nonAdminToken,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/assignments?dateTo='.$dateTo,
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
		$assignments = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($assignments) > 0);

		//Test if exactly 109 assignments are returned
		$this->assertTrue(109 === count($assignments['data']));

		//Test that each assignment has at minimum the below values
		foreach ($assignments['data'] as $assignment) {
			$this->assertArrayHasKey('id', $assignment);
			$this->assertArrayHasKey('start_date', $assignment);
			$this->assertArrayHasKey('break_duration', $assignment);
			$this->assertArrayHasKey('department', $assignment);
			$this->assertArrayHasKey('employee', $assignment);
			$this->assertArrayHasKey('id', $assignment['employee']);
		}
	}

	/**
	 * Tests the retrieval of assignments with only dateTo query parameter that a admin client is allowed to see.
	 */
	public function testGetAssignmentsWithOnlyDateToWithAdminToken()
	{
		$dateTo = '2017-04-15';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/assignments?dateTo='.$dateTo,
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
		$assignments = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($assignments) > 0);

		//Test if exactly 31 assignments are returned
		$this->assertTrue(31 === count($assignments['data']));

		//Test that each assignment has at minimum the below values
		foreach ($assignments['data'] as $assignment) {
			$this->assertArrayHasKey('id', $assignment);
			$this->assertArrayHasKey('start_date', $assignment);
			$this->assertArrayHasKey('break_duration', $assignment);
			$this->assertArrayHasKey('department', $assignment);
			$this->assertArrayHasKey('employee', $assignment);
			$this->assertArrayHasKey('id', $assignment['employee']);
		}
	}

	/**
	 * Tests the retrieval of assignments without date params that current logged in client is allowed to see, which defaults at assignments of current month.
	 *
	 * @group time-sensitive
	 */
	public function testGetAssignmentsWithoutDateQueryParamsWithAdminToken()
	{
		//mock current time in registered classes
		ClockMock::withClockMock(strtotime('2017-04-05 01:00:00'));

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/assignments',
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
		$assignments = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($assignments) > 0);

		//Test if exactly 93 assignments are returned
		$this->assertTrue(93 === count($assignments['data']));

		//Test that each assignment has at minimum the below values
		foreach ($assignments['data'] as $assignment) {
			$this->assertArrayHasKey('id', $assignment);
			$this->assertArrayHasKey('start_date', $assignment);
			$this->assertArrayHasKey('break_duration', $assignment);
			$this->assertArrayHasKey('department', $assignment);
			$this->assertArrayHasKey('employee', $assignment);
			$this->assertArrayHasKey('id', $assignment['employee']);
		}
	}

	/**
	 * Test Get single Assignment by ID.
	 */
	public function testGetSingleAssignment()
	{
		$assignmentId = 102457;
		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/assignments/'.$assignmentId,
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
		$assignment = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($assignment) > 0);

		$assignment = $assignment['data'];
		//Test that each assignment has at minimum the below values
		$this->assertArrayHasKey('id', $assignment);
		$this->assertEquals($assignmentId, $assignment['id']);
		$this->assertArrayHasKey('start_date', $assignment);
		$this->assertArrayHasKey('client_defined_end_date', $assignment);
		$this->assertArrayHasKey('break_duration', $assignment);
		$this->assertArrayHasKey('department', $assignment);
		$this->assertArrayHasKey('employee', $assignment);
		$this->assertArrayHasKey('id', $assignment['employee']);
		$this->assertEquals(52, $assignment['employee']['id']);
	}

	/**
	 * Test fail get single Assignment with unknown ID.
	 */
	public function testFailedGetSingleAssignmentNotFound()
	{
		$assignmentId = 99999999999;
		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/assignments/'.$assignmentId,
			array(),
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is NOT FOUND 404
		$this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
	}

	/**
	 * Tests the retrieval of unassigned assignments that current logged in client is allowed to see, which defaults at unassigned assignments of current month.
	 */
	public function testGetUnAssignmentsWithOnlyDateFrom()
	{
		// manually set DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS on if its off;
		$currentDorOpenShiftsClickToShowShiftsState = self::$originalSystemConfigValues[SystemConfigKey::DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS];
		if (!$currentDorOpenShiftsClickToShowShiftsState) {
			/** @var SystemConfig $dorOpenShiftsClickToShowShifts */
			$dorOpenShiftsClickToShowShifts = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS);
			$dorOpenShiftsClickToShowShifts->setValue('yes');
			self::$em->flush();
			self::$em->refresh($dorOpenShiftsClickToShowShifts);
			$currentDorOpenShiftsClickToShowShiftsState = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS)->getNormalizedValue();
		}

		// get unassigned assignments planned FAR in the future, as API won't return unassigned assignments planned in the past
		// TODO Create new assignment records dynamically x days ahead of current date in this test when Assignment CRUD is available
		$dateFrom = '2019-01-01';
		$assignmentState = 'unassigned';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/assignments?dateFrom='.$dateFrom.'&assignmentState='.$assignmentState,
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
		$unassignedAssignments = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($unassignedAssignments) > 0);

		//Test that each assignment has at minimum the below values
		foreach ($unassignedAssignments['data'] as $unassignedAssignment) {
			$this->assertArrayHasKey('id', $unassignedAssignment);
			$this->assertArrayHasKey('start_date', $unassignedAssignment);
			$this->assertArrayHasKey('break_duration', $unassignedAssignment);
			$this->assertArrayHasKey('department', $unassignedAssignment);
			$this->assertArrayNotHasKey('employee', $unassignedAssignment);
		}

		// return DOR_SCHEDULE_NO_ENDTIMES to original state
		if ($currentDorOpenShiftsClickToShowShiftsState !== self::$originalSystemConfigValues[SystemConfigKey::DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS]) {
			/** @var SystemConfig $dorOpenShiftsClickToShowShifts */
			$dorOpenShiftsClickToShowShifts = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS);
			$dorOpenShiftsClickToShowShifts->setValue('no');
			self::$em->flush();
			self::$em->refresh($dorOpenShiftsClickToShowShifts);
		}
	}

	/**
	 * Tests the retrieval of unassigned assignments that current logged in client is allowed to see, unassigned assignments in the past are not shown.
	 */
	public function testGetUnAssignmentsWithOnlyDateFromInThePastNotFound()
	{
		// manually set DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS on if its off;
		$currentDorOpenShiftsClickToShowShiftsState = self::$originalSystemConfigValues[SystemConfigKey::DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS];
		if (!$currentDorOpenShiftsClickToShowShiftsState) {
			/** @var SystemConfig $dorOpenShiftsClickToShowShifts */
			$dorOpenShiftsClickToShowShifts = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS);
			$dorOpenShiftsClickToShowShifts->setValue('yes');
			self::$em->flush();
			self::$em->refresh($dorOpenShiftsClickToShowShifts);
			$currentDorOpenShiftsClickToShowShiftsState = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS)->getNormalizedValue();
		}

		// get unassigned assignments planned FAR in the future, as API won't return unassigned assignments planned in the past
		// TODO Create new assignment records dynamically x days ahead of current date in this test when Assignment CRUD is available
		$dateFrom = '2017-05-01';
		$assignmentState = 'unassigned';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/assignments?dateFrom='.$dateFrom.'&assignmentState='.$assignmentState,
			array(),
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is NOT FOUND 404
		$this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());

		// return DOR_SCHEDULE_NO_ENDTIMES to original state
		if ($currentDorOpenShiftsClickToShowShiftsState !== self::$originalSystemConfigValues[SystemConfigKey::DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS]) {
			/** @var SystemConfig $dorOpenShiftsClickToShowShifts */
			$dorOpenShiftsClickToShowShifts = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS);
			$dorOpenShiftsClickToShowShifts->setValue('no');
			self::$em->flush();
			self::$em->refresh($dorOpenShiftsClickToShowShifts);
		}
	}

	/**
	 * Tests the retrieval of both assigned and unassigned assignments that current logged in client is allowed to see.
	 */
	public function testGetAssignmentsWithAssignmentStateIsAllWithNonAdminToken()
	{
		// manually set DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS on if its off;
		$currentDorOpenShiftsClickToShowShiftsState = self::$originalSystemConfigValues[SystemConfigKey::DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS];
		if (!$currentDorOpenShiftsClickToShowShiftsState) {
			/** @var SystemConfig $dorOpenShiftsClickToShowShifts */
			$dorOpenShiftsClickToShowShifts = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS);
			$dorOpenShiftsClickToShowShifts->setValue('yes');
			self::$em->flush();
			self::$em->refresh($dorOpenShiftsClickToShowShifts);
			$currentDorOpenShiftsClickToShowShiftsState = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS)->getNormalizedValue();
		}

		// get unassigned assignments planned FAR in the future, as API won't return unassigned assignments planned in the past
		// TODO Create new assignment records dynamically x days ahead of current date in this test when Assignment CRUD is available
		$dateFrom = '2019-01-01';
		$assignmentState = 'all';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$nonAdminToken,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/assignments?dateFrom='.$dateFrom.'&assignmentState='.$assignmentState,
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
		$assignments = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($assignments) > 0);

		//Test that each assignment has at minimum the below values
		$this->assertArrayHasKey('assigned_assignments', $assignments['data']);
		$this->assertArrayHasKey('unassigned_assignments', $assignments['data']);

		$assignedAssignments = $assignments['data']['assigned_assignments'];
		$unassignedAssignments = $assignments['data']['unassigned_assignments'];

		//Test if exactly 3 unassigned assignment is returned
		$this->assertTrue(3 === count($unassignedAssignments));
		//Test if exactly 3 assigned assignments are returned
		$this->assertTrue(3 === count($assignedAssignments));

		//Test that each assignment has at minimum the below values
		foreach ($unassignedAssignments as $unassignedAssignment) {
			$this->assertArrayHasKey('id', $unassignedAssignment);
			$this->assertArrayHasKey('start_date', $unassignedAssignment);
			$this->assertArrayHasKey('break_duration', $unassignedAssignment);
			$this->assertArrayHasKey('department', $unassignedAssignment);
			$this->assertArrayNotHasKey('employee', $unassignedAssignment);
		}

		//Test that each assignment has at minimum the below values
		foreach ($assignedAssignments as $assignedAssignment) {
			$this->assertArrayHasKey('id', $assignedAssignment);
			$this->assertArrayHasKey('start_date', $assignedAssignment);
			$this->assertArrayHasKey('break_duration', $assignedAssignment);
			$this->assertArrayHasKey('department', $assignedAssignment);
			$this->assertArrayHasKey('employee', $assignedAssignment);
			$this->assertArrayHasKey('id', $assignedAssignment['employee']);
		}

		// return DOR_SCHEDULE_NO_ENDTIMES to original state
		if ($currentDorOpenShiftsClickToShowShiftsState !== self::$originalSystemConfigValues[SystemConfigKey::DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS]) {
			/** @var SystemConfig $dorOpenShiftsClickToShowShifts */
			$dorOpenShiftsClickToShowShifts = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS);
			$dorOpenShiftsClickToShowShifts->setValue('no');
			self::$em->flush();
			self::$em->refresh($dorOpenShiftsClickToShowShifts);
		}
	}

	/**
	 * Tests the retrieval of unassigned assignments with DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS and DOR_OPENSHIFTS_SHOW_IF_SCHEDULED setting.
	 */
	public function testGetUnAssignmentsWithDorOpenShiftsClickToShowShifts()
	{
		// manually set DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS on if its off;
		$currentDorOpenShiftsClickToShowShiftsState = self::$originalSystemConfigValues[SystemConfigKey::DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS];
		if (!$currentDorOpenShiftsClickToShowShiftsState) {
			/** @var SystemConfig $dorOpenShiftsClickToShowShifts */
			$dorOpenShiftsClickToShowShifts = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS);
			$dorOpenShiftsClickToShowShifts->setValue('yes');
			self::$em->flush();
			self::$em->refresh($dorOpenShiftsClickToShowShifts);
			$currentDorOpenShiftsClickToShowShiftsState = $dorOpenShiftsClickToShowShifts->getNormalizedValue();
		}

		// manually set DOR_OPENSHIFTS_SHOW_IF_SCHEDULED on if its off;
		$currentDorOpenShiftsShowIfScheduledState = self::$originalSystemConfigValues[SystemConfigKey::DOR_OPENSHIFTS_SHOW_IF_SCHEDULED];
		if (!$currentDorOpenShiftsShowIfScheduledState) {
			/** @var SystemConfig $dorOpenShiftsShowIfScheduled */
			$dorOpenShiftsShowIfScheduled = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_OPENSHIFTS_SHOW_IF_SCHEDULED);
			$dorOpenShiftsShowIfScheduled->setValue(true);
			self::$em->flush();
			self::$em->refresh($dorOpenShiftsShowIfScheduled);
			$currentDorOpenShiftsShowIfScheduledState = $dorOpenShiftsShowIfScheduled->getNormalizedValue();
		}

		// get unassigned assignments planned FAR in the future, as API won't return unassigned assignments planned in the past
		// TODO Create new assignment records dynamically x days ahead of current date in this test when Assignment CRUD is available
		$dateFrom = '2019-01-01';
		$assignmentState = 'unassigned';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/assignments?dateFrom='.$dateFrom.'&assignmentState='.$assignmentState,
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
		$unassignedAssignments = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($unassignedAssignments) > 0);

		//Test if exactly 3 assignments are returned
		$this->assertTrue(3 === count($unassignedAssignments['data']));

		//Test that each assignment has at minimum the below values
		foreach ($unassignedAssignments['data'] as $unassignedAssignment) {
			$this->assertArrayHasKey('id', $unassignedAssignment);
			$this->assertArrayHasKey('start_date', $unassignedAssignment);
			$this->assertArrayHasKey('break_duration', $unassignedAssignment);
			$this->assertArrayHasKey('department', $unassignedAssignment);
			$this->assertArrayNotHasKey('employee', $unassignedAssignment);
		}

		// manually set DOR_OPENSHIFTS_SHOW_IF_SCHEDULED off;
		/** @var SystemConfig $dorOpenShiftsShowIfScheduled */
		$dorOpenShiftsShowIfScheduled = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_OPENSHIFTS_SHOW_IF_SCHEDULED);
		$dorOpenShiftsShowIfScheduled->setValue(false);
		self::$em->flush();
		self::$em->refresh($dorOpenShiftsShowIfScheduled);
		$currentDorOpenShiftsShowIfScheduledState = $dorOpenShiftsShowIfScheduled->getNormalizedValue();

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/assignments?dateFrom='.$dateFrom.'&assignmentState='.$assignmentState,
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
		$unassignedAssignments = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($unassignedAssignments) > 0);

		//Test if exactly 3 assignments are returned
		$this->assertTrue(1 === count($unassignedAssignments['data']));

		//Test that each assignment has at minimum the below values
		foreach ($unassignedAssignments['data'] as $unassignedAssignment) {
			$this->assertArrayHasKey('id', $unassignedAssignment);
			$this->assertEquals(104065, $unassignedAssignment['id']);
			$this->assertArrayHasKey('start_date', $unassignedAssignment);
			$this->assertArrayHasKey('break_duration', $unassignedAssignment);
			$this->assertArrayHasKey('department', $unassignedAssignment);
			$this->assertArrayNotHasKey('employee', $unassignedAssignment);
		}

		// manually set DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS off;
		/** @var SystemConfig $dorOpenShiftsClickToShowShifts */
		$dorOpenShiftsClickToShowShifts = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS);
		$dorOpenShiftsClickToShowShifts->setValue('no');
		self::$em->flush();
		self::$em->refresh($dorOpenShiftsClickToShowShifts);
		$currentDorOpenShiftsClickToShowShiftsState = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS)->getNormalizedValue();

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/assignments?dateFrom='.$dateFrom.'&assignmentState='.$assignmentState,
			array(),
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is NOT FOUND 404
		$this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());

		// return DOR_SCHEDULE_NO_ENDTIMES to original state
		if ($currentDorOpenShiftsClickToShowShiftsState !== self::$originalSystemConfigValues[SystemConfigKey::DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS]) {
			/** @var SystemConfig $dorOpenShiftsClickToShowShifts */
			$dorOpenShiftsClickToShowShifts = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS);
			$dorOpenShiftsClickToShowShifts->setValue('yes');
			self::$em->flush();
			self::$em->refresh($dorOpenShiftsClickToShowShifts);
		}

		// return DOR_SCHEDULE_NO_ENDTIMES to original state
		if ($currentDorOpenShiftsShowIfScheduledState !== self::$originalSystemConfigValues[SystemConfigKey::DOR_OPENSHIFTS_SHOW_IF_SCHEDULED]) {
			/** @var SystemConfig $dorOpenShiftsShowIfScheduled */
			$dorOpenShiftsShowIfScheduled = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_OPENSHIFTS_SHOW_IF_SCHEDULED);
			$dorOpenShiftsShowIfScheduled->setValue(self::$originalSystemConfigValues[SystemConfigKey::DOR_OPENSHIFTS_SHOW_IF_SCHEDULED]);
			self::$em->flush();
			self::$em->refresh($dorOpenShiftsShowIfScheduled);
			$currentDorOpenShiftsShowIfScheduledState = $dorOpenShiftsShowIfScheduled->getNormalizedValue();
		}
	}

	/**
	 * test get a list of eligible employees for shiftSwapRequest.
	 */
	public function testGetEligibleEmployeesForAssignment()
	{
		//The assignment to get eligible employees from
		$assignmentId = 104066;

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/assignments/'.$assignmentId.'/eligible_employees',
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
		$employees = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($employees) > 0);

		//Test if exactly 5 categories are returned
		$this->assertTrue(5 === count($employees['data']));

		$categories = $employees['data'];

		$this->assertArrayHasKey(EligibleEmployeeState::PREFERENCED_EMPLOYEES, $categories);
		$this->assertArrayHasKey(EligibleEmployeeState::AVAILABLE_EMPLOYEES, $categories);
		$this->assertArrayHasKey(EligibleEmployeeState::FREE_EMPLOYEES, $categories);
		$this->assertArrayHasKey(EligibleEmployeeState::UNAVAILABLE_EMPLOYEES, $categories);
		$this->assertArrayHasKey(EligibleEmployeeState::SCHEDULED_EMPLOYEES, $categories);

		$this->assertCount(0, $categories[EligibleEmployeeState::PREFERENCED_EMPLOYEES]);
		$this->assertCount(0, $categories[EligibleEmployeeState::AVAILABLE_EMPLOYEES]);
		$this->assertCount(9, $categories[EligibleEmployeeState::FREE_EMPLOYEES]);
		$this->assertCount(0, $categories[EligibleEmployeeState::UNAVAILABLE_EMPLOYEES]);
		$this->assertCount(1, $categories[EligibleEmployeeState::SCHEDULED_EMPLOYEES]);

		//Test that each person has at minimum the below values
		foreach ($employees['data'] as $category => $employees) {
			foreach ($employees as $employee) {
				$this->assertArrayHasKey('id', $employee);
				$this->assertArrayHasKey('firstname', $employee);
			}
		}
	}

	/**
	 * test get a list of eligible employees for shiftSwapRequest invalid assignment ID.
	 */
	public function testFailGetEligibleEmployeesForAssignmentNotFound()
	{
		//The assignment to get eligible employees from
		$assignmentId = 9999999;

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/assignments/'.$assignmentId.'/eligible_employees',
			array(),
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is NOT FOUND 404
		$this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
	}

	/**
	 * Test if unpublished Assignments are not returned.
	 */
	public function testGetUnpublishedAssignmentsNotFound()
	{
		//The unpublished assignment to try to get
		$assignmentId = 104067;
		$dateFrom = '2019-01-03';
		$dateTo = '2019-01-03';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/assignments?dateFrom='.$dateFrom.'&dateTo='.$dateTo,
			array(),
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is NOT FOUND 404
		$this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/assignments/'.$assignmentId,
			array(),
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is NOT FOUND 404
		$this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
	}
}
