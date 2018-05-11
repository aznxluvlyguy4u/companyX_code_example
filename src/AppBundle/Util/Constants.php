<?php

namespace AppBundle\Util;

/**
 * Class Constants.
 */
abstract class Constants
{
	const JSON_SERIALIZATON_FORMAT = 'json';
	const QUERY_PARAM_COMPANY_NAME = 'company_name';
	const QUERY_PARAM_USERNAME = 'username';
	const QUERY_PARAM_REMEMBER_ME = 'remember_me';
	const QUERY_PARAM_SESSION_ID = 'session_id';
	const QUERY_PARAM_REMOTE_ADDR = 'remote_addr';
	const COOKIE_Company_DEVELOPER = 'CompanyDeveloper';
	const COOKIE_Company_DEVELOPER_EMAIL = 'CompanyDeveloper_email';
	const COOKIE_Company_DEVELOPER_NAME = 'CompanyDeveloper_name';
	const TOKEN = 'token';
	const REFRESH_TOKEN = 'refresh_token';
	const PAYLOAD = 'payload';
	const DateFormatString = 'Y-m-d';
	const DATE_TIME_FORMAT_STRING = 'Y-m-d H:i:s';
	const HOURS_MINUTES_FORMAT_STRING = 'H:i';
	const HOURS_MINUTES_SECONDS_FORMAT_STRING = 'H:i:s';
	const INTERVAL_ISO8601 = 'P%yY%mM%dDT%hH%iM%sS';
	const MINUTES_IN_AN_HOUR = 60;
	const CURRENT_PAGE_NUMBER = 'current_page_number';
	const NUM_ITEMS_PER_PAGE = 'num_items_per_page';
	const TOTAL_COUNT = 'total_count';
}
