<?php

namespace AppBundle\Tests\Controller;

use AppBundle\Entity\Client;
use AppBundle\Entity\LargeDataLog;
use AppBundle\Entity\Register;
use AppBundle\Entity\RegisterLog;
use AppBundle\Entity\SystemConfig;
use AppBundle\Enumerator\HttpHeader;
use AppBundle\Enumerator\SerializerGroup;
use AppBundle\Enumerator\SystemConfigKey;
use AppBundle\Repository\AssignmentRepository;
use AppBundle\Repository\RegisterLogRepository;
use AppBundle\Repository\RegisterRepository;
use AppBundle\Repository\SystemConfigRepository;
use DateTime;
use AppBundle\Util\Constants;
use Doctrine\ORM\EntityManager;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;

/**
 * Class RegisterApiControllerTest.
 */
class RegisterApiControllerTest extends WebTestCase
{
	/** @var string */
	private static $token;

	/** @var string */
	private static $nonAdminToken;

	/** @var string */
	private static $superUserToken;

	/** @var array */
	private static $createdRegisters = [];

	/**
	 * @var EntityManager
	 */
	private static $em;

	/** @var Serializer */
	private static $serializer;

	/**
	 * @var SystemConfigRepository
	 */
	private static $systemConfigRepo;

	/**
	 * @var RegisterRepository
	 */
	private static $registerRepo;

	/**
	 * @var RegisterLogRepository
	 */
	private static $registerLogRepo;

	/** @var Datetime */
	private static $originalDorScheduleVacationTimeoutState;

	/** @var bool */
	private static $originalDorScheduleUseVacationTimeoutForUnavailabilityState;

	/** @var int */
	private static $originalDorAvailabilityBlockPlannedState;

	/** @var int */
	private static $originalDorFteMwHourRegistrationState;

	/** @var array */
	private static $createdSystemConfigs = [];

	/**
	 * Sets up a request to retrieve a token to be used in testcases.
	 */
	public static function setUpBeforeClass()
	{
		//The company to identify
		$companyName = 'companydemo';
		$sessionId = 888888;
		$contentString = '{"'.Constants::QUERY_PARAM_COMPANY_NAME.'": "'.$companyName.'", "session_id": '.$sessionId.'}';

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

		//Set up non Administrator token
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

		//set up headquarter token
		//The company to identify
		$companyName = 'companydemo';
		$contentString = '{"'.Constants::QUERY_PARAM_COMPANY_NAME.'": "'.$companyName.'"}';

		//Authorization - Basic authentication header
		$headers = array(
			'PHP_AUTH_USER' => 'matthijs', //Test user with access to headquarter
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
			self::$superUserToken = $tokenDetails['data']['token'];
		}

		// Register classes to use mock clock
		ClockMock::register(__CLASS__);
		ClockMock::register(AssignmentRepository::class);
		ClockMock::register(RegisterRepository::class);

		// set up entity manager
		self::bootKernel();
		self::$em = static::$kernel->getContainer()
			->get('doctrine')
			->getManager('customer');

		self::$systemConfigRepo = self::$em->getRepository(SystemConfig::class);
		self::$registerRepo = self::$em->getRepository(Register::class);
		self::$registerLogRepo = self::$em->getRepository(RegisterLog::class);

		// load serializer
		self::$serializer = static::$kernel->getContainer()
			->get('serializer');

		/** @var SystemConfig $dorScheduleVacationTimeout */
		$dorScheduleVacationTimeout = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_SCHEDULE_VACATION_TIMEOUT);
		if ($dorScheduleVacationTimeout) {
			self::$originalDorScheduleVacationTimeoutState = $dorScheduleVacationTimeout->getNormalizedValue();
		} else {
			// create the system config for test but delete it lateron
			self::$em->clear();
			$newDorScheduleVacationTimeout = new SystemConfig();
			$newDorScheduleVacationTimeout->setKey(SystemConfigKey::DOR_SCHEDULE_VACATION_TIMEOUT);
			$newDorScheduleVacationTimeout->setValue('+1 month');
			self::$em->persist($newDorScheduleVacationTimeout);
			self::$em->flush();
			// Refresh to allow postload listeners to kick in
			self::$em->refresh($newDorScheduleVacationTimeout);
			self::$originalDorScheduleVacationTimeoutState = $newDorScheduleVacationTimeout;
			self::$createdSystemConfigs[] = $newDorScheduleVacationTimeout;
		}

		/** @var SystemConfig $dorScheduleUseVacationTimeoutForUnavailability */
		$dorScheduleUseVacationTimeoutForUnavailability = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_SCHEDULE_USE_VACATION_TIMEOUT_FOR_UNAVAILABILITY);
		if ($dorScheduleUseVacationTimeoutForUnavailability) {
			self::$originalDorScheduleUseVacationTimeoutForUnavailabilityState = $dorScheduleUseVacationTimeoutForUnavailability->getNormalizedValue();
		} else {
			// create the system config for test but delete it lateron
			self::$em->clear();
			$newDorScheduleUseVacationTimeoutForUnavailability = new SystemConfig();
			$newDorScheduleUseVacationTimeoutForUnavailability->setKey(SystemConfigKey::DOR_SCHEDULE_USE_VACATION_TIMEOUT_FOR_UNAVAILABILITY);
			$newDorScheduleUseVacationTimeoutForUnavailability->setValue('no');
			$newDorScheduleUseVacationTimeoutForUnavailability->setObjectId(0);
			self::$em->persist($newDorScheduleUseVacationTimeoutForUnavailability);
			self::$em->flush();
			// Refresh to allow postload listeners to kick in
			self::$em->refresh($newDorScheduleUseVacationTimeoutForUnavailability);
			self::$originalDorScheduleUseVacationTimeoutForUnavailabilityState = $newDorScheduleUseVacationTimeoutForUnavailability->getNormalizedValue();
			self::$createdSystemConfigs[] = $newDorScheduleUseVacationTimeoutForUnavailability;
		}

		/** @var SystemConfig $dorAvailabilityBlockPlanned */
		$dorAvailabilityBlockPlanned = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_SCHEDULE_AVAILABILITY_BLOCK_PLANNED);
		if ($dorAvailabilityBlockPlanned) {
			self::$originalDorAvailabilityBlockPlannedState = $dorAvailabilityBlockPlanned->getNormalizedValue();
		} else {
			// create the system config for test but delete it lateron
			self::$em->clear();
			$newDorAvailabilityBlockPlanned = new SystemConfig();
			$newDorAvailabilityBlockPlanned->setKey(SystemConfigKey::DOR_SCHEDULE_AVAILABILITY_BLOCK_PLANNED);
			$newDorAvailabilityBlockPlanned->setValue(0);
			$newDorAvailabilityBlockPlanned->setObjectId(0);
			self::$em->persist($newDorAvailabilityBlockPlanned);
			self::$em->flush();
			// Refresh to allow postload listeners to kick in
			self::$em->refresh($newDorAvailabilityBlockPlanned);
			self::$originalDorAvailabilityBlockPlannedState = $newDorAvailabilityBlockPlanned->getNormalizedValue();
			self::$createdSystemConfigs[] = $newDorAvailabilityBlockPlanned;
		}

		/** @var SystemConfig $dorFteMwHourRegistration */
		$dorFteMwHourRegistration = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_FTE_MW_HOURREGISTRATION);
		if ($dorFteMwHourRegistration) {
			self::$originalDorFteMwHourRegistrationState = $dorFteMwHourRegistration->getNormalizedValue();
			// Manually set DOR_FTE_MW_HOURREGISTRATION to 1 so that CRUD tests can pass
			self::$em->clear();
			$dorFteMwHourRegistration->setValue(1);
			self::$em->flush();
		} else {
			// create the system config for test but delete it lateron
			self::$em->clear();
			$newDorFteMwHourRegistration = new SystemConfig();
			$newDorFteMwHourRegistration->setKey(SystemConfigKey::DOR_FTE_MW_HOURREGISTRATION);
			$newDorFteMwHourRegistration->setValue(1);
			$newDorFteMwHourRegistration->setObjectId(0);
			self::$em->persist($newDorFteMwHourRegistration);
			self::$em->flush();
			// Refresh to allow postload listeners to kick in
			self::$em->refresh($newDorFteMwHourRegistration);
			self::$originalDorFteMwHourRegistrationState = $newDorFteMwHourRegistration->getNormalizedValue();
			self::$createdSystemConfigs[] = $newDorFteMwHourRegistration;
		}

		self::$createdRegisters = [];
		self::$em->clear();
	}

	public static function tearDownAfterClass()
	{
		ClockMock::withClockMock(false);

		self::$em->clear();

		// make sure all created registers and related logs are deleted from DB
		foreach (self::$createdRegisters as $createdRegister) {
			/** @var Register $register */
			$register = self::$registerRepo->find($createdRegister);
			if ($register) {
				self::$em->remove($register);
				self::$em->flush();
			}

			$registerLogs = self::$registerLogRepo->findByPrimaryKey($createdRegister);
			foreach ($registerLogs as $registerLog) {
				self::$em->remove($registerLog);
				self::$em->flush();
			}
		}

		// manually set DOR_SCHEDULE_USE_VACATION_TIMEOUT_FOR_UNAVAILABILITY to original state;
		/** @var SystemConfig $dorScheduleUseVacationTimeoutForUnavailability */
		$dorScheduleUseVacationTimeoutForUnavailability = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_SCHEDULE_USE_VACATION_TIMEOUT_FOR_UNAVAILABILITY);
		if ($dorScheduleUseVacationTimeoutForUnavailability) {
			$dorScheduleUseVacationTimeoutForUnavailability->setValue(self::$originalDorScheduleUseVacationTimeoutForUnavailabilityState);
		}

		// manually set DOR_SCHEDULE_AVAILABILITY_BLOCK_PLANNED to original state;
		/** @var SystemConfig $dorScheduleAvailabilityBlockPlanned */
		$dorScheduleAvailabilityBlockPlanned = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_SCHEDULE_AVAILABILITY_BLOCK_PLANNED);
		if ($dorScheduleAvailabilityBlockPlanned) {
			$dorScheduleAvailabilityBlockPlanned->setValue(self::$originalDorAvailabilityBlockPlannedState);
		}

		// manually set DOR_FTE_MW_HOURREGISTRATION to original state;
		/** @var SystemConfig $dorFteMwHourRegistration */
		$dorFteMwHourRegistration = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_FTE_MW_HOURREGISTRATION);
		if ($dorFteMwHourRegistration) {
			$dorFteMwHourRegistration->setValue(self::$originalDorFteMwHourRegistrationState);
		}

		foreach (self::$createdSystemConfigs as $createdSystemConfig) {
			$toBeDeletedSystemConfig = self::$systemConfigRepo->find($createdSystemConfig->getId());
			self::$em->remove($toBeDeletedSystemConfig);
		}

		self::$em->flush();
		self::$createdRegisters = [];
		self::$em->close();
		self::$em = null; // avoid memory leaks
	}

	/**
	 * Tests the retrieval of registers with date range that current logged in client is allowed to see.
	 */
	public function testGetRegistersWithDateRange()
	{
		$dateFrom = '2016-08-01';
		$dateTo = '2016-08-31';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/registers?dateFrom='.$dateFrom.'&dateTo='.$dateTo,
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
		$registers = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($registers) > 0);

		//Test if exactly 8 registers are returned
		$this->assertTrue(8 === count($registers['data']));

		//Test that each registers has at minimum the below values
		foreach ($registers['data'] as $register) {
			$this->assertArrayHasKey('id', $register);
			$this->assertArrayHasKey('start_date', $register);
			$this->assertArrayHasKey('end_date', $register);
			$this->assertArrayHasKey('type', $register);
		}
	}

	/**
	 * Tests the retrieval of registers with only dateFrom query parameter that current logged in client is allowed to see.
	 */
	public function testGetRegistersWithOnlyDateFrom()
	{
		$dateFrom = '2016-08-29';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/registers?dateFrom='.$dateFrom,
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
		$registers = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($registers) > 0);

		//Test if exactly x registers are returned
		$this->assertTrue(4 === count($registers['data']));

		//Test that each registers has at minimum the below values
		foreach ($registers['data'] as $register) {
			$this->assertArrayHasKey('id', $register);
			$this->assertArrayHasKey('start_date', $register);
			$this->assertArrayHasKey('end_date', $register);
			$this->assertArrayHasKey('type', $register);
		}
	}

	/**
	 * Tests the retrieval of registers with only dateTo query parameter that current logged in client is allowed to see.
	 */
	public function testGetRegistersWithOnlyDateTo()
	{
		$dateTo = '2016-08-29';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/registers?dateTo='.$dateTo,
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
		$registers = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($registers) > 0);

		//Test if exactly x registers are returned
		$this->assertTrue(5 === count($registers['data']));

		//Test that each registers has at minimum the below values
		foreach ($registers['data'] as $register) {
			$this->assertArrayHasKey('id', $register);
			$this->assertArrayHasKey('start_date', $register);
			$this->assertArrayHasKey('end_date', $register);
			$this->assertArrayHasKey('type', $register);
		}
	}

	/**
	 * Tests the retrieval of registers without date params that current logged in client is allowed to see, which defaults at registers of current month.
	 *
	 * @group time-sensitive
	 */
	public function testGetRegistersWithoutDateQueryParams()
	{
		//mock current time in registered classes
		ClockMock::withClockMock(strtotime('2016-08-05 01:00:00'));

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/registers',
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
		$registers = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($registers) > 0);

		//Test if exactly x registers are returned
		$this->assertTrue(8 === count($registers['data']));

		//Test that each registers has at minimum the below values
		foreach ($registers['data'] as $register) {
			$this->assertArrayHasKey('id', $register);
			$this->assertArrayHasKey('start_date', $register);
			$this->assertArrayHasKey('end_date', $register);
			$this->assertArrayHasKey('type', $register);
		}
	}

	/**
	 * Test NonAdminTokens get registers with hidden remark field.
	 */
	public function testGetRegisterHiddenRemarkWithNonAdminToken()
	{
		$dateFrom = '2018-04-01';
		$dateTo = '2018-04-31';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$nonAdminToken,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/registers?dateFrom='.$dateFrom.'&dateTo='.$dateTo,
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
		$registers = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($registers) > 0);

		//Test if exactly 1 registers are returned
		$this->assertTrue(1 === count($registers['data']));

		//Test that each registers has at minimum the below values
		foreach ($registers['data'] as $register) {
			$this->assertArrayHasKey('id', $register);
			$this->assertArrayHasKey('start_date', $register);
			$this->assertArrayHasKey('end_date', $register);
			$this->assertArrayHasKey('type', $register);
		}

		//Test that each registers has doesn't have remark
		foreach ($registers['data'] as $register) {
			$this->assertArrayNotHasKey('remark', $register);
		}
	}

	/**
	 * test batch create registers success
	 * TODO maybe use fixture to simulate post.
	 */
	public function testBatchCreateRegistersSuccess()
	{
		//The register properties to post
		$start_date = '2017-09-04T00:00:00+02:00';
		$end_date = '2017-09-04T23:00:00+02:00';
		$type = 'WORK';
		$remark = 'CREATED BY TEST 1';
		$features = 2;
		$breakDuration = 30;
		$kilometers = 15.50;
		$employee = '{"id" : 52}';
		$department = '{"id" : 57}';
		$assignment = '{"id" : 102387}';

		$start_date2 = '2017-09-05T00:00:00+00:00';
		$end_date2 = '2017-09-05T23:59:00+00:00';
		$type2 = 'AVAILABLE';
		$remark2 = 'CREATED BY TEST 2';
		$employee2 = '{"id" : 52}';

		$contentString = '[{
            "start_date": "'.$start_date.'",
            "end_date": "'.$end_date.'",
            "type": "'.$type.'",
            "remark": "'.$remark.'",
            "features": '.$features.',
            "break_duration": '.$breakDuration.',
            "kilometers": '.$kilometers.',
            "employee": '.$employee.',
            "department": '.$department.',
            "assignment": '.$assignment.'
        }, {
            "start_date": "'.$start_date2.'",
            "end_date": "'.$end_date2.'",
            "type": "'.$type2.'",
            "remark": "'.$remark2.'",
            "employee": '.$employee2.'
        }]';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
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
		$registers = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($registers) > 0);

		//Test if exactly x registers are returned
		$this->assertTrue(2 === count($registers['data']));

		//Test that each registers has at minimum the below values
		foreach ($registers['data'] as $register) {
			$this->assertArrayHasKey('id', $register);
			$this->assertArrayHasKey('start_date', $register);
			$this->assertArrayHasKey('end_date', $register);
			$this->assertArrayHasKey('type', $register);
			$this->assertArrayHasKey('remark', $register);
			// keep track of the ids of the created registers in DB
			self::$createdRegisters[] = $register['id'];
		}

		// test if registers are indeed inserted in DB
		foreach (self::$createdRegisters as $createdRegister) {
			/** @var Register $register */
			$register = self::$registerRepo->find($createdRegister);
			$this->assertNotNull($register);
			$this->assertEquals($createdRegister, $register->getId());
			$this->assertEquals(52, $register->getEmployee()->getId());
			$this->assertEquals(888888, $register->getSessionId());
			if ($register->getType() == $type) {
				$this->assertEquals(23, $register->getWorkDuration());
				$this->assertEquals(22.5, $register->getCalculatedWorkDuration());
				$this->assertEquals($breakDuration, $register->getBreakDuration());
				$this->assertEquals($features, $register->getFeatures());
				$this->assertEquals($remark, $register->getOriginalRemark());
				$this->assertEquals($kilometers, $register->getKilometers());
				$this->assertEquals(57, $register->getDepartment()->getId());
				$this->assertEquals(102387, $register->getAssignment()->getId());
				$this->assertNull($register->getModifiedBy());
				$this->assertNotNull($register->getLocation());
				$this->assertNotNull($register->getActivity());
			}
			if ($register->getType() == $type2) {
				$this->assertEquals(4, $register->getModifiedBy()->getId());
				$this->assertEquals($remark2, $register->getOriginalRemark());
				$this->assertNull($register->getWorkDuration());
			}
		}

		// Test if RegisterLogs are created
		self::$em->clear();
		foreach (self::$createdRegisters as $createdRegister) {
			/** @var Register $register */
			$register = self::$registerRepo->find($createdRegister);
			/** @var RegisterLog $registerLog */
			$registerLog = self::$registerLogRepo->findByPrimaryKey((string) $register->getId())[0];
			$this->assertNotNull($registerLog);
			$this->assertEquals($registerLog->getPrimaryKey(), $register->getId());
			$this->assertEquals($registerLog->getChangedField(), 'created');
			$this->assertEquals($registerLog->getDate()->format(Constants::DateFormatString), $register->getCreated()->format(Constants::DateFormatString));
			$this->assertEquals($registerLog->getTime()->format(Constants::HOURS_MINUTES_FORMAT_STRING), $register->getCreated()->format(Constants::HOURS_MINUTES_FORMAT_STRING));
			$this->assertEquals($registerLog->getNewValue(), $register->getCreated()->format(Constants::DATE_TIME_FORMAT_STRING));
			$this->assertEquals($registerLog->getSessionId(), $register->getSessionId());

			// Delete Logs
			self::$em->remove($registerLog);
			self::$em->flush();
		}
		self::$em->clear();
	}

	/**
	 * test batch create registers fail
	 * TODO maybe use fixture to simulate post.
	 */
	public function testFailedCreateRegistersInvalidType()
	{
		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		//Test invalid type value
		$start_date = '2017-09-01T00:00:00+00:00';
		$end_date = '2017-09-01T23:59:00+00:00';
		$type = 'invalidType';
		$remark = 'CREATED BY TEST 1';
		$employee = '{"id" : 52}';
		$department = '{"id" : 57}';
		$assignment = '{"id" : 102387}';

		$contentString = '[{
            "start_date": "'.$start_date.'",
            "end_date": "'.$end_date.'",
            "type": "'.$type.'",
            "remark": "'.$remark.'",
            "employee": '.$employee.',
            "department": '.$department.',
            "assignment": '.$assignment.'
        }]';

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is PRECONDITION FAILED 412
		$this->assertSame(Response::HTTP_PRECONDITION_FAILED, $response->getStatusCode());
	}

	/**
	 * test fail to create Register of type WORK that either has a end_date that is in the future.
	 */
	public function testFailedCreateRegistersWorkInTheFuture()
	{
		/**
		 * Entire date range is in the future.
		 */

		// Calculate startDate endDate string that is in the future
		$currentDate = new \DateTime();
		$futureStartDateString = $currentDate->add(date_interval_create_from_date_string('1 minute'))->format(\DateTime::RFC3339);
		$futureEndDateString = $currentDate->add(date_interval_create_from_date_string('2 minute'))->format(\DateTime::RFC3339);

		//mock current time in registered classes
		ClockMock::withClockMock($currentDate);

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		//Test invalid type value
		$start_date = $futureStartDateString;
		$end_date = $futureEndDateString;
		$type = 'WORK';
		$remark = 'CREATED BY TEST 1';
		$employee = '{"id" : 52}';
		$department = '{"id" : 57}';
		$assignment = '{"id" : 102387}';

		$contentString = '[{
            "start_date": "'.$start_date.'",
            "end_date": "'.$end_date.'",
            "type": "'.$type.'",
            "remark": "'.$remark.'",
            "employee": '.$employee.',
            "department": '.$department.',
            "assignment": '.$assignment.'
        }]';

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is PRECONDITION FAILED 412
		$this->assertSame(Response::HTTP_PRECONDITION_FAILED, $response->getStatusCode(), $response->getContent());

		// Test if error message contains string
		$this->assertContains('date range is in the future', $response->getContent());

		/**
		 * Only endDate is in the future.
		 */

		// Calculate only endDate string that is in the future
		$currentDate = new \DateTime();
		$futureStartDateString = $currentDate->sub(date_interval_create_from_date_string('1 minute'))->format(\DateTime::RFC3339);
		$futureEndDateString = $currentDate->add(date_interval_create_from_date_string('2 minute'))->format(\DateTime::RFC3339);
		//mock current time in registered classes
		ClockMock::withClockMock($currentDate);

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);
		//Test invalid type value
		$start_date = $futureStartDateString;
		$end_date = $futureEndDateString;
		$type = 'WORK';
		$remark = 'CREATED BY TEST 1';
		$employee = '{"id" : 52}';
		$department = '{"id" : 57}';
		$assignment = '{"id" : 102387}';

		$contentString = '[{
            "start_date": "'.$start_date.'",
            "end_date": "'.$end_date.'",
            "type": "'.$type.'",
            "remark": "'.$remark.'",
            "employee": '.$employee.',
            "department": '.$department.',
            "assignment": '.$assignment.'
        }]';

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is PRECONDITION FAILED 412
		$this->assertSame(Response::HTTP_PRECONDITION_FAILED, $response->getStatusCode(), $response->getContent());

		// Test if error message contains string
		$this->assertContains('date range is in the future', $response->getContent());
	}

	/**
	 * test batch create registers fail when register type is WORK and no department is given.
	 */
	public function testFailedCreateRegistersMissingDepartmentField()
	{
		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		//Test invalid type value
		$start_date = '2017-09-01T00:00:00+00:00';
		$end_date = '2017-09-01T23:59:00+00:00';
		$type = 'WORK';
		$remark = 'CREATED BY TEST 1';
		$employee = '{"id" : 52}';
		$assignment = '{"id" : 102387}';

		$contentString = '[{
            "start_date": "'.$start_date.'",
            "end_date": "'.$end_date.'",
            "type": "'.$type.'",
            "remark": "'.$remark.'",
            "employee": '.$employee.',
            "assignment": '.$assignment.'
        }]';

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is PRECONDITION FAILED 412
		$this->assertSame(Response::HTTP_PRECONDITION_FAILED, $response->getStatusCode());
	}

	/**
	 * test batch create registers fail
	 * TODO maybe use fixture to simulate post.
	 */
	public function testFailedCreateRegistersStartDateBiggerThanEndDate()
	{
		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		//Test start date bigger than end date
		$start_date = '2017-09-10T00:00:00+00:00';
		$end_date = '2017-09-01T23:59:00+00:00';
		$type = 'WORK';
		$remark = 'CREATED BY TEST 1';
		$employee = '{"id" : 52}';
		$department = '{"id" : 57}';
		$assignment = '{"id" : 102387}';

		$contentString = '[{
            "start_date": "'.$start_date.'",
            "end_date": "'.$end_date.'",
            "type": "'.$type.'",
            "remark": "'.$remark.'",
            "employee": '.$employee.',
            "department": '.$department.',
            "assignment": '.$assignment.'
        }]';

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is PRECONDITION FAILED 412
		$this->assertSame(Response::HTTP_PRECONDITION_FAILED, $response->getStatusCode());
	}

	/**
	 * test batch create registers fail
	 * TODO maybe use fixture to simulate post.
	 */
	public function testFailedCreateRegistersNotFoundEmployee()
	{
		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		//Test not found employee
		$start_date = '2017-09-01T00:00:00+00:00';
		$end_date = '2017-09-01T23:59:00+00:00';
		$type = 'WORK';
		$remark = 'CREATED BY TEST 1';
		$employee = '{"id" : 9999}';
		$department = '{"id" : 57}';
		$assignment = '{"id" : 102387}';

		$contentString = '[{
            "start_date": "'.$start_date.'",
            "end_date": "'.$end_date.'",
            "type": "'.$type.'",
            "remark": "'.$remark.'",
            "employee": '.$employee.',
            "department": '.$department.',
            "assignment": '.$assignment.'
        }]';

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is NOT FOUND 404
		$this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
	}

	/**
	 * test batch create registers fail
	 * TODO maybe use fixture to simulate post.
	 */
	public function testFailedCreateRegistersNotFoundDepartment()
	{
		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		//Test not found employee
		$start_date = '2017-09-01T00:00:00+00:00';
		$end_date = '2017-09-01T23:59:00+00:00';
		$type = 'WORK';
		$remark = 'CREATED BY TEST 1';
		$employee = '{"id" : 52}';
		$department = '{"id" : 99999}';
		$assignment = '{"id" : 102387}';

		$contentString = '[{
            "start_date": "'.$start_date.'",
            "end_date": "'.$end_date.'",
            "type": "'.$type.'",
            "remark": "'.$remark.'",
            "employee": '.$employee.',
            "department": '.$department.',
            "assignment": '.$assignment.'
        }]';

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is NOT FOUND 404
		$this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
	}

	/**
	 * test batch create registers fail
	 * TODO maybe use fixture to simulate post.
	 */
	public function testFailedCreateRegistersNotFoundAssignment()
	{
		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		//Test not found employee
		$start_date = '2017-09-01T00:00:00+00:00';
		$end_date = '2017-09-01T23:59:00+00:00';
		$type = 'WORK';
		$remark = 'CREATED BY TEST 1';
		$employee = '{"id" : 52}';
		$department = '{"id" : 57}';
		$assignment = '{"id" : 9999999}';

		$contentString = '[{
            "start_date": "'.$start_date.'",
            "end_date": "'.$end_date.'",
            "type": "'.$type.'",
            "remark": "'.$remark.'",
            "employee": '.$employee.',
            "department": '.$department.',
            "assignment": '.$assignment.'
        }]';

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is NOT FOUND 404
		$this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
	}

	/**
	 * test batch create registers fail
	 * TODO maybe use fixture to simulate post.
	 */
	public function testFailedCreateRegistersRandomInvalidValues()
	{
		//The register properties to post
		$start_date = '2017-09-01T00:00:00+00:00';
		$end_date = '2017-09-01T23:00:00+00:00';
		$type = 'WORK';
		$remark = 'CREATED BY TEST 1';
		$features = 2;
		$breakDuration = 30;
		$kilometers = 15.50;
		$employee = '{"id" : 52}';
		$department = '{"id" : 57}';
		$assignment = '{"id" : "102387"}';

		$contentString = '[{
            "start_date": "'.$start_date.'",
            "end_date": "'.$end_date.'",
            "type": "'.$type.'",
            "remark": "'.$remark.'",
            "features": '.$features.',
            "break_duration": '.$breakDuration.',
            "kilometers": '.$kilometers.',
            "employee": '.$employee.',
            "department": '.$department.',
            "assignment": '.$assignment.'
        }]';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is PRECONDITION FAILED 412
		$this->assertSame(Response::HTTP_PRECONDITION_FAILED, $response->getStatusCode());
	}

	/**
	 * Test fail to create register for other employees with non Admin token.
	 */
	public function testFailedCreateRegistersForOtherEmployeesWithNonAdminToken()
	{
		//The register properties to post
		$start_date = '2017-09-01T00:00:00+00:00';
		$end_date = '2017-09-01T23:00:00+00:00';
		$type = 'WORK';
		$remark = 'CREATED BY TEST 1';
		$features = 2;
		$breakDuration = 30;
		$kilometers = 15.50;
		// Note the 52 is not the employee id of chrisbos (nonAdminToken)
		$employee = '{"id" : 52}';
		$department = '{"id" : 57}';
		$assignment = '{"id" : 104072}';

		$contentString = '[{
            "start_date": "'.$start_date.'",
            "end_date": "'.$end_date.'",
            "type": "'.$type.'",
            "remark": "'.$remark.'",
            "features": '.$features.',
            "break_duration": '.$breakDuration.',
            "kilometers": '.$kilometers.',
            "employee": '.$employee.',
            "department": '.$department.',
            "assignment": '.$assignment.'       
        }]';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$nonAdminToken,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is PRECONDITION FAILED 412
		$this->assertSame(Response::HTTP_PRECONDITION_FAILED, $response->getStatusCode());
	}

	/**
	 * Test success edit registers.
	 *
	 * @depends testBatchCreateRegistersSuccess
	 */
	public function testBatchEditRegistersSuccess()
	{
		//The register properties to post
		$id = self::$createdRegisters[0];
		$start_date = '2017-09-01T00:00:00+00:00';
		$end_date = '2017-09-01T23:00:00+00:00';
		$type = 'WORK';
		$remark = 'EDITED BY TEST 1';
		$features = 2;
		$breakDuration = 60;
		$kilometers = 15.50;
		$employee = '{"id" : 68}';
		$department = '{"id" : 57}';
		$assignment = '{"id" : 102387}';

		$id2 = self::$createdRegisters[1];
		$start_date2 = '2017-09-02T00:00:00+00:00';
		$end_date2 = '2017-09-02T23:59:00+00:00';
		$type2 = 'AVAILABLE';
		$remark2 = 'EDITED BY TEST 2';
		$employee2 = '{"id" : 52}';

		// Store the original values before edit
		/** @var Register[] $originalRegisters */
		$originalRegisters = [];
		foreach (self::$createdRegisters as $originalRegister) {
			$originalRegisters[] = self::$em->find(Register::class, $originalRegister);
		}
		self::$em->clear();

		$contentString = '[{
            "id": '.$id.',
            "remark": "'.$remark.'",
            "break_duration": '.$breakDuration.'
        }, {
            "id": '.$id2.',
            "remark": "'.$remark2.'"
        }]';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_PUT,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
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
		$registers = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($registers) > 0);

		//Test if exactly x registers are returned
		$this->assertTrue(2 === count($registers['data']));

		//Test that each registers has at minimum the below values
		foreach ($registers['data'] as $register) {
			$this->assertArrayHasKey('id', $register);
			$this->assertArrayHasKey('start_date', $register);
			$this->assertArrayHasKey('end_date', $register);
			$this->assertArrayHasKey('type', $register);
			$this->assertArrayHasKey('remark', $register);
		}

		// test if registers are indeed inserted in DB
		foreach (self::$createdRegisters as $editedRegister) {
			/** @var Register $register */
			$register = self::$registerRepo->find($editedRegister);
			self::$em->refresh($register);
			$this->assertNotNull($register);
			$this->assertEquals($editedRegister, $register->getId());
			$this->assertEquals(888888, $register->getSessionId());
			if ($register->getId() == $id) {
				$this->assertEquals(23, $register->getWorkDuration());
				$this->assertEquals(22, $register->getCalculatedWorkDuration());
				$this->assertEquals($breakDuration, $register->getBreakDuration());
				$this->assertEquals($features, $register->getFeatures());
				$this->assertEquals($remark, $register->getOriginalRemark());
				$this->assertEquals($kilometers, $register->getKilometers());
				$this->assertEquals(57, $register->getDepartment()->getId());
				$this->assertEquals(4, $register->getClient()->getId());
				$this->assertNotNull($register->getLocation());
				$this->assertNotNull($register->getActivity());
			}
			if ($register->getId() == $id2) {
				$this->assertEquals($remark2, $register->getOriginalRemark());
				$this->assertNull($register->getWorkDuration());
			}

			// Test if RegisterLogs are created during update
			$registerLogs = self::$registerLogRepo->findByPrimaryKey($editedRegister);
			if ($register->getId() == $id) {
				$this->assertCount(3, $registerLogs);

				foreach ($registerLogs as $registerLog) {
					/** @var RegisterLog $registerLog */
					if ('opmerking' == $registerLog->getChangedField()) {
						$this->assertEquals($registerLog->getOldValue(), $originalRegisters[0]->getOriginalRemark());
						$this->assertEquals($registerLog->getNewValue(), $register->getOriginalRemark());
					}

					if ('pauze' == $registerLog->getChangedField()) {
						$this->assertEquals($registerLog->getOldValue(), $originalRegisters[0]->getBreakDuration());
						$this->assertEquals($registerLog->getNewValue(), $register->getBreakDuration());
					}

					if ('modified' == $registerLog->getChangedField()) {
						$this->assertEquals($registerLog->getOldValue(), $originalRegisters[0]->getModified()->format(Constants::DATE_TIME_FORMAT_STRING));
						$this->assertEquals($registerLog->getNewValue(), $register->getModified()->format(Constants::DATE_TIME_FORMAT_STRING));
					}
				}
			}

			if ($register->getId() == $id2) {
				$this->assertCount(2, $registerLogs);
			}

			// Delete logs
			foreach ($registerLogs as $registerLog) {
				self::$em->remove($registerLog);
				self::$em->flush();
			}
		}

		self::$em->clear();
	}

	/**
	 * test fail to edit Register of type WORK that either has a end_date that is in the future.
	 *
	 * @depends testBatchCreateRegistersSuccess
	 */
	public function testFailedEditRegistersWorkInTheFuture()
	{
		/**
		 * Entire date range is in the future.
		 */

		// Calculate startDate endDate string that is in the future
		$currentDate = new \DateTime();
		$futureStartDateString = $currentDate->add(date_interval_create_from_date_string('1 minute'))->format(\DateTime::RFC3339);
		$futureEndDateString = $currentDate->add(date_interval_create_from_date_string('2 minute'))->format(\DateTime::RFC3339);

		//mock current time in registered classes
		ClockMock::withClockMock($currentDate);
		//The register properties to put
		$id = self::$createdRegisters[0];
		// THIS IS THE INVALID FIELD
		$start_date = $futureStartDateString;
		$end_date = $futureEndDateString;
		$contentString = '[{
            "id": '.$id.',
            "start_date": "'.$start_date.'",
            "end_date": "'.$end_date.'"
        }]';
		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);
		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_PUT,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
		);
		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is PRECONDITION FAILED 412
		$this->assertSame(Response::HTTP_PRECONDITION_FAILED, $response->getStatusCode(), $response->getContent());

		// Test if error message contains string
		$this->assertContains('date range is in the future', $response->getContent());

		/**
		 * Only endDate is in the future.
		 */

		// Calculate only endDate string that is in the future
		$currentDate = new \DateTime();
		$futureStartDateString = $currentDate->sub(date_interval_create_from_date_string('1 minute'))->format(\DateTime::RFC3339);
		$futureEndDateString = $currentDate->add(date_interval_create_from_date_string('2 minute'))->format(\DateTime::RFC3339);

		//mock current time in registered classes
		ClockMock::withClockMock($currentDate);
		//The register properties to put
		$id = self::$createdRegisters[0];
		// THIS IS THE INVALID FIELD
		$start_date = $futureStartDateString;
		$end_date = $futureEndDateString;
		$contentString = '[{
            "id": '.$id.',
            "start_date": "'.$start_date.'",
            "end_date": "'.$end_date.'"
        }]';
		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);
		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_PUT,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
		);
		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is PRECONDITION FAILED 412
		$this->assertSame(Response::HTTP_PRECONDITION_FAILED, $response->getStatusCode(), $response->getContent());

		// Test if error message contains string
		$this->assertContains('date range is in the future', $response->getContent());
	}

	/**
	 * Test fail edit register of other Employee with non admin token.
	 *
	 * @depends testBatchCreateRegistersSuccess
	 */
	public function testFailedEditRegisterOfOtherEmployeesWithNonAdminToken()
	{
		//The register properties to post
		$id = self::$createdRegisters[0];
		$start_date = '2017-09-01T00:00:00+00:00';
		$end_date = '2017-09-01T23:00:00+00:00';
		$type = 'WORK';
		$remark = 'EDITED BY TEST 1';
		$features = 2;
		$breakDuration = 60;
		$kilometers = 15.50;
		$employee = '{"id" : 68}';
		$department = '{"id" : 57}';
		$assignment = '{"id" : 102387}';

		$contentString = '[{
            "id": '.$id.',
            "remark": "'.$remark.'",
            "break_duration": '.$breakDuration.',
            "employee": '.$employee.'
        }]';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$nonAdminToken,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_PUT,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is NOT FOUND 404
		$this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
	}

	/**
	 * Test fail edit register of type UNAVAILABLE with status DENIED by Employee with non admin token.
	 *
	 * @depends testBatchCreateRegistersSuccess
	 */
	public function testFailedEditDeniedUnavailableRegisterWithNonAdminToken()
	{
		//The register in DB to PUT
		$id = 37572;
		$status = 2;

		$contentString = '[{
            "id": '.$id.',
            "status": '.$status.'
        }]';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$nonAdminToken,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_PUT,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is PRECONDITION FAILED 412
		$this->assertSame(Response::HTTP_PRECONDITION_FAILED, $response->getStatusCode());
	}

	/**
	 * Test fail edit register not found id.
	 */
	public function testFailedEditRegisterNotFound()
	{
		//The register properties to post
		$id = 999999999999;
		$start_date = '2017-09-01T00:00:00+00:00';
		$end_date = '2017-09-01T23:00:00+00:00';
		$type = 'WORK';
		$remark = 'EDITED BY TEST 1';
		$features = 2;
		$breakDuration = 60;
		$kilometers = 15.50;
		$employee = '{"id" : 68}';
		$department = '{"id" : 57}';
		$assignment = '{"id" : 102387}';

		$contentString = '[{
            "id": '.$id.',
            "remark": "'.$remark.'",
            "break_duration": '.$breakDuration.',
            "employee": '.$employee.'
        }]';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_PUT,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is NOT FOUND 404
		$this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
	}

	/**
	 * Test fail edit register random invalid value.
	 *
	 * @depends testBatchCreateRegistersSuccess
	 */
	public function testFailedEditRegisterRandomInvalidValue()
	{
		//The register properties to put
		$id = self::$createdRegisters[0];
		$start_date = '2017-09-01T00:00:00+00:00';
		$end_date = '2017-09-01T23:00:00+00:00';
		// THIS IS THE INVALID FIELD
		$type = 'werk';
		$remark = 'EDITED BY TEST 1';
		$features = 2;
		$breakDuration = 60;
		$kilometers = 15.50;
		$employee = '{"id" : 68}';
		$department = '{"id" : 57}';
		$assignment = '{"id" : 102387}';

		$contentString = '[{
            "id": '.$id.',
            "remark": "'.$remark.'",
            "type": "'.$type.'",
            "break_duration": '.$breakDuration.',
            "employee": '.$employee.'
        }]';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_PUT,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is PRECONDITION FAILED 412
		$this->assertSame(Response::HTTP_PRECONDITION_FAILED, $response->getStatusCode());
	}

	/**
	 * Test fail edit register to a date range with overlapping Register.
	 *
	 * @depends testBatchCreateRegistersSuccess
	 */
	public function testFailedEditRegisterWithOverlapping()
	{
		//The register properties to put
		$id = self::$createdRegisters[0];
		// THIS IS THE INVALID FIELD
		$start_date = '2017-09-01T00:00:00+02:00';
		$end_date = '2017-09-01T23:00:00+02:00';

		$contentString = '[{
            "id": '.$id.',
            "start_date": "'.$start_date.'",
            "end_date": "'.$end_date.'"
        }]';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_PUT,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is PRECONDITION FAILED 412
		$this->assertSame(Response::HTTP_PRECONDITION_FAILED, $response->getStatusCode());

		// Test if error message contains string
		$this->assertContains('overlaps with an existing Register', $response->getContent());
	}

	/**
	 * Test success delete Register.
	 *
	 * @depends testBatchCreateRegistersSuccess
	 */
	public function testBatchDeleteRegistersSuccess()
	{
		//The register properties to post
		$id = self::$createdRegisters[0];
		$id2 = self::$createdRegisters[1];

		$contentString = '[{
            "id": '.$id.'
        }, {
            "id": '.$id2.'
        }]';

		// Store the original values before edit
		/** @var Register[] $originalRegisters */
		$originalRegisters = [];
		foreach (self::$createdRegisters as $originalRegister) {
			$originalRegisters[$originalRegister] = self::$em->find(Register::class, $originalRegister);
		}
		self::$em->clear();

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$superUserToken,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_DELETE,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
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
		$registers = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($registers) > 0);

		//Test if exactly x registers are returned
		$this->assertTrue(2 === count($registers['data']));

		//Test that each registers has at minimum the below values
		foreach ($registers['data'] as $register) {
			$this->assertArrayHasKey('id', $register);
		}

		self::$em->clear();

		// test if registers are indeed inserted in DB
		$client = self::$em->find(Client::class, 2);
		foreach (self::$createdRegisters as $editedRegister) {
			/** @var Register $register */
			$register = self::$registerRepo->find($editedRegister);
			$this->assertNull($register);

			$originalRegisters[$editedRegister]->setModifiedBy($client);
			$serializedRegister = self::$serializer->serialize($originalRegisters[$editedRegister], Constants::JSON_SERIALIZATON_FORMAT, array(
				'groups' => array(
					SerializerGroup::REGISTER_LOG,
				),
			));

			// Test if RegisterLogs are created during delete
			/** @var RegisterLog $registerLog */
			$registerLog = self::$registerLogRepo->findOneByPrimaryKey($editedRegister);
			$this->assertNotNull($registerLog);
			$this->assertEquals($registerLog->getChangedField(), 'deleted');

			if (strlen($serializedRegister) > 127) {
				$this->assertInstanceOf(LargeDataLog::class, $registerLog->getLargeDataLog());
				$this->assertEquals($registerLog->getLargeDataLog()->getData(), $serializedRegister);
			}

			// Delete logs
			self::$em->remove($registerLog);
			self::$em->flush();
		}
	}

	/**
	 * Test failed delete Register of other Employee with non admin token.
	 *
	 * @depends testBatchCreateRegistersSuccess
	 */
	public function testFailedDeleteRegisterOfOtherEmployeeWithNonAdminToken()
	{
		//First create a new Register with Pieter
		//The register properties to post
		$start_date = '2017-09-03T00:00:00+00:00';
		$end_date = '2017-09-03T23:00:00+00:00';
		$type = 'WORK';
		$remark = 'CREATED BY TEST 1';
		$features = 2;
		$breakDuration = 30;
		$kilometers = 15.50;
		$employee = '{"id" : 52}';
		$department = '{"id" : 57}';
		$assignment = '{"id" : 102387}';

		$contentString = '[{
            "start_date": "'.$start_date.'",
            "end_date": "'.$end_date.'",
            "type": "'.$type.'",
            "remark": "'.$remark.'",
            "features": '.$features.',
            "break_duration": '.$breakDuration.',
            "kilometers": '.$kilometers.',
            "employee": '.$employee.',
            "department": '.$department.',
            "assignment": '.$assignment.'
        }]';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
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
		$registers = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($registers) > 0);

		//Test if exactly x registers are returned
		$this->assertTrue(1 === count($registers['data']));

		//Test that each registers has at minimum the below values
		foreach ($registers['data'] as $register) {
			$this->assertArrayHasKey('id', $register);
			$this->assertArrayHasKey('start_date', $register);
			$this->assertArrayHasKey('end_date', $register);
			$this->assertArrayHasKey('type', $register);
			$this->assertArrayHasKey('remark', $register);
			// keep track of the ids of the created registers in DB
			self::$createdRegisters[] = $register['id'];
		}

		// test if registers are indeed inserted in DB
		/** @var Register $createdRegister */
		$createdRegister = self::$registerRepo->find($register['id']);
		$this->assertNotNull($createdRegister);
		$this->assertEquals($register['id'], $createdRegister->getId());
		$this->assertEquals(52, $createdRegister->getEmployee()->getId());
		$this->assertEquals(888888, $createdRegister->getSessionId());
		$this->assertEquals(23, $createdRegister->getWorkDuration());
		$this->assertEquals(22.5, $createdRegister->getCalculatedWorkDuration());
		$this->assertEquals($breakDuration, $createdRegister->getBreakDuration());
		$this->assertEquals($features, $createdRegister->getFeatures());
		$this->assertEquals($remark, $createdRegister->getOriginalRemark());
		$this->assertEquals($kilometers, $createdRegister->getKilometers());
		$this->assertEquals(57, $createdRegister->getDepartment()->getId());
		$this->assertEquals(102387, $createdRegister->getAssignment()->getId());
		$this->assertNull($createdRegister->getModifiedBy());
		$this->assertNotNull($createdRegister->getLocation());
		$this->assertNotNull($createdRegister->getActivity());

		//Then test delete the last created Register of Pieter with non admin token chrisbos
		//The register properties to DELETE
		$id = self::$createdRegisters[count(self::$createdRegisters) - 1];

		$contentString = '[{
            "id": '.$id.'
        }]';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$nonAdminToken,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_DELETE,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is NOT FOUND 404
		$this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
	}

	/**
	 * Test failed delete Register not found.
	 *
	 * @depends testBatchCreateRegistersSuccess
	 */
	public function testFailedDeleteRegisterNotFound()
	{
		//The register properties to post
		$id = self::$createdRegisters[0];

		$contentString = '[{
            "id": '.$id.'
        }]';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_DELETE,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is NOT FOUND 404
		$this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
	}

	/**
	 * Test fail create Register type VACATION and UNAVAILABLE with date before DOR_SCHEDULE_VACATION_TIMEOUT setting.
	 */
	public function testFailBatchCreateRegistersWithVacationTimeoutSetting()
	{
		$timeoutInterval = self::$originalDorScheduleVacationTimeoutState;
		$type = array_keys($timeoutInterval)[0];

		// strtotime accepts strings like "+14 days -10 days"
		$intervalTime = strtotime('+'.$timeoutInterval[$type].' '.$type.' -10 days');
		$invalidDateString = date(Constants::DateFormatString, $intervalTime);

		//The register properties to post
		$start_date = $invalidDateString.'T00:00:00+00:00';
		$end_date = $invalidDateString.'T23:00:00+00:00';
		$type = 'VACATION';
		$remark = 'CREATED BY TEST 1';
		$features = 2;
		$breakDuration = 30;
		$kilometers = 15.50;
		$employee = '{"id" : 52}';
		$department = '{"id" : 57}';
		$assignment = '{"id" : 102387}';

		$contentString = '[{
            "start_date": "'.$start_date.'",
            "end_date": "'.$end_date.'",
            "type": "'.$type.'",
            "remark": "'.$remark.'",
            "features": '.$features.',
            "break_duration": '.$breakDuration.',
            "kilometers": '.$kilometers.',
            "employee": '.$employee.',
            "department": '.$department.',
            "assignment": '.$assignment.'
        }]';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is PRECONDITION FAILED 412
		$this->assertSame(Response::HTTP_PRECONDITION_FAILED, $response->getStatusCode());

		// manually set DOR_SCHEDULE_USE_VACATION_TIMEOUT_FOR_UNAVAILABILITY to no if its yes;
		$currentDorScheduleUseVacationTimeoutForUnavailabilityState = self::$originalDorScheduleUseVacationTimeoutForUnavailabilityState;
		if ($currentDorScheduleUseVacationTimeoutForUnavailabilityState) {
			self::$em->clear();
			/** @var SystemConfig $dorScheduleUseVacationTimeoutForUnavailability */
			$dorScheduleUseVacationTimeoutForUnavailability = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_SCHEDULE_USE_VACATION_TIMEOUT_FOR_UNAVAILABILITY);
			$dorScheduleUseVacationTimeoutForUnavailability->setValue(false);
			self::$em->flush();
			self::$em->refresh($dorScheduleUseVacationTimeoutForUnavailability);
			$currentDorScheduleUseVacationTimeoutForUnavailabilityState = $dorScheduleUseVacationTimeoutForUnavailability->getNormalizedValue();
		}

		//The register properties to post
		$start_date = $invalidDateString.'T00:00:00+00:00';
		$end_date = $invalidDateString.'T23:00:00+00:00';
		$type = 'UNAVAILABLE';
		$remark = 'CREATED BY TEST 1';
		$employee = '{"id" : 52}';

		$contentString = '[{
            "start_date": "'.$start_date.'",
            "end_date": "'.$end_date.'",
            "type": "'.$type.'",
            "remark": "'.$remark.'",
            "employee": '.$employee.'
        }]';

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
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
		$registers = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($registers) > 0);

		//Test if exactly x registers are returned
		$this->assertTrue(1 === count($registers['data']));

		//Test that each registers has at minimum the below values
		foreach ($registers['data'] as $register) {
			$this->assertArrayHasKey('id', $register);
			$this->assertArrayHasKey('start_date', $register);
			$this->assertArrayHasKey('end_date', $register);
			$this->assertArrayHasKey('type', $register);
			$this->assertArrayHasKey('remark', $register);
			// keep track of the ids of the created registers in DB
			self::$createdRegisters[] = $register['id'];
			$lastCreatedRegisterId = $register['id'];
		}

		// test if registers are indeed inserted in DB
		/** @var Register $register */
		$register = self::$registerRepo->find($lastCreatedRegisterId);
		$this->assertNotNull($register);
		$this->assertEquals($lastCreatedRegisterId, $register->getId());
		$this->assertEquals(52, $register->getEmployee()->getId());
		$this->assertEquals(888888, $register->getSessionId());
		$this->assertEquals($remark, $register->getOriginalRemark());

		// manually set DOR_SCHEDULE_USE_VACATION_TIMEOUT_FOR_UNAVAILABILITY to yes
		self::$em->clear();
		/** @var SystemConfig $dorScheduleUseVacationTimeoutForUnavailability */
		$dorScheduleUseVacationTimeoutForUnavailability = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_SCHEDULE_USE_VACATION_TIMEOUT_FOR_UNAVAILABILITY);
		$dorScheduleUseVacationTimeoutForUnavailability->setValue(true);
		self::$em->flush();
		self::$em->refresh($dorScheduleUseVacationTimeoutForUnavailability);
		$currentDorScheduleUseVacationTimeoutForUnavailabilityState = $dorScheduleUseVacationTimeoutForUnavailability->getNormalizedValue();

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is PRECONDITION FAILED 412
		$this->assertSame(Response::HTTP_PRECONDITION_FAILED, $response->getStatusCode());

		// manually set DOR_SCHEDULE_USE_VACATION_TIMEOUT_FOR_UNAVAILABILITY to original value;
		if ($currentDorScheduleUseVacationTimeoutForUnavailabilityState !== self::$originalDorScheduleUseVacationTimeoutForUnavailabilityState) {
			self::$em->clear();
			/** @var SystemConfig $dorScheduleUseVacationTimeoutForUnavailability */
			$dorScheduleUseVacationTimeoutForUnavailability = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_SCHEDULE_USE_VACATION_TIMEOUT_FOR_UNAVAILABILITY);
			$dorScheduleUseVacationTimeoutForUnavailability->setValue(self::$originalDorScheduleUseVacationTimeoutForUnavailabilityState);
			self::$em->flush();
			self::$em->refresh($dorScheduleUseVacationTimeoutForUnavailability);
			$currentDorScheduleUseVacationTimeoutForUnavailabilityState = $dorScheduleUseVacationTimeoutForUnavailability->getNormalizedValue();
		}
	}

	/**
	 * Test fail delete already approved Registers.
	 */
	public function testFailBatchDeleteApprovedRegisters()
	{
		//The register id with status != 0
		$id = 29793;

		$contentString = '[{
            "id": '.$id.'
        }]';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_DELETE,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is FORBIDDEN 403
		$this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
	}

	/**
	 * Test fail single delete already approved Register.
	 */
	public function testFailSingleDeleteApprovedRegister()
	{
		//The register id with status != 0
		$id = 29793;

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_DELETE,
			'/api/v1/registers/'.$id,
			array(),
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is FORBIDDEN 403
		$this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
	}

	/**
	 * Test create Register of type AVAILABLE UNAVAILABLE on a date when already assigned with an Assignment with various settings form DOR_SCHEDULE_AVAILABILITY_BLOCK_PLANNED.
	 */
	public function testBatchCreateUnavailabilityRegistersOnAlreadyAssignedDate()
	{
		// manually set DOR_SCHEDULE_AVAILABILITY_BLOCK_PLANNED to 0;
		$currentDorScheduleAvailabilityBlockPlannedState = self::$originalDorAvailabilityBlockPlannedState;
		if (0 !== $currentDorScheduleAvailabilityBlockPlannedState) {
			/** @var SystemConfig $dorScheduleAvailabilityBlockPlanned */
			$dorScheduleAvailabilityBlockPlanned = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_SCHEDULE_AVAILABILITY_BLOCK_PLANNED);
			$dorScheduleAvailabilityBlockPlanned->setValue('0');
			self::$em->flush();
			self::$em->refresh($dorScheduleAvailabilityBlockPlanned);
			$currentDorScheduleAvailabilityBlockPlannedState = $dorScheduleAvailabilityBlockPlanned->getNormalizedValue();
		}
		// On 2019-01-04 is an published assignment assigned to employee id 52 hardcoded in admin_companydemo
		//The register properties to post
		$start_date = '2019-01-04T00:00:00+00:00';
		$end_date = '2019-01-04T23:00:00+00:00';
		$type = 'UNAVAILABLE';
		$remark = 'BLOCK PLANNED TEST 1';
		$employee = '{"id" : 52}';

		$contentString = '[{
            "start_date": "'.$start_date.'",
            "end_date": "'.$end_date.'",
            "type": "'.$type.'",
            "remark": "'.$remark.'",
            "employee": '.$employee.'
        }]';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
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
		$registers = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($registers) > 0);

		//Test if exactly x registers are returned
		$this->assertTrue(1 === count($registers['data']));

		//Test that each registers has at minimum the below values
		foreach ($registers['data'] as $register) {
			$this->assertArrayHasKey('id', $register);
			$this->assertArrayHasKey('start_date', $register);
			$this->assertArrayHasKey('end_date', $register);
			$this->assertArrayHasKey('type', $register);
			$this->assertArrayHasKey('remark', $register);
			// keep track of the ids of the created registers in DB
			self::$createdRegisters[] = $register['id'];
		}

		// test if registers are indeed inserted in DB
		/** @var Register $register */
		$register = self::$registerRepo->find($registers['data'][0]['id']);
		$this->assertNotNull($register);
		$this->assertEquals(52, $register->getEmployee()->getId());
		$this->assertEquals(888888, $register->getSessionId());
		$this->assertEquals($remark, $register->getOriginalRemark());

		// manually set DOR_SCHEDULE_AVAILABILITY_BLOCK_PLANNED to 1;
		/** @var SystemConfig $dorScheduleAvailabilityBlockPlanned */
		$dorScheduleAvailabilityBlockPlanned = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_SCHEDULE_AVAILABILITY_BLOCK_PLANNED);
		$dorScheduleAvailabilityBlockPlanned->setValue('1');
		self::$em->flush();
		self::$em->refresh($dorScheduleAvailabilityBlockPlanned);
		$currentDorScheduleAvailabilityBlockPlannedState = $dorScheduleAvailabilityBlockPlanned->getNormalizedValue();

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is PRECONDITION FAILED 412
		$this->assertSame(Response::HTTP_PRECONDITION_FAILED, $response->getStatusCode());

		// manually set DOR_SCHEDULE_AVAILABILITY_BLOCK_PLANNED to 2;
		/** @var SystemConfig $dorScheduleAvailabilityBlockPlanned */
		$dorScheduleAvailabilityBlockPlanned = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_SCHEDULE_AVAILABILITY_BLOCK_PLANNED);
		$dorScheduleAvailabilityBlockPlanned->setValue('2');
		self::$em->flush();
		self::$em->refresh($dorScheduleAvailabilityBlockPlanned);
		$currentDorScheduleAvailabilityBlockPlannedState = $dorScheduleAvailabilityBlockPlanned->getNormalizedValue();

		// On 2019-01-03 is an unpublished assignment assigned to employee id 52 hardcoded in admin_companydemo
		//The register properties to post
		$start_date = '2019-01-04T00:00:00+00:00';
		$end_date = '2019-01-04T23:00:00+00:00';
		$type = 'UNAVAILABLE';
		$remark = 'BLOCK PLANNED TEST 1';
		$employee = '{"id" : 52}';

		$contentString = '[{
            "start_date": "'.$start_date.'",
            "end_date": "'.$end_date.'",
            "type": "'.$type.'",
            "remark": "'.$remark.'",
            "employee": '.$employee.'
        }]';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is PRECONDITION FAILED 412
		$this->assertSame(Response::HTTP_PRECONDITION_FAILED, $response->getStatusCode());

		// manually set DOR_SCHEDULE_AVAILABILITY_BLOCK_PLANNED to original state;
		if ($currentDorScheduleAvailabilityBlockPlannedState !== self::$originalDorAvailabilityBlockPlannedState) {
			/** @var SystemConfig $dorScheduleAvailabilityBlockPlanned */
			$dorScheduleAvailabilityBlockPlanned = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_SCHEDULE_AVAILABILITY_BLOCK_PLANNED);
			$dorScheduleAvailabilityBlockPlanned->setValue(self::$originalDorAvailabilityBlockPlannedState);
			self::$em->flush();
			self::$em->refresh($dorScheduleAvailabilityBlockPlanned);
			$currentDorScheduleAvailabilityBlockPlannedState = $dorScheduleAvailabilityBlockPlanned->getNormalizedValue();
		}
	}

	/**
	 * Test disable registration with DOR_FTE_MW_HOURREGISTRATION setting.
	 *
	 * @depends testBatchCreateUnavailabilityRegistersOnAlreadyAssignedDate
	 */
	public function testDisabledRegistrationWithDorFteMwHourRegistration()
	{
		// manually set DOR_FTE_MW_HOURREGISTRATION to 0;
		/** @var SystemConfig $dorFteMwHourRegistration */
		$dorFteMwHourRegistration = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_FTE_MW_HOURREGISTRATION);
		$dorFteMwHourRegistration->setValue('0');
		self::$em->flush();
		self::$em->refresh($dorFteMwHourRegistration);
		$currentDorFteMwHourRegistrationState = $dorFteMwHourRegistration->getNormalizedValue();

		//The register properties to post
		$start_date = '2017-09-01T00:00:00+00:00';
		$end_date = '2017-09-01T23:00:00+00:00';
		$type = 'WORK';
		$remark = 'CREATED BY TEST 1';
		$features = 2;
		$breakDuration = 30;
		$kilometers = 15.50;
		$employee = '{"id" : 52}';
		$department = '{"id" : 57}';
		$assignment = '{"id" : 102387}';

		$contentString = '[{
            "start_date": "'.$start_date.'",
            "end_date": "'.$end_date.'",
            "type": "'.$type.'",
            "remark": "'.$remark.'",
            "features": '.$features.',
            "break_duration": '.$breakDuration.',
            "kilometers": '.$kilometers.',
            "employee": '.$employee.',
            "department": '.$department.',
            "assignment": '.$assignment.'
        }]';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is FORBIDDEN 403
		$this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());

		//The register properties to put
		$id = self::$createdRegisters[2];
		$remark = 'EDITED BY TEST 1';
		$breakDuration = 60;
		$employee = '{"id" : 68}';

		$contentString = '[{
            "id": '.$id.',
            "remark": "'.$remark.'",
            "break_duration": '.$breakDuration.',
            "employee": '.$employee.'
        }]';

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_PUT,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is FORBIDDEN 403
		$this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());

		//The register properties to DELETE
		$id = self::$createdRegisters[2];

		$contentString = '[{
            "id": '.$id.'
        }]';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_DELETE,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is FORBIDDEN 403
		$this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_DELETE,
			'/api/v1/registers/'.$id,
			array(),
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is FORBIDDEN 403
		$this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());

		// manually set DOR_FTE_MW_HOURREGISTRATION to original state;
		if ($currentDorFteMwHourRegistrationState !== self::$originalDorFteMwHourRegistrationState) {
			/** @var SystemConfig $dorFteMwHourRegistration */
			$dorFteMwHourRegistration = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_FTE_MW_HOURREGISTRATION);
			$dorFteMwHourRegistration->setValue(self::$originalDorFteMwHourRegistrationState);
			self::$em->flush();
			self::$em->refresh($dorFteMwHourRegistration);
			$currentDorFteMwHourRegistrationState = $dorFteMwHourRegistration->getNormalizedValue();
		}
	}

	/**
	 * Test batch create Registers With Different Timezone Date Strings.
	 */
	public function testBatchCreateRegistersWithDifferentTimezoneDateStrings()
	{
		//The register properties to post
		$start_date = '2017-09-06T00:00:00+09:00';
		$end_date = '2017-09-06T23:00:00+09:00';
		$type = 'WORK';
		$remark = 'CREATED BY TEST 1';
		$features = 2;
		$breakDuration = 30;
		$kilometers = 15.50;
		$employee = '{"id" : 52}';
		$department = '{"id" : 57}';
		$assignment = '{"id" : 102387}';

		$contentString = '[{
            "start_date": "'.$start_date.'",
            "end_date": "'.$end_date.'",
            "type": "'.$type.'",
            "remark": "'.$remark.'",
            "features": '.$features.',
            "break_duration": '.$breakDuration.',
            "kilometers": '.$kilometers.',
            "employee": '.$employee.',
            "department": '.$department.',
            "assignment": '.$assignment.'
        }]';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
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
		$registers = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($registers) > 0);

		//Test if exactly x registers are returned
		$this->assertTrue(1 === count($registers['data']));

		//Test that each registers has at minimum the below values
		foreach ($registers['data'] as $register) {
			$this->assertArrayHasKey('id', $register);
			$this->assertArrayHasKey('start_date', $register);
			$this->assertArrayHasKey('end_date', $register);
			$this->assertArrayHasKey('type', $register);
			$this->assertArrayHasKey('remark', $register);
			// keep track of the ids of the created registers in DB
			self::$createdRegisters[] = $register['id'];
			$lastCreatedRegisterId = $register['id'];
		}

		// test if registers are indeed inserted in DB
		/** @var Register $register */
		$register = self::$registerRepo->find($lastCreatedRegisterId);
		$this->assertNotNull($register);
		$this->assertEquals($lastCreatedRegisterId, $register->getId());
		$this->assertEquals('2017-09-05T17:00:00+02:00', $register->getStartDate()->format(\DateTime::RFC3339));
		$this->assertEquals('2017-09-06T16:00:00+02:00', $register->getEndDate()->format(\DateTime::RFC3339));
		$this->assertEquals(52, $register->getEmployee()->getId());
		$this->assertEquals(888888, $register->getSessionId());
		$this->assertEquals(23, $register->getWorkDuration());
		$this->assertEquals(22.5, $register->getCalculatedWorkDuration());
		$this->assertEquals($breakDuration, $register->getBreakDuration());
		$this->assertEquals($features, $register->getFeatures());
		$this->assertEquals($remark, $register->getOriginalRemark());
		$this->assertEquals($kilometers, $register->getKilometers());
		$this->assertEquals(57, $register->getDepartment()->getId());
		$this->assertEquals(102387, $register->getAssignment()->getId());
		$this->assertNull($register->getModifiedBy());
		$this->assertNotNull($register->getLocation());
		$this->assertNotNull($register->getActivity());
	}

	/**
	 * Test fail double register from the same user for an open assignment.
	 */
	public function testFailedRegisterDoublePreferredAssignment()
	{
		//The register properties to post
		$start_date = '2017-09-01T00:00:00+00:00';
		$end_date = '2017-09-01T23:00:00+00:00';
		$type = 'PREFERENCE';
		$remark = 'CREATED BY TEST 1';
		$features = 2;
		$breakDuration = 30;
		$kilometers = 15.50;
		$employee = '{"id" : 52}';
		$department = '{"id" : 57}';
		$assignment = '{"id" : 104063}';

		$contentString = '[{
            "start_date": "'.$start_date.'",
            "end_date": "'.$end_date.'",
            "type": "'.$type.'",
            "remark": "'.$remark.'",
            "features": '.$features.',
            "break_duration": '.$breakDuration.',
            "kilometers": '.$kilometers.',
            "employee": '.$employee.',
            "department": '.$department.',
            "assignment": '.$assignment.'
        }]';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
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
		$createdRegisters = json_decode($response->getContent(), true);
		// test if registers are indeed inserted in DB
		foreach ($createdRegisters['data'] as $createdRegister) {
			// make sure all created shiftSwapRequests are deleted from DB
			// keep track of the ids of the created registers in DB
			self::$createdRegisters[] = $createdRegister['id'];

			/** @var Register $register */
			$register = self::$registerRepo->find($createdRegister['id']);
			$this->assertNotNull($register);
			$this->assertEquals($createdRegister['id'], $register->getId());
			$this->assertEquals(52, $register->getEmployee()->getId());
			$this->assertEquals($type, $register->getType());
			$this->assertEquals(104063, $register->getAssignment()->getId());
		}

		// Send twice so we always have a duplicate
		$client->request(
			Request::METHOD_POST,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is PRECONDITION FAILED 412
		$this->assertSame(Response::HTTP_PRECONDITION_FAILED, $response->getStatusCode());
	}

	/**
	 * Test fail create register with overlapping.
	 */
	public function testFailedCreateRegistersWithOverlapping()
	{
		//The register properties to post
		$start_date = '2017-09-01T00:00:00+02:00';
		$end_date = '2017-09-01T23:00:00+02:00';
		$type = 'WORK';
		$remark = 'CREATED BY TEST 1';
		$features = 2;
		$breakDuration = 30;
		$kilometers = 15.50;
		$employee = '{"id" : 52}';
		$department = '{"id" : 57}';
		$assignment = '{"id" : 102387}';

		$contentString = '[{
            "start_date": "'.$start_date.'",
            "end_date": "'.$end_date.'",
            "type": "'.$type.'",
            "remark": "'.$remark.'",
            "features": '.$features.',
            "break_duration": '.$breakDuration.',
            "kilometers": '.$kilometers.',
            "employee": '.$employee.',
            "department": '.$department.',
            "assignment": '.$assignment.'
        }]';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/registers',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is 412 PRECONDITION_FAILED
		$this->assertSame(Response::HTTP_PRECONDITION_FAILED, $response->getStatusCode());

		// Test if error message contains string
		$this->assertContains('overlaps with an existing Register', $response->getContent());
	}
}
