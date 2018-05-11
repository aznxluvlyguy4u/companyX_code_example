<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Bulletin;
use AppBundle\Entity\Client;
use AppBundle\Enumerator\SerializerGroup;
use AppBundle\Repository\BulletinRepository;
use AppBundle\Util\Constants;
use AppBundle\Util\ResponseUtil;
use DateTime;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Nelmio\ApiDocBundle\Annotation\Operation;
use Swagger\Annotations as SWG;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;

/**
 * Class BulletinApiController.
 *
 * @Route("/api/v1/bulletins")
 */
class BulletinApiController extends BaseApiController
{
	/**
	 * Get all bulletins the authenticated clients are allowed to see.
	 * If both dateFrom and dateTo query parameters are given, bulletins within the interval dateFrom and dateTo are returned
	 * If only dateFrom and no dateTo given, bulletins are returned from the dateFrom value to the end of that month.
	 * If only dateTo and no dateFrom given, bulletins are returned from the start of the month of the dateFrom to the dateFrom value.
	 * If no dateFrom and no dateTo given, bulletins are returned of the current month.
	 * If an officeId is given, bulletins in that specific office are returned, provided that the Client is an member of that office.
	 * If no officeId given, bulletins of all offices that the authenticated Client is a member of are returned.
	 *
	 * @Operation(
	 *     tags={"Bulletins"},
	 *     summary="Get all Bulletins the authenticated clients are allowed to see.",
	 *     @SWG\Parameter(
	 *            name="Authorization",
	 *            in="header",
	 *            required=true,
	 *            type="string",
	 *            default="Bearer {jwt}",
	 *            description="Authorization"
	 *        ),
	 *     @SWG\Parameter(
	 *         name="dateFrom",
	 *         in="query",
	 *         description="Provide a start date query parameter to fetch bulletins from that specific date",
	 *         required=false,
	 *         type="string"
	 *     ),
	 *     @SWG\Parameter(
	 *         name="dateTo",
	 *         in="query",
	 *         description="Provide a end date query parameter to fetch bulletins to that specific date",
	 *         required=false,
	 *         type="string",
	 *         format="date"
	 *     ),
	 *     @SWG\Parameter(
	 *         name="officeId",
	 *         in="query",
	 *         description="ID of an Office",
	 *         required=false,
	 *         type="integer"
	 *     ),
	 *     @SWG\Response(
	 *         response="200",
	 *         description="OK"
	 *     ),
	 *     @SWG\Response(
	 *         response="401",
	 *         description="Unauthorized"
	 *     ),
	 *     @SWG\Response(
	 *         response="403",
	 *         description="Forbidden"
	 *     ),
	 *     @SWG\Response(
	 *         response="404",
	 *         description="Not Found"
	 *     ),
	 *     @SWG\Response(
	 *         response="500",
	 *         description="Internal Server Error"
	 *     )
	 * )
	 *
	 * @param $dateFrom
	 * @param $dateTo
	 * @param $officeId
	 * @QueryParam(name="dateFrom", requirements="%iso_date_regex%", default=null, description="start of the date range")
	 * @QueryParam(name="dateTo", requirements="%iso_date_regex%", default=null, description="end of the date range")
	 * @QueryParam(name="officeId", requirements="\d+", strict=true, nullable=true, default=null, description="ID of an Office")
	 * @Route("")
	 * @Method("GET")
	 *
	 * @return Response
	 */
	public function getBulletins($dateFrom, $dateTo, $officeId)
	{
		/** @var Client $client */
		$client = $this->getUser();

		$dateTimeFrom = $dateFrom ? new DateTime($dateFrom) : null;
		$dateTimeTo = $dateTo ? new DateTime($dateTo) : null;

		/** @var BulletinRepository $bulletinRepository */
		$bulletinRepository = $this->getEntityManager()
			->getRepository(Bulletin::class);

		$bulletins = $bulletinRepository->findAllWithParametersWithRestrictionCheck($dateTimeFrom, $dateTimeTo,
			$officeId, $client);

		if (!$bulletins) {
			return ResponseUtil::HTTP_NOT_FOUND();
		}

		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();
		$result = $serializer->normalize($bulletins, Constants::JSON_SERIALIZATON_FORMAT, array(
			'groups' => array(
				SerializerGroup::BULLETINS,
			),
		));

		return ResponseUtil::HTTP_OK($result);
	}

	/**
	 * Get a single bulletin by ID that the client is allowed to see.
	 *
	 * @Operation(
	 *     tags={"Bulletins"},
	 *     summary="Get Bulletin by ID that the client is allowed to see",
	 *     @SWG\Parameter(
	 *            name="Authorization",
	 *            in="header",
	 *            required=true,
	 *            type="string",
	 *            default="Bearer {jwt}",
	 *            description="Authorization"
	 *        ),
	 *     @SWG\Parameter(
	 *            name="bulletinId",
	 *            in="path",
	 *            required=true,
	 *            type="integer",
	 *            description="Bulletin Id"
	 *        ),
	 *     @SWG\Response(
	 *         response="200",
	 *         description="OK"
	 *     ),
	 *     @SWG\Response(
	 *         response="401",
	 *         description="Unauthorized"
	 *     ),
	 *     @SWG\Response(
	 *         response="403",
	 *         description="Forbidden"
	 *     ),
	 *     @SWG\Response(
	 *         response="404",
	 *         description="Not Found"
	 *     ),
	 *     @SWG\Response(
	 *         response="500",
	 *         description="Internal Server Error"
	 *     )
	 * )
	 *
	 * @param $bulletinId
	 * @Route("/{bulletinId}", requirements={"bulletinId" = "\d+"})
	 * @Method("GET")
	 *
	 * @return Response
	 */
	public function getBulletin($bulletinId)
	{
		$client = $this->getUser();

		/** @var BulletinRepository $bulletinRepository */
		$bulletinRepository = $this->getEntityManager()
			->getRepository(Bulletin::class);

		$bulletin = $bulletinRepository->findOneWithRestriction($bulletinId, $client);

		if (!$bulletin) {
			return ResponseUtil::HTTP_NOT_FOUND();
		}

		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();
		$result = $serializer->normalize($bulletin, Constants::JSON_SERIALIZATON_FORMAT, array(
			'groups' => array(
				SerializerGroup::BULLETINS,
			),
		));

		return ResponseUtil::HTTP_OK($result);
	}
}
