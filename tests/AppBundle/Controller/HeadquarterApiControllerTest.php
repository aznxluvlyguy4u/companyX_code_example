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
 * Class HeadquarterApiControllerTest.
 */
class HeadquarterApiControllerTest extends WebTestCase
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
	 * Tests the retrieval of headquarters with varying result depending on DOR_HEADQUARTER_ID and client access to headquarter.
	 */
	public function testGetHeadquartersWithRestrictionCheck()
	{
		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$headquarterToken,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/headquarters',
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

		//Test that a offices has at minimum the below values
		$office = $offices['data'];
		$this->assertArrayHasKey('id', $office);
		$this->assertArrayHasKey('name', $office);
		$this->assertEquals($office['id'], self::$originalHeadquarterIdState);

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/headquarters',
			array(),
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is NOT FOUND
		$this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
	}

	/**
	 * Tests the retrieval of headquarter with varying result depending on DOR_HEADQUARTER_ID and client access to headquarter.
	 */
	public function testGetHeadquarterOfficesWithRestrictionCheck()
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
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$headquarterToken,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/headquarters/offices',
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
		$this->assertTrue(7 === count($offices['data']));

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
			'/api/v1/headquarters/offices',
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
}
