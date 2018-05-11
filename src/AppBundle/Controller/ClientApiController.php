<?php

namespace AppBundle\Controller;

use AppBundle\Enumerator\SerializerGroup;
use AppBundle\Util\Constants;
use AppBundle\Util\ResponseUtil;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Nelmio\ApiDocBundle\Annotation\Operation;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;

/**
 * Class ClientApiController.
 *
 * @Route("/api/v1/clients")
 */
class ClientApiController extends BaseApiController
{
	/**
	 * @Operation(
	 *     tags={"Clients"},
	 *     summary="Returns authenticated client details",
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
	 *
	 * @Route("/me")
	 * @Method("GET")
	 *
	 * @return Response
	 */
	public function getAuthenticatedClient()
	{
		$client = $this->getUser();

		if (!$client) {
			return ResponseUtil::HTTP_NOT_FOUND();
		}

		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();
		$result = $serializer->normalize($client, Constants::JSON_SERIALIZATON_FORMAT, array(
			'groups' => array(
				SerializerGroup::CLIENTS,
			),
		));

		return ResponseUtil::HTTP_OK($result);
	}
}
