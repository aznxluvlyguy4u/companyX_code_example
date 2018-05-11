<?php

namespace AppBundle\Service;

use AppBundle\Entity\ShiftSwapRequest;
use AppBundle\Enumerator\ShiftSwapRequestStatus;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig_Environment;
use Swift_Mailer;
use Swift_Message;

/**
 * Class ShiftSwapRequestNotifierService.
 */
class ShiftSwapRequestNotifierService
{
	/** @var Swift_Mailer */
	private $mailer;

	/** @var Twig_Environment */
	private $twig;

	/** @var string|null */
	private $fromEmail;

	/** @var string|null */
	private $webroot;

	/** @var string|null */
	private $applicationName;

	/** @var TokenStorageInterface */
	private $tokenStorage;

	/**
	 * ShiftSwapRequestNotifierService constructor.
	 *
	 * @param null                  $fromEmail
	 * @param null                  $webroot
	 * @param null                  $applicationName
	 * @param Twig_Environment      $twig
	 * @param Swift_Mailer          $mailer
	 * @param TokenStorageInterface $tokenStorage
	 */
	public function __construct($fromEmail = null, $webroot = null, $applicationName = null, Twig_Environment $twig, Swift_Mailer $mailer, TokenStorageInterface $tokenStorage)
	{
		$this->mailer = $mailer;
		$this->twig = $twig;
		$this->fromEmail = $fromEmail;
		$this->webroot = $webroot;
		$this->applicationName = $applicationName;
		$this->tokenStorage = $tokenStorage;
	}

	/**
	 * Send notice to those concerned for a ShiftSwapRequest depending on status change.
	 *
	 * @param ShiftSwapRequest $shiftSwapRequest
	 *
	 * @return bool
	 */
	public function notifyConcernedForShiftSwapRequest(ShiftSwapRequest $shiftSwapRequest)
	{
		$status = $shiftSwapRequest->getStatus();

		$template = null;
		$subject = null;
		$to = null;
		$toName = null;
		$replyTo = null;
		$replyToName = null;

		if (ShiftSwapRequestStatus::UNPROCESSED_BY_RECEIVER === $status) {
			$template = 'EmailTemplates/newShiftSwapRequestToReceiver.html.twig';
			$subject = 'Ruilverzoek '.$shiftSwapRequest->getApplicant()->getFullName();
			$to = $shiftSwapRequest->getReceiver()->getEmailAddress();
			$toName = $shiftSwapRequest->getReceiver()->getFullName();
			$replyTo = $shiftSwapRequest->getApplicant()->getEmailAddress();
			$replyToName = $shiftSwapRequest->getApplicant()->getFullName();
		} elseif (ShiftSwapRequestStatus::GRANTED_BY_RECEIVER === $status) {
			$template = 'EmailTemplates/grantedShiftSwapRequestToApplicant.html.twig';
			$subject = 'Status ruilverzoek';
			$to = $shiftSwapRequest->getApplicant()->getEmailAddress();
			$toName = $shiftSwapRequest->getApplicant()->getFullName();
			$replyTo = $shiftSwapRequest->getReceiver()->getEmailAddress();
			$replyToName = $shiftSwapRequest->getReceiver()->getFullName();
		} elseif (ShiftSwapRequestStatus::DENIED_BY_RECEIVER === $status) {
			$template = 'EmailTemplates/deniedShiftSwapRequestToApplicant.html.twig';
			$subject = 'Afwijzing ruilverzoek';
			$to = $shiftSwapRequest->getApplicant()->getEmailAddress();
			$toName = $shiftSwapRequest->getApplicant()->getFullName();
			$replyTo = $shiftSwapRequest->getReceiver()->getEmailAddress();
			$replyToName = $shiftSwapRequest->getReceiver()->getFullName();
		} elseif (ShiftSwapRequestStatus::WITHDRAWN_BY_APPLICANT === $status) {
			$template = 'EmailTemplates/withdrawnShiftSwapRequestToReceiver.html.twig';
			$subject = $shiftSwapRequest->getApplicant()->getFullName().' heeft het ruilverzoek ingetrokken';
			$to = $shiftSwapRequest->getReceiver()->getEmailAddress();
			$toName = $shiftSwapRequest->getReceiver()->getFullName();
			$replyTo = null;
			$replyToName = null;
		} elseif (ShiftSwapRequestStatus::WITHDRAWN_BY_RECEIVER === $status) {
			$template = 'EmailTemplates/withdrawnShiftSwapRequestToApplicant.html.twig';
			$subject = $shiftSwapRequest->getReceiver()->getFullName().' heeft het ruilverzoek alsnog afgewezen';
			$to = $shiftSwapRequest->getApplicant()->getEmailAddress();
			$toName = $shiftSwapRequest->getApplicant()->getFullName();
			$replyTo = null;
			$replyToName = null;
		}

		//Check if we got an mail adress
		if (!$to) {
			return false;
		}

		$message = new Swift_Message();
		$message
			->setFrom($this->fromEmail)
			->setTo($to, $toName)
			->setReplyTo($replyTo, $replyToName)
			->setSubject($subject)
			->setBody(
				$this->twig->render(
					$template,
					array(
						'shiftSwapRequest' => $shiftSwapRequest,
						'webroot' => $this->webroot,
						'applicationName' => $this->applicationName,
						'companyIdentifier' => $this->tokenStorage->getToken()->getUser()->getEntityManagerIdentifier(),
					)
				),
				'text/html'
			);

		return $this->mailer->send($message);
	}
}
