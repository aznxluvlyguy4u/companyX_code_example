<?php

namespace AppBundle\Tests\Controller;

use AppBundle\Enumerator\HttpHeader;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use AppBundle\Util\Constants;

/**
 * Class SystemConfigApiControllerTest.
 */
class SystemConfigApiControllerTest extends WebTestCase
{
	/** @var string */
	private static $token;

	/**
	 * @var EntityManager
	 */
	private static $em;

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

		// set up entity manager
		self::bootKernel();
		self::$em = static::$kernel->getContainer()
			->get('doctrine')
			->getManager('customer');
	}

	public static function tearDownAfterClass()
	{
		self::$em->close();
		self::$em = null; // avoid memory leaks
	}

	/**
	 * Test the retrieval of systemConfigs of CompanyX page.
	 */
	public function testGetSystemConfigsPerPageSuccess()
	{
		//The office to get employees from
		$page = 'rooster2/index2';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/system_configs?page='.$page,
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
		$systemConfigs = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($systemConfigs) > 0);

		//Test if atleast x systemConfigs are returned (some are optionally missing in db)
		$this->assertTrue(count($systemConfigs['data']) > 14);

		//Test that each systemConfig has at minimum the below values
		foreach ($systemConfigs['data'] as $systemConfig) {
			$this->assertArrayHasKey('key', $systemConfig);
		}
	}

	/**
	 * Test fail retrieval of systemConfigs of CompanyX page with invalid page name.
	 */
	public function testFailedGetSystemConfigsInvalidPageName()
	{
		//The office to get employees from
		$page = 'rooster2/xxxx';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/system_configs?page='.$page,
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
	 * Test fail retrieval of systemConfigs of CompanyX page with invalid page name.
	 */
	public function testFailedGetSystemConfigsNoPageNameSupplied()
	{
		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/system_configs',
			array(),
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is NOT FOUND
		$this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
	}
}
