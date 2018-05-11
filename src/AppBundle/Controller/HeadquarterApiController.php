<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Headquarter;
use AppBundle\Entity\Office;
use AppBundle\Enumerator\SerializerGroup;
use AppBundle\Repository\HeadquarterRepository;
use AppBundle\Util\Constants;
use AppBundle\Util\ResponseUtil;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Nelmio\ApiDocBundle\Annotation\Operation;
use Swagger\Annotations as SWG;
use Symfony\Component\Serializer\Serializer;

/**
 * Class HeadquarterApiController.
 *
 * @Route("/api/v1/headquarters")
 */
class HeadquarterApiController extends BaseApiController
{
	/**
	 * @Operation(
	 *     tags={"Headquarters"},
	 *     summary="Find the Headquarter if it is set and if client is a member of Headquarter",
	 *     @SWG\Parameter(
	 *            name="Authorization",
	 *            in="header",
	 *            required=true,
	 *            type="string",
	 *            default="Bearer {jwt}",
	 *            description="Authorization"
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
	 * @Route("")
	 * @Method("GET")
	 *
	 * @return Response
	 */
	public function getHeadquarters()
	{
		$client = $this->getUser();

		/** @var HeadquarterRepository $headquarterRepository */
		$headquarterRepository = $this->getEntityManager()
			->getRepository(Headquarter::class);

		/** @var Office[]\ArrayCollection $offices */
		$headquarter = $headquarterRepository->findHeadquarterWithRestrictionCheck($client);

		if (!$headquarter) {
			return ResponseUtil::HTTP_NOT_FOUND();
		}

		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();
		$result = $serializer->normalize($headquarter, Constants::JSON_SERIALIZATON_FORMAT, array(
			'groups' => array(
				SerializerGroup::HEADQUARTERS,
			),
		));

		return ResponseUtil::HTTP_OK($result);
	}

	/**
	 * @Operation(
	 *     tags={"Headquarters"},
	 *     summary="Returns all offices of a headquarter",
	 *     @SWG\Parameter(
	 *            name="Authorization",
	 *            in="header",
	 *            required=true,
	 *            type="string",
	 *            default="Bearer {jwt}",
	 *            description="Authorization"
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
	 * @Route("/offices")
	 * @Method("GET")
	 *
	 * @return Response
	 */
	public function getHeadquarterOffices()
	{
		$client = $this->getUser();

		/** @var HeadquarterRepository $headquarterRepository */
		$headquarterRepository = $this->getEntityManager()
			->getRepository(Headquarter::class);

		/** @var Office[]\ArrayCollection $offices */
		$headquarter = $headquarterRepository->findHeadquarterWithRestrictionCheck($client);

		if (!$headquarter) {
			return ResponseUtil::HTTP_NOT_FOUND();
		}

		$offices = $headquarter->getOffices();

		if (!$offices) {
			return ResponseUtil::HTTP_NOT_FOUND();
		}

		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();
		$result = $serializer->normalize($offices, Constants::JSON_SERIALIZATON_FORMAT, array(
			'groups' => array(
				SerializerGroup::OFFICES,
			),
		));

		return ResponseUtil::HTTP_OK($result);
	}
}
