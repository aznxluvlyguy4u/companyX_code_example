<?php

namespace AppBundle\Tests\Controller;

use AppBundle\Enumerator\HttpHeader;
use AppBundle\Util\Constants;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AuthApiControllerTest.
 */
class AuthApiControllerTest extends WebTestCase
{
	/** @var string */
	private $token;

	/** @var string */
	private $expiredToken;

	/** @var string */
	private $refreshToken;

	/**
	 * Sets up a request to retrieve a token to be used in testcases.
	 */
	public function setUp()
	{
		parent::setUp();

		//The company to identify
		$companyName = 'companydemo';
		$contentString = '{"'.Constants::QUERY_PARAM_COMPANY_NAME.'": "'.$companyName.'", "remember_me": true}';

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
			$this->token = $tokenDetails['data']['token'];
			$this->refreshToken = $tokenDetails['data']['refresh_token'];
		}

		//The company to identify
		$companyName = 'companydemo';
		$contentString = '{"'.Constants::QUERY_PARAM_COMPANY_NAME.'": "'.$companyName.'"}';

		//Authorization - Basic authentication header
		$headers = array(
			'PHP_AUTH_USER' => 'pieter', //Test user
			'PHP_AUTH_PW' => 'XGthJU1234#',
		);

		// HTTP client
		$expiredClient = static::createClient(array(
			'environment' => 'expired_token_test',
		));
		$expiredClient->request(
			Request::METHOD_POST,
			'/api/v1/auth',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$expiredResponse = $expiredClient->getResponse();

		//Deserialize response
		$expiredTokenDetails = json_decode($expiredResponse->getContent(), true);

		if (sizeof($expiredTokenDetails) > 0) {
			$this->expiredToken = $expiredTokenDetails['data']['token'];
		}
	}

	/**
	 * Tests a succeeded basic authentication.
	 */
	public function testSucceededAuthentication()
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

		// Test if response is OK
		$this->assertSame(Response::HTTP_OK, $response->getStatusCode());

		// Test that response is not empty
		$this->assertNotEmpty($response->getContent());

		// Test if Content-Type is valid application/json
		$this->assertSame(HttpHeader::APPLICATION_JSON, $response->headers->get(HttpHeader::CONTENT_TYPE));

		//Deserialize response
		$tokenDetails = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($tokenDetails) > 0);

		//Test that the response has a payload with a token value
		$this->assertArrayHasKey('data', $tokenDetails);

		$this->assertArrayHasKey(Constants::TOKEN, $tokenDetails['data']);
	}

	/**
	 * Tests a succeeded basic authentication with remember me.
	 */
	public function testSucceededAuthenticationWithRememberMe()
	{
		//The company to identify
		$companyName = 'companydemo';
		$contentString = '{"'.Constants::QUERY_PARAM_COMPANY_NAME.'": "'.$companyName.'", "remember_me": true}';

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

		// Test if response is OK
		$this->assertSame(Response::HTTP_OK, $response->getStatusCode());

		// Test that response is not empty
		$this->assertNotEmpty($response->getContent());

		// Test if Content-Type is valid application/json
		$this->assertSame(HttpHeader::APPLICATION_JSON, $response->headers->get(HttpHeader::CONTENT_TYPE));

		//Deserialize response
		$tokenDetails = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($tokenDetails) > 0);

		//Test that the response has a payload with a token value
		$this->assertArrayHasKey('data', $tokenDetails);

		$this->assertArrayHasKey(Constants::TOKEN, $tokenDetails['data']);

		$this->assertArrayHasKey(Constants::REFRESH_TOKEN, $tokenDetails['data']);
	}

	/**
	 * Tests a failed basic authentication.
	 */
	public function testFailedAuthentication()
	{
		//The company to identify
		$companyName = 'companydemo';
		$contentString = '{"'.Constants::QUERY_PARAM_COMPANY_NAME.'": "'.$companyName.'"}';

		//Authorization - Basic authentication header
		$headers = array(
			'PHP_AUTH_USER' => 'pieter', //Test user
			'PHP_AUTH_PW' => '4R4nd0m5tr1n9',
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

		// Test if response is UNAUTHORIZED
		$this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
	}

	/**
	 * Tests a failed authentication without authentication header.
	 */
	public function testFailedAuthenticationNoHeader()
	{
		//The company to identify
		$companyName = 'companydemo';
		$contentString = '{"'.Constants::QUERY_PARAM_COMPANY_NAME.'": "'.$companyName.'"}';

		//Authorization - No header
		$headers = array(
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

		// Test if response is UNAUTHORIZED
		$this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
	}

	/**
	 * Tests a failed basic authentication without companyIdentifier.
	 */
	public function testFailedAuthenticationNoCompanyIdentifier()
	{
		//The company to identify
		$contentString = '{"remember_me": true}';

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

		// Test if response is UNAUTHORIZED
		$this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
	}

	/**
	 * Tests a valid token.
	 */
	public function testSucceededTokenValidation()
	{
		$this->assertNotNull($this->token);

		$headers = array(
			 'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.$this->token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			 Request::METHOD_GET,
			 '/api/v1/auth/validate-token',
			 array(),
			 array(),
			 $headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		$this->assertSame(Response::HTTP_OK, $response->getStatusCode());
	}

	/**
	 * Tests a valid token.
	 */
	public function testValidateTokenInvalid()
	{
		$this->token = '4N0t50r4nd0m5tr1n9';

		$headers = array(
			 'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.$this->token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			 Request::METHOD_GET,
			 '/api/v1/auth/validate-token',
			 array(),
			 array(),
			 $headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is UNAUTHORIZED
		$this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
	}

	/**
	 * Tests getting new access token with expired access token and valid refresh token.
	 */
	public function testSucceededRefreshToken()
	{
		$this->expiredToken;

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.$this->expiredToken,
		);

		$parameters = array(
			Constants::REFRESH_TOKEN => $this->refreshToken,
		);

		// HTTP client
		$client = static::createClient();

		$client->request(
			Request::METHOD_POST,
			'/api/v1/auth/refresh-token',
				$parameters,
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
		$tokenDetails = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($tokenDetails) > 0);

		//Test that the response has a payload with a token value
		$this->assertArrayHasKey('data', $tokenDetails);

		$this->assertArrayHasKey(Constants::TOKEN, $tokenDetails['data']);

		$this->assertArrayHasKey(Constants::REFRESH_TOKEN, $tokenDetails['data']);
	}

	/**
	 * Tests failed attempt getting new access token with expired access token with INvalid refresh token.
	 */
	public function testFailedRefreshTokenInvalidRefreshToken()
	{
		$this->refreshToken = 'invalid-lalala-land';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.$this->expiredToken,
		);

		$parameters = array(
			Constants::REFRESH_TOKEN => $this->refreshToken,
		);

		// HTTP client
		$client = static::createClient();

		$client->request(
			Request::METHOD_POST,
			'/api/v1/auth/refresh-token',
			$parameters,
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is UNAUTHORIZED
		$this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
	}

	/**
	 * Tests failed attempt getting new access token with NOT expired valid access token with valid refresh token.
	 */
	public function testFailedRefreshTokenValidToken()
	{
		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.$this->token,
		);

		$parameters = array(
			Constants::REFRESH_TOKEN => $this->refreshToken,
		);

		// HTTP client
		$client = static::createClient();

		$client->request(
			Request::METHOD_POST,
			'/api/v1/auth/refresh-token',
			$parameters,
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is UNAUTHORIZED
		$this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
	}

	/**
	 * Tests failed attempt getting new access token with INvalid access token with valid refresh token.
	 */
	public function testFailedRefreshTokenInvalidToken()
	{
		$this->token = 'invalid-lala-land';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.$this->token,
		);

		$parameters = array(
			Constants::REFRESH_TOKEN => $this->refreshToken,
		);

		// HTTP client
		$client = static::createClient();

		$client->request(
			Request::METHOD_POST,
			'/api/v1/auth/refresh-token',
			$parameters,
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is UNAUTHORIZED
		$this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
	}

	/**
	 * Tests a succeeded basic authentication to auth/auth-with-session-id.
	 */
	public function testSucceededAuthenticationWithSessionId()
	{
		//The company to identify
		$companyName = 'companydemo';
		$sessionId = 12345;
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

		// Test if response is OK
		$this->assertSame(Response::HTTP_OK, $response->getStatusCode());

		// Test that response is not empty
		$this->assertNotEmpty($response->getContent());

		// Test if Content-Type is valid application/json
		$this->assertSame(HttpHeader::APPLICATION_JSON, $response->headers->get(HttpHeader::CONTENT_TYPE));

		//Deserialize response
		$tokenDetails = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($tokenDetails) > 0);

		//Test that the response has a payload with a token value
		$this->assertArrayHasKey('data', $tokenDetails);

		$this->assertArrayHasKey(Constants::TOKEN, $tokenDetails['data']);
	}

	/**
	 * Tests a succeeded basic authentication with remember me.
	 */
	public function testSucceededAuthenticationWithSessionIdAndRememberMe()
	{
		//The company to identify
		$companyName = 'companydemo';
		$sessionId = 12345;
		$contentString = '{"'.Constants::QUERY_PARAM_COMPANY_NAME.'": "'.$companyName.'", "session_id": '.$sessionId.', "remember_me": true}';

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

		// Test if response is OK
		$this->assertSame(Response::HTTP_OK, $response->getStatusCode());

		// Test that response is not empty
		$this->assertNotEmpty($response->getContent());

		// Test if Content-Type is valid application/json
		$this->assertSame(HttpHeader::APPLICATION_JSON, $response->headers->get(HttpHeader::CONTENT_TYPE));

		//Deserialize response
		$tokenDetails = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($tokenDetails) > 0);

		//Test that the response has a payload with a token value
		$this->assertArrayHasKey('data', $tokenDetails);

		$this->assertArrayHasKey(Constants::TOKEN, $tokenDetails['data']);

		$this->assertArrayHasKey(Constants::REFRESH_TOKEN, $tokenDetails['data']);
	}

	/**
	 * Tests a failed basic authentication to auth/auth-with-session-id with invalid session_id.
	 */
	public function testFailedAuthenticationWithInvalidSessionId()
	{
		//The company to identify
		$companyName = 'companydemo';
		$sessionId = '123x5';
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

		// Test if response is UNAUTHORIZED
		$this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
	}

	/**
	 * Test GET /docs success from whitelisted IPs.
	 */
	public function testSucceededAccessControlToDocs()
	{
		// Set HTTP client ip address to 127.0.0.1
		$client = static::createClient([], ['REMOTE_ADDR' => '127.0.0.1']);
		$client->request(
			Request::METHOD_GET,
			'/docs/',
			array(),
			array()
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is OK
		$this->assertSame(Response::HTTP_OK, $response->getStatusCode(), 'failed getting acces to docs: '.$response->getContent());

		// Set HTTP client ip address to 172.18.0.1
		$client = static::createClient([], ['REMOTE_ADDR' => '172.18.0.1']);
		$client->request(
			Request::METHOD_GET,
			'/docs/',
			array(),
			array()
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is OK
		$this->assertSame(Response::HTTP_OK, $response->getStatusCode());

		// Set HTTP client ip address to 172.17.0.1
		$client = static::createClient([], ['REMOTE_ADDR' => '172.17.0.1']);
		$client->request(
			Request::METHOD_GET,
			'/docs/',
			array(),
			array()
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is OK
		$this->assertSame(Response::HTTP_OK, $response->getStatusCode());
	}

	/**
	 * Test GET /docs fail from IPs outside whitelisted IPs.
	 */
	public function testFailedAccessControlToDocs()
	{
		// Set HTTP client ip address to random IP outside whitelisted IPs
		$client = static::createClient([], ['REMOTE_ADDR' => '157.97.116.18']);
		$client->request(
			Request::METHOD_GET,
			'/docs/',
			array(),
			array()
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is UNAUTHORIZED
		$this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode(), $response->getContent());

		// Set HTTP client ip address to random IP outside whitelisted IPs
		$client = static::createClient([], ['REMOTE_ADDR' => '127.0.0.2']);
		$client->request(
			Request::METHOD_GET,
			'/docs/',
			array(),
			array()
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is UNAUTHORIZED
		$this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode(), $response->getContent());

		// Set HTTP client to authenticated client but from remote IP outside whitelisted IPs
		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.$this->token,
		);

		// HTTP client
		$client = static::createClient([], ['REMOTE_ADDR' => '157.97.116.18']);
		$client->request(
			Request::METHOD_GET,
			'/docs/',
			array(),
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is UNAUTHORIZED
		$this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode(), $response->getContent());
	}

	/**
	 * Test Rate Limited response to Auth endpoints.
	 */
	public function testRateLimitToAuth()
	{
		// Prepare kernel and clear cache for env = rate_limit_test
		$kernel = static::createKernel(array(
			'environment' => 'rate_limit_test',
		));
		$kernel->boot();
		$application = new Application($kernel);
		$application->setAutoExit(false);
		$arguments = array(
			'command' => 'cache:clear',
			'--env' => 'rate_limit_test',
			'--no-warmup' => true,
		);
		$input = new ArrayInput($arguments);
		$output = new NullOutput();
		$application->run($input, $output);

		//The company to identify
		$companyName = 'companydemo';
		$contentString = '{"'.Constants::QUERY_PARAM_COMPANY_NAME.'": "'.$companyName.'"}';

		//Authorization - Basic authentication header
		$headers = array(
			'PHP_AUTH_USER' => 'pieter', //Test user
			'PHP_AUTH_PW' => 'XGthJU1234#',
		);

		// Setup HTTP client using config_rate_limit_test.yml with NoxLogic RateLimitBundle on
		$OKrateLimitedClient = static::createClient(array(
			'environment' => 'rate_limit_test',
		));

		// Try to auth 5 times in a loop and expect still a OK response
		for ($x = 0; $x <= 4; ++$x) {
			$OKrateLimitedClient->request(
				Request::METHOD_POST,
				'/api/v1/auth',
				array(),
				array(),
				$headers,
				$contentString
			);
		}

		/** @var Response $response */
		$response = $OKrateLimitedClient->getResponse();

		// clear cache for env = rate_limit_test again
		$application->run($input, $output);

		// Test if response is OK
		$this->assertSame(Response::HTTP_OK, $response->getStatusCode(), 'Failed rate limit testing: '.$response->getContent());

		// Setup HTTP client using config_rate_limit_test.yml with NoxLogic RateLimitBundle on
		$failRateLimitedClient = static::createClient(array(
			'environment' => 'rate_limit_test',
		));

		// Try to auth 6 times in a loop and expect a Too Many Request error
		for ($x = 0; $x <= 5; ++$x) {
			$failRateLimitedClient->request(
				Request::METHOD_POST,
				'/api/v1/auth',
				array(),
				array(),
				$headers,
				$contentString
			);
		}

		/** @var Response $response */
		$response = $failRateLimitedClient->getResponse();

		// Test if response is 429 TOO MANY REQUESTS
		$this->assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode(), $response->getContent());

		// clear cache for env = rate_limit_test again
		$application->run($input, $output);
	}
}
