<?php

namespace AppBundle\Security;

use Symfony\Component\Security\Core\Encoder\BasePasswordEncoder;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

/**
 * Class CompanyXPasswordEncoder.
 */
class CompanyXPasswordEncoder extends BasePasswordEncoder
{
	/**
	 * @param string $raw
	 * @param string $salt
	 *
	 * @return string|void
	 */
	public function encodePassword($raw, $salt)
	{
		if ($this->isPasswordTooLong($raw)) {
			throw new BadCredentialsException('Invalid password.');
		}
	}

	/**
	 * @param string $encoded
	 * @param string $raw
	 * @param string $salt
	 *
	 * @return bool
	 */
	public function isPasswordValid($encoded, $raw, $salt)
	{
		if ($this->isPasswordTooLong($raw)) {
			return false;
		}

		// Check if check if password is valid using MD5
		if (md5('gm'.'password'.$raw) == $encoded) {
			return true;
		}

		// Check if check if password is valid using Blowfish
		if (password_verify($raw, $encoded)) {
			return true;
		}

		return false;
	}
}
