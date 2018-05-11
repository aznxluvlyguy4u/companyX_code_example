<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

//TODO Rename table name = sc_lib_logsh to "system_config_log" matching the entity name during DB switch
//TODO Rename column names to default names during DB switch
/**
 * SystemConfigLog.
 *
 * @ORM\Table(name="sc_lib_logsh")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\SystemConfigLogRepository")
 */
class SystemConfigLog extends BaseLog
{
}
