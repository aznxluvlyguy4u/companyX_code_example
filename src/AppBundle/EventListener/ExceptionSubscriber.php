<?php

namespace AppBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use AppBundle\Util\ResponseUtil;
use Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException;

class ExceptionSubscriber implements EventSubscriberInterface
{
	/**
	 * Returns an array of event names this subscriber wants to listen to.
	 *
	 * The array keys are event names and the value can be:
	 *
	 *  * The method name to call (priority defaults to 0)
	 *  * An array composed of the method name to call and the priority
	 *  * An array of arrays composed of the method names to call and respective
	 *    priorities, or 0 if unset
	 *
	 * For instance:
	 *
	 *  * array('eventName' => 'methodName')
	 *  * array('eventName' => array('methodName', $priority))
	 *  * array('eventName' => array(array('methodName1', $priority), array('methodName2')))
	 *
	 * @return array The event names to listen to
	 */
	public static function getSubscribedEvents()
	{
		return array(
			KernelEvents::EXCEPTION => 'onKernelException',
		);
	}

	public function onKernelException(GetResponseForExceptionEvent $event)
	{
		$exception = $event->getException();

		$response = ResponseUtil::HTTP_INTERNAL_SERVER_ERROR($exception->getMessage());
		$event->setResponse($response);

		// Return custom json response for AccessDeniedHttpException
		if ($exception instanceof AccessDeniedHttpException) {
			$response = ResponseUtil::HTTP_FORBIDDEN();
			$event->setResponse($response);
		}

		// Return custom json response for NotFoundHttpException
		if ($exception instanceof NotFoundHttpException) {
			$response = ResponseUtil::HTTP_NOT_FOUND();
			$event->setResponse($response);
		}

		// Return custom json response for InsufficientAuthenticationException
		if ($exception instanceof InsufficientAuthenticationException) {
			$response = ResponseUtil::HTTP_UNAUTHORIZED();
			$event->setResponse($response);
		}

		// Return custom json response for TooManyRequestsHttpException
		if ($exception instanceof TooManyRequestsHttpException) {
			$response = ResponseUtil::HTTP_TOO_MANY_REQUESTS();
			$event->setResponse($response);
		}

		// Return custom json response for BadRequestsHttpException
		if ($exception instanceof BadRequestHttpException) {
			$response = ResponseUtil::HTTP_BAD_REQUEST($exception->getMessage());
			$event->setResponse($response);
		}

		// Return custom json response for HTTP_PRECONDITION_FAILED
		if ($exception instanceof PreconditionFailedHttpException) {
			$response = ResponseUtil::HTTP_PRECONDITION_FAILED($exception->getMessage());
			$event->setResponse($response);
		}
	}
}
