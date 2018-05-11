<?php

namespace AppBundle\Repository;

use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository;

/**
 * Class JwtRefreshTokenRepository.
 */
class JwtRefreshTokenRepository extends RefreshTokenRepository
{
	/**
	 * @param null $datetime
	 *
	 * @return array
	 */
	public function findInvalid($datetime = null)
	{
		$datetime = (null === $datetime) ? new \DateTime() : $datetime;

		return $this->createQueryBuilder('u')
			->where('u.valid < :datetime')
			->setParameter(':datetime', $datetime)
			->getQuery()
			->getResult();
	}
}
