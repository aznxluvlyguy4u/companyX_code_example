<?php

namespace AppBundle\Controller;

use AppBundle\Entity\ClockInterval;
use AppBundle\Enumerator\SerializerGroup;
use AppBundle\Util\Constants;
use AppBundle\Util\ResponseUtil;
use Doctrine\ORM\EntityManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * Class ClockIntervalApiController.
 *
 * @Route("/api/v1/clock_intervals")
 */
class ClockIntervalApiController extends BaseApiController
{
	// TODO determine proper GET behaviour before finishing this endpoint
//    /**
//     * @ApiDoc(
//     *   section = "ClockIntervals",
//     *   parameters={
//     *      {
//     *        "name"="dateFrom",
//     *        "dataType"="string",
//     *        "required"=false,
//     *        "description"="Provide a start date query parameter to fetch ClockIntervals from that specific date",
//     *        "format"="?dateFrom=yyyy-mm-dd"
//     *      },
//     *     {
//     *        "name"="dateTo",
//     *        "dataType"="string",
//     *        "required"=false,
//     *        "description"="Provide a end date query parameter to fetch ClockIntervals to that specific date",
//     *        "format"="?dateTo=yyyy-mm-dd"
//     *      },
//     *     {
//     *        "name"="officeId",
//     *        "dataType"="integer",
//     *        "required"=false,
//     *        "description"="ID of office",
//     *        "format"="?officeId=1"
//     *      },
//     *     {
//     *        "name"="departmentId",
//     *        "dataType"="integer",
//     *        "required"=false,
//     *        "description"="ID of department",
//     *        "format"="?departmentId=1"
//     *      },
//     *     {
//     *        "name"="employeeId",
//     *        "dataType"="integer",
//     *        "required"=false,
//     *        "description"="ID of an employee",
//     *        "format"="?departmentId=1"
//     *      }
//     *   },
//     *   resource = true,
//     *   description = "Get all ClockIntervals the clients are allowed to see.",
//     *   requirements={
//     *     {
//     *       "name"="Authorization",
//     *       "dataType"="string",
//     *       "requirement"="Bearer {jwt}",
//     *       "description"="A valid non expired access token"
//     *     }
//     *   },
//     *   output = "AppBundle\Entity\ClockMoment[]",
//     *   statusCodes = {
//     *      200 = "OK",
//     *      401 = "Unauthorized",
//     *      403 = "Forbidden",
//     *      404 = "Not Found",
//     *      500 = "Internal Server Error"
//     *   },
//     * )
//     *
//     * @QueryParam(name="dateFrom", requirements="%iso_date_regex%", default=null, description="start of the date range")
//     * @QueryParam(name="dateTo", requirements="%iso_date_regex%", default=null, description="end of the date range")
//     * @QueryParam(name="officeId", requirements="\d+", strict=true, nullable=true, default=null, description="ID of an office")
//     * @QueryParam(name="departmentId", requirements="\d+", strict=true, nullable=true, default=null, description="ID of a department")
//     * @QueryParam(name="employeeId", requirements="\d+", strict=true, nullable=true, default=null, description="ID of an employee")
//     *
//     * @Route("")
//     * @Method("GET")
//     *
//     * @return Response
//     * @Security("has_role('HOURS_REGISTER')")
//     */
//    public function getClockIntervals($dateFrom, $dateTo, $officeId, $departmentId, $employeeId)
//    {
//        $client = $this->getUser();
//        /** @var EntityManager $entityManager */
//        $entityManager = $this->getEntityManager();
//
//        if (!$client) {
//            return ResponseUtil::HTTP_NOT_FOUND();
//        }
//
//        $clockRepo = $entityManager->getRepository(ClockInterval::class);
//        $clockMoment = $clockRepo->find(4564);
//
//        /** @var Serializer $serializer */
//        $serializer = $this->getSerializerService();
//        $result = $serializer->normalize($clockMoment, Constants::JSON_SERIALIZATON_FORMAT, array(
//            'groups' => array(
//                SerializerGroup::CLOCK_INTERVALS
//            )
//        ));
//
//        return ResponseUtil::HTTP_OK($result);
//    }
}
