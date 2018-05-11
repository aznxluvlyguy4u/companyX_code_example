<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

//TODO Rename table name = iclock_attendence_logsh to "clock_moment_log" matching the entity name during DB switch
//TODO Rename column names to default names during DB switch
/**
 * ClockMomentLog.
 *
 * @ORM\Table(name="iclock_attendence_logsh")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\ClockMomentLogRepository")
 */
class ClockMomentLog extends BaseLog
{
}
