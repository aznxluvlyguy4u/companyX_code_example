<?php

namespace AppBundle\Util;

use AppBundle\Enumerator\JsonResponseMessage;
use Carpediem\JSend\JSend;
use Knp\Bundle\PaginatorBundle\Pagination\SlidingPagination;
use Symfony\Component\HttpFoundation\Response;

abstract class ResponseUtil
{
	/**
	 * Returns json response with:.
	 *
	 * {
	 *      "status" : "success",
	 *      "data" : [{data}]
	 * }
	 *
	 * @param $data
	 *
	 * @return Response
	 */
	public static function HTTP_OK($data)
	{
		return new Response(JSend::success($data), Response::HTTP_OK);
	}

	/**
	 * Returns json response with:.
	 *
	 * {
	 *      "status" : "success",
	 *      "data" : [{data}],
	 *      "currentPageNumber": "\d+",
	 *      "numItemsPerPage": {integer},
	 *      "totalCount": {integer}
	 * }
	 *
	 * @param array             $data
	 * @param SlidingPagination $slidingPagination
	 *
	 * @return Response
	 */
	public static function HTTP_OK_PAGINATED(array $data, SlidingPagination $slidingPagination)
	{
		return new Response(
			json_encode(
				array_merge(
					JSend::success($data)->toArray(),
					array(
						Constants::CURRENT_PAGE_NUMBER => $slidingPagination->getCurrentPageNumber(),
						Constants::NUM_ITEMS_PER_PAGE => $slidingPagination->getItemNumberPerPage(),
						Constants::TOTAL_COUNT => $slidingPagination->getTotalItemCount(),
					)
				), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
			),
			Response::HTTP_OK
		);
	}

	/**
	 * Returns json response with:.
	 *
	 * {
	 *      "status" : "error",
	 *      "message" : "Unauthorized",
	 *      "code": 401
	 * }
	 *
	 * @param null $errorMessage
	 *
	 * @return Response
	 */
	public static function HTTP_UNAUTHORIZED($errorMessage = null)
	{
		return new Response(JSend::error(
			$errorMessage ? $errorMessage : JsonResponseMessage::UNAUTHORIZED,
			Response::HTTP_UNAUTHORIZED, []),
			Response::HTTP_UNAUTHORIZED
		);
	}

	/**
	 * Returns json response with:.
	 *
	 * {
	 *      "status" : "error",
	 *      "message" : "Forbidden",
	 *      "code": 403
	 * }
	 *
	 * @param null $errorMessage
	 *
	 * @return Response
	 */
	public static function HTTP_FORBIDDEN($errorMessage = null)
	{
		return new Response(JSend::error(
			$errorMessage ? $errorMessage : JsonResponseMessage::FORBIDDEN,
			Response::HTTP_FORBIDDEN, []),
			Response::HTTP_FORBIDDEN
		);
	}

	/**
	 * Returns json response with:.
	 *
	 * {
	 *      "status" : "error",
	 *      "message" : "Not found",
	 *      "code": 404
	 * }
	 *
	 * @param null $errorMessage
	 *
	 * @return Response
	 */
	public static function HTTP_NOT_FOUND($errorMessage = null)
	{
		return new Response(JSend::error(
			$errorMessage ? $errorMessage : JsonResponseMessage::NOT_FOUND,
			Response::HTTP_NOT_FOUND, []),
			Response::HTTP_NOT_FOUND
		);
	}

	/**
	 * Returns json response with:.
	 *
	 * {
	 *      "status" : "error",
	 *      "message" : "Precondition failed",
	 *      "code": 412
	 * }
	 *
	 * @param null $errorMessage
	 *
	 * @return Response
	 */
	public static function HTTP_PRECONDITION_FAILED($errorMessage = null)
	{
		return new Response(JSend::error(
			$errorMessage ? $errorMessage : JsonResponseMessage::PRECONDITION_FAILED,
			Response::HTTP_PRECONDITION_FAILED, []),
			Response::HTTP_PRECONDITION_FAILED
		);
	}

	/**
	 * Returns json response with:.
	 *
	 * {
	 *      "status" : "error",
	 *      "message" : "Too many requests",
	 *      "code": 429
	 * }
	 *
	 * @param null $errorMessage
	 *
	 * @return Response
	 */
	public static function HTTP_TOO_MANY_REQUESTS($errorMessage = null)
	{
		return new Response(JSend::error(
			$errorMessage ? $errorMessage : JsonResponseMessage::TOO_MANY_REQUESTS,
			Response::HTTP_TOO_MANY_REQUESTS, []),
			Response::HTTP_TOO_MANY_REQUESTS
		);
	}

	/**
	 * Returns json response with:.
	 *
	 * {
	 *      "status" : "error",
	 *      "message" : "Bad request",
	 *      "code": 400
	 * }
	 *
	 * @param null $errorMessage
	 *
	 * @return Response
	 */
	public static function HTTP_BAD_REQUEST($errorMessage = null)
	{
		return new Response(JSend::error(
			$errorMessage ? $errorMessage : JsonResponseMessage::BAD_REQUEST,
			Response::HTTP_BAD_REQUEST, []),
			Response::HTTP_BAD_REQUEST
		);
	}

	public static function HTTP_INTERNAL_SERVER_ERROR($errorMessage = null)
	{
		return new Response(JSend::error(
			$errorMessage ? $errorMessage : JsonResponseMessage::INTERNAL_SERVER_ERROR,
			Response::HTTP_INTERNAL_SERVER_ERROR, []),
			Response::HTTP_INTERNAL_SERVER_ERROR
		);
	}
}
