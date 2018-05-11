<?php

namespace AppBundle\Tests\Controller;

use AppBundle\Entity\SystemConfig;
use AppBundle\Enumerator\HttpHeader;
use AppBundle\Enumerator\SystemConfigKey;
use AppBundle\Repository\SystemConfigRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use AppBundle\Util\Constants;

/**
 * Class OfficeApiControllerTest.
 */
class OfficeApiControllerTest extends WebTestCase
{
	/** @var string */
	private static $token;

	/** @var string */
	private static $headquarterToken;

	/** @var string */
	private static $superUserToken;

	/**
	 * @var EntityManager
	 */
	private static $em;

	/** @var bool */
	private static $originalPhonelistRestrictState;

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

		// set up entity manager
		self::bootKernel();
		self::$em = static::$kernel->getContainer()
			->get('doctrine')
			->getManager('customer');

		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = self::$em->getRepository(SystemConfig::class);
		$phonelistRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);

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
		self::$em->clear();

		$headquarterId = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_HEADQUARTER_ID);

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
	}

	public static function tearDownAfterClass()
	{
		self::$em->clear();
		foreach (self::$createdSystemConfigs as $createdSystemConfig) {
			$systemConfigRepo = self::$em->getRepository(SystemConfig::class);
			$toBeDeletedSystemConfig = $systemConfigRepo->find($createdSystemConfig->getId());
			self::$em->remove($toBeDeletedSystemConfig);
			self::$em->flush();
		}
		self::$em->close();
		self::$em = null; // avoid memory leaks
	}

	/**
	 * Tests the retrieval of offices with varying result depending on Dor.Telefoonlijst.Restrict setting.
	 */
	public function testGetOfficesWithRestrictionCheck()
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

		$this->assertNotNull(self::$token);

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/offices',
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
		$offices = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($offices) > 0);

		//Test if exactly x offices are returned
		$this->assertTrue(1 === count($offices['data']));

		//Test that a offices has at minimum the below values
		foreach ($offices['data'] as $office) {
			$this->assertArrayHasKey('id', $office);
			$this->assertArrayHasKey('name', $office);
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
			'/api/v1/offices',
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
		$offices = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($offices) > 0);

		//Test if exactly x offices are returned
		$this->assertTrue(8 === count($offices['data']));

		//Test that a offices has at minimum the below values
		foreach ($offices['data'] as $office) {
			$this->assertArrayHasKey('id', $office);
			$this->assertArrayHasKey('name', $office);
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
	 * Tests the retrieval of offices with varying result depending on Dor.Telefoonlijst.Restrict setting.
	 */
	public function testGetOfficesWithRestrictionCheckWithSuperUser()
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
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$superUserToken,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/offices',
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
		$offices = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($offices) > 0);

		//Test if exactly x offices are returned
		$this->assertTrue(8 === count($offices['data']));

		//Test that a offices has at minimum the below values
		foreach ($offices['data'] as $office) {
			$this->assertArrayHasKey('id', $office);
			$this->assertArrayHasKey('name', $office);
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
			'/api/v1/offices',
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
		$offices = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($offices) > 0);

		//Test if exactly x offices are returned
		$this->assertTrue(8 === count($offices['data']));

		//Test that a offices has at minimum the below values
		foreach ($offices['data'] as $office) {
			$this->assertArrayHasKey('id', $office);
			$this->assertArrayHasKey('name', $office);
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
	 * Tests the retrieval of a office with varying result depending on Dor.Telefoonlijst.Restrict setting.
	 */
	public function testGetOfficeWithRestrictionCheck()
	{
		// manually set phonelistRestrict on if its off;
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = self::$em->getRepository(SystemConfig::class);
		$currentPhonelistRestrictState = self::$originalPhonelistRestrictState;
		if (!$currentPhonelistRestrictState) {
			/** @var SystemConfig $dorEmployeeRestrict */
			$dorEmployeeRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
			$dorEmployeeRestrict->setValue('yes');
			self::$em->flush();
			self::$em->refresh($dorEmployeeRestrict);
		}

		//The office to get details from
		$officeId = 105;

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/offices/'.$officeId,
			array(),
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is NOT FOUND
		$this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());

		// manually set phonelistRestrict off if its on;
		$dorEmployeeRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
		$dorEmployeeRestrict->setValue('no');
		self::$em->flush();
		self::$em->refresh($dorEmployeeRestrict);
		$currentPhonelistRestrictState = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT)->getNormalizedValue();

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/offices/'.$officeId,
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
		$office = json_decode($response->getContent(), true);
		$office = $office['data'];

		//Test that a office has at minimum the below values
		$this->assertArrayHasKey('id', $office);
		$this->assertArrayHasKey('name', $office);
		$this->assertArrayHasKey('departments', $office);

		//Test that an address has at minimum the below values
		$departments = $office['departments'];
		$this->assertTrue(sizeof($departments) > 0);
		foreach ($departments as $department) {
			$this->assertArrayHasKey('id', $department);
			$this->assertArrayHasKey('name', $department);
		}

		// return phonelist restrict to original state
		if ($currentPhonelistRestrictState !== self::$originalPhonelistRestrictState) {
			$dorEmployeeRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
			$dorEmployeeRestrict->setValue('yes');
			self::$em->flush();
			self::$em->refresh($dorEmployeeRestrict);
		}
	}

	/**
	 * Tests the retrieval of departments of a given office with varying result depending on Dor.Telefoonlijst.Restrict setting.
	 */
	public function testGetDepartmentsWithRestrictionCheck()
	{
		// manually set phonelistRestrict on if its off;
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = self::$em->getRepository(SystemConfig::class);
		$currentPhonelistRestrictState = self::$originalPhonelistRestrictState;
		if (!$currentPhonelistRestrictState) {
			/** @var SystemConfig $dorEmployeeRestrict */
			$dorEmployeeRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
			$dorEmployeeRestrict->setValue('yes');
			self::$em->flush();
			self::$em->refresh($dorEmployeeRestrict);
		}

		//The office to get departments from
		$officeId = 55;

		$headers = array(
			 'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			 Request::METHOD_GET,
			 '/api/v1/offices/'.$officeId.'/departments',
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
		$departments = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($departments) > 0);

		//Test if exactly x departments are returned
		$this->assertTrue(2 === count($departments['data']));

		//Test that a department has at minimum the below values
		foreach ($departments['data'] as $department) {
			$this->assertArrayHasKey('id', $department);
			$this->assertArrayHasKey('name', $department);
			$this->assertArrayHasKey('children', $department);

			//Test that a child has at minimum the below values
			$children = $department['children'];
			$this->assertTrue(sizeof($children) > 0);
			foreach ($children as $child) {
				$this->assertArrayHasKey('id', $child);
				$this->assertArrayHasKey('name', $child);
				$this->assertArrayHasKey('children', $child);
			}
		}

		// manually set phonelistRestrict off if its on;
		$dorEmployeeRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
		$dorEmployeeRestrict->setValue('no');
		self::$em->flush();
		self::$em->refresh($dorEmployeeRestrict);
		$currentPhonelistRestrictState = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT)->getNormalizedValue();

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/offices/'.$officeId.'/departments',
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
		$departments = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($departments) > 0);

		//Test if exactly x departments are returned
		$this->assertTrue(3 === count($departments['data']));

		//Test that a department has at minimum the below values
		foreach ($departments['data'] as $department) {
			$this->assertArrayHasKey('id', $department);
			$this->assertArrayHasKey('name', $department);
			$this->assertArrayHasKey('children', $department);

			//Test that a child has at minimum the below values
			$children = $department['children'];
			$this->assertTrue(sizeof($children) > 0);
			foreach ($children as $child) {
				$this->assertArrayHasKey('id', $child);
				$this->assertArrayHasKey('name', $child);
				$this->assertArrayHasKey('children', $child);
			}
		}

		// return phonelist restrict to original state
		if ($currentPhonelistRestrictState !== self::$originalPhonelistRestrictState) {
			$dorEmployeeRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
			$dorEmployeeRestrict->setValue('yes');
			self::$em->flush();
			self::$em->refresh($dorEmployeeRestrict);
		}
	}

	/**
	 * Tests the retrieval of a department with varying result depending on Dor.Telefoonlijst.Restrict setting.
	 */
	public function testGetDepartmentWithRestrictionCheck()
	{
		// manually set phonelistRestrict on if its off;
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = self::$em->getRepository(SystemConfig::class);
		$currentPhonelistRestrictState = self::$originalPhonelistRestrictState;
		if (!$currentPhonelistRestrictState) {
			/** @var SystemConfig $dorEmployeeRestrict */
			$dorEmployeeRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
			$dorEmployeeRestrict->setValue('yes');
			self::$em->flush();
			self::$em->refresh($dorEmployeeRestrict);
		}

		//The office to get departments from
		$officeId = 55;

		//The department to get details from
		$departmentId = 56;

		$headers = array(
			 'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			 Request::METHOD_GET,
			 '/api/v1/offices/'.$officeId.'/departments'.'/'.$departmentId,
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
		$department = json_decode($response->getContent(), true);
		$department = $department['data'];

		//Test that a office has at minimum the below values
		$this->assertArrayHasKey('id', $department);
		$this->assertArrayHasKey('name', $department);
		$this->assertArrayHasKey('children', $department);

		//Test that a child has at minimum the below values
		$children = $department['children'];
		$this->assertTrue(sizeof($children) > 0);

		//Test if exactly x departments are returned
		$this->assertTrue(2 === count($children));

		foreach ($children as $child) {
			$this->assertArrayHasKey('id', $child);
			$this->assertArrayHasKey('name', $child);
			$this->assertArrayHasKey('children', $child);
		}

		// manually set phonelistRestrict off if its on;
		$dorEmployeeRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
		$dorEmployeeRestrict->setValue('no');
		self::$em->flush();
		self::$em->refresh($dorEmployeeRestrict);
		$currentPhonelistRestrictState = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT)->getNormalizedValue();

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/offices/'.$officeId.'/departments'.'/'.$departmentId,
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
		$department = json_decode($response->getContent(), true);
		$department = $department['data'];

		//Test that a office has at minimum the below values
		$this->assertArrayHasKey('id', $department);
		$this->assertArrayHasKey('name', $department);
		$this->assertArrayHasKey('children', $department);

		//Test that a child has at minimum the below values
		$children = $department['children'];
		$this->assertTrue(sizeof($children) > 0);

		//Test if exactly x departments are returned
		$this->assertTrue(3 === count($children));

		foreach ($children as $child) {
			$this->assertArrayHasKey('id', $child);
			$this->assertArrayHasKey('name', $child);
			$this->assertArrayHasKey('children', $child);
		}

		// return phonelist restrict to original state
		if ($currentPhonelistRestrictState !== self::$originalPhonelistRestrictState) {
			$dorEmployeeRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
			$dorEmployeeRestrict->setValue('yes');
			self::$em->flush();
			self::$em->refresh($dorEmployeeRestrict);
		}
	}

	/**
	 * Tests the retrieval of a non-existing department.
	 */
	public function testGetDepartmentNotFound()
	{
		//The office to get details from
		$officeId = 1;

		//The non-existing department
		$departmentId = 99999999;

		$headers = array(
			 'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			 Request::METHOD_GET,
			 '/api/v1/offices/'.$officeId.'/departments/'.$departmentId,
			 array(),
			 array(),
			 $headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is OK
		$this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());

		// Test that response is not empty
		$this->assertNotEmpty($response->getContent());

		// Test if Content-Type is valid application/json
		$this->assertSame(HttpHeader::APPLICATION_JSON, $response->headers->get(HttpHeader::CONTENT_TYPE));

		//Deserialize response
		$result = json_decode($response->getContent(), true);

		//Test that the status is error
		$this->assertEquals('error', $result['status']);
		//Test that the message is Not found
		$this->assertEquals('Not found', $result['message']);
	}

	/**
	 * Tests the retrieval of a headquarter (top level) in offices.
	 */
	public function testGetHeadquarterInOffices()
	{
		// manually set phonelistRestrict on if its off;
		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = self::$em->getRepository(SystemConfig::class);
		$currentPhonelistRestrictState = self::$originalPhonelistRestrictState;
		if ($currentPhonelistRestrictState) {
			/** @var SystemConfig $dorPhonelistRestrict */
			$dorPhonelistRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
			$dorPhonelistRestrict->setValue('no');
			self::$em->flush();
			self::$em->refresh($dorPhonelistRestrict);
			$currentPhonelistRestrictState = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT)->getNormalizedValue();
		}

		$currentHeadquarterIdState = self::$originalHeadquarterIdState;

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$headquarterToken,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/offices',
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
		$offices = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($offices) > 0);

		//Test if exactly x offices are returned
		$this->assertTrue(8 === count($offices['data']));

		//Test that a offices has at minimum the below values
		foreach ($offices['data'] as $office) {
			$this->assertArrayHasKey('id', $office);
			$this->assertArrayHasKey('name', $office);
		}

		// Test if response contains headquarter
		$headquarter = $offices['data'][7];
		$this->assertArrayHasKey('is_headquarter', $headquarter);
		$this->assertEquals($headquarter['is_headquarter'], true);
		$this->assertEquals($headquarter['id'], $currentHeadquarterIdState);

		// return phonelist restrict to original state
		if ($currentPhonelistRestrictState !== self::$originalPhonelistRestrictState) {
			$dorEmployeeRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
			$dorEmployeeRestrict->setValue('yes');
			self::$em->flush();
			self::$em->refresh($dorEmployeeRestrict);
		}
	}

	/**
	 * Tests the retrieval of a single office that is headquarter (top level).
	 */
	public function testGetSingleHeadquarterAsOfficeWithRestrictionCheck()
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
		}

		$currentHeadquarterIdState = self::$originalHeadquarterIdState;

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$headquarterToken,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/offices/'.$currentHeadquarterIdState,
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
		$office = json_decode($response->getContent(), true);
		$office = $office['data'];

		//Test that a office has at minimum the below values
		$this->assertArrayHasKey('id', $office);
		$this->assertArrayHasKey('name', $office);
		$this->assertArrayHasKey('departments', $office);

		//Test that a headquarter has also offices as mocked departments with at minimum the below values
		$departments = $office['departments'];
		$this->assertTrue(sizeof($departments) > 0);
		$this->assertTrue(7 === count($departments));

		foreach ($departments as $department) {
			$this->assertArrayHasKey('id', $department);
			$this->assertArrayHasKey('name', $department);
			$this->assertArrayHasKey('departments', $department);
		}

		// manually set phonelistRestrict off if its on;
		$dorEmployeeRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
		$dorEmployeeRestrict->setValue('no');
		self::$em->flush();
		self::$em->refresh($dorEmployeeRestrict);
		$currentPhonelistRestrictState = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT)->getNormalizedValue();

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/offices/'.$currentHeadquarterIdState,
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
		$office = json_decode($response->getContent(), true);
		$office = $office['data'];

		//Test that a office has at minimum the below values
		$this->assertArrayHasKey('id', $office);
		$this->assertArrayHasKey('name', $office);
		$this->assertArrayHasKey('departments', $office);

		//Test that a headquarter has also offices as mocked departments with at minimum the below values
		$departments = $office['departments'];
		$this->assertTrue(sizeof($departments) > 0);
		$this->assertTrue(8 === count($departments));

		foreach ($departments as $department) {
			$this->assertArrayHasKey('id', $department);
			$this->assertArrayHasKey('name', $department);
			$this->assertArrayHasKey('departments', $department);
		}

		// return phonelist restrict to original state
		if ($currentPhonelistRestrictState !== self::$originalPhonelistRestrictState) {
			$dorEmployeeRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
			$dorEmployeeRestrict->setValue('yes');
			self::$em->flush();
			self::$em->refresh($dorEmployeeRestrict);
		}
	}

	/**
	 * Tests the retrieval of a children departments/offices of a headquarter office.
	 */
	public function testGetHeadquarterChildrenWithRestrictionCheck()
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
		}

		$currentHeadquarterIdState = self::$originalHeadquarterIdState;

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$headquarterToken,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/offices/'.$currentHeadquarterIdState.'/departments',
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
		$offices = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($offices) > 0);

		//Test if exactly x departments are returned
		$this->assertTrue(7 === count($offices['data']));

		//Test that a department has at minimum the below values
		foreach ($offices['data'] as $office) {
			$this->assertArrayHasKey('id', $office);
			$this->assertArrayHasKey('name', $office);
			$this->assertArrayHasKey('departments', $office);

			//Test that a child has at minimum the below values
			$children = $office['departments'];
			if (sizeof($children) > 0) {
				foreach ($children as $department) {
					$this->assertArrayHasKey('id', $department);
					$this->assertArrayHasKey('name', $department);
					$this->assertArrayHasKey('children', $department);

					//Test that a child has at minimum the below values
					$children = $department['children'];
					foreach ($children as $child) {
						$this->assertArrayHasKey('id', $child);
						$this->assertArrayHasKey('name', $child);
						$this->assertArrayHasKey('children', $child);
					}
				}
			}
		}

		// manually set phonelistRestrict off if its on;
		$dorEmployeeRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
		$dorEmployeeRestrict->setValue('no');
		self::$em->flush();
		self::$em->refresh($dorEmployeeRestrict);
		$currentPhonelistRestrictState = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT)->getNormalizedValue();

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/offices/'.$currentHeadquarterIdState.'/departments',
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
		$offices = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($offices) > 0);

		//Test if exactly x departments are returned
		$this->assertTrue(8 === count($offices['data']));

		//Test that a department has at minimum the below values
		foreach ($offices['data'] as $office) {
			$this->assertArrayHasKey('id', $office);
			$this->assertArrayHasKey('name', $office);
			$this->assertArrayHasKey('departments', $office);

			//Test that a child has at minimum the below values
			$children = $office['departments'];
			if (sizeof($children) > 0) {
				foreach ($children as $department) {
					$this->assertArrayHasKey('id', $department);
					$this->assertArrayHasKey('name', $department);
					$this->assertArrayHasKey('children', $department);

					//Test that a child has at minimum the below values
					$children = $department['children'];
					foreach ($children as $child) {
						$this->assertArrayHasKey('id', $child);
						$this->assertArrayHasKey('name', $child);
						$this->assertArrayHasKey('children', $child);
					}
				}
			}
		}

		// return phonelist restrict to original state
		if ($currentPhonelistRestrictState !== self::$originalPhonelistRestrictState) {
			$dorEmployeeRestrict = $systemConfigRepo->findOneByKey(SystemConfigKey::DOR_PHONELIST_RESTRICT);
			$dorEmployeeRestrict->setValue('yes');
			self::$em->flush();
			self::$em->refresh($dorEmployeeRestrict);
		}
	}

	/**
	 * Tests the retrieval of a departments depending on a specific user Role.
	 */
	public function testGetDepartmentsWithRoleQueryParameter()
	{
		//The office to get departments from
		$officeId = 55;
		$role = 'HOURS_REGISTER';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/offices/'.$officeId.'/departments?role='.$role,
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
		$departments = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($departments) > 0);

		//Test if exactly x departments are returned
		$this->assertTrue(2 === count($departments['data']));

		//Test that a department has at minimum the below values
		foreach ($departments['data'] as $department) {
			$this->assertArrayHasKey('id', $department);
			$this->assertArrayHasKey('name', $department);
			$this->assertArrayHasKey('children', $department);

			//Test that a child has at minimum the below values
			$children = $department['children'];
			$this->assertTrue(sizeof($children) > 0);
			foreach ($children as $child) {
				$this->assertArrayHasKey('id', $child);
				$this->assertArrayHasKey('name', $child);
				$this->assertArrayHasKey('children', $child);
			}
		}
	}
}
