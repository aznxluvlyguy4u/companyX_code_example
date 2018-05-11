<?php

namespace AppBundle\Tests\Controller;

use AppBundle\Enumerator\HttpHeader;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use AppBundle\Util\Constants;

/**
 * Class ClientApiControllerTest.
 */
class ClientApiControllerTest extends WebTestCase
{
	/** @var string */
	private static $token;

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
	}

	/**
	 * Tests the retrieval of a authenticated client detail info.
	 */
	public function testGetAuthenticatedClient()
	{
		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/clients/me',
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
		$client = json_decode($response->getContent(), true);
		$client = $client['data'];

		//Test that client details has at minimum the below values
		$this->assertEquals($client['id'], 4);
		$this->assertEquals($client['username'], 'pieter');
		$this->assertEquals($client['firstname'], 'Peter');
		$this->assertEquals($client['lastname'], 'Puk');
		$this->assertEquals($client['insertion'], 'van');
		$this->assertArrayHasKey('employee', $client);
		$employee = $client['employee'];
		$this->assertEquals($employee['id'], 52);
	}
}
