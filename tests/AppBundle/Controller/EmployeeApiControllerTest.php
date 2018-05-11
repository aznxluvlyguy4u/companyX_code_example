<?php

namespace AppBundle\Tests\Controller;

use AppBundle\Entity\SystemConfig;
use AppBundle\Enumerator\HttpHeader;
use AppBundle\Enumerator\SystemConfigKey;
use AppBundle\Repository\AssignmentRepository;
use AppBundle\Repository\ClockIntervalRepository;
use AppBundle\Repository\ClockMomentRepository;
use AppBundle\Repository\RegisterRepository;
use AppBundle\Repository\SystemConfigRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use AppBundle\Util\Constants;

/**
 * Class EmployeeApiControllerTest.
 */
class EmployeeApiControllerTest extends WebTestCase
{
	/** @var string */
	private static $token;

	/** @var string */
	private static $nonAdminToken;

	/** @var string */
	private static $headquarterToken;

	/**
	 * @var EntityManager
	 */
	private static $em;

	/** @var bool */
	private static $originalPhonelistRestrictState;

	/** @var bool */
	private static $originalEmployeesPrivacyModeState;

	/** @var string */
	private static $originalThemeDisabledMenuItemsState;

	/** @var int */
	private static $originalHeadquarterIdState;

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

		//set up headquarter token
		//The company to identify
		$companyName = 'companydemo';
		$contentString = '{"'.Constants::QUERY_PARAM_COMPANY_NAME.'": "'.$companyName.'"}';

		//Authorization - Basic authentication header
		$headers = array(
			'PHP_AUTH_USER' => 'evelien', //Test user with access to headquarter
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
			self::$headquarterToken = $tokenDetails['data']['token'];
		}

		// Register classes to use mock clock
		ClockMock::register(__CLASS__);
		ClockMock::register(AssignmentRepository::class);
		ClockMock::register(RegisterRepository::class);
		ClockMock::register(ClockMomentRepository::class);
		ClockMock::register(ClockIntervalRepository::class);

		// set up entity manager
		self::bootKernel();
		self::$em = static::$kernel->getContainer()
			->get('doctrine')
			->getManager('customer');

		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = self::$em->getRepository(SystemConfig::class);
		$phonelistRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
		$employeesPrivacyMode = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_EMPLOYEES_PRIVACYMODE);
		$themeDisabledMenuItems = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_THEME_DISABLED_MENU_ITEMS);
		$headquarterId = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_HEADQUARTER_ID);

		/** @var SystemConfig $phonelistRestrict */
		if ($phonelistRestrict) {
			self::$originalPhonelistRestrictState = $phonelistRestrict->getNormalizedValue();
		} else {
			// create the system config for test but delete it lateron
			self::$em->clear();
			$newPhonelistRestrict = new SystemConfig();
			$newPhonelistRestrict->setKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
			$newPhonelistRestrict->setValue('no');
			self::$em->persist($newPhonelistRestrict);
			self::$em->flush();
			self::$em->refresh($newPhonelistRestrict);
			self::$originalPhonelistRestrictState = false;
			self::$createdSystemConfigs[] = $newPhonelistRestrict;
		}

		/** @var SystemConfig $employeesPrivacyMode */
		if ($employeesPrivacyMode) {
			self::$originalEmployeesPrivacyModeState = $employeesPrivacyMode->getNormalizedValue();
		} else {
			// create the system config for test but delete it lateron
			self::$em->clear();
			$newEmployeesPrivacyMode = new SystemConfig();
			$newEmployeesPrivacyMode->setKey(SystemConfigKey::DOR_EMPLOYEES_PRIVACYMODE);
			$newEmployeesPrivacyMode->setValue(0);
			self::$em->persist($newEmployeesPrivacyMode);
			self::$em->flush();
			self::$em->refresh($newEmployeesPrivacyMode);
			self::$originalEmployeesPrivacyModeState = $newEmployeesPrivacyMode->getNormalizedValue();
			self::$createdSystemConfigs[] = $newEmployeesPrivacyMode;
		}

		/** @var SystemConfig $themeDisabledMenuItems */
		if ($themeDisabledMenuItems) {
			self::$originalThemeDisabledMenuItemsState = $themeDisabledMenuItems->getNormalizedValue();
			// Manually set DOR_THEME_DISABLED_MENU_ITEMS to empty string so that tests can pass
			self::$em->clear();
			$themeDisabledMenuItems->setValue('');
			self::$em->flush();
		} else {
			// create the system config for test but delete it lateron
			self::$em->clear();
			$newThemeDisabledMenuItems = new SystemConfig();
			$newThemeDisabledMenuItems->setKey(SystemConfigKey::DOR_THEME_DISABLED_MENU_ITEMS);
			$newThemeDisabledMenuItems->setValue('');
			$newThemeDisabledMenuItems->setObjectId(0);
			self::$em->persist($newThemeDisabledMenuItems);
			self::$em->flush();
			// Refresh to allow postload listeners to kick in
			self::$em->refresh($newThemeDisabledMenuItems);
			self::$originalThemeDisabledMenuItemsState = $newThemeDisabledMenuItems->getNormalizedValue();
			self::$createdSystemConfigs[] = $newThemeDisabledMenuItems;
		}

		/** @var SystemConfig $headquarterId */
		if ($headquarterId) {
			self::$originalHeadquarterIdState = $headquarterId->getNormalizedValue();
		} else {
			// create the system config for test but delete it lateron
			self::$em->clear();
			$newHeadquarterId = new SystemConfig();
			$newHeadquarterId->setKey(SystemConfigKey::DOR_HEADQUARTER_ID);
			$newHeadquarterId->setValue('104');
			self::$em->persist($newHeadquarterId);
			self::$em->flush();
			self::$em->refresh($newHeadquarterId);
			self::$originalHeadquarterIdState = $newHeadquarterId->getNormalizedValue();
			self::$createdSystemConfigs[] = $newHeadquarterId;
		}

		self::$em->clear();
	}

	public static function tearDownAfterClass()
	{
		ClockMock::withClockMock(false);
		self::$em->clear();

		// manually set DOR_THEME_DISABLED_MENU_ITEMS to original state;
		/** @var SystemConfig $themeDisabledMenuItems */
		$systemConfigRepo = self::$em->getRepository(SystemConfig::class);
		$themeDisabledMenuItems = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_THEME_DISABLED_MENU_ITEMS);
		if ($themeDisabledMenuItems) {
			$themeDisabledMenuItems->setValue(self::$originalThemeDisabledMenuItemsState);
		}

		foreach (self::$createdSystemConfigs as $createdSystemConfig) {
			$systemConfigRepo = self::$em->getRepository(SystemConfig::class);
			$toBeDeletedSystemConfig = $systemConfigRepo->find($createdSystemConfig->getId());
			self::$em->remove($toBeDeletedSystemConfig);
		}

		self::$em->flush();
		self::$em->close();
		self::$em = null; // avoid memory leaks
	}

	/**
	 * Tests the retrieval of a list of employees for a given office with varying result depending on Dor.Telefoonlijst.Restrict setting.
	 */
	public function testGetEmployeesByOfficeWithRestrictionCheck()
	{
		// manually set phonelistRestrict on if its off;
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = self::$em->getRepository(SystemConfig::class);
		$currentPhonelistRestrictState = self::$originalPhonelistRestrictState;
		if (!$currentPhonelistRestrictState) {
			/** @var SystemConfig $dorPhonelistRestrict */
			$dorPhonelistRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
			$dorPhonelistRestrict->setValue('yes');
			self::$em->flush();
			self::$em->refresh($dorPhonelistRestrict);
			$currentPhonelistRestrictState = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT)->getNormalizedValue();
		}

		//The office to get employees from
		$officeId = 55;

		$headers = array(
			 'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			 Request::METHOD_GET,
			 '/api/v1/employees?officeId='.$officeId,
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

		//Test if exactly x employees are returned
		$this->assertTrue(12 === count($employees['data']));

		//Test that each person has at minimum the below values
		foreach ($employees['data'] as $employee) {
			$this->assertArrayHasKey('id', $employee);
			$this->assertArrayHasKey('firstname', $employee);
			$this->assertArrayHasKey('assignments', $employee);
			$this->assertArrayHasKey('registers', $employee);
		}

		// manually set phonelistRestrict off if its on;
		$dorPhonelistRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
		$dorPhonelistRestrict->setValue('no');
		self::$em->flush();
		self::$em->refresh($dorPhonelistRestrict);
		$currentPhonelistRestrictState = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT)->getNormalizedValue();

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees?officeId='.$officeId,
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

		//Test if exactly x employees are returned
		$this->assertTrue(18 === count($employees['data']));

		//Test that each person has at minimum the below values
		foreach ($employees['data'] as $employee) {
			$this->assertArrayHasKey('id', $employee);
			$this->assertArrayHasKey('firstname', $employee);
			$this->assertArrayHasKey('assignments', $employee);
			$this->assertArrayHasKey('registers', $employee);
		}

		// return phonelist restrict to original state
		if ($currentPhonelistRestrictState !== self::$originalPhonelistRestrictState) {
			$dorPhonelistRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
			$dorPhonelistRestrict->setValue('yes');
			self::$em->flush();
			self::$em->refresh($dorPhonelistRestrict);
		}
	}

	/**
	 * Tests the retrieval of a list of employees for a given department with varying result depending on Dor.Telefoonlijst.Restrict setting.
	 */
	public function testGetEmployeesByDepartmentWithRestrictionCheck()
	{
		// manually set phonelistRestrict on if its off;
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = self::$em->getRepository(SystemConfig::class);
		$currentPhonelistRestrictState = self::$originalPhonelistRestrictState;
		if (!$currentPhonelistRestrictState) {
			/** @var SystemConfig $dorPhonelistRestrict */
			$dorPhonelistRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
			$dorPhonelistRestrict->setValue('yes');
			self::$em->flush();
			self::$em->refresh($dorPhonelistRestrict);
			$currentPhonelistRestrictState = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT)->getNormalizedValue();
		}

		//The department to get employees from
		$departmentId = 56;

		$headers = array(
			 'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			 Request::METHOD_GET,
			 '/api/v1/employees?departmentId='.$departmentId,
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

		//Test if exactly x employees are returned
		$this->assertTrue(11 === count($employees['data']));

		//Test that each person has at minimum the below values
		foreach ($employees['data'] as $employee) {
			$this->assertArrayHasKey('id', $employee);
			$this->assertArrayHasKey('firstname', $employee);
			$this->assertArrayHasKey('assignments', $employee);
			$this->assertArrayHasKey('registers', $employee);
		}

		// manually set phonelistRestrict off if its on;
		$dorPhonelistRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
		$dorPhonelistRestrict->setValue('no');
		self::$em->flush();
		self::$em->refresh($dorPhonelistRestrict);
		$currentPhonelistRestrictState = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT)->getNormalizedValue();

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees?departmentId='.$departmentId,
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

		//Test if exactly x employees are returned
		$this->assertTrue(13 === count($employees['data']));

		//Test that each person has at minimum the below values
		foreach ($employees['data'] as $employee) {
			$this->assertArrayHasKey('id', $employee);
			$this->assertArrayHasKey('firstname', $employee);
			$this->assertArrayHasKey('assignments', $employee);
			$this->assertArrayHasKey('registers', $employee);
		}

		// return phonelist restrict to original state
		if ($currentPhonelistRestrictState !== self::$originalPhonelistRestrictState) {
			$dorPhonelistRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
			$dorPhonelistRestrict->setValue('yes');
			self::$em->flush();
			self::$em->refresh($dorPhonelistRestrict);
		}
	}

	/**
	 * Tests the retrieval of a list of employees for a given string with searchByName queryParam.
	 */
	public function testSearchEmployeesByNameWithRestrictionCheck()
	{
		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		//The string to search for
		$name = 'pet';

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees?name='.$name,
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

		//Test if exactly 2 employees are returned
		$this->assertTrue(2 === count($employees['data']));

		//Test that each person has at minimum the below values
		foreach ($employees['data'] as $employee) {
			$this->assertArrayHasKey('id', $employee);
			$this->assertArrayHasKey('firstname', $employee);
			$this->assertArrayHasKey('assignments', $employee);
			$this->assertArrayHasKey('registers', $employee);
			if (43 == $employee['id']) {
				$this->assertEquals('Pet', $employee['lastname']);
			}
			if (52 == $employee['id']) {
				$this->assertEquals('Peter', $employee['firstname']);
			}
		}

		/**
		 * Test query with departmentId and searchByName query parameters.
		 */

		//The string to search for
		$name = 'ende';
		$departmentId = 56;

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees?departmentId='.$departmentId.'&name='.$name,
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

		//Test if exactly 2 employees are returned
		$this->assertTrue(2 === count($employees['data']));

		//Test that each person has at minimum the below values
		foreach ($employees['data'] as $employee) {
			$this->assertArrayHasKey('id', $employee);
			$this->assertArrayHasKey('firstname', $employee);
			$this->assertArrayHasKey('assignments', $employee);
			$this->assertArrayHasKey('registers', $employee);
			if (19 == $employee['id']) {
				$this->assertEquals('Ende', $employee['lastname']);
			}
			if (60 == $employee['id']) {
				$this->assertEquals('Evelien', $employee['firstname']);
			}
		}
	}

	/**
	 * Tests the retrieval of an employee with varying result depending on Dor.Telefoonlijst.Restrict setting.
	 */
	public function testGetEmployeeWithRestrictionCheck()
	{
		// manually set phonelistRestrict on if its off;
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = self::$em->getRepository(SystemConfig::class);
		$currentPhonelistRestrictState = self::$originalPhonelistRestrictState;
		if (!$currentPhonelistRestrictState) {
			/** @var SystemConfig $dorPhonelistRestrict */
			$dorPhonelistRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
			$dorPhonelistRestrict->setValue('yes');
			self::$em->flush();
			self::$em->refresh($dorPhonelistRestrict);
			$currentPhonelistRestrictState = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT)->getNormalizedValue();
		}

		//The employee to get details from
		$employeeId = 38;

		$headers = array(
			 'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			 Request::METHOD_GET,
			 '/api/v1/employees/'.$employeeId,
			 array(),
			 array(),
			 $headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is NOT FOUND
		$this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());

		// manually set phonelistRestrict off if its on;
		$dorPhonelistRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
		$dorPhonelistRestrict->setValue('no');
		self::$em->flush();
		self::$em->refresh($dorPhonelistRestrict);
		$currentPhonelistRestrictState = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT)->getNormalizedValue();

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees/'.$employeeId,
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
		$employee = json_decode($response->getContent(), true);
		$employee = $employee['data'];

		//Test that an employee has at minimum the below values
		$this->assertArrayHasKey('id', $employee);
		$this->assertArrayHasKey('firstname', $employee);
		$this->assertArrayHasKey('lastname', $employee);
		$this->assertArrayHasKey('assignments', $employee);
		$this->assertArrayHasKey('registers', $employee);

		// return phonelist restrict to original state
		if ($currentPhonelistRestrictState !== self::$originalPhonelistRestrictState) {
			$dorPhonelistRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
			$dorPhonelistRestrict->setValue('yes');
			self::$em->flush();
			self::$em->refresh($dorPhonelistRestrict);
		}
	}

	/**
	 * Tests the retrieval of all employees of a company with varying result depending on Dor.Telefoonlijst.Restrict setting.
	 */
	public function testGetAllEmployeesWithRestrictionCheckAndPagination()
	{
		// manually set phonelistRestrict on if its off;
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = self::$em->getRepository(SystemConfig::class);
		$currentPhonelistRestrictState = self::$originalPhonelistRestrictState;
		if (!$currentPhonelistRestrictState) {
			/** @var SystemConfig $dorPhonelistRestrict */
			$dorPhonelistRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
			$dorPhonelistRestrict->setValue('yes');
			self::$em->flush();
			self::$em->refresh($dorPhonelistRestrict);
			$currentPhonelistRestrictState = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT)->getNormalizedValue();
		}

		$headers = array(
			 'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			 Request::METHOD_GET,
			 '/api/v1/employees',
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

		//Test if exactly x employees are returned
		$this->assertTrue(12 === count($employees['data']));

		//Test that each person has at minimum the below values
		foreach ($employees['data'] as $employee) {
			$this->assertArrayHasKey('id', $employee);
			$this->assertArrayHasKey('firstname', $employee);
			$this->assertArrayHasKey('assignments', $employee);
			$this->assertArrayHasKey('registers', $employee);
		}

		// manually set phonelistRestrict off if its on;
		$dorPhonelistRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
		$dorPhonelistRestrict->setValue('no');
		self::$em->flush();
		self::$em->refresh($dorPhonelistRestrict);
		$currentPhonelistRestrictState = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT)->getNormalizedValue();

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees',
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

		//Test if exactly x employees are returned
		$this->assertTrue(82 === count($employees['data']));

		//Test that each person has at minimum the below values
		foreach ($employees['data'] as $employee) {
			$this->assertArrayHasKey('id', $employee);
			$this->assertArrayHasKey('firstname', $employee);
			$this->assertArrayHasKey('assignments', $employee);
			$this->assertArrayHasKey('registers', $employee);
		}

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees?page=2&limit=50',
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

		//Test if exactly x employees are returned
		$this->assertTrue(32 === count($employees['data']));

		//Test that each person has at minimum the below values
		foreach ($employees['data'] as $employee) {
			$this->assertArrayHasKey('id', $employee);
			$this->assertArrayHasKey('firstname', $employee);
			$this->assertArrayHasKey('assignments', $employee);
			$this->assertArrayHasKey('registers', $employee);
		}

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees?limit=25',
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

		//Test if exactly x employees are returned
		$this->assertTrue(25 === count($employees['data']));

		//Test that each person has at minimum the below values
		foreach ($employees['data'] as $employee) {
			$this->assertArrayHasKey('id', $employee);
			$this->assertArrayHasKey('firstname', $employee);
			$this->assertArrayHasKey('assignments', $employee);
			$this->assertArrayHasKey('registers', $employee);
		}

		// return phonelist restrict to original state
		if ($currentPhonelistRestrictState !== self::$originalPhonelistRestrictState) {
			$dorPhonelistRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
			$dorPhonelistRestrict->setValue('yes');
			self::$em->flush();
			self::$em->refresh($dorPhonelistRestrict);
		}
	}

	/**
	 * Tests the retrieval of assignments of a given employee with date range.
	 */
	public function testGetEmployeeAssignmentsWithDateRange()
	{
		//The employee to get details from
		$employeeId = 52;

		$dateFrom = '2017-04-01';
		$dateTo = '2017-04-20';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees/'.$employeeId.'/assignments?dateFrom='.$dateFrom.'&dateTo='.$dateTo,
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

		//Test if exactly x assignments are returned
		$this->assertTrue(9 === count($assignments['data']));

		//Test that each assignment has at minimum the below values
		foreach ($assignments['data'] as $assignment) {
			$this->assertArrayHasKey('id', $assignment);
			$this->assertArrayHasKey('start_date', $assignment);
			$this->assertArrayHasKey('break_duration', $assignment);
			$this->assertArrayHasKey('department', $assignment);
		}
	}

	/**
	 * Tests the retrieval of assignments of a other employee with admin token success.
	 */
	public function testGetOtherEmployeeAssignmentsWithNonAdminTokenSuccess()
	{
		//The employee to get details from
		$employeeId = 45; //Ivo

		$dateFrom = '2017-04-01';
		$dateTo = '2017-04-20';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees/'.$employeeId.'/assignments?dateFrom='.$dateFrom.'&dateTo='.$dateTo,
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

		//Test if exactly x assignments are returned
		$this->assertTrue(9 === count($assignments['data']));

		//Test that each assignment has at minimum the below values
		foreach ($assignments['data'] as $assignment) {
			$this->assertArrayHasKey('id', $assignment);
			$this->assertArrayHasKey('start_date', $assignment);
			$this->assertArrayHasKey('break_duration', $assignment);
			$this->assertArrayHasKey('department', $assignment);
		}
	}

	/**
	 * Tests the retrieval of assignments of a given employee with only dateFrom query parameter.
	 */
	public function testGetEmployeeAssignmentsWithOnlyDateFrom()
	{
		//The employee to get details from
		$employeeId = 52;

		$dateFrom = '2017-04-15';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees/'.$employeeId.'/assignments?dateFrom='.$dateFrom,
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

		//Test if exactly x assignments are returned
		$this->assertTrue(10 === count($assignments['data']));

		//Test that each assignment has at minimum the below values
		foreach ($assignments['data'] as $assignment) {
			$this->assertArrayHasKey('id', $assignment);
			$this->assertArrayHasKey('start_date', $assignment);
			$this->assertArrayHasKey('break_duration', $assignment);
			$this->assertArrayHasKey('department', $assignment);
		}
	}

	/**
	 * Tests the retrieval of assignments of a given employee with only dateTo query parameter.
	 */
	public function testGetEmployeeAssignmentsWithOnlyDateTo()
	{
		//The employee to get details from
		$employeeId = 52;

		$dateTo = '2017-04-15';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees/'.$employeeId.'/assignments?dateTo='.$dateTo,
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

		//Test if exactly x assignments are returned
		$this->assertTrue(5 === count($assignments['data']));

		//Test that each assignment has at minimum the below values
		foreach ($assignments['data'] as $assignment) {
			$this->assertArrayHasKey('id', $assignment);
			$this->assertArrayHasKey('start_date', $assignment);
			$this->assertArrayHasKey('break_duration', $assignment);
			$this->assertArrayHasKey('department', $assignment);
		}
	}

	/**
	 * Tests the retrieval of assignments of a given employee without date params which defaults at assignments of current month.
	 *
	 * @group time-sensitive
	 */
	public function testGetEmployeeAssignmentsWithoutDateQueryParams()
	{
		//The employee to get details from
		$employeeId = 52;

		//mock current time in registered classes
		ClockMock::withClockMock(strtotime('2017-04-05 01:00:00'));

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees/'.$employeeId.'/assignments',
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

		//Test if exactly x assignments are returned
		$this->assertTrue(15 === count($assignments['data']));

		//Test that each assignment has at minimum the below values
		foreach ($assignments['data'] as $assignment) {
			$this->assertArrayHasKey('id', $assignment);
			$this->assertArrayHasKey('start_date', $assignment);
			$this->assertArrayHasKey('break_duration', $assignment);
			$this->assertArrayHasKey('department', $assignment);
		}
	}

	/**
	 * Tests the retrieval of registers of a given employee with date range.
	 */
	public function testGetEmployeeRegistersWithDateRange()
	{
		//The employee to get details from
		$employeeId = 52;

		$dateFrom = '2016-08-01';
		$dateTo = '2016-08-31';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees/'.$employeeId.'/registers?dateFrom='.$dateFrom.'&dateTo='.$dateTo,
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
		$this->assertTrue(7 === count($registers['data']));

		//Test that each registers has at minimum the below values
		foreach ($registers['data'] as $register) {
			$this->assertArrayHasKey('id', $register);
			$this->assertArrayHasKey('start_date', $register);
			$this->assertArrayHasKey('end_date', $register);
			$this->assertArrayHasKey('type', $register);
		}
	}

	/**
	 * Tests the retrieval of registers of a given employee with only dateFrom query parameter.
	 */
	public function testGetEmployeeRegistersWithOnlyDateFrom()
	{
		//The employee to get details from
		$employeeId = 52;

		$dateFrom = '2016-08-29';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees/'.$employeeId.'/registers?dateFrom='.$dateFrom,
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
		$this->assertTrue(3 === count($registers['data']));

		//Test that each registers has at minimum the below values
		foreach ($registers['data'] as $register) {
			$this->assertArrayHasKey('id', $register);
			$this->assertArrayHasKey('start_date', $register);
			$this->assertArrayHasKey('end_date', $register);
			$this->assertArrayHasKey('type', $register);
		}
	}

	/**
	 * Tests the retrieval of registers of a given employee with only dateTo query parameter.
	 */
	public function testGetEmployeeRegistersWithOnlyDateTo()
	{
		//The employee to get details from
		$employeeId = 52;

		$dateTo = '2016-08-29';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees/'.$employeeId.'/registers?dateTo='.$dateTo,
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
	 * Tests the retrieval of registers of a given employee without date params which defaults at registers of current month.
	 *
	 * @group time-sensitive
	 */
	public function testGetEmployeeRegistersWithoutDateQueryParams()
	{
		//The employee to get details from
		$employeeId = 52;

		//mock current time in registered classes
		ClockMock::withClockMock(strtotime('2016-08-05 01:00:00'));

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees/'.$employeeId.'/registers',
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
		$this->assertTrue(7 === count($registers['data']));

		//Test that each registers has at minimum the below values
		foreach ($registers['data'] as $register) {
			$this->assertArrayHasKey('id', $register);
			$this->assertArrayHasKey('start_date', $register);
			$this->assertArrayHasKey('end_date', $register);
			$this->assertArrayHasKey('type', $register);
		}
	}

	/**
	 * Tests Dor.Employees.PrivacyMode setting 0 1 2 3.
	 */
	public function testGetEmployeesPrivacyModeWithDifferentSettings()
	{
		// Use regular user, because admin always has access to employee data
		//Authorization - Basic authentication header
		$headers = array(
			'PHP_AUTH_USER' => 'chrisbos', //Test user
			'PHP_AUTH_PW' => 'XGthJU1234#',
		);

		//The company to identify
		$companyName = 'companydemo';
		$contentString = '{"'.Constants::QUERY_PARAM_COMPANY_NAME.'": "'.$companyName.'"}';

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
			$nonAdminToken = $tokenDetails['data']['token'];
		}

		// manually set dor.employees.privacymode to 0;
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = self::$em->getRepository(SystemConfig::class);
		$currentPrivacyModeState = self::$originalEmployeesPrivacyModeState;

		/* @var SystemConfig $dorEmployeesPrivacyMode */
		self::$em->clear();
		$dorEmployeesPrivacyMode = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_EMPLOYEES_PRIVACYMODE);
		$dorEmployeesPrivacyMode->setValue('0');
		self::$em->flush();
		self::$em->refresh($dorEmployeesPrivacyMode);
		$currentPrivacyModeState = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_EMPLOYEES_PRIVACYMODE)->getNormalizedValue();

		//The employee to get details from
		$employeeId = 60;

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.$nonAdminToken,
		);

		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees/'.$employeeId,
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
		$employee = json_decode($response->getContent(), true);
		$employee = $employee['data'];

		//Test that an employee has at minimum the below values
		$this->assertArrayHasKey('email_address', $employee);
		$this->assertArrayHasKey('phone_number', $employee);

		// manually set dor.employees.privacymode to 1;
		/** @var SystemConfig $dorEmployeesPrivacyMode */
		$dorEmployeesPrivacyMode = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_EMPLOYEES_PRIVACYMODE);
		$dorEmployeesPrivacyMode->setValue('1');
		self::$em->flush();
		self::$em->refresh($dorEmployeesPrivacyMode);
		$currentPrivacyModeState = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_EMPLOYEES_PRIVACYMODE)->getNormalizedValue();

		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees/'.$employeeId,
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
		$employee = json_decode($response->getContent(), true);
		$employee = $employee['data'];

		//Test that an employee has at minimum the below values
		$this->assertArrayHasKey('email_address', $employee);
		$this->assertArrayNotHasKey('phone_number', $employee);

		// manually set dor.employees.privacymode to 2;
		/** @var SystemConfig $dorEmployeesPrivacyMode */
		$dorEmployeesPrivacyMode = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_EMPLOYEES_PRIVACYMODE);
		$dorEmployeesPrivacyMode->setValue('2');
		self::$em->flush();
		self::$em->refresh($dorEmployeesPrivacyMode);
		$currentPrivacyModeState = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_EMPLOYEES_PRIVACYMODE)->getNormalizedValue();

		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees/'.$employeeId,
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
		$employee = json_decode($response->getContent(), true);
		$employee = $employee['data'];

		//Test that an employee has at minimum the below values
		$this->assertArrayHasKey('phone_number', $employee);
		$this->assertArrayNotHasKey('email_address', $employee);

		// manually set dor.employees.privacymode to 3;
		/** @var SystemConfig $dorEmployeesPrivacyMode */
		$dorEmployeesPrivacyMode = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_EMPLOYEES_PRIVACYMODE);
		$dorEmployeesPrivacyMode->setValue('3');
		self::$em->flush();
		self::$em->refresh($dorEmployeesPrivacyMode);
		$currentPrivacyModeState = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_EMPLOYEES_PRIVACYMODE)->getNormalizedValue();

		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees/'.$employeeId,
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
		$employee = json_decode($response->getContent(), true);
		$employee = $employee['data'];

		//Test that an employee has at minimum the below values
		$this->assertArrayNotHasKey('phone_number', $employee);
		$this->assertArrayNotHasKey('email_address', $employee);

		// return employees privacymode to original state
		if ($currentPrivacyModeState !== self::$originalEmployeesPrivacyModeState) {
			$dorEmployeesPrivacyMode = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_EMPLOYEES_PRIVACYMODE);
			$dorEmployeesPrivacyMode->setValue(self::$originalEmployeesPrivacyModeState);
			self::$em->flush();
			self::$em->refresh($dorEmployeesPrivacyMode);
		}
	}

	/**
	 * Tests access control to phone numbers and email addresses.
	 */
	public function testGetEmployeesAccessControl()
	{
		// manually set dor.employees.privacymode to 0;
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = self::$em->getRepository(SystemConfig::class);
		$currentPrivacyModeState = self::$originalEmployeesPrivacyModeState;

		//Log in someone who doesn't have ADMINISTRATORS role
		//The company to identify
		$companyName = 'companydemo';
		$contentString = '{"'.Constants::QUERY_PARAM_COMPANY_NAME.'": "'.$companyName.'"}';

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
			$nonAdminToken = $tokenDetails['data']['token'];
		}

		/*
		 * Test get Employees with privacy mode 0
		 */
		/* @var SystemConfig $dorEmployeesPrivacyMode */
		self::$em->clear();
		$dorEmployeesPrivacyMode = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_EMPLOYEES_PRIVACYMODE);
		// Hide Phone Numbers only
		$dorEmployeesPrivacyMode->setValue('0');
		self::$em->flush();
		self::$em->refresh($dorEmployeesPrivacyMode);
		$currentPrivacyModeState = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_EMPLOYEES_PRIVACYMODE)->getNormalizedValue();

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.$nonAdminToken,
		);
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees',
			array(),
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is OK, and not empty, and if it is json
		$this->assertSame(Response::HTTP_OK, $response->getStatusCode());
		$this->assertNotEmpty($response->getContent());
		$this->assertSame(HttpHeader::APPLICATION_JSON, $response->headers->get(HttpHeader::CONTENT_TYPE));

		//Deserialize response, and check again that it is not empty
		$employees = json_decode($response->getContent(), true);
		$this->assertTrue(sizeof($employees) > 0);

		// Test that each person has at minimum the below values, we merge it till we have one mixed employee left.
		// Because we only need to know if one email and one telephone number show
		$employee = [];
		foreach ($employees['data'] as $emp) {
			$employee = array_merge($employee, $emp);
		}
		$this->assertArrayHasKey('phone_number', $employee);
		$this->assertArrayHasKey('email_address', $employee);

		/*
		 * Test get Employees with privacy mode 1
		 */
		/* @var SystemConfig $dorEmployeesPrivacyMode */
		self::$em->clear();
		$dorEmployeesPrivacyMode = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_EMPLOYEES_PRIVACYMODE);
		// Hide Phone Numbers only
		$dorEmployeesPrivacyMode->setValue('1');
		self::$em->flush();
		self::$em->refresh($dorEmployeesPrivacyMode);
		$currentPrivacyModeState = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_EMPLOYEES_PRIVACYMODE)->getNormalizedValue();

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.$nonAdminToken,
		);
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees',
			array(),
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is OK, and not empty, and if it is json
		$this->assertSame(Response::HTTP_OK, $response->getStatusCode());
		$this->assertNotEmpty($response->getContent());
		$this->assertSame(HttpHeader::APPLICATION_JSON, $response->headers->get(HttpHeader::CONTENT_TYPE));

		//Deserialize response, and check again that it is not empty
		$employees = json_decode($response->getContent(), true);
		$this->assertTrue(sizeof($employees) > 0);

		// Test that each person has at minimum the below values, we merge it till we have one mixed employee left.
		// Because we only need to know if one email and one telephone number show
		$employee = [];
		foreach ($employees['data'] as $emp) {
			$employee = array_merge($employee, $emp);
		}
		$this->assertArrayNotHasKey('phone_number', $employee);
		$this->assertArrayHasKey('email_address', $employee);

		/*
		 * Test get Employees with privacy mode 2
		 */
		/* @var SystemConfig $dorEmployeesPrivacyMode */
		self::$em->clear();
		$dorEmployeesPrivacyMode = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_EMPLOYEES_PRIVACYMODE);
		// Hide Phone Numbers only
		$dorEmployeesPrivacyMode->setValue('2');
		self::$em->flush();
		self::$em->refresh($dorEmployeesPrivacyMode);
		$currentPrivacyModeState = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_EMPLOYEES_PRIVACYMODE)->getNormalizedValue();

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.$nonAdminToken,
		);
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees',
			array(),
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is OK, and not empty, and if it is json
		$this->assertSame(Response::HTTP_OK, $response->getStatusCode());
		$this->assertNotEmpty($response->getContent());
		$this->assertSame(HttpHeader::APPLICATION_JSON, $response->headers->get(HttpHeader::CONTENT_TYPE));

		//Deserialize response, and check again that it is not empty
		$employees = json_decode($response->getContent(), true);
		$this->assertTrue(sizeof($employees) > 0);

		// Test that each person has at minimum the below values, we merge it till we have one mixed employee left.
		// Because we only need to know if one email and one telephone number show
		$employee = [];
		foreach ($employees['data'] as $emp) {
			$employee = array_merge($employee, $emp);
		}
		$this->assertArrayHasKey('phone_number', $employee);
		$this->assertArrayNotHasKey('email_address', $employee);

		/*
		 * Test get Employees with privacy mode 3
		 */
		/* @var SystemConfig $dorEmployeesPrivacyMode */
		self::$em->clear();
		$dorEmployeesPrivacyMode = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_EMPLOYEES_PRIVACYMODE);
		// Hide Phone Numbers only
		$dorEmployeesPrivacyMode->setValue('3');
		self::$em->flush();
		self::$em->refresh($dorEmployeesPrivacyMode);
		$currentPrivacyModeState = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_EMPLOYEES_PRIVACYMODE)->getNormalizedValue();

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.$nonAdminToken,
		);
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees',
			array(),
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is OK, and not empty, and if it is json
		$this->assertSame(Response::HTTP_OK, $response->getStatusCode());
		$this->assertNotEmpty($response->getContent());
		$this->assertSame(HttpHeader::APPLICATION_JSON, $response->headers->get(HttpHeader::CONTENT_TYPE));

		//Deserialize response, and check again that it is not empty
		$employees = json_decode($response->getContent(), true);
		$this->assertTrue(sizeof($employees) > 0);

		// Test that each person has at minimum the below values, we merge it till we have one mixed employee left.
		// Because we only need to know if one email and one telephone number show
		$employee = [];
		foreach ($employees['data'] as $emp) {
			$employee = array_merge($employee, $emp);
		}
		$this->assertArrayNotHasKey('phone_number', $employee);
		$this->assertArrayNotHasKey('email_address', $employee);

		/*
		 * Test get Employees with privacy mode 3, while the user logged in is an admin
		 */
		/* @var SystemConfig $dorEmployeesPrivacyMode */
		self::$em->clear();
		$dorEmployeesPrivacyMode = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_EMPLOYEES_PRIVACYMODE);
		// Hide Phone Numbers only
		$dorEmployeesPrivacyMode->setValue('3');
		self::$em->flush();
		self::$em->refresh($dorEmployeesPrivacyMode);
		$currentPrivacyModeState = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_EMPLOYEES_PRIVACYMODE)->getNormalizedValue();

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees',
			array(),
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is OK, and not empty, and if it is json
		$this->assertSame(Response::HTTP_OK, $response->getStatusCode());
		$this->assertNotEmpty($response->getContent());
		$this->assertSame(HttpHeader::APPLICATION_JSON, $response->headers->get(HttpHeader::CONTENT_TYPE));

		//Deserialize response, and check again that it is not empty
		$employees = json_decode($response->getContent(), true);
		$this->assertTrue(sizeof($employees) > 0);

		// Test that each person has at minimum the below values, we merge it till we have one mixed employee left.
		// Because we only need to know if one email and one telephone number show
		$employee = [];
		foreach ($employees['data'] as $emp) {
			$employee = array_merge($employee, $emp);
		}
		$this->assertArrayHasKey('phone_number', $employee);
		$this->assertArrayHasKey('email_address', $employee);

		// Test if dashboard/telefoonlijst or btools/telefoonlijst is blocked by CompanyX App
		// Manually set btools/telefoonlijst to DOR_THEME_DISABLED_MENU_ITEMS
		/* @var SystemConfig $dorEmployeesPrivacyMode */
		self::$em->clear();
		$dorThemeDisabledMenuItems = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_THEME_DISABLED_MENU_ITEMS);
		$dorThemeDisabledMenuItems->setValue('btools/telefoonlijst');
		self::$em->flush();
		self::$em->refresh($dorThemeDisabledMenuItems);
		$currentDorThemeDisabledMenuItemsState = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_THEME_DISABLED_MENU_ITEMS)->getNormalizedValue();

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.$nonAdminToken,
		);

		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees',
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

		//Test that each person has at minimum the below values
		foreach ($employees['data'] as $employee) {
			$this->assertArrayNotHasKey('phone_number', $employee);
			$this->assertArrayNotHasKey('email_address', $employee);
		}

		// Manually set btools/telefoonlijst to DOR_THEME_DISABLED_MENU_ITEMS
		/* @var SystemConfig $dorEmployeesPrivacyMode */
		self::$em->clear();
		$dorThemeDisabledMenuItems = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_THEME_DISABLED_MENU_ITEMS);
		$dorThemeDisabledMenuItems->setValue('dashboard/telefoonlijst');
		self::$em->flush();
		self::$em->refresh($dorThemeDisabledMenuItems);
		$currentDorThemeDisabledMenuItemsState = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_THEME_DISABLED_MENU_ITEMS)->getNormalizedValue();

		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees',
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

		//Test that each person has at minimum the below values
		foreach ($employees['data'] as $employee) {
			$this->assertArrayNotHasKey('phone_number', $employee);
			$this->assertArrayNotHasKey('email_address', $employee);
		}

		// Return  DOR_THEME_DISABLED_MENU_ITEMS to original state
		if ($currentDorThemeDisabledMenuItemsState !== self::$originalThemeDisabledMenuItemsState) {
			/* @var SystemConfig $dorEmployeesPrivacyMode */
			self::$em->clear();
			$dorThemeDisabledMenuItems = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_THEME_DISABLED_MENU_ITEMS);
			$dorThemeDisabledMenuItems->setValue(self::$originalThemeDisabledMenuItemsState);
			self::$em->flush();
			self::$em->refresh($dorThemeDisabledMenuItems);
		}

		// return employees privacymode to original state
		if ($currentPrivacyModeState !== self::$originalEmployeesPrivacyModeState) {
			self::$em->clear();
			$dorEmployeesPrivacyMode = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_EMPLOYEES_PRIVACYMODE);
			$dorEmployeesPrivacyMode->setValue(self::$originalEmployeesPrivacyModeState);
			self::$em->flush();
			self::$em->refresh($dorEmployeesPrivacyMode);
		}
	}

	/**
	 * Tests get employees by Headquarter as an Office.
	 */
	public function testGetEmployeesByHeadquarterAsOfficeWithRestrictionCheck()
	{
		// manually set phonelistRestrict on if its off;
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = self::$em->getRepository(SystemConfig::class);
		$currentPhonelistRestrictState = self::$originalPhonelistRestrictState;
		if (!$currentPhonelistRestrictState) {
			/** @var SystemConfig $dorPhonelistRestrict */
			$dorPhonelistRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
			$dorPhonelistRestrict->setValue('yes');
			self::$em->flush();
			self::$em->refresh($dorPhonelistRestrict);
			$currentPhonelistRestrictState = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT)->getNormalizedValue();
		}

		//The office to get employees from
		$currentHeadquarterIdState = self::$originalHeadquarterIdState;

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees?officeId='.$currentHeadquarterIdState,
			array(),
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is NOT FOUND
		$this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());

		// manually set phonelistRestrict off if its on;
		$dorPhonelistRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
		$dorPhonelistRestrict->setValue('no');
		self::$em->flush();
		self::$em->refresh($dorPhonelistRestrict);
		$currentPhonelistRestrictState = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT)->getNormalizedValue();

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees?officeId='.$currentHeadquarterIdState,
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

		//Test if exactly x employees are returned
		$this->assertTrue(6 === count($employees['data']));

		//Test that each person has at minimum the below values
		foreach ($employees['data'] as $employee) {
			$this->assertArrayHasKey('id', $employee);
			$this->assertArrayHasKey('firstname', $employee);
			$this->assertArrayHasKey('assignments', $employee);
			$this->assertArrayHasKey('registers', $employee);
		}

		// return phonelist restrict to original state
		if ($currentPhonelistRestrictState !== self::$originalPhonelistRestrictState) {
			$dorPhonelistRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
			$dorPhonelistRestrict->setValue('yes');
			self::$em->flush();
			self::$em->refresh($dorPhonelistRestrict);
		}
	}

	/**
	 * Tests the retrieval of clockMoments of a given employee with date range.
	 */
	public function testGetEmployeeClockMomentsWithDateRange()
	{
		//The employee to get details from
		$employeeId = 52;

		$dateFrom = '2016-04-01';
		$dateTo = '2016-05-31';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees/'.$employeeId.'/clock_moments?dateFrom='.$dateFrom.'&dateTo='.$dateTo,
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
		$clockMoments = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($clockMoments) > 0);

		//Test if exactly x registers are returned
		$this->assertTrue(4 === count($clockMoments['data']));

		//Test that each clockMoment has at minimum the below values
		foreach ($clockMoments['data'] as $clockMoment) {
			$this->assertArrayHasKey('id', $clockMoment);
			$this->assertArrayHasKey('time_stamp', $clockMoment);
			$this->assertArrayHasKey('status', $clockMoment);
			$this->assertArrayHasKey('active', $clockMoment);
			$this->assertArrayHasKey('modified_by', $clockMoment);
			$this->assertArrayHasKey('employee', $clockMoment);
			$this->assertArrayHasKey('clock_interval', $clockMoment);
			$this->assertArrayHasKey('department', $clockMoment);
		}
	}

	/**
	 * Tests the retrieval of clockMoments of a given employee with only dateFrom query parameter.
	 */
	public function testGetEmployeeClockMomentsWithOnlyDateFrom()
	{
		//The employee to get details from
		$employeeId = 52;

		$dateFrom = '2016-05-01';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees/'.$employeeId.'/clock_moments?dateFrom='.$dateFrom,
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
		$clockMoments = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($clockMoments) > 0);

		//Test if exactly x registers are returned
		$this->assertTrue(2 === count($clockMoments['data']));

		//Test that each clockMoment has at minimum the below values
		foreach ($clockMoments['data'] as $clockMoment) {
			$this->assertArrayHasKey('id', $clockMoment);
			$this->assertArrayHasKey('time_stamp', $clockMoment);
			$this->assertArrayHasKey('status', $clockMoment);
			$this->assertArrayHasKey('active', $clockMoment);
			$this->assertArrayHasKey('modified_by', $clockMoment);
			$this->assertArrayHasKey('employee', $clockMoment);
			$this->assertArrayHasKey('clock_interval', $clockMoment);
			$this->assertArrayHasKey('department', $clockMoment);
		}
	}

	/**
	 * Tests the retrieval of clockMoments of a given employee with only dateTo query parameter.
	 */
	public function testGetEmployeeClockMomentsWithOnlyDateTo()
	{
		//The employee to get details from
		$employeeId = 52;

		$dateTo = '2015-06-16';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees/'.$employeeId.'/clock_moments?dateTo='.$dateTo,
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
		$clockMoments = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($clockMoments) > 0);

		//Test if exactly x registers are returned
		$this->assertTrue(3 === count($clockMoments['data']));

		//Test that each clockMoment has at minimum the below values
		foreach ($clockMoments['data'] as $clockMoment) {
			$this->assertArrayHasKey('id', $clockMoment);
			$this->assertArrayHasKey('time_stamp', $clockMoment);
			$this->assertArrayHasKey('status', $clockMoment);
			$this->assertArrayHasKey('active', $clockMoment);
			$this->assertArrayHasKey('modified_by', $clockMoment);
			$this->assertArrayHasKey('employee', $clockMoment);
			$this->assertArrayHasKey('clock_interval', $clockMoment);
			$this->assertArrayHasKey('department', $clockMoment);
		}
	}

	/**
	 * Tests the retrieval of clockIntervals of a given employee without date params which defaults at clockIntervals of current month.
	 *
	 * @group time-sensitive
	 */
	public function testGetEmployeeClockMomentsWithoutDateQueryParams()
	{
		//The employee to get details from
		$employeeId = 52;

		//mock current time in registered classes
		ClockMock::withClockMock(strtotime('2016-02-05 01:00:00'));

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees/'.$employeeId.'/clock_moments',
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
		$clockMoments = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($clockMoments) > 0);

		//Test if exactly x registers are returned
		$this->assertTrue(4 === count($clockMoments['data']));

		//Test that each clockMoment has at minimum the below values
		foreach ($clockMoments['data'] as $clockMoment) {
			$this->assertArrayHasKey('id', $clockMoment);
			$this->assertArrayHasKey('time_stamp', $clockMoment);
			$this->assertArrayHasKey('status', $clockMoment);
			$this->assertArrayHasKey('active', $clockMoment);
			$this->assertArrayHasKey('modified_by', $clockMoment);
			$this->assertArrayHasKey('employee', $clockMoment);
			$this->assertArrayHasKey('clock_interval', $clockMoment);
			$this->assertArrayHasKey('department', $clockMoment);
		}
	}

	/**
	 * Tests the forbidden response during retrieval of clockMoments of a given employee with client without ROLE_HOURS.
	 *
	 * @group time-sensitive
	 */
	public function testGetEmployeeClockMomentsForbidden()
	{
		//The employee to get details from
		$employeeId = 52;

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$headquarterToken,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees/'.$employeeId.'/clock_moments',
			array(),
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is OK
		$this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $response->getContent());
	}

	/**
	 * Tests the retrieval of clockIntervals of a given employee with date range.
	 */
	public function testGetEmployeeClockIntervalsWithDateRange()
	{
		//The employee to get details from
		$employeeId = 52;

		$dateFrom = '2016-02-01';
		$dateTo = '2016-05-31';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees/'.$employeeId.'/clock_intervals?dateFrom='.$dateFrom.'&dateTo='.$dateTo,
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
		$clockIntervals = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($clockIntervals) > 0);

		//Test if exactly x clockIntervals are returned
		$this->assertTrue(4 === count($clockIntervals['data']));

		//Test that each clockInterval has at minimum the below values
		foreach ($clockIntervals['data'] as $clockInterval) {
			$this->assertArrayHasKey('id', $clockInterval);
			$this->assertArrayHasKey('start_date', $clockInterval);
			$this->assertArrayHasKey('end_date', $clockInterval);
			$this->assertArrayHasKey('break_duration', $clockInterval);
			$this->assertArrayHasKey('problem', $clockInterval);
		}
	}

	/**
	 * Tests the retrieval of clockInterval of a given employee with only dateFrom query parameter.
	 */
	public function testGetEmployeeClockIntervalsWithOnlyDateFrom()
	{
		//The employee to get details from
		$employeeId = 52;

		$dateFrom = '2015-06-16';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees/'.$employeeId.'/clock_intervals?dateFrom='.$dateFrom,
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
		$clockIntervals = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($clockIntervals) > 0);

		//Test if exactly x registers are returned
		$this->assertTrue(2 === count($clockIntervals['data']));

		//Test that each clockInterval has at minimum the below values
		foreach ($clockIntervals['data'] as $clockInterval) {
			$this->assertArrayHasKey('id', $clockInterval);
			$this->assertArrayHasKey('start_date', $clockInterval);
			$this->assertArrayHasKey('end_date', $clockInterval);
			$this->assertArrayHasKey('break_duration', $clockInterval);
			$this->assertArrayHasKey('problem', $clockInterval);
		}
	}

	/**
	 * Tests the retrieval of clockIntervals of a given employee with only dateTo query parameter.
	 */
	public function testGetEmployeeClockIntervalsWithOnlyDateTo()
	{
		//The employee to get details from
		$employeeId = 52;

		$dateTo = '2015-06-16';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees/'.$employeeId.'/clock_intervals?dateTo='.$dateTo,
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
		$clockIntervals = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($clockIntervals) > 0);

		//Test if exactly x registers are returned
		$this->assertTrue(3 === count($clockIntervals['data']));

		//Test that each clockInterval has at minimum the below values
		foreach ($clockIntervals['data'] as $clockInterval) {
			$this->assertArrayHasKey('id', $clockInterval);
			$this->assertArrayHasKey('start_date', $clockInterval);
			$this->assertArrayHasKey('end_date', $clockInterval);
			$this->assertArrayHasKey('break_duration', $clockInterval);
			$this->assertArrayHasKey('problem', $clockInterval);
		}
	}

	/**
	 * Tests the retrieval of clockIntervals of a given employee without date params which defaults at clockIntervals of current month.
	 *
	 * @group time-sensitive
	 */
	public function testGetEmployeeClockIntervalsWithoutDateQueryParams()
	{
		//The employee to get details from
		$employeeId = 52;

		//mock current time in registered classes
		ClockMock::withClockMock(strtotime('2015-06-05 01:00:00'));

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees/'.$employeeId.'/clock_intervals',
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
		$clockIntervals = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($clockIntervals) > 0);

		//Test if exactly x registers are returned
		$this->assertTrue(4 === count($clockIntervals['data']));

		//Test that each clockInterval has at minimum the below values
		foreach ($clockIntervals['data'] as $clockInterval) {
			$this->assertArrayHasKey('id', $clockInterval);
			$this->assertArrayHasKey('start_date', $clockInterval);
			$this->assertArrayHasKey('end_date', $clockInterval);
			$this->assertArrayHasKey('break_duration', $clockInterval);
			$this->assertArrayHasKey('problem', $clockInterval);
		}
	}

	/**
	 * Tests the forbidden response during retrieval of clockIntervals of a given employee with client without ROLE_HOURS.
	 *
	 * @group time-sensitive
	 */
	public function testGetEmployeeClockIntervalsForbidden()
	{
		//The employee to get details from
		$employeeId = 52;

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$headquarterToken,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees/'.$employeeId.'/clock_intervals',
			array(),
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is OK
		$this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $response->getContent());
	}

	/**
	 * Test if unpublished Assignments are not returned from Employee endpoints.
	 */
	public function testGetUnpublishedAssignmentsNotFound()
	{
		$employeeId = 52;
		$dateFrom = '2019-01-03';
		$dateTo = '2019-01-03';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees?assignmentRegisterDate='.$dateFrom,
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

		//Test if exactly x employees are returned
		$this->assertTrue(12 === count($employees['data']));

		//Test that each person has at minimum the below values
		foreach ($employees['data'] as $employee) {
			$this->assertArrayHasKey('id', $employee);
			$this->assertArrayHasKey('firstname', $employee);
			$this->assertArrayHasKey('assignments', $employee);
			$this->assertArrayHasKey('registers', $employee);
			$assignments = $employee['assignments'];
			$this->assertEmpty($assignments);
		}

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/employees/'.$employeeId.'/assignments?dateFrom='.$dateFrom.'&dateTo='.$dateTo,
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
