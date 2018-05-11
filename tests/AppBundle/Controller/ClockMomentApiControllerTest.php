<?php

namespace AppBundle\Tests\Controller;

use AppBundle\Entity\ClockMoment;
use AppBundle\Entity\ClockMomentLog;
use AppBundle\Enumerator\HttpHeader;
use AppBundle\Repository\ClockMomentLogRepository;
use AppBundle\Repository\ClockMomentRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use AppBundle\Util\Constants;

/**
 * Class ClockMomentApiControllerTest.
 */
class ClockMomentApiControllerTest extends WebTestCase
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

	/**
	 * @var ClockMomentRepository
	 */
	private static $clockMomentRepo;

	/**
	 * @var ClockMomentLogRepository
	 */
	private static $clockMomentLogRepo;

	/** @var array */
	private static $createdClockMoments = [];

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

		self::$clockMomentRepo = self::$em->getRepository(ClockMoment::class);
		self::$clockMomentLogRepo = self::$em->getRepository(ClockMomentLog::class);
	}

	public static function tearDownAfterClass()
	{
		self::$em->clear();

		// make sure all created clockMoments and logs are deleted from DB
		foreach (self::$createdClockMoments as $createdClockMoment) {
			/** @var ClockMoment $clockMoment */
			$clockMoment = self::$clockMomentRepo->find($createdClockMoment);
			if ($clockMoment) {
				self::$em->remove($clockMoment);
				self::$em->flush();
			}

			$clockMomentLogs = self::$clockMomentLogRepo->findByPrimaryKey($createdClockMoment);
			foreach ($clockMomentLogs as $clockMomentLog) {
				self::$em->remove($clockMomentLog);
				self::$em->flush();
			}
		}

		self::$em->close();
		self::$em = null; // avoid memory leaks
	}

	/**
	 * test create clockMoment success.
	 */
	public function testCreateClockMomentRequestSuccess()
	{
		//The clockMoment properties to post
		$timeStamp = '2017-11-16T10:00:00+02:00';
		$remark = 'CREATED BY TEST';
		$status = 0;
		$employee = '{"id" : 52}';
		$department = '{"id" : 56}';

		$contentString = '{
            "time_stamp": "'.$timeStamp.'",
            "remark": "'.$remark.'",
            "status": '.$status.',
            "employee": '.$employee.',
            "department": '.$department.'
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->enableProfiler();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/clock_moments',
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
		$clockMoments = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($clockMoments) > 0);

		$clockMoment = $clockMoments['data'];

		//Test that each clockMoment has at minimum the below values
		$this->assertArrayHasKey('id', $clockMoment);
		$this->assertArrayHasKey('time_stamp', $clockMoment);
		$this->assertArrayHasKey('remark', $clockMoment);
		$this->assertArrayHasKey('status', $clockMoment);
		$this->assertArrayHasKey('active', $clockMoment);
		$this->assertArrayHasKey('modified_by', $clockMoment);
		$this->assertArrayHasKey('employee', $clockMoment);
		$this->assertArrayHasKey('department', $clockMoment);

		// keep track of the ids of the created clockMoments in DB
		self::$createdClockMoments[] = $clockMoment['id'];

		// test if clockMoments are indeed inserted in DB
		foreach (self::$createdClockMoments as $createdClockMoment) {
			/** @var ClockMoment $clockMoment */
			$clockMoment = self::$clockMomentRepo->find($createdClockMoment);
			$this->assertNotNull($clockMoment);
			$this->assertEquals($createdClockMoment, $clockMoment->getId());
			$this->assertEquals(new \DateTime($timeStamp), $clockMoment->getTimeStamp());
			$this->assertEquals($remark, $clockMoment->getRemark());
			$this->assertEquals($status, $clockMoment->getStatus());
			$this->assertEquals(52, $clockMoment->getEmployee()->getId());
			$this->assertEquals(56, $clockMoment->getDepartment()->getId());
			$this->assertEquals(4, $clockMoment->getModifiedBy()->getId());
		}

		// Test if ClockMomentLogs are created
		self::$em->clear();
		foreach (self::$createdClockMoments as $createdClockMoment) {
			/** @var ClockMoment $clockMoment */
			$clockMoment = self::$clockMomentRepo->find($createdClockMoment);
			/** @var ClockMomentLog $clockMomentLog */
			$clockMomentLog = self::$clockMomentLogRepo->findByPrimaryKey($clockMoment->getId())[0];
			$this->assertNotNull($clockMomentLog);
			$this->assertEquals($clockMomentLog->getPrimaryKey(), $clockMoment->getId());
			$this->assertEquals($clockMomentLog->getChangedField(), 'created');
			$this->assertEquals($clockMomentLog->getDate()->format(Constants::DateFormatString), $clockMoment->getCreated()->format(Constants::DateFormatString));
			$this->assertEquals($clockMomentLog->getTime()->format(Constants::HOURS_MINUTES_FORMAT_STRING), $clockMoment->getCreated()->format(Constants::HOURS_MINUTES_FORMAT_STRING));
			$this->assertEquals($clockMomentLog->getNewValue(), $clockMoment->getCreated()->format(Constants::DATE_TIME_FORMAT_STRING));
			$this->assertEquals($clockMomentLog->getSessionId(), 888888);

			// Delete Logs
			self::$em->remove($clockMomentLog);
			self::$em->flush();
		}

		// make sure all created clockMoments are deleted from DB
		self::$em->clear();
		/** @var ClockMoment $clockMoment */
		$clockMoment = self::$clockMomentRepo->find($clockMoment->getId());
		if ($clockMoment) {
			self::$em->remove($clockMoment);
		}
		self::$em->flush();
	}

	/**
	 * test create clockMoment fail Missing Required fields.
	 */
	public function testFailCreateClockMomentRequestMissingRequired()
	{
		/**
		 * MISSING EMPLOYEE.
		 */

		//The clockMoment properties to post
		$timeStamp = '2017-11-16T10:00:00+02:00';
		$remark = 'CREATED BY TEST';
		$status = 0;
		$employee = '{"id" : 52}';
		$department = '{"id" : 56}';

		$contentString = '{
            "time_stamp": "'.$timeStamp.'",
            "remark": "'.$remark.'",
            "status": '.$status.',
            "department": '.$department.'
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/clock_moments',
			array(),
			array(),
			$headers,
			$contentString
		);

		/** @var Response $response */
		$response = $client->getResponse();

		// Test if response is PRECONDITION FAILED 412
		$this->assertSame(Response::HTTP_PRECONDITION_FAILED, $response->getStatusCode(), 'Failed testing missing fields: '.$response->getContent());

		/**
		 * MISSING DEPARTMENT.
		 */
		$contentString = '{
            "time_stamp": "'.$timeStamp.'",
            "remark": "'.$remark.'",
            "status": '.$status.',
            "employee": '.$employee.'
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/clock_moments',
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
		 * MISSING TIMESTAMP.
		 */
		$contentString = '{
            "remark": "'.$remark.'",
            "status": '.$status.',
            "employee": '.$employee.',
            "department": '.$department.'
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/clock_moments',
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
		 * MISSING STATUS.
		 */
		$contentString = '{
            "time_stamp": "'.$timeStamp.'",
            "remark": "'.$remark.'",
            "employee": '.$employee.',
            "department": '.$department.'
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->enableProfiler();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/clock_moments',
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
	 * test create clockMoment fail Invalid fields.
	 */
	public function testFailCreateClockMomentRequestInvalidFields()
	{
		/**
		 * INVALID STATUS.
		 */

		//The clockMoment properties to post
		$timeStamp = '2017-11-16T10:00:00+02:00';
		$remark = 'CREATED BY TEST';
		$status = 2;
		$employee = '{"id" : 52}';
		$department = '{"id" : 56}';

		$contentString = '{
            "time_stamp": "'.$timeStamp.'",
            "remark": "'.$remark.'",
            "status": '.$status.',
            "employee": '.$employee.',
            "department": '.$department.'
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->enableProfiler();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/clock_moments',
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
		 * INVALID EMPLOYEE.
		 */

		//The clockMoment properties to post
		$timeStamp = '2017-11-16T10:00:00+02:00';
		$remark = 'CREATED BY TEST';
		$status = 0;
		$employee = '{"id" : 99999}';
		$department = '{"id" : 56}';

		$contentString = '{
            "time_stamp": "'.$timeStamp.'",
            "remark": "'.$remark.'",
            "status": '.$status.',
            "employee": '.$employee.',
            "department": '.$department.'
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->enableProfiler();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/clock_moments',
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
		 * INVALID DEPARTMENT.
		 */

		//The clockMoment properties to post
		$timeStamp = '2017-11-16T10:00:00+02:00';
		$remark = 'CREATED BY TEST';
		$status = 0;
		$employee = '{"id" : 55}';
		$department = '{"id" : 99999}';

		$contentString = '{
            "time_stamp": "'.$timeStamp.'",
            "remark": "'.$remark.'",
            "status": '.$status.',
            "employee": '.$employee.',
            "department": '.$department.'
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->enableProfiler();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/clock_moments',
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
	 * Test success edit ClockMoments.
	 */
	public function testEditClockMomentsSuccess()
	{
		// First create one ClockMoment
		//The clockMoment properties to post
		$timeStamp = '2017-11-16T10:00:00+02:00';
		$remark = 'CREATED BY TEST';
		$status = 0;
		$employee = '{"id" : 52}';
		$department = '{"id" : 56}';

		$contentString = '{
            "time_stamp": "'.$timeStamp.'",
            "remark": "'.$remark.'",
            "status": '.$status.',
            "employee": '.$employee.',
            "department": '.$department.'
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->enableProfiler();
		$client->request(
			Request::METHOD_POST,
			'/api/v1/clock_moments',
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
		$clockMoments = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($clockMoments) > 0);

		$newClockMoment = $clockMoments['data'];

		//Test that each clockMoment has at minimum the below values
		$this->assertArrayHasKey('id', $newClockMoment);
		$this->assertArrayHasKey('time_stamp', $newClockMoment);
		$this->assertArrayHasKey('remark', $newClockMoment);
		$this->assertArrayHasKey('status', $newClockMoment);
		$this->assertArrayHasKey('active', $newClockMoment);
		$this->assertArrayHasKey('modified_by', $newClockMoment);
		$this->assertArrayHasKey('employee', $newClockMoment);
		$this->assertArrayHasKey('department', $newClockMoment);

		// keep track of the ids of the created clockMoments in DB
		self::$createdClockMoments[] = $newClockMoment['id'];

		// test if clockMoments are indeed inserted in DB
		/** @var ClockMoment $newClockMoment */
		$newClockMoment = self::$clockMomentRepo->find($newClockMoment['id']);
		$this->assertNotNull($newClockMoment);
		$this->assertEquals(new \DateTime($timeStamp), $newClockMoment->getTimeStamp());
		$this->assertEquals($remark, $newClockMoment->getRemark());
		$this->assertEquals($status, $newClockMoment->getStatus());
		$this->assertEquals(true, $newClockMoment->getActive());
		$this->assertEquals(52, $newClockMoment->getEmployee()->getId());
		$this->assertEquals(56, $newClockMoment->getDepartment()->getId());
		$this->assertEquals(4, $newClockMoment->getModifiedBy()->getId());

		// Test if ClockMomentLogs are created
		self::$em->clear();
		/** @var ClockMomentLog $clockMomentLog */
		$clockMomentLog = self::$clockMomentLogRepo->findByPrimaryKey($newClockMoment->getId())[0];
		$this->assertNotNull($clockMomentLog);
		$this->assertEquals($clockMomentLog->getPrimaryKey(), $newClockMoment->getId());
		$this->assertEquals($clockMomentLog->getChangedField(), 'created');
		$this->assertEquals($clockMomentLog->getDate()->format(Constants::DateFormatString), $newClockMoment->getCreated()->format(Constants::DateFormatString));
		$this->assertEquals($clockMomentLog->getTime()->format(Constants::HOURS_MINUTES_FORMAT_STRING), $newClockMoment->getCreated()->format(Constants::HOURS_MINUTES_FORMAT_STRING));
		$this->assertEquals($clockMomentLog->getNewValue(), $newClockMoment->getCreated()->format(Constants::DATE_TIME_FORMAT_STRING));
		$this->assertEquals($clockMomentLog->getSessionId(), 888888);

		// Delete Logs
		self::$em->remove($clockMomentLog);
		self::$em->flush();
		self::$em->clear();

		/**
		 * TEST PATCH STATUS FROM 0 to 1 AND REMARK AND ACTIVE.
		 */

		//The clockMoment properties to PATCH
		$id = $newClockMoment->getId();
		$remark = 'EDITED BY TEST';
		$active = 'false';
		$status = 1;

		$contentString = '{
            "id": '.$id.',
            "remark": "'.$remark.'",
            "status": '.$status.',
            "active": '.$active.'
        }';

		$headers = array(
			'HTTP_AUTHORIZATION' => HttpHeader::BEARER.' '.self::$token,
		);

		// HTTP client
		$client = static::createClient();
		$client->enableProfiler();
		$client->request(
			Request::METHOD_PATCH,
			'/api/v1/clock_moments',
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
		$clockMoments = json_decode($response->getContent(), true);

		//Test that the association array is not empty
		$this->assertTrue(sizeof($clockMoments) > 0);

		$editedClockMoment = $clockMoments['data'];

		//Test that each editedClockMoment has at minimum the below values
		$this->assertArrayHasKey('id', $editedClockMoment);
		$this->assertArrayHasKey('time_stamp', $editedClockMoment);
		$this->assertArrayHasKey('remark', $editedClockMoment);
		$this->assertArrayHasKey('status', $editedClockMoment);
		$this->assertArrayHasKey('active', $editedClockMoment);
		$this->assertArrayHasKey('modified_by', $editedClockMoment);
		$this->assertArrayHasKey('employee', $editedClockMoment);
		$this->assertArrayHasKey('department', $editedClockMoment);

		// test if clockMoments are indeed inserted in DB
		self::$em->clear();
		/** @var ClockMoment $editedClockMoment */
		$editedClockMoment = self::$clockMomentRepo->find($editedClockMoment['id']);
		$this->assertNotNull($editedClockMoment);
		$this->assertEquals($id, $editedClockMoment->getId());
		$this->assertEquals(new \DateTime($timeStamp), $editedClockMoment->getTimeStamp());
		$this->assertEquals($remark, $editedClockMoment->getRemark());
		$this->assertEquals($status, $editedClockMoment->getStatus());
		$this->assertEquals(false, $editedClockMoment->getActive());
		$this->assertEquals(52, $editedClockMoment->getEmployee()->getId());
		$this->assertEquals(56, $editedClockMoment->getDepartment()->getId());
		$this->assertEquals(4, $editedClockMoment->getModifiedBy()->getId());

		// Test if ClockMomentLog are created during update
		$clockMomentLogs = self::$clockMomentLogRepo->findByPrimaryKey($editedClockMoment);
		$ClockMomentClassMetaData = self::$em->getClassMetadata(ClockMoment::class);

		$this->assertCount(3, $clockMomentLogs);

		foreach ($clockMomentLogs as $clockMomentLog) {
			/** @var ClockMomentLog $clockMomentLog */
			if ($clockMomentLog->getChangedField() == $ClockMomentClassMetaData->getColumnName('remark')) {
				$this->assertEquals($clockMomentLog->getOldValue(), $newClockMoment->getRemark());
				$this->assertEquals($clockMomentLog->getNewValue(), $editedClockMoment->getRemark());
			}

			if ($clockMomentLog->getChangedField() == $ClockMomentClassMetaData->getColumnName('status')) {
				$this->assertEquals($clockMomentLog->getOldValue(), $newClockMoment->getStatus());
				$this->assertEquals($clockMomentLog->getNewValue(), $editedClockMoment->getStatus());
			}

			if ($clockMomentLog->getChangedField() == $ClockMomentClassMetaData->getColumnName('active')) {
				$this->assertEquals($clockMomentLog->getOldValue(), (int) $newClockMoment->getActive());
				$this->assertEquals($clockMomentLog->getNewValue(), (int) $editedClockMoment->getActive());
			}
		}

		// Delete logs
		foreach ($clockMomentLogs as $clockMomentLog) {
			self::$em->remove($clockMomentLog);
			self::$em->flush();
		}

		// make sure all created clockMoments are deleted from DB
		self::$em->clear();
		/** @var ClockMoment $clockMoment */
		$newClockMoment = self::$clockMomentRepo->find($newClockMoment->getId());
		if ($newClockMoment) {
			self::$em->remove($newClockMoment);
		}
		self::$em->flush();
	}
}
