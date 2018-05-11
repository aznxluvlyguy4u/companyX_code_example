<?php

namespace AppBundle\Component\SwiftTransport;

use Postmark\Transport;
use Swift_Events_EventDispatcher;
use Symfony\Component\HttpFoundation\Response;

class Swift_PostmarkTransport extends Transport
{
	protected $apiKey;

	/**
	 * @var Swift_Events_EventDispatcher
	 */
	protected $eventDispatcher;

	public function __construct($token = null, \Swift_Events_EventDispatcher $eventDispatcher)
	{
		parent::__construct($token);
		$this->eventDispatcher = $eventDispatcher;
	}

	/**
	 * {@inheritdoc}
	 */
	public function isStarted()
	{
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function start()
	{
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function stop()
	{
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function send(\Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
	{
		if ($evt = $this->eventDispatcher->createSendEvent($this, $message)) {
			$this->eventDispatcher->dispatchEvent($evt, 'beforeSendPerformed');
			if ($evt->bubbleCancelled()) {
				return 0;
			}
		}

		/**
		 * when parent::$response->getStatusCode() === 200 it will return the successfully sent messages
		 * If not, it will return 0.
		 */
		$recipientCount = parent::send($message, $failedRecipients);

		if ($evt) {
			$evt->setResult(\Swift_Events_SendEvent::RESULT_SUCCESS);
			$this->eventDispatcher->dispatchEvent($evt, 'sendPerformed');
		}

		if ($recipientCount > 0) {
			$responseEvent = $this->eventDispatcher->createResponseEvent(
				$this,
				Response::HTTP_OK,
				true
			);
			$this->eventDispatcher->dispatchEvent($responseEvent, 'responseReceived');
		}

		return 1;
	}

	/**
	 * {@inheritdoc}
	 */
	public function registerPlugin(\Swift_Events_EventListener $plugin)
	{
		$this->eventDispatcher->bindEventListener($plugin);
	}
}
