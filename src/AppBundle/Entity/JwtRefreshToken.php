<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * JwtRefreshToken.
 *
 * @ORM\Table(name="jwt_refresh_token")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\JwtRefreshTokenRepository")
 * @UniqueEntity("refreshToken")
 */
class JwtRefreshToken extends BaseRefreshToken
{
}
