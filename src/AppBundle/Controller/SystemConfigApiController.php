<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Client;
use AppBundle\Entity\SystemConfig;
use AppBundle\Enumerator\SerializerGroup;
use AppBundle\Repository\SystemConfigRepository;
use AppBundle\Util\Constants;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Nelmio\ApiDocBundle\Annotation\Operation;
use Swagger\Annotations as SWG;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use AppBundle\Enumerator\SystemConfigKey;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;
use AppBundle\Util\ResponseUtil;

/**
 * Class SystemConfigApiController.
 *
 * @Route("/api/v1/system_configs")
 */
class SystemConfigApiController extends BaseApiController
{
	// TODO Reevaluate how to fetch systemConfigs without using current CompanyX Route names

	/**
	 * Get frontend system config settings of a specific page/path address of current CompanyX app.
	 *
	 * @Operation(
	 *     tags={"SystemConfigs"},
	 *     summary="Get systemConfigs of a specific page/path address of the current CompanyX app.",
	 *     @SWG\Parameter(
	 *            name="Authorization",
	 *            in="header",
	 *            required=true,
	 *            type="string",
	 *            default="Bearer {jwt}",
	 *            description="Authorization"
	 *        ),
	 *     @SWG\Parameter(
	 *         name="page",
	 *         in="query",
	 *         description="Provide a valid page route of the current CompanyX application",
	 *         required=false,
	 *         type="string",
	 *         format="?page=xxxx/xxxx"
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
	 * @QueryParam(name="page", requirements="^\w+\/\w+$", default=null, description="path to the page of the original CompanyX app")
	 * @Route("")
	 * @Method("GET")
	 *
	 * @param $page
	 *
	 * @return Response
	 */
	public function getSystemConfigsForPage($page)
	{
		if (!$page) {
			return ResponseUtil::HTTP_NOT_FOUND();
		}

		/** @var Client $client */
		$client = $this->getUser();

		// TODO Index all currently known systemConfigs per CompanyX route/page HERE!!!!
		$systemConfigsMatchingTable = [
			'rooster2/index2' => [
				SystemConfigKey::DOR_SCHEDULE_NO_ENDTIMES,
				SystemConfigKey::DOR_SCHEDULE_HIDE_BREAKS,
				SystemConfigKey::DOR_SCHEDULE_LOCK_APPROVED,
				SystemConfigKey::DOR_SCHEDULE_USE_VACATION_TIMEOUT_FOR_UNAVAILABILITY,
				SystemConfigKey::DOR_SCHEDULE_VACATION_MESSAGE,
				SystemConfigKey::DOR_SCHEDULE_VACATION_TIMEOUT,
				SystemConfigKey::DOR_SCHEDULE_AVAILABILITY_BLOCK_PLANNED,
				SystemConfigKey::DOR_SCHEDULE_HOURS_OVERVIEW_SHOW_SALDI,
				SystemConfigKey::DOR_SCHEDULE_HOURS_OVERVIEW_EXTENDED,
				SystemConfigKey::DOR_REGISTRATION_FEATURES,
				SystemConfigKey::DOR_REGISTRATION_ENABLE_BREAK,
				SystemConfigKey::DOR_REGISTRATION_COMPONENTS_KILOMETER_ALLOWENCE,
				SystemConfigKey::DOR_REGISTRATION_COMPONENT_MEALCHECKBOX,
				SystemConfigKey::DOR_OPENSHIFTS_CLICK_TO_SHOW_SHIFTS,
				SystemConfigKey::DOR_OPENSHIFTS_ALWAYS_SHOW_OPEN_SHIFTS,
				SystemConfigKey::DOR_OPENSHIFTS_SHOW_IF_SCHEDULED,
				SystemConfigKey::DOR_FTE_MW_HOURREGISTRATION,
				SystemConfigKey::DOR_HOUR_REGISTRATION_AUTOFILL,
				SystemConfigKey::DOR_FEATURES_SHIFT_SWAP_REQUEST,
			],
		];

		$systemConfigKeys = $systemConfigsMatchingTable[$page] ?? null;

		if (!$systemConfigKeys) {
			return ResponseUtil::HTTP_NOT_FOUND();
		}

		/** @var SystemConfigRepository $systemConfigRepo */
		$systemConfigRepo = $this->getEntityManager()
			->getRepository(SystemConfig::class);

		$systemConfigs = $systemConfigRepo->findByKey($systemConfigKeys);

		/** @var Serializer $serializer */
		$serializer = $this->getSerializerService();
		$result = $serializer->normalize($systemConfigs, Constants::JSON_SERIALIZATON_FORMAT, array(
			'groups' => array(
				SerializerGroup::SYSTEMCONFIGS,
			),
		));

		return ResponseUtil::HTTP_OK($result);
	}
}
