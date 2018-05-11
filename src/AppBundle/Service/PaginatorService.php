<?php

namespace AppBundle\Service;

use Doctrine\ORM\QueryBuilder;
use Knp\Component\Pager\PaginatorInterface;
use Knp\Bundle\PaginatorBundle\Pagination\SlidingPagination;

/**
 * Class PaginatorService.
 */
class PaginatorService
{
	/** @var PaginatorInterface */
	private $knpPaginator;

	/**
	 * PaginatorService constructor.
	 *
	 * @param PaginatorInterface $paginator
	 */
	public function __construct(PaginatorInterface $paginator)
	{
		$this->knpPaginator = $paginator;
	}

	/**
	 * Paginate a queryBuilder.
	 *
	 * @param QueryBuilder $queryBuilder
	 * @param string       $sort
	 * @param string       $direction
	 * @param int          $page
	 * @param int          $limit
	 *
	 * @return SlidingPagination
	 */
	public function paginate(QueryBuilder $queryBuilder, string $sort, string $direction = 'asc', int $page = 1, int $limit)
	{
		$rootAlias = $queryBuilder->getRootAliases()[0];
		// Overwrite $_GET variables for sort and direction with default values. Must set it to $_GET manually because knp_paginator listeners apparently ready query parameters from $_GET directly
		// key name sort and direction are configured in config.yml under knp_paginator key
		$_GET['sort'] = $rootAlias.'.'.$sort;
		$_GET['direction'] = $direction;

		/** @var SlidingPagination $paginator */
		$paginator = $this->knpPaginator->paginate(
			$queryBuilder->getQuery(), /* query NOT result */
			$page, /*page number*/
			$limit /*limit per page*/
		);

		return $paginator;
	}
}
