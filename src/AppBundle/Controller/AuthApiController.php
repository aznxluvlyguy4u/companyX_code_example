<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Client;
use AppBundle\Enumerator\HttpHeader;
use AppBundle\Repository\ClientRepository;
use AppBundle\Security\CompanyXSuperUserCookieEncoder;
use AppBundle\Util\Constants;
use AppBundle\Util\ResponseUtil;
use Doctrine\DBAL\ConnectionException;
use Jose\Factory\JWEFactory;
use Jose\Factory\JWKFactory;
use Jose\Loader;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTEncodedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationFailureResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\PreAuthenticationJWTUserToken;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWSProvider\JWSProviderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTManager;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\TokenExtractorInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Nelmio\ApiDocBundle\Annotation\Operation;
use Swagger\Annotations as SWG;
use Ramsey\Uuid\Uuid;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Serializer;

/**
 * Class AuthApiController.
 *
 * @Route("/api/v1/auth")
 */
class AuthApiController extends BaseApiController
{
	/**
	 * @Operation(
	 *     tags={"Auth"},
	 *     summary="Returns an access token if user is authenticated successfully, a session id can be optionally supplied",
	 *     @SWG\Parameter(
	 *            name="Authorization",
	 *            in="header",
	 *            required=true,
	 *            type="string",
	 *            default="Basic cGlldGVyOlhHdGhKVTEyMzQj",
	 *            description="Authorization"
	 *        ),
	 *     @SWG\Parameter(
	 *         name="body",
	 *         in="body",
	 *         required=true,
	 *         type="string",
	 *     	   @SWG\Schema(
	 *            	type="object",
	 *     			required={"company_name"},
	 *      		@SWG\Property(
	 *     				property="company_name",
	 *     				type="string",
	 *     				example="companydemo"
	 *	 			),
	 *              @SWG\Property(
	 *     				property="session_id",
	 *     				description="generated session ID by CompanyX",
	 *     				type="integer",
	 *     				example=88888
	 *	 			),
	 *              @SWG\Property(
	 *     				property="remember_me",
	 *					description="optional param to generate refresh-token",
	 *     				type="boolean",
	 *     				example=true
	 *	 			)
	 *         )
	 *     ),
	 *     @SWG\Response(
	 *         response="200",
	 *         description="OK"
	 *     ),
	 *     @SWG\Response(
	 *         response="401",
	 *         description="Unauthorized"
	 *     ),
	 *     @SWG\Response(
	 *         response="403",
	 *         description="Forbidden"
	 *     ),
	 *     @SWG\Response(
	 *         response="404",
	 *         description="Not Found"
	 *     ),
	 *     @SWG\Response(
	 *         response="500",
	 *         description="Internal Server Error"
	 *     )
	 * )
	 *
	 * @Route("")
	 * @Method("POST")
	 *
	 * @param Request                      $request
	 * @param EventDispatcherInterface     $dispatcher
	 * @param JWTEncoderInterface          $JWTEncoder
	 * @param AuthenticationSuccessHandler $jwtSuccessHandler
	 * @param JWTManager                   $JWTManager
	 *
	 * @return Response
	 *
	 * @throws \Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTEncodeFailureException
	 */
	public function authenticate(
		Request $request,
		EventDispatcherInterface $dispatcher,
		JWTEncoderInterface $JWTEncoder,
		AuthenticationSuccessHandler $jwtSuccessHandler,
		JWTManager $JWTManager
	) {
		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();

		try {
			$postData = $serializer->decode($request->getContent(), Constants::JSON_SERIALIZATON_FORMAT);
		} catch (UnexpectedValueException $exception) {
			return ResponseUtil::HTTP_UNAUTHORIZED();
		}

		if (!array_key_exists(Constants::QUERY_PARAM_COMPANY_NAME, $postData)) {
			return ResponseUtil::HTTP_UNAUTHORIZED();
		}

		$companyName = $postData[Constants::QUERY_PARAM_COMPANY_NAME];
		$sessionId = (array_key_exists(Constants::QUERY_PARAM_SESSION_ID,
			$postData)) ? $postData[Constants::QUERY_PARAM_SESSION_ID] : null;
		$rememberMe = (array_key_exists(Constants::QUERY_PARAM_REMEMBER_ME,
				$postData) && true === $postData[Constants::QUERY_PARAM_REMEMBER_ME]) ? true : false;
		$credentials = $request->headers->get(HttpHeader::AUTHORIZATION);

		if ($sessionId && !is_int($sessionId)) {
			return ResponseUtil::HTTP_UNAUTHORIZED();
		}

		if (!$credentials) {
			return ResponseUtil::HTTP_UNAUTHORIZED();
		}

		$credentials = str_replace('Basic ', '', $credentials);
		$credentials = base64_decode($credentials, true);
		$token = null;

		list($username, $password) = explode(':', $credentials, 2);

		if (!$username && !$password) {
			return ResponseUtil::HTTP_UNAUTHORIZED();
		}

		$encoder = $this->get('security.password_encoder');
		$username = strtolower($username);

		try {
			/** @var ClientRepository $clientRepository */
			$clientRepository = $this->getEntityManagerForClient($companyName)
				->getRepository(Client::class);
		} catch (ConnectionException $exception) {
			return ResponseUtil::HTTP_UNAUTHORIZED();
		}

		/** @var Client $client */
		$client = $clientRepository->findOneByUsername($username);

		if (!$client) {
			return ResponseUtil::HTTP_UNAUTHORIZED();
		}

		$client->setEntityManagerIdentifier($companyName);

		if (!$encoder->isPasswordValid($client, $password)) {
			return ResponseUtil::HTTP_UNAUTHORIZED();
		}

		// Manually generate JWT token
		$identityField = $JWTManager->getUserIdentityField();
		$companyField = Constants::QUERY_PARAM_COMPANY_NAME;
		$sessionIdField = Constants::QUERY_PARAM_SESSION_ID;

		// Encrypt payload using JOSE bundle
		// TODO Write the encryption logic to a seperate service
		$publicKey = JWKFactory::createFromKeyFile(
			$this->getParameter('jwt_public_key_path'),
			null,
			[
				'kid' => 'Jwt Public RSA key',
				'use' => 'enc',
				'alg' => 'RSA-OAEP-256',
			]
		);

		$payloadData = [
			$identityField => $client->getUsername(),
			$companyField => $companyName,
			$sessionIdField => $sessionId,
			'uuid' => Uuid::uuid4()->toString(),
		];

		$encryptedPayload = JWEFactory::createJWEToCompactJSON(
			$payloadData,                    // The message to encrypt
			$publicKey,                        // The key of the recipient
			[
				'alg' => 'RSA-OAEP-256',
				'enc' => 'A256CBC-HS512',
				'zip' => 'DEF',
			]
		);

		$payload = [Constants::PAYLOAD => $encryptedPayload];
		$jwtCreatedEvent = new JWTCreatedEvent($payload, $client);
		$dispatcher->dispatch(Events::JWT_CREATED, $jwtCreatedEvent);
		$token = $JWTEncoder->encode($jwtCreatedEvent->getData());
		$jwtEncodedEvent = new JWTEncodedEvent($token);
		$dispatcher->dispatch(Events::JWT_ENCODED, $jwtEncodedEvent);
		$data = array(Constants::TOKEN => $token);

		/*
		 * Only if remember me is on, dispatch authentication success event
		 * to allow refresh token bundle to attach refresh token to the response,
		 * it also persists the refresh token to the db table refresh_tokens
		 * in the background.
		 */
		if ($rememberMe) {
			$data = json_decode($jwtSuccessHandler
				->handleAuthenticationSuccess($client, $token)->getContent(), true);
		}

		return ResponseUtil::HTTP_OK($data);
	}

	/**
	 * TODO: set cookie.
	 *
	 * @Operation(
	 *     tags={"Auth"},
	 *     summary="Returns a Super User access token if user is authenticated successfully with a valid CompanyDeveloper cookie",
	 *     @SWG\Parameter(
	 *            name="Authorization",
	 *            in="header",
	 *            required=true,
	 *            type="string",
	 *            default="",
	 *            description="A valid CompanyDeveloper super user cookie"
	 *        ),*
	 *     @SWG\Parameter(
	 *         name="body",
	 *         in="body",
	 *         required=true,
	 *         type="string",
	 *     	   @SWG\Schema(
	 *            	type="object",
	 *     			required={"company_name"},
	 *      		@SWG\Property(
	 *     				property="company_name",
	 *     				type="string",
	 *     				example="switch"
	 *	 			),
	 *              @SWG\Property(
	 *     				property="session_id",
	 *     				description="generated session ID by CompanyX",
	 *     				type="integer",
	 *     				example="12345"
	 *	 			),
	 *              @SWG\Property(
	 *     				property="remote_addr",
	 *					description="remote client address",
	 *     				type="string",
	 *     				example="127.0.0.1"
	 *	 			)
	 *         )
	 *     ),
	 *     @SWG\Response(
	 *         response="200",
	 *         description="OK"
	 *     ),
	 *     @SWG\Response(
	 *         response="401",
	 *         description="Unauthorized"
	 *     ),
	 *     @SWG\Response(
	 *         response="403",
	 *         description="Forbidden"
	 *     ),
	 *     @SWG\Response(
	 *         response="404",
	 *         description="Not Found"
	 *     ),
	 *     @SWG\Response(
	 *         response="500",
	 *         description="Internal Server Error"
	 *     )
	 * )
	 *
	 * @Route("/super-user")
	 * @Method("POST")
	 *
	 * @param Request                  $request
	 * @param EventDispatcherInterface $dispatcher
	 * @param JWTEncoderInterface      $JWTEncoder
	 * @param JWTManager               $JWTManager
	 *
	 * @return Response
	 */
	public function authenticateSuperUser(
		Request $request,
		EventDispatcherInterface $dispatcher,
		JWTEncoderInterface $JWTEncoder,
		JWTManager $JWTManager
	) {
		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();

		try {
			$postData = $serializer->decode($request->getContent(), Constants::JSON_SERIALIZATON_FORMAT);
		} catch (UnexpectedValueException $exception) {
			return ResponseUtil::HTTP_UNAUTHORIZED();
		}

		$companyName = $postData[Constants::QUERY_PARAM_COMPANY_NAME];
		$sessionId = (array_key_exists(Constants::QUERY_PARAM_SESSION_ID, $postData))
			? $postData[Constants::QUERY_PARAM_SESSION_ID]
			: null;
		$superUserCookie = $request->cookies->get(Constants::COOKIE_Company_DEVELOPER);

		if ($sessionId && !is_int($sessionId)) {
			return ResponseUtil::HTTP_UNAUTHORIZED();
		}

		// if super user cookie string invalid
		if (!$superUserCookie) {
			return ResponseUtil::HTTP_UNAUTHORIZED();
		}

		$token = null;

		// CompanyX Super User Encryptor
		/** @var CompanyXSuperUserCookieEncoder $companyXSuperUserEncoder */
		$companyXSuperUserEncoder = $this->get(CompanyXSuperUserCookieEncoder::class);

		if (!$companyXSuperUserEncoder->isPasswordValid($superUserCookie)) {
			return ResponseUtil::HTTP_UNAUTHORIZED();
		}

		try {
			/** @var ClientRepository $clientRepository */
			$clientRepository = $this->getEntityManagerForClient($companyName)
				->getRepository(Client::class);
		} catch (ConnectionException $exception) {
			return ResponseUtil::HTTP_UNAUTHORIZED();
		}

		/** @var Client $superUserClient */
		$superUserClient = $clientRepository->find(1);

		if (!$superUserClient) {
			return ResponseUtil::HTTP_UNAUTHORIZED();
		}

		$superUserClient->setEntityManagerIdentifier($companyName);

		// Manually generate JWT token

		$identityField = $JWTManager->getUserIdentityField();
		$companyField = Constants::QUERY_PARAM_COMPANY_NAME;
		$sessionIdField = Constants::QUERY_PARAM_SESSION_ID;

		// Encrypt payload using JOSE bundle
		// TODO Write the encryption logic to a seperate service
		$publicKey = JWKFactory::createFromKeyFile(
			$this->getParameter('jwt_public_key_path'),
			null,
			[
				'kid' => 'Jwt Public RSA key',
				'use' => 'enc',
				'alg' => 'RSA-OAEP-256',
			]
		);

		$payloadData = [
			$identityField => $superUserClient->getUsername(),
			$companyField => $companyName,
			$sessionIdField => $sessionId,
			'uuid' => Uuid::uuid4()->toString(),
		];

		$encryptedPayload = JWEFactory::createJWEToCompactJSON(
			$payloadData,                    // The message to encrypt
			$publicKey,                        // The key of the recipient
			[
				'alg' => 'RSA-OAEP-256',
				'enc' => 'A256CBC-HS512',
				'zip' => 'DEF',
			]
		);

		$payload = [Constants::PAYLOAD => $encryptedPayload];
		$jwtCreatedEvent = new JWTCreatedEvent($payload, $superUserClient);
		$dispatcher->dispatch(Events::JWT_CREATED, $jwtCreatedEvent);
		$token = $JWTEncoder->encode($jwtCreatedEvent->getData());
		$jwtEncodedEvent = new JWTEncodedEvent($token);
		$dispatcher->dispatch(Events::JWT_ENCODED, $jwtEncodedEvent);
		$data = array(Constants::TOKEN => $token);

		return ResponseUtil::HTTP_OK($data);
	}

	/**
	 * TODO: set cookie.
	 *
	 * @Operation(
	 *     tags={"Auth"},
	 *     summary="Returns an access token of a different Client if user is authenticated successfully with a valid CompanyDeveloper cookie",
	 *     @SWG\Parameter(
	 *            name="Authorization",
	 *            in="header",
	 *            required=true,
	 *            type="string",
	 *            default="",
	 *            description="A valid CompanyDeveloper super user cookie"
	 *        ),
	 *     @SWG\Parameter(
	 *         name="body",
	 *         in="body",
	 *         required=true,
	 *         type="string",
	 *     	   @SWG\Schema(
	 *            	type="object",
	 *     			required={"company_name", "username"},
	 *      		@SWG\Property(
	 *     				property="company_name",
	 *     				type="string",
	 *     				example="switch"
	 *	 			),
	 *      		@SWG\Property(
	 *     				property="username",
	 *     				description="username of the user to switch to",
	 *     				type="string",
	 *     				example="pietje"
	 *	 			),
	 *              @SWG\Property(
	 *     				property="session_id",
	 *     				description="generated session ID by CompanyX",
	 *     				type="integer",
	 *     				example=""
	 *	 			),
	 *              @SWG\Property(
	 *     				property="remote_addr",
	 *					description="remote client address",
	 *     				type="string",
	 *     				example="127.0.0.1"
	 *	 			)
	 *         )
	 *     ),
	 *     @SWG\Response(
	 *         response="200",
	 *         description="OK"
	 *     ),
	 *     @SWG\Response(
	 *         response="401",
	 *         description="Unauthorized"
	 *     ),
	 *     @SWG\Response(
	 *         response="403",
	 *         description="Forbidden"
	 *     ),
	 *     @SWG\Response(
	 *         response="404",
	 *         description="Not Found"
	 *     ),
	 *     @SWG\Response(
	 *         response="500",
	 *         description="Internal Server Error"
	 *     )
	 * )
	 *
	 * @Route("/switch-user")
	 * @Method("POST")
	 *
	 * @param Request                  $request
	 * @param EventDispatcherInterface $dispatcher
	 * @param JWTEncoderInterface      $JWTEncoder
	 * @param JWTManager               $JWTManager
	 *
	 * @return Response
	 */
	public function switchAuthenticatedUser(
		Request $request,
		EventDispatcherInterface $dispatcher,
		JWTEncoderInterface $JWTEncoder,
		JWTManager $JWTManager
	) {
		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();

		try {
			$postData = $serializer->decode($request->getContent(), Constants::JSON_SERIALIZATON_FORMAT);
		} catch (UnexpectedValueException $exception) {
			return ResponseUtil::HTTP_UNAUTHORIZED();
		}

		$companyName = $postData[Constants::QUERY_PARAM_COMPANY_NAME];
		$username = $postData[Constants::QUERY_PARAM_USERNAME];
		$sessionId = (array_key_exists(Constants::QUERY_PARAM_SESSION_ID, $postData))
			? $postData[Constants::QUERY_PARAM_SESSION_ID]
			: null;
		$superUserCookie = $request->cookies->get(Constants::COOKIE_Company_DEVELOPER);

		if (!$username || ($username && !is_string($username))) {
			return ResponseUtil::HTTP_UNAUTHORIZED();
		}

		if ($sessionId && !is_int($sessionId)) {
			return ResponseUtil::HTTP_UNAUTHORIZED();
		}

		// if super user cookie string invalid
		if (!$superUserCookie) {
			return ResponseUtil::HTTP_UNAUTHORIZED();
		}

		$token = null;

		// CompanyX Super User Encryptor
		/** @var CompanyXSuperUserCookieEncoder $companyXSuperUserEncoder */
		$companyXSuperUserEncoder = $this->get('AppBundle\Security\CompanyXSuperUserCookieEncoder');

		if (!$companyXSuperUserEncoder->isPasswordValid($superUserCookie)) {
			return ResponseUtil::HTTP_UNAUTHORIZED();
		}

		try {
			/** @var ClientRepository $clientRepository */
			$clientRepository = $this->getEntityManagerForClient($companyName)
				->getRepository(Client::class);
		} catch (ConnectionException $exception) {
			return ResponseUtil::HTTP_UNAUTHORIZED();
		}

		/** @var Client $switchedClient */
		$switchedClient = $clientRepository->findOneByUsername($username);

		if (!$switchedClient) {
			return ResponseUtil::HTTP_UNAUTHORIZED();
		}

		$switchedClient->setEntityManagerIdentifier($companyName);

		// Manually generate JWT token
		$identityField = $JWTManager->getUserIdentityField();
		$companyField = Constants::QUERY_PARAM_COMPANY_NAME;
		$sessionIdField = Constants::QUERY_PARAM_SESSION_ID;

		// Encrypt payload using JOSE bundle
		// TODO Write the encryption logic to a seperate service
		$publicKey = JWKFactory::createFromKeyFile(
			$this->getParameter('jwt_public_key_path'),
			null,
			[
				'kid' => 'Jwt Public RSA key',
				'use' => 'enc',
				'alg' => 'RSA-OAEP-256',
			]
		);

		$payloadData = [
			$identityField => $switchedClient->getUsername(),
			$companyField => $companyName,
			$sessionIdField => $sessionId,
			'uuid' => Uuid::uuid4()->toString(),
		];

		$encryptedPayload = JWEFactory::createJWEToCompactJSON(
			$payloadData,                    // The message to encrypt
			$publicKey,                        // The key of the recipient
			[
				'alg' => 'RSA-OAEP-256',
				'enc' => 'A256CBC-HS512',
				'zip' => 'DEF',
			]
		);

		$payload = [Constants::PAYLOAD => $encryptedPayload];
		$jwtCreatedEvent = new JWTCreatedEvent($payload, $switchedClient);
		$dispatcher->dispatch(Events::JWT_CREATED, $jwtCreatedEvent);
		$token = $JWTEncoder->encode($jwtCreatedEvent->getData());
		$jwtEncodedEvent = new JWTEncodedEvent($token);
		$dispatcher->dispatch(Events::JWT_ENCODED, $jwtEncodedEvent);
		$data = array(Constants::TOKEN => $token);

		return ResponseUtil::HTTP_OK($data);
	}

	/**
	 * TODO: set cookie.
	 *
	 * @Operation(
	 *     tags={"Auth"},
	 *     summary="Check if a valid Database connection can be set up for company_name. Requires valid CompanyDeveloper cookie",
	 *
	 *     @SWG\Parameter(
	 *            name="Authorization",
	 *            in="header",
	 *            required=true,
	 *            type="string",
	 *            default="",
	 *            description="A valid CompanyDeveloper super user cookie"
	 *        ),*
	 *     @SWG\Parameter(
	 *         name="body",
	 *         in="body",
	 *         required=true,
	 *         type="string",
	 *     	   @SWG\Schema(
	 *            	type="object",
	 *     			required={"company_name"},
	 *      		@SWG\Property(
	 *     				property="company_name",
	 *     				type="string",
	 *     				example="switch"
	 *	 			),
	 *              @SWG\Property(
	 *     				property="remote_addr",
	 *					description="remote client address",
	 *     				type="string",
	 *     				example="127.0.0.1"
	 *	 			)
	 *         )
	 *     ),
	 *     @SWG\Response(
	 *         response="200",
	 *         description="OK"
	 *     ),
	 *     @SWG\Response(
	 *         response="401",
	 *         description="Unauthorized"
	 *     ),
	 *     @SWG\Response(
	 *         response="404",
	 *         description="Not Found"
	 *     ),
	 *     @SWG\Response(
	 *         response="500",
	 *         description="Internal Server Error"
	 *     )
	 * )
	 *
	 * @Route("/check-customer")
	 * @Method("POST")
	 *
	 * @param Request $request
	 *
	 * @return Response
	 */
	public function checkCustomer(Request $request)
	{
		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();

		$superUserCookie = $request->cookies->get(Constants::COOKIE_Company_DEVELOPER);

		// if super user cookie string invalid
		if (!$superUserCookie) {
			return ResponseUtil::HTTP_UNAUTHORIZED();
		}

		// CompanyX Super User Encryptor
		/** @var CompanyXSuperUserCookieEncoder $companyXSuperUserEncoder */
		$companyXSuperUserEncoder = $this->get('AppBundle\Security\CompanyXSuperUserCookieEncoder');

		if (!$companyXSuperUserEncoder->isPasswordValid($superUserCookie)) {
			return ResponseUtil::HTTP_UNAUTHORIZED();
		}

		try {
			$postData = $serializer->decode($request->getContent(), Constants::JSON_SERIALIZATON_FORMAT);
		} catch (UnexpectedValueException $exception) {
			return ResponseUtil::HTTP_UNAUTHORIZED();
		}

		$companyName = $postData[Constants::QUERY_PARAM_COMPANY_NAME];

		try {
			$em = $this->getEntityManagerForClient($companyName);
		} catch (\Exception $e) {
			return ResponseUtil::HTTP_NOT_FOUND('Not Found');
		}

		return ResponseUtil::HTTP_OK(['message' => 'OK']);
	}

	/**
	 * @Operation(
	 *     tags={"Auth"},
	 *     summary="Return token validation status",
	 *     @SWG\Parameter(
	 *            name="Authorization",
	 *            in="header",
	 *            required=true,
	 *            type="string",
	 *            default="Bearer {jwt}",
	 *            description="Authorization"
	 *        ),
	 *     @SWG\Response(
	 *         response="200",
	 *         description="OK"
	 *     ),
	 *     @SWG\Response(
	 *         response="401",
	 *         description="Unauthorized"
	 *     ),
	 *     @SWG\Response(
	 *         response="403",
	 *         description="Forbidden"
	 *     ),
	 *     @SWG\Response(
	 *         response="500",
	 *         description="Internal Server Error"
	 *     )
	 * )
	 *
	 * @Route("/validate-token")
	 * @Method("GET")
	 *
	 * @return Response
	 */
	public function validateToken()
	{
		return ResponseUtil::HTTP_OK(['message' => 'valid']);
	}

	/**
	 * @Operation(
	 *     tags={"Auth"},
	 *     summary="Return new access token by sending a valid refresh token",
	 *     @SWG\Parameter(
	 *         name="body",
	 *         in="body",
	 *         required=true,
	 *         type="string",
	 *     	   @SWG\Schema(
	 *            	type="object",
	 *     			required={"refresh_token"},
	 *              @SWG\Property(
	 *     				property="refresh_token",
	 *					description="A valid non expired refresh token",
	 *     				type="string",
	 *     				example=""
	 *	 			)
	 *         )
	 *     ),
	 *     @SWG\Response(
	 *         response="200",
	 *         description="OK"
	 *     ),
	 *     @SWG\Response(
	 *         response="401",
	 *         description="Unauthorized"
	 *     ),
	 *     @SWG\Response(
	 *         response="500",
	 *         description="Internal Server Error"
	 *     )
	 * )
	 *
	 * @Route("/refresh-token")
	 * @Method("POST")
	 *
	 * @param Request                 $request
	 * @param TokenExtractorInterface $tokenExtractor
	 * @param JWSProviderInterface    $JWSProvider
	 * @param JWTEncoderInterface     $JWTEncoder
	 * @param JWTManager              $JWTManager
	 *
	 * @return Response
	 *
	 * @throws \Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTEncodeFailureException
	 */
	public function refreshToken(
		Request $request,
		TokenExtractorInterface $tokenExtractor,
		JWSProviderInterface $JWSProvider,
		JWTEncoderInterface $JWTEncoder,
		JWTManager $JWTManager
	) {
		if (false === ($jsonWebToken = $tokenExtractor->extract($request))) {
			return ResponseUtil::HTTP_UNAUTHORIZED();
		}

		/** @var PreAuthenticationJWTUserToken $preAuthToken */
		$preAuthToken = new PreAuthenticationJWTUserToken($jsonWebToken);

		try {
			/** @var JWTManager $JWTManager */
			if (!$payload = $JWTManager->decode($preAuthToken)) {
				return ResponseUtil::HTTP_UNAUTHORIZED();
			}
		} catch (JWTDecodeFailureException $e) {
			if (JWTDecodeFailureException::EXPIRED_TOKEN !== $e->getReason()) {
				return ResponseUtil::HTTP_UNAUTHORIZED();
			}
			// Store raw payload before authenticating token
			$rawPayload = $JWSProvider->load($jsonWebToken)->getPayload();
			// Check if provided refresh token exist
			//TODO: inject in method for SF4 when bundle autowires
			$refreshTokenService = $this->container->get('gesdinet.jwtrefreshtoken');
			$checkResponse = $refreshTokenService->refresh($request);
			if ($checkResponse instanceof JWTAuthenticationFailureResponse) {
				return ResponseUtil::HTTP_UNAUTHORIZED();
			}

			$data = json_decode($checkResponse->getContent(), true);

			// Decrypt current token payload
			// TODO Write decrypt logic to seperate service along with encrypt
			$encryptedPayload = $rawPayload[Constants::PAYLOAD];

			$privateKey = JWKFactory::createFromKeyFile(
				$this->getParameter('jwt_private_key_path'),
				$this->getParameter('jwt_key_pass_phrase'),
				[
					'kid' => 'My Private RSA key',
					'use' => 'enc',
					'alg' => 'RSA-OAEP-256',
				]
			);

			$loader = new Loader();

			$decryptedPayload = $loader->loadAndDecryptUsingKey(
				$encryptedPayload,
				$privateKey,
				['RSA-OAEP-256'],      // A list of allowed key encryption algorithms
				['A256CBC-HS512'],
				$recipient_index
			);

			/** @var array $payloadData */
			$payloadData = $decryptedPayload->getPayload();

			/** @var string $identityField */
			$identityField = $JWTManager->getUserIdentityField();

			if (!isset($payloadData[$identityField])) {
				return ResponseUtil::HTTP_UNAUTHORIZED();
			}

			$username = $payloadData[$identityField];
			$companyname = $payloadData[Constants::QUERY_PARAM_COMPANY_NAME];
			$sessionId = (array_key_exists(Constants::QUERY_PARAM_SESSION_ID,
				$payloadData)) ? $payloadData[Constants::QUERY_PARAM_SESSION_ID] : null;
			$companyField = Constants::QUERY_PARAM_COMPANY_NAME;
			$sessionIdField = Constants::QUERY_PARAM_SESSION_ID;

			// Manually generate new access token and replace check response token key

			// Encrypt payload using JOSE bundle
			// TODO Write the encryption logic to a seperate service
			$publicKey = JWKFactory::createFromKeyFile(
				$this->getParameter('jwt_public_key_path'),
				null,
				[
					'kid' => 'Jwt Public RSA key',
					'use' => 'enc',
					'alg' => 'RSA-OAEP-256',
				]
			);

			$newPayloadData = [
				$identityField => $username,
				$companyField => $companyname,
				$sessionIdField => $sessionId,
				'uuid' => Uuid::uuid4()->toString(),
			];

			$encryptedPayload = JWEFactory::createJWEToCompactJSON(
				$newPayloadData,                    // The message to encrypt
				$publicKey,                        // The key of the recipient
				[
					'alg' => 'RSA-OAEP-256',
					'enc' => 'A256CBC-HS512',
					'zip' => 'DEF',
				]
			);

			try {
				/** @var ClientRepository $clientRepository */
				$clientRepository = $this->getEntityManagerForClient($companyname)
					->getRepository(Client::class);
			} catch (ConnectionException $exception) {
				return ResponseUtil::HTTP_UNAUTHORIZED();
			}

			/** @var Client $client */
			$client = $clientRepository->findOneByUsername($username);
			$client->setEntityManagerIdentifier($companyname);

			if (!$client) {
				return ResponseUtil::HTTP_UNAUTHORIZED();
			}

			$payload = [Constants::PAYLOAD => $encryptedPayload];
			$token = $JWTEncoder->encode($payload);

			$data[Constants::TOKEN] = $token;

			return ResponseUtil::HTTP_OK($data);
		}

		return ResponseUtil::HTTP_UNAUTHORIZED();
	}
}
