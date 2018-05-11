<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

//TODO Rename table name = dor_register_logsh to "register_log" matching the entity name during DB switch
//TODO Rename column names to default names during DB switch
/**
 * RegisterLog.
 *
 * @ORM\Table(name="dor_register_logsh")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\RegisterLogRepository")
 */
class RegisterLog extends BaseLog
{
}
