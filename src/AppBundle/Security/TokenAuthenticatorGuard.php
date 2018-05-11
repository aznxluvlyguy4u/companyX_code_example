<?php

namespace AppBundle\Security;

use AppBundle\Entity\Client;
use AppBundle\Enumerator\JsonResponseMessage;
use AppBundle\Repository\ClientRepository;
use AppBundle\Service\EntityManagerMapperService;
use Carpediem\JSend\JSend;
use Doctrine\ORM\EntityManagerInterface;
use Jose\Factory\JWKFactory;
use Jose\Loader;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTAuthenticatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\ExpiredTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidPayloadException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\UserNotFoundException;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationFailureResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\JWTUserToken;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\PreAuthenticationJWTUserToken;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\TokenExtractorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\GuardAuthenticatorInterface;
use AppBundle\Util\Constants;

/**
 * Class TokenAuthenticatorGuard.
 */
class TokenAuthenticatorGuard implements GuardAuthenticatorInterface
{
	/** @var EntityManagerMapperService */
	private $mapperService;

	/** @var EntityManagerInterface */
	private $entityManager;

	/**
	 * @var JWTTokenManagerInterface
	 */
	private $jwtManager;

	/**
	 * @var EventDispatcherInterface
	 */
	private $dispatcher;

	/**
	 * @var TokenExtractorInterface
	 */
	private $tokenExtractor;

	/**
	 * @var TokenStorageInterface
	 */
	private $preAuthenticationTokenStorage;

	/**
	 * @var ContainerInterface
	 */
	private $container;

	/**
	 * @param EntityManagerMapperService $mapperService
	 * @param EntityManagerInterface     $entityManager
	 * @param JWTTokenManagerInterface   $jwtManager
	 * @param EventDispatcherInterface   $dispatcher
	 * @param TokenExtractorInterface    $tokenExtractor
	 * @param ContainerInterface         $container
	 */
	public function __construct(EntityManagerMapperService $mapperService,
								EntityManagerInterface $entityManager,
								JWTTokenManagerInterface $jwtManager,
								EventDispatcherInterface $dispatcher,
								TokenExtractorInterface $tokenExtractor,
								ContainerInterface $container)
	{
		$this->mapperService = $mapperService;
		$this->entityManager = $entityManager;
		$this->jwtManager = $jwtManager;
		$this->dispatcher = $dispatcher;
		$this->tokenExtractor = $tokenExtractor;
		$this->preAuthenticationTokenStorage = new TokenStorage();
		$this->container = $container;
	}

	/**
	 * Returns a decoded JWT token extracted from a request.
	 *
	 * {@inheritdoc}
	 *
	 * @return PreAuthenticationJWTUserToken | null
	 *
	 * @throws InvalidTokenException If an error occur while decoding the token
	 * @throws ExpiredTokenException If the request token is expired
	 */
	public function getCredentials(Request $request)
	{
		$tokenExtractor = $this->getTokenExtractor();

		if (!$tokenExtractor instanceof TokenExtractorInterface) {
			throw new \RuntimeException(
				 sprintf('Method "%s::getTokenExtractor()" must return an instance of "%s".',
					  __CLASS__, TokenExtractorInterface::class)
				);
		}

		if (false === ($jsonWebToken = $tokenExtractor->extract($request))) {
			return null;
		}

		/** @var PreAuthenticationJWTUserToken $preAuthToken */
		$preAuthToken = new PreAuthenticationJWTUserToken($jsonWebToken);

		try {
			if (!$payload = $this->jwtManager->decode($preAuthToken)) {
				throw new InvalidTokenException('Invalid JWT Token');
			}

			$preAuthToken->setPayload($payload);
		} catch (JWTDecodeFailureException $e) {
			if (JWTDecodeFailureException::EXPIRED_TOKEN === $e->getReason()) {
				throw new ExpiredTokenException();
			}

			throw new InvalidTokenException('Invalid JWT Token', 0, $e);
		}

		return $preAuthToken;
	}

	/**
	 * Returns an user object loaded from a JWT token.
	 *
	 * {@inheritdoc}
	 *
	 * @param PreAuthenticationJWTUserToken
	 *
	 * @throws \InvalidArgumentException If preAuthToken is not of the good type
	 * @throws InvalidPayloadException   If the user identity field is not a key of the payload
	 * @throws UserNotFoundException     If no user can be loaded from the given token
	 */
	public function getUser($preAuthToken, UserProviderInterface $userProvider)
	{
		if (!$preAuthToken instanceof PreAuthenticationJWTUserToken) {
			throw new \InvalidArgumentException(
				sprintf('The first argument of the "%s()" method must be an instance of "%s".',
					 __METHOD__, PreAuthenticationJWTUserToken::class)
			);
		}
		$encryptedPayload = $preAuthToken->getPayload()[Constants::PAYLOAD];

		$privateKey = JWKFactory::createFromKeyFile(
			$this->container->getParameter('jwt_private_key_path'),
			$this->container->getParameter('jwt_key_pass_phrase'),
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

		$identityField = $this->jwtManager->getUserIdentityField();

		if (!isset($payloadData[$identityField])) {
			throw new InvalidPayloadException($identityField);
		}

		$username = $payloadData[$identityField];
		$companyname = $payloadData[Constants::QUERY_PARAM_COMPANY_NAME];
		$sessionId = (array_key_exists(Constants::QUERY_PARAM_SESSION_ID, $payloadData)) ? $payloadData[Constants::QUERY_PARAM_SESSION_ID] : null;

		/** @var ClientRepository $clientRepository */
		$clientRepository = $this->mapperService->getEntityManager($companyname)
			->getRepository(Client::class);

		/** @var Client $user */
		$user = $clientRepository->findOneByUsername($username);
		$user->setEntityManagerIdentifier($companyname);
		if ($sessionId) {
			$user->setSessionId($sessionId);
		}

		if (!$user) {
			throw new UserNotFoundException($identityField, $username);
		}

		$this->preAuthenticationTokenStorage->setToken($preAuthToken);

		return $user;
	}

	/**
	 * @param mixed         $credentials
	 * @param UserInterface $user
	 *
	 * @return bool
	 */
	public function checkCredentials($credentials, UserInterface $user)
	{
		return true;
	}

	/**
	 * Create an authenticated token for the given user.
	 *
	 * If you don't care about which token class is used or don't really
	 * understand what a "token" is, you can skip this method by extending
	 * the AbstractGuardAuthenticator class from your authenticator.
	 *
	 * @see AbstractGuardAuthenticator
	 *
	 * @param UserInterface $user
	 * @param string        $providerKey The provider (i.e. firewall) key
	 *
	 * @return JWTUserToken
	 *
	 * @throws \RuntimeException If there is no pre-authenticated token previously stored
	 */
	public function createAuthenticatedToken(UserInterface $user, $providerKey)
	{
		/** @var PreAuthenticationJWTUserToken $preAuthToken */
		$preAuthToken = $this->preAuthenticationTokenStorage->getToken();
		if (null === $preAuthToken) {
			throw new \RuntimeException(
				 'Unable to return an authenticated token since there is no pre authentication token.'
			);
		}

		$authToken = new JWTUserToken(
			 $user->getRoles(),
			 $user, $preAuthToken->getCredentials(),
			 $providerKey
		);

		$this->dispatcher->dispatch(
			 Events::JWT_AUTHENTICATED,
			 new JWTAuthenticatedEvent($preAuthToken->getPayload(), $authToken)
		);

		$this->preAuthenticationTokenStorage->setToken(null);

		return $authToken;
	}

	/**
	 * @param Request        $request
	 * @param TokenInterface $token
	 * @param string         $providerKey
	 *
	 * @return null|Response
	 */
	public function onAuthenticationSuccess(Request $request,
											TokenInterface $token,
											$providerKey)
	{
		return null;
	}

	/**
	 * @param Request                 $request
	 * @param AuthenticationException $authException
	 *
	 * @return Response
	 */
	public function onAuthenticationFailure(Request $request,
											AuthenticationException $authException)
	{
		$response = new JWTAuthenticationFailureResponse($authException->getMessageKey());

		if ($authException instanceof ExpiredTokenException) {
			$event = new JWTExpiredEvent($authException, $response);
			$this->dispatcher->dispatch(Events::JWT_EXPIRED, $event);
		} else {
			$event = new JWTInvalidEvent($authException, $response);
			$this->dispatcher->dispatch(Events::JWT_INVALID, $event);
		}

		return new Response(JSend::error(
			strtr($authException->getMessageKey(), $authException->getMessageData()),
			Response::HTTP_UNAUTHORIZED, []),
			Response::HTTP_UNAUTHORIZED
		);
	}

	/**
	 * Called when authentication is needed, but it's not sent.
	 *
	 * @param Request                      $request
	 * @param AuthenticationException|null $authException
	 *
	 * @return Response
	 */
	public function start(Request $request, AuthenticationException $authException = null)
	{
		return new Response(JSend::error(
			JsonResponseMessage::UNAUTHORIZED,
			Response::HTTP_UNAUTHORIZED, []),
			Response::HTTP_UNAUTHORIZED
		);
	}

	/**
	 * @return bool
	 */
	public function supportsRememberMe()
	{
		return false;
	}

	/**
	 * Gets the token extractor to be used for retrieving a JWT token in the
	 * current request.
	 *
	 * Override this method for adding/removing extractors to the chain one or
	 * returning a different {@link TokenExtractorInterface} implementation.
	 *
	 * @return TokenExtractorInterface
	 */
	protected function getTokenExtractor()
	{
		return $this->tokenExtractor;
	}
}
