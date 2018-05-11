<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

//TODO Rename table name = dysc_users_logsh to "employee_log" matching the entity name during DB switch
//TODO Rename column names to default names during DB switch
/**
 * EmployeeLog.
 *
 * @ORM\Table(name="dysc_users_logsh")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\EmployeeLogRepository")
 */
class EmployeeLog extends BaseLog
{
}
