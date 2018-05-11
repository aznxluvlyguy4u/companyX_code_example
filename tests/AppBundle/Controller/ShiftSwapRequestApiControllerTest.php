<?php

namespace AppBundle\Tests\Controller;

use AppBundle\Entity\ShiftSwapRequest;
use AppBundle\Entity\SystemConfig;
use AppBundle\Enumerator\HttpHeader;
use AppBundle\Enumerator\ShiftSwapRequestStatus;
use AppBundle\Enumerator\SystemConfigKey;
use AppBundle\Repository\AssignmentRepository;
use AppBundle\Repository\ShiftSwapRequestRepository;
use AppBundle\Repository\SystemConfigRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\SwiftmailerBundle\DataCollector\MessageDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use AppBundle\Util\Constants;

/**
 * Class ShiftSwapRequestApiControllerTest.
 */
class ShiftSwapRequestApiControllerTest extends WebTestCase
{
	/** @var string */
	private static $token;

	/** @var string */
	private static $receiverToken;

	/**
	 * @var EntityManager
	 */
	private static $em;

	/**
	 * @var ShiftSwapRequestRepository
	 */
	private static $shiftSwapRequestRepo;

	/**
	 * @var SystemConfigRepository
	 */
	private static $systemConfigRepo;

	/** @var array */
	private static $originalSystemConfigValues = [];

	/** @var array */
	private static $createdSystemConfigs = [];

	/** @var array */
	private static $createdShiftSwapRequests = [];

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

		//The company to identify
		$companyName = 'companydemo';
		$contentString = '{"'.Constants::QUERY_PARAM_COMPANY_NAME.'": "'.$companyName.'"}';

		//Authorization - Basic authentication header
		$headers = array(
			'PHP_AUTH_USER' => 'Thomas', //Test user
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
			self::$receiverToken = $tokenDetails['data']['token'];
		}

		// ShiftSwapRequest classes to use mock clock
		ClockMock::register(__CLASS__);
		ClockMock::register(AssignmentRepository::class);
		ClockMock::register(ShiftSwapRequestRepository::class);

		// set up entity manager
		self::bootKernel();
		self::$em = static::$kernel->getContainer()
			->get('doctrine')
			->getManager('customer');

		self::$shiftSwapRequestRepo = self::$em->getRepository(ShiftSwapRequest::class);
		self::$systemConfigRepo = self::$em->getRepository(SystemConfig::class);

		// prepare the systemconfig keys

		// DOR_FEATURES_SHIFT_SWAP_REQUEST
		/** @var SystemConfig $dorFeaturesShiftSwapRequest */
		$dorFeaturesShiftSwapRequest = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_FEATURES_SHIFT_SWAP_REQUEST);
		if ($dorFeaturesShiftSwapRequest) {
			self::$originalSystemConfigValues[SystemConfigKey::DOR_FEATURES_SHIFT_SWAP_REQUEST] = $dorFeaturesShiftSwapRequest->getNormalizedValue();
			// Manually set DOR_FEATURES_SHIFT_SWAP_REQUEST to true so that CRUD tests can pass
			self::$em->clear();
			$dorFeaturesShiftSwapRequest->setValue(true);
			self::$em->flush();
		} else {
			// create the system config for test but delete it lateron
			self::$em->clear();
			$newDorFeaturesShiftSwapRequest = new SystemConfig();
			$newDorFeaturesShiftSwapRequest->setKey(SystemConfigKey::DOR_FEATURES_SHIFT_SWAP_REQUEST);
			// Set default DOR_FEATURES_SHIFT_SWAP_REQUEST true
			$newDorFeaturesShiftSwapRequest->setValue(true);
			self::$em->persist($newDorFeaturesShiftSwapRequest);
			self::$em->flush();
			self::$em->refresh($newDorFeaturesShiftSwapRequest);
			self::$originalSystemConfigValues[SystemConfigKey::DOR_FEATURES_SHIFT_SWAP_REQUEST] = true;
			self::$createdSystemConfigs[] = $newDorFeaturesShiftSwapRequest;
		}

		self::$em->clear();
	}

	public static function tearDownAfterClass()
	{
		self::$em->clear();

		// make sure all created shiftSwapRequests are deleted from DB
		foreach (self::$createdShiftSwapRequests as $createdShiftSwapRequest) {
			/** @var ShiftSwapRequest $shiftSwapRequest */
			$shiftSwapRequest = self::$shiftSwapRequestRepo->find($createdShiftSwapRequest);
			if ($shiftSwapRequest) {
				self::$em->remove($shiftSwapRequest);
			}
		}

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
		self::$createdShiftSwapRequests = [];

		self::$em->close();
		self::$em = null; // avoid memory leaks
	}

	// TODO Dynamically create assignment in the future and create shift swap from it when Assignment CRUD is available

	/**
	 * Tests the retrieval of shitSwapRequests with date range that current logged in client is allowed to see.
	 */
	public function testGetShiftSwapRequestsWithDateRange()
	{
		$dateFrom = '2019-01-01';
		$dateTo = '2019-01-30';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/shift_swap_requests?dateFrom='.$dateFrom.'&dateTo='.$dateTo,
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
		$shiftSwapRequests = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($shiftSwapRequests) > 0);

		//Test if exactly 2 shiftSwapRequests are returned
		$this->assertTrue(2 === count($shiftSwapRequests['data']));

		//Test that each shiftSwapRequest has at minimum the below values
		foreach ($shiftSwapRequests['data'] as $shiftSwapRequest) {
			$this->assertArrayHasKey('id', $shiftSwapRequest);
			$this->assertArrayHasKey('applicant', $shiftSwapRequest);
			$this->assertArrayHasKey('receiver', $shiftSwapRequest);
			$this->assertArrayHasKey('assignment', $shiftSwapRequest);
			$this->assertArrayHasKey('status', $shiftSwapRequest);
			$this->assertArrayHasKey('start_date', $shiftSwapRequest);
			$this->assertArrayHasKey('end_date', $shiftSwapRequest);
		}
	}

	/**
	 * Tests the retrieval of shiftSwapRequests with only dateFrom query parameter that current logged in client is allowed to see.
	 */
	public function testGetShiftSwapRequestsWithOnlyDateFrom()
	{
		$dateFrom = '2019-01-05';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/shift_swap_requests?dateFrom='.$dateFrom,
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
		$shiftSwapRequests = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($shiftSwapRequests) > 0);

		//Test if exactly x shiftSwapRequests are returned
		$this->assertTrue(1 === count($shiftSwapRequests['data']));

		//Test that each shiftSwapRequest has at minimum the below values
		foreach ($shiftSwapRequests['data'] as $shiftSwapRequest) {
			$this->assertArrayHasKey('id', $shiftSwapRequest);
			$this->assertArrayHasKey('applicant', $shiftSwapRequest);
			$this->assertArrayHasKey('receiver', $shiftSwapRequest);
			$this->assertArrayHasKey('assignment', $shiftSwapRequest);
			$this->assertArrayHasKey('status', $shiftSwapRequest);
			$this->assertArrayHasKey('start_date', $shiftSwapRequest);
			$this->assertArrayHasKey('end_date', $shiftSwapRequest);
		}
	}

	/**
	 * Tests the retrieval of shiftSwapRequests with only dateTo query parameter that current logged in client is allowed to see.
	 */
	public function testGetShiftSwapRequestsWithOnlyDateTo()
	{
		$dateTo = '2019-01-04';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/shift_swap_requests?dateTo='.$dateTo,
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
		$shiftSwapRequests = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($shiftSwapRequests) > 0);

		//Test if exactly x shiftSwapRequests are returned
		$this->assertTrue(1 === count($shiftSwapRequests['data']));

		//Test that each shiftSwapRequest has at minimum the below values
		foreach ($shiftSwapRequests['data'] as $shiftSwapRequest) {
			$this->assertArrayHasKey('id', $shiftSwapRequest);
			$this->assertArrayHasKey('applicant', $shiftSwapRequest);
			$this->assertArrayHasKey('receiver', $shiftSwapRequest);
			$this->assertArrayHasKey('assignment', $shiftSwapRequest);
			$this->assertArrayHasKey('status', $shiftSwapRequest);
			$this->assertArrayHasKey('start_date', $shiftSwapRequest);
			$this->assertArrayHasKey('end_date', $shiftSwapRequest);
		}
	}

	/**
	 * Tests the retrieval of shiftSwapRequests without date params that current logged in client is allowed to see, which defaults at shiftSwapRequests of current month.
	 *
	 * @group time-sensitive
	 */
	public function testGetShiftSwapRequestsWithoutDateQueryParams()
	{
		//mock current time in registered classes
		ClockMock::withClockMock(strtotime('2019-01-02 01:00:00'));

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/shift_swap_requests',
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
		$shiftSwapRequests = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($shiftSwapRequests) > 0);

		//Test if exactly x shiftSwapRequests are returned
		$this->assertTrue(2 === count($shiftSwapRequests['data']));

		//Test that each shiftSwapRequest has at minimum the below values
		foreach ($shiftSwapRequests['data'] as $shiftSwapRequest) {
			$this->assertArrayHasKey('id', $shiftSwapRequest);
			$this->assertArrayHasKey('applicant', $shiftSwapRequest);
			$this->assertArrayHasKey('receiver', $shiftSwapRequest);
			$this->assertArrayHasKey('assignment', $shiftSwapRequest);
			$this->assertArrayHasKey('status', $shiftSwapRequest);
			$this->assertArrayHasKey('start_date', $shiftSwapRequest);
			$this->assertArrayHasKey('end_date', $shiftSwapRequest);
		}
	}

	/**
	 * Tests the retrieval of single shiftSwapRequest by ID.
	 *
	 * @group time-sensitive
	 */
	public function testGetSingleShiftSwapRequestSuccess()
	{
		$id = 40;

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/shift_swap_requests/'.$id,
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
		$shiftSwapRequest = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($shiftSwapRequest) > 0);

		$shiftSwapRequest = $shiftSwapRequest['data'];
		//Test that each assignment has at minimum the below values
		$this->assertArrayHasKey('id', $shiftSwapRequest);
		$this->assertArrayHasKey('applicant', $shiftSwapRequest);
		$this->assertArrayHasKey('receiver', $shiftSwapRequest);
		$this->assertArrayHasKey('assignment', $shiftSwapRequest);
		$this->assertArrayHasKey('status', $shiftSwapRequest);
		$this->assertArrayHasKey('start_date', $shiftSwapRequest);
		$this->assertArrayHasKey('end_date', $shiftSwapRequest);
	}

	/**
	 * test create shiftSwapRequest success
	 * TODO maybe use fixture to simulate post.
	 */
	public function testCreateShiftSwapRequestSuccess()
	{
		//The shiftSwapRequest properties to post
		$receiver = '{"id" : 19}';
		$assignment = '{"id" : 104077}';
		$applicantMessage = 'CREATED BY POST TEST';

		$contentString = '{
            "receiver": '.$receiver.',
            "assignment": '.$assignment.',
            "applicant_message": "'.$applicantMessage.'"
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->enableProfiler();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/shift_swap_requests',
			array(),
			array(),
			$headers,
			$contentString
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
		$shiftSwapRequests = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($shiftSwapRequests) > 0);

		$shiftSwapRequest = $shiftSwapRequests['data'];

		//Test that each shiftSwapRequests has at minimum the below values
		$this->assertArrayHasKey('id', $shiftSwapRequest);
		$this->assertArrayHasKey('applicant', $shiftSwapRequest);
		$this->assertArrayHasKey('receiver', $shiftSwapRequest);
		$this->assertArrayHasKey('assignment', $shiftSwapRequest);
		$this->assertArrayHasKey('status', $shiftSwapRequest);
		$this->assertArrayHasKey('start_date', $shiftSwapRequest);
		$this->assertArrayHasKey('end_date', $shiftSwapRequest);

		// keep track of the ids of the created shiftSwapRequests in DB
		self::$createdShiftSwapRequests[] = $shiftSwapRequest['id'];

		// test if shiftSwapRequests are indeed inserted in DB
		foreach (self::$createdShiftSwapRequests as $createdShiftSwapRequest) {
			/** @var ShiftSwapRequest $shiftSwapRequest */
			$shiftSwapRequest = self::$shiftSwapRequestRepo->find($createdShiftSwapRequest);
			$this->assertNotNull($shiftSwapRequest);
			$this->assertEquals($createdShiftSwapRequest, $shiftSwapRequest->getId());
			$this->assertEquals(52, $shiftSwapRequest->getApplicant()->getId());
			$this->assertEquals(19, $shiftSwapRequest->getReceiver()->getId());
			$this->assertEquals(104077, $shiftSwapRequest->getAssignment()->getId());
			$this->assertEquals($applicantMessage, $shiftSwapRequest->getApplicantMessage());
			$this->assertEquals($shiftSwapRequest->getAssignment()->getStartDate(), $shiftSwapRequest->getStartDate());
			$this->assertEquals($shiftSwapRequest->getAssignment()->getEndDate(), $shiftSwapRequest->getEndDate());
			$this->assertEquals(0, $shiftSwapRequest->getStatus());
			$this->assertEquals($shiftSwapRequest->getAssignment()->getDepartment()->getName(),
				$shiftSwapRequest->getDepartmentName());
		}

		// make sure all created shiftSwapRequests are deleted from DB
		self::$em->clear();
		/** @var ShiftSwapRequest $shiftSwapRequest */
		$shiftSwapRequest = self::$shiftSwapRequestRepo->find($shiftSwapRequest->getId());
		if ($shiftSwapRequest) {
			self::$em->remove($shiftSwapRequest);
		}
		self::$em->flush();

		/**
		 * Test email sent.
		 */
		/** @var MessageDataCollector $mailCollector */
		$mailCollector = $client->getProfile()->getCollector('swiftmailer');

		// Check that an email was sent
		$this->assertEquals(1, $mailCollector->getMessageCount());

		$collectedMessages = $mailCollector->getMessages();

		/** @var \Swift_Message $message */
		$message = $collectedMessages[0];

		// Asserting email data
		$this->assertInstanceOf('Swift_Message', $message);
		$this->assertEquals('Ruilverzoek '.$shiftSwapRequest->getApplicant()->getFullName(), $message->getSubject());
		$this->assertEquals('donotreply@planning.nu', key($message->getFrom()));
		$this->assertEquals($shiftSwapRequest->getReceiver()->getEmailAddress(), key($message->getTo()));
		$this->assertContains(
			$shiftSwapRequest->getApplicant()->getFullName().' heeft je een ruilverzoek gestuurd.',
			$message->getBody()
		);
	}

	/**
	 * test create shiftSwapRequest fail.
	 */
	public function testFailCreateShiftSwapRequestReceiverEqualsApplicant()
	{
		//The shiftSwapRequest properties to post
		$receiver = '{"id" : 52}';
		$assignment = '{"id" : 104077}';
		$applicantMessage = 'CREATED BY POST TEST';

		$contentString = '{
            "receiver": '.$receiver.',
            "assignment": '.$assignment.',
            "applicant_message": "'.$applicantMessage.'"
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/shift_swap_requests',
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
	 * test create shiftSwapRequest fail.
	 */
	public function testFailCreateShiftSwapRequestMissingRequired()
	{
		//The shiftSwapRequest properties to post
		$receiver = '{"id" : 52}';
		$applicantMessage = 'CREATED BY POST TEST';

		$contentString = '{
            "receiver": '.$receiver.',
            "applicant_message": "'.$applicantMessage.'"
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/shift_swap_requests',
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
	 * test create shiftSwapRequest fail.
	 */
	public function testFailCreateShiftSwapRequestAssignmentOfOtherPeople()
	{
		//The shiftSwapRequest properties to post
		$receiver = '{"id" : 52}';
		$assignment = '{"id" : 104072}';
		$applicantMessage = 'CREATED BY POST TEST';

		$contentString = '{
            "receiver": '.$receiver.',
            "assignment": '.$assignment.',
            "applicant_message": "'.$applicantMessage.'"
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/shift_swap_requests',
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
	 * test create shiftSwapRequest fail assignment with already shift swap request issued.
	 */
	public function testFailCreateShiftSwapRequestAssignmentWithExistingShiftSwapRequest()
	{
		//The shiftSwapRequest properties to post
		$receiver = '{"id" : 52}';
		$assignment = '{"id" : 104066}';
		$applicantMessage = 'CREATED BY POST TEST';

		$contentString = '{
            "receiver": '.$receiver.',
            "assignment": '.$assignment.',
            "applicant_message": "'.$applicantMessage.'"
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/shift_swap_requests',
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
	 * test create shiftSwapRequest fail.
	 */
	public function testFailCreateShiftSwapRequestReceiverInvalidStatus()
	{
		//The shiftSwapRequest properties to post
		$receiver = '{"id" : 52}';
		$assignment = '{"id" : 104077}';
		$applicantMessage = 'CREATED BY POST TEST';
		$status = 3;

		$contentString = '{
            "receiver": '.$receiver.',
            "assignment": '.$assignment.',
            "applicant_message": "'.$applicantMessage.'",
            "status": '.$status.'
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/shift_swap_requests',
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
	 * test create shiftSwapRequest fail.
	 */
	public function testFailCreateShiftSwapRequestReceiverInvalidField()
	{
		//The shiftSwapRequest properties to post
		$receiver = '{"id" : 52}';
		$assignment = '{"id" : 104077}';
		$applicantMessage = 'CREATED BY POST TEST';

		$contentString = '{
            "receiver": '.$receiver.',
            "assignment": '.$assignment.',
            "receiver_message": "'.$applicantMessage.'",
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/shift_swap_requests',
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
	 * test create shiftSwapRequest fail.
	 */
	public function testFailCreateShiftSwapRequestNotExistObject()
	{
		//The shiftSwapRequest properties to post
		$receiver = '{"id" : 52}';
		$assignment = '{"id" : 99999999}';
		$applicantMessage = 'CREATED BY POST TEST';

		$contentString = '{
            "receiver": '.$receiver.',
            "assignment": '.$assignment.',
            "receiver_message": "'.$applicantMessage.'",
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/shift_swap_requests',
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
	 * Test success edit shiftSwapRequests as applicant.
	 */
	public function testWithdrawShiftSwapRequestsAsApplicantSuccess()
	{
		// First create one ShiftSwapRequest
		//The shiftSwapRequest properties to post
		$receiver = '{"id" : 19}';
		$assignment = '{"id" : 104077}';
		$applicantMessage = 'CREATED BY POST TEST';

		$contentString = '{
            "receiver": '.$receiver.',
            "assignment": '.$assignment.',
            "applicant_message": "'.$applicantMessage.'"
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/shift_swap_requests',
			array(),
			array(),
			$headers,
			$contentString
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
		$shiftSwapRequests = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($shiftSwapRequests) > 0);

		$newShiftSwapRequest = $shiftSwapRequests['data'];

		//Test that each shiftSwapRequests has at minimum the below values
		$this->assertArrayHasKey('id', $newShiftSwapRequest);
		$this->assertArrayHasKey('applicant', $newShiftSwapRequest);
		$this->assertArrayHasKey('receiver', $newShiftSwapRequest);
		$this->assertArrayHasKey('assignment', $newShiftSwapRequest);
		$this->assertArrayHasKey('status', $newShiftSwapRequest);
		$this->assertArrayHasKey('start_date', $newShiftSwapRequest);
		$this->assertArrayHasKey('end_date', $newShiftSwapRequest);

		// keep track of the ids of the created shiftSwapRequests in DB
		self::$createdShiftSwapRequests[] = $newShiftSwapRequest['id'];

		/**
		 * TEST PUT STATUS 5 WITHDRAW FROM APPLICANT.
		 */

		//The shiftSwapRequest properties to PUT
		$id = $newShiftSwapRequest['id'];
		$applicantMessage = 'CREATED BY POST TEST';
		$applicantWithdrawalMessage = 'WITHDRAWN BY TEST';
		$receiverMessage = 'RECEIVER MESSAGE BY TEST';
		$receiverWithdrawalMessage = 'RECEIVER WITHDRAW BY TEST';
		$status = 5;

		$contentString = '{
            "id": '.$id.',
            "applicant_withdrawal_message": "'.$applicantWithdrawalMessage.'",
            "status": '.$status.'
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->enableProfiler();
		$client->request(
			Request::METHOD_PATCH,
			'/api/v1/shift_swap_requests',
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
		$shiftSwapRequests = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($shiftSwapRequests) > 0);

		$editedShiftSwapRequest = $shiftSwapRequests['data'];

		//Test that each shiftSwapRequests has at minimum the below values
		$this->assertArrayHasKey('id', $editedShiftSwapRequest);
		$this->assertArrayHasKey('applicant', $editedShiftSwapRequest);
		$this->assertArrayHasKey('receiver', $editedShiftSwapRequest);
		$this->assertArrayHasKey('assignment', $editedShiftSwapRequest);
		$this->assertArrayHasKey('status', $editedShiftSwapRequest);
		$this->assertArrayHasKey('start_date', $editedShiftSwapRequest);
		$this->assertArrayHasKey('end_date', $editedShiftSwapRequest);

		// test if shiftSwapRequests are indeed inserted in DB
		/** @var ShiftSwapRequest $newShiftSwapRequest */
		$editedShiftSwapRequest = self::$shiftSwapRequestRepo->find($editedShiftSwapRequest['id']);
		$this->assertNotNull($editedShiftSwapRequest);
		$this->assertEquals(52, $editedShiftSwapRequest->getApplicant()->getId());
		$this->assertEquals(19, $editedShiftSwapRequest->getReceiver()->getId());
		$this->assertEquals(104077, $editedShiftSwapRequest->getAssignment()->getId());
		$this->assertEquals($applicantMessage, $editedShiftSwapRequest->getApplicantMessage());
		$this->assertEquals($applicantWithdrawalMessage, $editedShiftSwapRequest->getApplicantWithdrawalMessage());
		$this->assertEquals($editedShiftSwapRequest->getAssignment()->getStartDate(),
			$editedShiftSwapRequest->getStartDate());
		$this->assertEquals($editedShiftSwapRequest->getAssignment()->getEndDate(),
			$editedShiftSwapRequest->getEndDate());
		$this->assertEquals(5, $editedShiftSwapRequest->getStatus());
		$this->assertEquals($editedShiftSwapRequest->getAssignment()->getDepartment()->getName(),
			$editedShiftSwapRequest->getDepartmentName());

		/**
		 * Test email sent.
		 */
		/** @var MessageDataCollector $mailCollector */
		$mailCollector = $client->getProfile()->getCollector('swiftmailer');

		// Check that an email was sent
		$this->assertEquals(1, $mailCollector->getMessageCount());

		$collectedMessages = $mailCollector->getMessages();

		/** @var \Swift_Message $message */
		$message = $collectedMessages[0];

		// Asserting email data
		$this->assertInstanceOf('Swift_Message', $message);
		$this->assertEquals($editedShiftSwapRequest->getApplicant()->getFullName().' heeft het ruilverzoek ingetrokken',
			$message->getSubject());
		$this->assertEquals('donotreply@planning.nu', key($message->getFrom()));
		$this->assertEquals($editedShiftSwapRequest->getReceiver()->getEmailAddress(), key($message->getTo()));
		$this->assertContains(
			$editedShiftSwapRequest->getApplicant()->getFullName().' heeft het ruilverzoek ingetrokken',

			$message->getBody()
		);

		/*
		 * TEST PUT STATUS 5 WITHDRAW FROM APPLICANT WHEN ALREADY GRANTED BY RECEIVER
		 */

		// change status to 1 (granted by receiver)
		self::$em->clear();
		/** @var ShiftSwapRequest $newShiftSwapRequest */
		$newShiftSwapRequest = self::$shiftSwapRequestRepo->find($newShiftSwapRequest['id']);
		if ($newShiftSwapRequest) {
			$newShiftSwapRequest->setStatus(ShiftSwapRequestStatus::GRANTED_BY_RECEIVER);
		}
		self::$em->flush();

		//The shiftSwapRequest properties to PUT
		$id = $newShiftSwapRequest->getId();
		$applicantMessage = 'CREATED BY POST TEST';
		$applicantWithdrawalMessage = 'WITHDRAWN BY TEST';
		$receiverMessage = 'RECEIVER MESSAGE BY TEST';
		$receiverWithdrawalMessage = 'RECEIVER WITHDRAW BY TEST';
		$status = 5;

		$contentString = '{
            "id": '.$id.',
            "applicant_withdrawal_message": "'.$applicantWithdrawalMessage.'",
            "status": '.$status.'
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->enableProfiler();
		$client->request(
			Request::METHOD_PATCH,
			'/api/v1/shift_swap_requests',
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
		$shiftSwapRequests = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($shiftSwapRequests) > 0);

		$editedShiftSwapRequest = $shiftSwapRequests['data'];

		//Test that each shiftSwapRequests has at minimum the below values
		$this->assertArrayHasKey('id', $editedShiftSwapRequest);
		$this->assertArrayHasKey('applicant', $editedShiftSwapRequest);
		$this->assertArrayHasKey('receiver', $editedShiftSwapRequest);
		$this->assertArrayHasKey('assignment', $editedShiftSwapRequest);
		$this->assertArrayHasKey('status', $editedShiftSwapRequest);
		$this->assertArrayHasKey('start_date', $editedShiftSwapRequest);
		$this->assertArrayHasKey('end_date', $editedShiftSwapRequest);

		// test if shiftSwapRequests are indeed inserted in DB
		self::$em->clear();
		$editedShiftSwapRequest = self::$shiftSwapRequestRepo->find($editedShiftSwapRequest['id']);
		$this->assertNotNull($editedShiftSwapRequest);
		$this->assertEquals(52, $editedShiftSwapRequest->getApplicant()->getId());
		$this->assertEquals(19, $editedShiftSwapRequest->getReceiver()->getId());
		$this->assertEquals(104077, $editedShiftSwapRequest->getAssignment()->getId());
		$this->assertEquals($applicantMessage, $editedShiftSwapRequest->getApplicantMessage());
		$this->assertEquals($applicantWithdrawalMessage, $editedShiftSwapRequest->getApplicantWithdrawalMessage());
		$this->assertEquals($editedShiftSwapRequest->getAssignment()->getStartDate(),
			$editedShiftSwapRequest->getStartDate());
		$this->assertEquals($editedShiftSwapRequest->getAssignment()->getEndDate(),
			$editedShiftSwapRequest->getEndDate());
		$this->assertEquals(5, $editedShiftSwapRequest->getStatus());
		$this->assertEquals($editedShiftSwapRequest->getAssignment()->getDepartment()->getName(),
			$editedShiftSwapRequest->getDepartmentName());

		/**
		 * Test email sent.
		 */
		/** @var MessageDataCollector $mailCollector */
		$mailCollector = $client->getProfile()->getCollector('swiftmailer');

		// Check that an email was sent
		$this->assertEquals(1, $mailCollector->getMessageCount());

		$collectedMessages = $mailCollector->getMessages();

		/** @var \Swift_Message $message */
		$message = $collectedMessages[0];

		// Asserting email data
		$this->assertInstanceOf('Swift_Message', $message);
		$this->assertEquals($editedShiftSwapRequest->getApplicant()->getFullName().' heeft het ruilverzoek ingetrokken',
			$message->getSubject());
		$this->assertEquals('donotreply@planning.nu', key($message->getFrom()));
		$this->assertEquals($editedShiftSwapRequest->getReceiver()->getEmailAddress(), key($message->getTo()));
		$this->assertContains(
			$editedShiftSwapRequest->getApplicant()->getFullName().' heeft het ruilverzoek ingetrokken',

			$message->getBody()
		);

		// make sure all created shiftSwapRequests are deleted from DB
		self::$em->clear();
		/** @var ShiftSwapRequest $newShiftSwapRequest */
		$newShiftSwapRequest = self::$shiftSwapRequestRepo->find($newShiftSwapRequest->getId());
		if ($newShiftSwapRequest) {
			self::$em->remove($newShiftSwapRequest);
		}
		self::$em->flush();
	}

	/**
	 * Test fail edit shiftSwapRequests invalid ID.
	 */
	public function testPatchShiftSwapRequestsAsApplicantFailInvalidId()
	{
		//The shiftSwapRequest properties to PUT
		$id = 1;
		$applicantMessage = 'CREATED BY POST TEST';
		$applicantWithdrawalMessage = 'WITHDRAWN BY TEST';
		$receiverMessage = 'RECEIVER MESSAGE BY TEST';
		$receiverWithdrawalMessage = 'RECEIVER WITHDRAW BY TEST';
		$status = 5;

		$contentString = '{
            "id": '.$id.',
            "applicant_withdrawal_message": "'.$applicantWithdrawalMessage.'",
            "status": '.$status.'
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_PATCH,
			'/api/v1/shift_swap_requests',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is NOT FOUND 404
		$this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode(), $response->getContent());
	}

	/**
	 * Test edit shiftSwapRequests success as receiver.
	 */
	public function testConfirmDenyWithdrawShiftSwapRequestsAsReceiverSuccess()
	{
		// First create one ShiftSwapRequest
		//The shiftSwapRequest properties to post
		$receiver = '{"id" : 19}';
		$assignment = '{"id" : 104077}';
		$applicantMessage = 'CREATED BY POST TEST';

		$contentString = '{
            "receiver": '.$receiver.',
            "assignment": '.$assignment.',
            "applicant_message": "'.$applicantMessage.'"
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/shift_swap_requests',
			array(),
			array(),
			$headers,
			$contentString
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
		$shiftSwapRequests = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($shiftSwapRequests) > 0);

		$newShiftSwapRequest = $shiftSwapRequests['data'];

		//Test that each shiftSwapRequests has at minimum the below values
		$this->assertArrayHasKey('id', $newShiftSwapRequest);
		$this->assertArrayHasKey('applicant', $newShiftSwapRequest);
		$this->assertArrayHasKey('receiver', $newShiftSwapRequest);
		$this->assertArrayHasKey('assignment', $newShiftSwapRequest);
		$this->assertArrayHasKey('status', $newShiftSwapRequest);
		$this->assertArrayHasKey('start_date', $newShiftSwapRequest);
		$this->assertArrayHasKey('end_date', $newShiftSwapRequest);

		// keep track of the ids of the created shiftSwapRequests in DB
		self::$createdShiftSwapRequests[] = $newShiftSwapRequest['id'];

		/**
		 * TEST PUT STATUS 1 FROM RECEIVER.
		 */

		//The shiftSwapRequest properties to PUT
		$id = $newShiftSwapRequest['id'];
		$applicantMessage = 'CREATED BY POST TEST';
		$applicantWithdrawalMessage = 'WITHDRAWN BY TEST';
		$receiverMessage = 'RECEIVER APRROVED MESSAGE BY TEST';
		$receiverWithdrawalMessage = 'RECEIVER WITHDRAW BY TEST';
		$status = 1;

		$contentString = '{
            "id": '.$id.',
            "receiver_message": "'.$receiverMessage.'",
            "status": '.$status.'
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$receiverToken,
		);

		// HTTP client
		$client = static::createClient();
		$client->enableProfiler();
		$client->request(
			Request::METHOD_PATCH,
			'/api/v1/shift_swap_requests',
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
		$shiftSwapRequests = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($shiftSwapRequests) > 0);

		$editedShiftSwapRequest = $shiftSwapRequests['data'];

		//Test that each shiftSwapRequests has at minimum the below values
		$this->assertArrayHasKey('id', $editedShiftSwapRequest);
		$this->assertArrayHasKey('applicant', $editedShiftSwapRequest);
		$this->assertArrayHasKey('receiver', $editedShiftSwapRequest);
		$this->assertArrayHasKey('assignment', $editedShiftSwapRequest);
		$this->assertArrayHasKey('status', $editedShiftSwapRequest);
		$this->assertArrayHasKey('start_date', $editedShiftSwapRequest);
		$this->assertArrayHasKey('end_date', $editedShiftSwapRequest);

		// test if shiftSwapRequests are indeed inserted in DB
		/** @var ShiftSwapRequest $editedShiftSwapRequest */
		$editedShiftSwapRequest = self::$shiftSwapRequestRepo->find($editedShiftSwapRequest['id']);
		$this->assertNotNull($editedShiftSwapRequest);
		$this->assertEquals(52, $editedShiftSwapRequest->getApplicant()->getId());
		$this->assertEquals(19, $editedShiftSwapRequest->getReceiver()->getId());
		$this->assertEquals(104077, $editedShiftSwapRequest->getAssignment()->getId());
		$this->assertEquals($applicantMessage, $editedShiftSwapRequest->getApplicantMessage());
		$this->assertEquals($receiverMessage, $editedShiftSwapRequest->getReceiverMessage());
		$this->assertEquals($editedShiftSwapRequest->getAssignment()->getStartDate(),
			$editedShiftSwapRequest->getStartDate());
		$this->assertEquals($editedShiftSwapRequest->getAssignment()->getEndDate(),
			$editedShiftSwapRequest->getEndDate());
		$this->assertEquals($status, $editedShiftSwapRequest->getStatus());
		$this->assertEquals($editedShiftSwapRequest->getAssignment()->getDepartment()->getName(),
			$editedShiftSwapRequest->getDepartmentName());

		/**
		 * Test email sent.
		 */
		/** @var MessageDataCollector $mailCollector */
		$mailCollector = $client->getProfile()->getCollector('swiftmailer');

		// Check that an email was sent
		$this->assertEquals(1, $mailCollector->getMessageCount());

		$collectedMessages = $mailCollector->getMessages();

		/** @var \Swift_Message $message */
		$message = $collectedMessages[0];

		// Asserting email data
		$this->assertInstanceOf('Swift_Message', $message);
		$this->assertEquals('Status ruilverzoek', $message->getSubject());
		$this->assertEquals('donotreply@planning.nu', key($message->getFrom()));
		$this->assertEquals($editedShiftSwapRequest->getApplicant()->getEmailAddress(), key($message->getTo()));
		$this->assertContains(
			'Je ruilverzoek is geaccepteerd door '.$editedShiftSwapRequest->getReceiver()->getFullName(),
			$message->getBody()
		);

		/*
		 * TEST PUT STATUS 2 DENY BY RECEIVER
		 */

		// change status to 0 (newly created)
		self::$em->clear();
		/** @var ShiftSwapRequest $newShiftSwapRequest */
		$newShiftSwapRequest = self::$shiftSwapRequestRepo->find($newShiftSwapRequest['id']);
		if ($newShiftSwapRequest) {
			$newShiftSwapRequest->setStatus(ShiftSwapRequestStatus::UNPROCESSED_BY_RECEIVER);
		}
		self::$em->flush();

		//The shiftSwapRequest properties to PUT
		$id = $newShiftSwapRequest->getId();
		$applicantMessage = 'CREATED BY POST TEST';
		$applicantWithdrawalMessage = 'WITHDRAWN BY TEST';
		$receiverMessage = 'RECEIVER DENIED MESSAGE BY TEST';
		$receiverWithdrawalMessage = 'RECEIVER WITHDRAW BY TEST';
		$status = ShiftSwapRequestStatus::DENIED_BY_RECEIVER;

		$contentString = '{
            "id": '.$id.',
            "receiver_message": "'.$receiverMessage.'",
            "status": '.$status.'
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$receiverToken,
		);

		// HTTP client
		$client = static::createClient();
		$client->enableProfiler();
		$client->request(
			Request::METHOD_PATCH,
			'/api/v1/shift_swap_requests',
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
		$shiftSwapRequests = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($shiftSwapRequests) > 0);

		/** @var array $editedShiftSwapRequest */
		$editedShiftSwapRequest = $shiftSwapRequests['data'];

		//Test that each shiftSwapRequests has at minimum the below values
		$this->assertArrayHasKey('id', $editedShiftSwapRequest);
		$this->assertArrayHasKey('applicant', $editedShiftSwapRequest);
		$this->assertArrayHasKey('receiver', $editedShiftSwapRequest);
		$this->assertArrayHasKey('assignment', $editedShiftSwapRequest);
		$this->assertArrayHasKey('status', $editedShiftSwapRequest);
		$this->assertArrayHasKey('start_date', $editedShiftSwapRequest);
		$this->assertArrayHasKey('end_date', $editedShiftSwapRequest);

		// test if shiftSwapRequests are indeed inserted in DB
		/** @var ShiftSwapRequest $editedShiftSwapRequest */
		$editedShiftSwapRequest = self::$shiftSwapRequestRepo->find($editedShiftSwapRequest['id']);
		self::$em->refresh($editedShiftSwapRequest);
		$this->assertNotNull($editedShiftSwapRequest);
		$this->assertEquals(52, $editedShiftSwapRequest->getApplicant()->getId());
		$this->assertEquals(19, $editedShiftSwapRequest->getReceiver()->getId());
		$this->assertEquals(104077, $editedShiftSwapRequest->getAssignment()->getId());
		$this->assertEquals($applicantMessage, $editedShiftSwapRequest->getApplicantMessage());
		$this->assertEquals($receiverMessage, $editedShiftSwapRequest->getReceiverMessage());
		$this->assertEquals($editedShiftSwapRequest->getAssignment()->getStartDate(),
			$editedShiftSwapRequest->getStartDate());
		$this->assertEquals($editedShiftSwapRequest->getAssignment()->getEndDate(),
			$editedShiftSwapRequest->getEndDate());
		$this->assertEquals($status, $editedShiftSwapRequest->getStatus());
		$this->assertEquals($editedShiftSwapRequest->getAssignment()->getDepartment()->getName(),
			$editedShiftSwapRequest->getDepartmentName());

		/**
		 * Test email sent.
		 */
		/** @var MessageDataCollector $mailCollector */
		$mailCollector = $client->getProfile()->getCollector('swiftmailer');

		// Check that an email was sent
		$this->assertEquals(1, $mailCollector->getMessageCount());

		$collectedMessages = $mailCollector->getMessages();

		/** @var \Swift_Message $message */
		$message = $collectedMessages[0];

		// Asserting email data
		$this->assertInstanceOf('Swift_Message', $message);
		$this->assertEquals('Afwijzing ruilverzoek', $message->getSubject());
		$this->assertEquals('donotreply@planning.nu', key($message->getFrom()));
		$this->assertEquals($editedShiftSwapRequest->getApplicant()->getEmailAddress(), key($message->getTo()));
		$this->assertContains(
			'Je ruilverzoek is afgewezen door '.$editedShiftSwapRequest->getReceiver()->getFullName(),
			$message->getBody()
		);

		/*
		 * TEST PUT STATUS 6 WITHDRAWN BY RECEIVER WHEN ALREADY GRANTED
		 */

		// change status to 1 (granted by receiver)
		self::$em->clear();
		/** @var ShiftSwapRequest $newShiftSwapRequest */
		$newShiftSwapRequest = self::$shiftSwapRequestRepo->find($newShiftSwapRequest->getId());
		if ($newShiftSwapRequest) {
			$newShiftSwapRequest->setStatus(ShiftSwapRequestStatus::GRANTED_BY_RECEIVER);
		}
		self::$em->flush();

		//The shiftSwapRequest properties to PUT
		$id = $newShiftSwapRequest->getId();
		$applicantMessage = 'CREATED BY POST TEST';
		$applicantWithdrawalMessage = 'WITHDRAWN BY TEST';
		$receiverMessage = 'RECEIVER MESSAGE BY TEST';
		$receiverWithdrawalMessage = 'RECEIVER WITHDRAW BY TEST';
		$status = ShiftSwapRequestStatus::WITHDRAWN_BY_RECEIVER;

		$contentString = '{
            "id": '.$id.',
            "receiver_withdrawal_message": "'.$receiverWithdrawalMessage.'",
            "status": '.$status.'
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$receiverToken,
		);

		// HTTP client
		$client = static::createClient();
		$client->enableProfiler();
		$client->request(
			Request::METHOD_PATCH,
			'/api/v1/shift_swap_requests',
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
		$shiftSwapRequests = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($shiftSwapRequests) > 0);

		/** @var array $editedShiftSwapRequest */
		$editedShiftSwapRequest = $shiftSwapRequests['data'];

		//Test that each shiftSwapRequests has at minimum the below values
		$this->assertArrayHasKey('id', $editedShiftSwapRequest);
		$this->assertArrayHasKey('applicant', $editedShiftSwapRequest);
		$this->assertArrayHasKey('receiver', $editedShiftSwapRequest);
		$this->assertArrayHasKey('assignment', $editedShiftSwapRequest);
		$this->assertArrayHasKey('status', $editedShiftSwapRequest);
		$this->assertArrayHasKey('start_date', $editedShiftSwapRequest);
		$this->assertArrayHasKey('end_date', $editedShiftSwapRequest);

		// test if shiftSwapRequests are indeed inserted in DB
		/** @var ShiftSwapRequest $editedShiftSwapRequest */
		$editedShiftSwapRequest = self::$shiftSwapRequestRepo->find($editedShiftSwapRequest['id']);
		self::$em->refresh($editedShiftSwapRequest);
		$this->assertNotNull($editedShiftSwapRequest);
		$this->assertEquals(52, $editedShiftSwapRequest->getApplicant()->getId());
		$this->assertEquals(19, $editedShiftSwapRequest->getReceiver()->getId());
		$this->assertEquals(104077, $editedShiftSwapRequest->getAssignment()->getId());
		$this->assertEquals($applicantMessage, $editedShiftSwapRequest->getApplicantMessage());
		$this->assertEquals($receiverWithdrawalMessage, $editedShiftSwapRequest->getReceiverWithdrawalMessage());
		$this->assertEquals($editedShiftSwapRequest->getAssignment()->getStartDate(),
			$editedShiftSwapRequest->getStartDate());
		$this->assertEquals($editedShiftSwapRequest->getAssignment()->getEndDate(),
			$editedShiftSwapRequest->getEndDate());
		$this->assertEquals($status, $editedShiftSwapRequest->getStatus());
		$this->assertEquals($editedShiftSwapRequest->getAssignment()->getDepartment()->getName(),
			$editedShiftSwapRequest->getDepartmentName());

		// make sure all created shiftSwapRequests are deleted from DB
		self::$em->clear();
		/** @var ShiftSwapRequest $toBeDeletedShiftSwapRequest */
		$toBeDeletedShiftSwapRequest = self::$shiftSwapRequestRepo->find($newShiftSwapRequest->getId());
		if ($toBeDeletedShiftSwapRequest) {
			self::$em->remove($toBeDeletedShiftSwapRequest);
		}
		self::$em->flush();

		/**
		 * Test email sent.
		 */
		/** @var MessageDataCollector $mailCollector */
		$mailCollector = $client->getProfile()->getCollector('swiftmailer');

		// Check that an email was sent
		$this->assertEquals(1, $mailCollector->getMessageCount());

		$collectedMessages = $mailCollector->getMessages();

		/** @var \Swift_Message $message */
		$message = $collectedMessages[0];

		// Asserting email data
		$this->assertInstanceOf('Swift_Message', $message);
		$this->assertEquals($editedShiftSwapRequest->getReceiver()->getFullName().' heeft het ruilverzoek alsnog afgewezen',
			$message->getSubject());
		$this->assertEquals('donotreply@planning.nu', key($message->getFrom()));
		$this->assertEquals($editedShiftSwapRequest->getApplicant()->getEmailAddress(), key($message->getTo()));
		$this->assertContains(
			'Je ruilverzoek is alsnog afgewezen door '.$editedShiftSwapRequest->getReceiver()->getFullName(),
			$message->getBody()
		);
	}

	/**
	 * Test edit shiftSwapRequests fail as applicant.
	 */
	public function testFailEditShiftSwapRequestsAsApplicant()
	{
		// First create one ShiftSwapRequest
		//The shiftSwapRequest properties to post
		$receiver = '{"id" : 19}';
		$assignment = '{"id" : 104077}';
		$applicantMessage = 'CREATED BY POST TEST';

		$contentString = '{
            "receiver": '.$receiver.',
            "assignment": '.$assignment.',
            "applicant_message": "'.$applicantMessage.'"
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/shift_swap_requests',
			array(),
			array(),
			$headers,
			$contentString
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
		$shiftSwapRequests = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($shiftSwapRequests) > 0);

		$newShiftSwapRequest = $shiftSwapRequests['data'];

		//Test that each shiftSwapRequests has at minimum the below values
		$this->assertArrayHasKey('id', $newShiftSwapRequest);
		$this->assertArrayHasKey('applicant', $newShiftSwapRequest);
		$this->assertArrayHasKey('receiver', $newShiftSwapRequest);
		$this->assertArrayHasKey('assignment', $newShiftSwapRequest);
		$this->assertArrayHasKey('status', $newShiftSwapRequest);
		$this->assertArrayHasKey('start_date', $newShiftSwapRequest);
		$this->assertArrayHasKey('end_date', $newShiftSwapRequest);

		// keep track of the ids of the created shiftSwapRequests in DB
		self::$createdShiftSwapRequests[] = $newShiftSwapRequest['id'];

		/**
		 * TEST PUT INVALID FIELD.
		 */
		//The shiftSwapRequest properties to PUT
		$id = $newShiftSwapRequest['id'];
		$applicantMessage = 'CREATED BY POST TEST';
		$applicantWithdrawalMessage = 'WITHDRAWN BY TEST';
		$receiverMessage = 'RECEIVER APRROVED MESSAGE BY TEST';
		$receiverWithdrawalMessage = 'RECEIVER WITHDRAW BY TEST';
		$status = ShiftSwapRequestStatus::GRANTED_BY_RECEIVER;

		$contentString = '{
            "id": '.$id.',
            "applicant_withdrawal_message": "'.$applicantWithdrawalMessage.'",
            "status": '.$status.'
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_PATCH,
			'/api/v1/shift_swap_requests',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is PRECONDITION FAILED 412
		$this->assertSame(Response::HTTP_PRECONDITION_FAILED, $response->getStatusCode());

		/**
		 * TEST PUT INVALID STATUS.
		 */
		//The shiftSwapRequest properties to PUT
		$id = $newShiftSwapRequest['id'];
		$applicantMessage = 'CREATED BY POST TEST';
		$applicantWithdrawalMessage = 'WITHDRAWN BY TEST';
		$receiverMessage = 'RECEIVER APRROVED MESSAGE BY TEST';
		$receiverWithdrawalMessage = 'RECEIVER WITHDRAW BY TEST';
		$status = ShiftSwapRequestStatus::GRANTED_BY_PLANNER;

		$contentString = '{
            "id": '.$id.',
            "applicant_message": "'.$applicantMessage.'",
            "status": '.$status.'
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_PATCH,
			'/api/v1/shift_swap_requests',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is PRECONDITION FAILED 412
		$this->assertSame(Response::HTTP_PRECONDITION_FAILED, $response->getStatusCode());

		/*
		 * TEST PUT INVALID WITHDRAWAL ALREADY GRANTED
		 */

		// change status to 3 (granted by planner)
		self::$em->clear();
		/** @var ShiftSwapRequest $newShiftSwapRequest */
		$newShiftSwapRequest = self::$shiftSwapRequestRepo->find($newShiftSwapRequest['id']);
		if ($newShiftSwapRequest) {
			$newShiftSwapRequest->setStatus(ShiftSwapRequestStatus::GRANTED_BY_PLANNER);
		}
		self::$em->flush();

		//The shiftSwapRequest properties to PUT
		$id = $newShiftSwapRequest->getId();
		$applicantMessage = 'CREATED BY POST TEST';
		$applicantWithdrawalMessage = 'WITHDRAWN BY TEST';
		$receiverMessage = 'RECEIVER APRROVED MESSAGE BY TEST';
		$receiverWithdrawalMessage = 'RECEIVER WITHDRAW BY TEST';
		$status = ShiftSwapRequestStatus::WITHDRAWN_BY_RECEIVER;

		$contentString = '{
            "id": '.$id.',
            "receiver_withdrawal_message": "'.$applicantWithdrawalMessage.'",
            "status": '.$status.'
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_PATCH,
			'/api/v1/shift_swap_requests',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is PRECONDITION FAILED 412
		$this->assertSame(Response::HTTP_PRECONDITION_FAILED, $response->getStatusCode());

		// make sure all created shiftSwapRequests are deleted from DB
		self::$em->clear();
		/** @var ShiftSwapRequest $toBeDeletedShiftSwapRequest */
		$toBeDeletedShiftSwapRequest = self::$shiftSwapRequestRepo->find($newShiftSwapRequest->getId());
		if ($toBeDeletedShiftSwapRequest) {
			self::$em->remove($toBeDeletedShiftSwapRequest);
		}
		self::$em->flush();
	}

	/**
	 * Test edit shiftSwapRequests fail as receiver.
	 */
	public function testFailEditShiftSwapRequestsAsReceiver()
	{
		// First create one ShiftSwapRequest
		//The shiftSwapRequest properties to post
		$receiver = '{"id" : 19}';
		$assignment = '{"id" : 104077}';
		$applicantMessage = 'CREATED BY POST TEST';

		$contentString = '{
            "receiver": '.$receiver.',
            "assignment": '.$assignment.',
            "applicant_message": "'.$applicantMessage.'"
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/shift_swap_requests',
			array(),
			array(),
			$headers,
			$contentString
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
		$shiftSwapRequests = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($shiftSwapRequests) > 0);

		$newShiftSwapRequest = $shiftSwapRequests['data'];

		//Test that each shiftSwapRequests has at minimum the below values
		$this->assertArrayHasKey('id', $newShiftSwapRequest);
		$this->assertArrayHasKey('applicant', $newShiftSwapRequest);
		$this->assertArrayHasKey('receiver', $newShiftSwapRequest);
		$this->assertArrayHasKey('assignment', $newShiftSwapRequest);
		$this->assertArrayHasKey('status', $newShiftSwapRequest);
		$this->assertArrayHasKey('start_date', $newShiftSwapRequest);
		$this->assertArrayHasKey('end_date', $newShiftSwapRequest);

		// keep track of the ids of the created shiftSwapRequests in DB
		self::$createdShiftSwapRequests[] = $newShiftSwapRequest['id'];

		/**
		 * TEST PUT INVALID FIELD.
		 */
		//The shiftSwapRequest properties to PUT
		$id = $newShiftSwapRequest['id'];
		$applicantMessage = 'CREATED BY POST TEST';
		$applicantWithdrawalMessage = 'WITHDRAWN BY TEST';
		$receiverMessage = 'RECEIVER APRROVED MESSAGE BY TEST';
		$receiverWithdrawalMessage = 'RECEIVER WITHDRAW BY TEST';
		$status = ShiftSwapRequestStatus::GRANTED_BY_RECEIVER;

		$contentString = '{
            "id": '.$id.',
            "receiver_withdrawal_message": "'.$applicantWithdrawalMessage.'",
            "status": '.$status.'
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$receiverToken,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_PATCH,
			'/api/v1/shift_swap_requests',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is PRECONDITION FAILED 412
		$this->assertSame(Response::HTTP_PRECONDITION_FAILED, $response->getStatusCode());

		/**
		 * TEST PUT INVALID STATUS.
		 */
		//The shiftSwapRequest properties to PUT
		$id = $newShiftSwapRequest['id'];
		$applicantMessage = 'CREATED BY POST TEST';
		$applicantWithdrawalMessage = 'WITHDRAWN BY TEST';
		$receiverMessage = 'RECEIVER APRROVED MESSAGE BY TEST';
		$receiverWithdrawalMessage = 'RECEIVER WITHDRAW BY TEST';
		$status = ShiftSwapRequestStatus::GRANTED_BY_PLANNER;

		$contentString = '{
            "id": '.$id.',
            "receiver_message": "'.$applicantWithdrawalMessage.'",
            "status": '.$status.'
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$receiverToken,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_PATCH,
			'/api/v1/shift_swap_requests',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is PRECONDITION FAILED 412
		$this->assertSame(Response::HTTP_PRECONDITION_FAILED, $response->getStatusCode());

		/*
		 * TEST PUT INVALID WITHDRAWAL ALREADY GRANTED
		 */

		// change status to 3 (granted by planner)
		self::$em->clear();
		/** @var ShiftSwapRequest $newShiftSwapRequest */
		$newShiftSwapRequest = self::$shiftSwapRequestRepo->find($newShiftSwapRequest['id']);
		if ($newShiftSwapRequest) {
			$newShiftSwapRequest->setStatus(ShiftSwapRequestStatus::GRANTED_BY_PLANNER);
		}
		self::$em->flush();

		//The shiftSwapRequest properties to PUT
		$id = $newShiftSwapRequest->getId();
		$applicantMessage = 'CREATED BY POST TEST';
		$applicantWithdrawalMessage = 'WITHDRAWN BY TEST';
		$receiverMessage = 'RECEIVER APRROVED MESSAGE BY TEST';
		$receiverWithdrawalMessage = 'RECEIVER WITHDRAW BY TEST';
		$status = ShiftSwapRequestStatus::WITHDRAWN_BY_RECEIVER;

		$contentString = '{
            "id": '.$id.',
            "receiver_withdrawal_message": "'.$applicantWithdrawalMessage.'",
            "status": '.$status.'
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$receiverToken,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_PATCH,
			'/api/v1/shift_swap_requests',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is PRECONDITION FAILED 412
		$this->assertSame(Response::HTTP_PRECONDITION_FAILED, $response->getStatusCode());

		// make sure all created shiftSwapRequests are deleted from DB
		self::$em->clear();
		/** @var ShiftSwapRequest $toBeDeletedShiftSwapRequest */
		$toBeDeletedShiftSwapRequest = self::$shiftSwapRequestRepo->find($newShiftSwapRequest->getId());
		if ($toBeDeletedShiftSwapRequest) {
			self::$em->remove($toBeDeletedShiftSwapRequest);
		}
		self::$em->flush();
	}

	/**
	 * Test DOR_FEATURES_SHIFT_SWAP_REQUEST setting with get.
	 */
	public function test_DOR_FEATURES_SHIFT_SWAP_REQUEST_settings()
	{
		// First create one ShiftSwapRequest
		//The shiftSwapRequest properties to post
		$receiver = '{"id" : 19}';
		$assignment = '{"id" : 104077}';
		$applicantMessage = 'CREATED BY POST TEST';

		$contentString = '{
            "receiver": '.$receiver.',
            "assignment": '.$assignment.',
            "applicant_message": "'.$applicantMessage.'"
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/shift_swap_requests',
			array(),
			array(),
			$headers,
			$contentString
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
		$shiftSwapRequests = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($shiftSwapRequests) > 0);

		$newShiftSwapRequest = $shiftSwapRequests['data'];

		//Test that each shiftSwapRequests has at minimum the below values
		$this->assertArrayHasKey('id', $newShiftSwapRequest);
		$this->assertArrayHasKey('applicant', $newShiftSwapRequest);
		$this->assertArrayHasKey('receiver', $newShiftSwapRequest);
		$this->assertArrayHasKey('assignment', $newShiftSwapRequest);
		$this->assertArrayHasKey('status', $newShiftSwapRequest);
		$this->assertArrayHasKey('start_date', $newShiftSwapRequest);
		$this->assertArrayHasKey('end_date', $newShiftSwapRequest);

		// keep track of the ids of the created shiftSwapRequests in DB
		self::$createdShiftSwapRequests[] = $newShiftSwapRequest['id'];

		// manually set DOR_FEATURES_SHIFT_SWAP_REQUEST to off;
		/** @var SystemConfig $dorFeaturesShiftSwapRequest */
		$dorFeaturesShiftSwapRequest = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_FEATURES_SHIFT_SWAP_REQUEST);
		$dorFeaturesShiftSwapRequest->setValue(false);
		self::$em->flush();
		self::$em->refresh($dorFeaturesShiftSwapRequest);
		$currentDorFeaturesShiftSwapRequestState = $dorFeaturesShiftSwapRequest->getNormalizedValue();

		$dateFrom = '2019-01-01';
		$dateTo = '2019-01-30';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_GET,
			'/api/v1/shift_swap_requests?dateFrom='.$dateFrom.'&dateTo='.$dateTo,
			array(),
			array(),
			$headers
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is FORBIDDEN 403
		$this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());

		/**
		 * Test create.
		 */

		//The shiftSwapRequest properties to post
		$receiver = '{"id" : 19}';
		$assignment = '{"id" : 104077}';
		$applicantMessage = 'CREATED BY POST TEST';

		$contentString = '{
            "receiver": '.$receiver.',
            "assignment": '.$assignment.',
            "applicant_message": "'.$applicantMessage.'"
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/shift_swap_requests',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is FORBIDDEN 403
		$this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());

		/**
		 * Test PATCH.
		 */
		//The shiftSwapRequest properties to PUT
		$id = $newShiftSwapRequest['id'];
		$applicantMessage = 'CREATED BY POST TEST';
		$applicantWithdrawalMessage = 'WITHDRAWN BY TEST';
		$receiverMessage = 'RECEIVER MESSAGE BY TEST';
		$receiverWithdrawalMessage = 'RECEIVER WITHDRAW BY TEST';
		$status = 5;

		$contentString = '{
            "id": '.$id.',
            "applicant_withdrawal_message": "'.$applicantWithdrawalMessage.'",
            "status": '.$status.'
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_PATCH,
			'/api/v1/shift_swap_requests',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is FORBIDDEN 403
		$this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());

		// manually set DOR_FEATURES_SHIFT_SWAP_REQUEST to original state;
		if ($currentDorFeaturesShiftSwapRequestState !== self::$originalSystemConfigValues[SystemConfigKey::DOR_FEATURES_SHIFT_SWAP_REQUEST]) {
			/** @var SystemConfig $dorFeaturesShiftSwapRequest */
			$dorFeaturesShiftSwapRequest = self::$systemConfigRepo->findOneByKey(SystemConfigKey::DOR_FEATURES_SHIFT_SWAP_REQUEST);
			$dorFeaturesShiftSwapRequest->setValue(self::$originalSystemConfigValues[SystemConfigKey::DOR_FEATURES_SHIFT_SWAP_REQUEST]);
			self::$em->flush();
			self::$em->refresh($dorFeaturesShiftSwapRequest);
			$currentDorFeaturesShiftSwapRequestState = $dorFeaturesShiftSwapRequest->getNormalizedValue();
		}

		// make sure all created shiftSwapRequests are deleted from DB
		self::$em->clear();
		/** @var ShiftSwapRequest $toBeDeletedShiftSwapRequest */
		$toBeDeletedShiftSwapRequest = self::$shiftSwapRequestRepo->find($newShiftSwapRequest['id']);
		if ($toBeDeletedShiftSwapRequest) {
			self::$em->remove($toBeDeletedShiftSwapRequest);
		}
		self::$em->flush();
	}
}
