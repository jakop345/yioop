<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2009 - 2014  Chris Pollett chris@pollett.org
 *
 * LICENSE:
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * END LICENSE
 *
 * Used to set the configuration settings of the SeekQuarry project.
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage configs
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR') ||
    defined('PROFILE_FILE_NAME')) {echo "BAD REQUEST"; exit();}
/** Version number for upgrade function
 * @var int
 */
define('YIOOP_VERSION', 26);
/*
    pcre is an external library to php which can cause Yioop
    to seg fault if given instances of reg expressions with
    large recursion depth on a string.
    https://bugs.php.net/bug.php?id=47376
    The goal here is to cut off these problems before they happen.
    We do this in config.php because it is included in most Yioop
    files.
 */
ini_set('pcre.recursion_limit', 3000);
ini_set('pcre.backtrack_limit', 1000000);
/** Don't display any query info*/
define('NO_DEBUG_INFO', 0);
/** bit of DEBUG_LEVEL used to indicate test cases should be displayable*/
define('TEST_INFO', 1);
/** bit of DEBUG_LEVEL used to indicate query statistics should be displayed*/
define('QUERY_INFO', 2);
/** bit of DEBUG_LEVEL used to indicate php messages should be displayed*/
define('ERROR_INFO', 4);
/** Maintenance mode restricts access to local machine*/
define("MAINTENANCE_MODE", false);
if(file_exists(BASE_DIR."/configs/local_config.php")) {
    /** Include any locally specified defines (could use as an alternative
        way to set work directory) */
    require_once(BASE_DIR."/configs/local_config.php");
}
/**
 * @global array which activities are in which Component classes (use this
 *     array so don't have to instantiate classes to find out. Keys are
 *     names of components, values are the activities in that component.
 */
$COMPONENT_ACTIVITIES = array(
    "accountaccess" => array("signin", "manageAccount", "manageUsers",
        "manageRoles"),
    "crawl" => array("manageCrawls", "manageClassifiers", "pageOptions",
        "resultsEditor", "searchSources"),
    "social" => array("manageGroups", "groupFeeds", "mixCrawls", "wiki"),
    "system" => array("manageMachines", "manageLocales",
        "serverSettings", "security", "configure")
);
/** setting profile.php to something else in local_config.php allows one to have
 *  two different yioop instances share the same work_directory but maybe have
 *  different configuration settings. This might be useful if one was production
 *  and one was more dev.
 */
if(!defined('PROFILE_FILE_NAME')) {
    define('PROFILE_FILE_NAME', "/profile.php");
}
if(!defined('MAINTENANCE_MESSAGE')) {
    define('MAINTENANCE_MESSAGE', <<<EOD
This Yioop! installation is undergoing maintenance, please come back later!
EOD
);
}
if(MAINTENANCE_MODE && $_SERVER["SERVER_ADDR"] != $_SERVER["REMOTE_ADDR"]) {
    echo MAINTENANCE_MESSAGE;
    exit();
}

if(!defined('WORK_DIRECTORY')) {
/*+++ The next block of code is machine edited, change at
your own risk, please use configure web page instead +++*/
define('WORK_DIRECTORY', '');
/*++++++*/
// end machine edited code
}
/** Directory for local versions of web app classes*/
define('APP_DIR', WORK_DIRECTORY."/app");
/** Directory to place files such as dictionaries that will be
   converted to Bloom filter using token_tool.php. Similarly,
   can be used to hold files which will be used to prepare
   a file to assist in crawling or serving search results
*/
define('PREP_DIR', WORK_DIRECTORY."/prepare");
/** Locale dir to use in case LOCALE_DIR does not exist yet or is
 * missing some file
 */
define('FALLBACK_LOCALE_DIR', BASE_DIR."/locale");
/** Captcha mode indicating to use a text captcha*/
define('TEXT_CAPTCHA', 1);
/** Captcha mode indicating to use a hash cash computation for a captcha*/
define('HASH_CAPTCHA', 2);
/** Captcha mode indicating to use a classic image based captcha*/
define('IMAGE_CAPTCHA', 3);
/** Authentication Mode Possibility*/
define('NORMAL_AUTHENTICATION', 1);
/** Authentication Mode Possibility*/
define('ZKP_AUTHENTICATION', 2);
/** If ZKP Authentication via Fiat Shamir Protocol used how many iterations
 * to do
 */
define('FIAT_SHAMIR_ITERATIONS', 20);
if(file_exists(WORK_DIRECTORY.PROFILE_FILE_NAME)) {
    require_once(WORK_DIRECTORY.PROFILE_FILE_NAME);
    define('PROFILE', true);
    define('CRAWL_DIR', WORK_DIRECTORY);
    if(is_dir(WORK_DIRECTORY."/locale")) {
        define('LOCALE_DIR', WORK_DIRECTORY."/locale");
    } else {
        /** @ignore */
        define('LOCALE_DIR', FALLBACK_LOCALE_DIR);
    }
    define('LOG_DIR', WORK_DIRECTORY."/log");
    if(defined('DB_URL') && !defined('DB_HOST')) {
        define('DB_HOST', DB_URL); //for backward compatibility
    }
    if(defined('QUEUE_SERVER') && !defined('NAME_SERVER')) {
        define('NAME_SERVER', QUEUE_SERVER); //for backward compatibility
    }
    if(NAME_SERVER == 'http://' || NAME_SERVER == 'https://') {
        define("FIX_NAME_SERVER", true);
    }
} else {
    if((!isset( $_SERVER['SERVER_NAME'])||$_SERVER['SERVER_NAME']!=='localhost')
        && !defined("NO_LOCAL_CHECK") && !defined("WORK_DIRECTORY")
        && php_sapi_name() != 'cli' ) {
        echo "SERVICE AVAILABLE ONLY VIA LOCALHOST UNTIL CONFIGURED";
        exit();
    }
    /** @ignore */
    define('PROFILE', false);
    define('DBMS', 'sqlite3');
    define('AUTHENTICATION_MODE', NORMAL_AUTHENTICATION);
    define('DEBUG_LEVEL', NO_DEBUG_INFO);
    define('USE_FILECACHE', false);
    define('WEB_ACCESS', true);
    define('RSS_ACCESS', true);
    define('API_ACCESS', true);
    define('REGISTRATION_TYPE', 'disable_registration');
    define('USE_MAIL_PHP', true);
    define('MAIL_SERVER', '');
    define('MAIL_PORT', '');
    define('MAIL_USERNAME', '');
    define('MAIL_PASSWORD', '');
    define('MAIL_SECURITY', '');
    define('DB_NAME', "default");
    define('DB_USER', '');
    define('DB_PASSWORD', '');
    define('DB_HOST', '');
    /** @ignore */
    define('CRAWL_DIR', BASE_DIR);
    /** @ignore */
    define('LOCALE_DIR', FALLBACK_LOCALE_DIR);
    /** @ignore */
    define('LOG_DIR', BASE_DIR."/log");
    define('NAME_SERVER', "http://localhost/");
    define('USER_AGENT_SHORT', "NeedsNameBot");
    define('DEFAULT_LOCALE', "en-US");
    define('AUTH_KEY', 0);
    define('USE_MEMCACHE', false);
    define('USE_PROXY', false);
    define('TOR_PROXY', '127.0.0.1:9150');
    define('PROXY_SERVERS', NULL);
    define('WORD_SUGGEST', true);
    define('CACHE_LINK', true);
    define('SIMILAR_LINK', true);
    define('IN_LINK', true);
    define('IP_LINK', true);
    define('SIGNIN_LINK', true);
    define('NEWS_MODE', 'news_off');
    /** BM25F weight for title text */
    define ('TITLE_WEIGHT', 4);
    /** BM25F weight for other text within doc*/
    define ('DESCRIPTION_WEIGHT', 1);
    /** BM25F weight for other text within links to a doc*/
    define ('LINK_WEIGHT', 2);
    /** If that many exist, the minimum number of results to get
        and group before trying to compute the top x (say 10) results
     */
    define ('MIN_RESULTS_TO_GROUP', 200);
    /** For a given number of search results total to return (total_num)
        server_alpha*total_num/num_servers will be returned any a given
        queue server machine*/
    define ('SERVER_ALPHA', 1.6);
    define('BACKGROUND_COLOR', "#FFF");
    define('FOREGROUND_COLOR', "#FFF");
    define('SIDEBAR_COLOR', "#8A4");
    define('TOPBAR_COLOR', "#EEF");
    $INDEXING_PLUGINS = array();
}
if(!defined("BASE_URL")) {
    define('BASE_URL', NAME_SERVER);
}
if(!defined('LOGO')) {
    /*  these defines were added to the profile at same. So we add them all in
        one go to both the case where we have no profile and in the older
        profile case where they were not defined.
     */
    define('LOGO', "resources/yioop.png");
    define('M_LOGO', "resources/m-yioop.png");
    define('FAVICON', BASE_URL."favicon.ico");
    define('TIMEZONE', 'America/Los_Angeles');
    /* name of the cookie used to manage the session
       (store language and perpage settings), define CSRF token
     */
    define('SESSION_NAME', "yioopbiscuit");
    define('CSRF_TOKEN', "YIOOP_TOKEN");
}
date_default_timezone_set(TIMEZONE);
if((DEBUG_LEVEL & ERROR_INFO) == ERROR_INFO) {
    error_reporting(-1);
} else {
    error_reporting(0);
}
/** if true tests are diplayable*/
define('DISPLAY_TESTS', ((DEBUG_LEVEL & TEST_INFO) == TEST_INFO));
/** if true query statistics are diplayed */
define('QUERY_STATISTICS', ((DEBUG_LEVEL & QUERY_INFO) == QUERY_INFO));
//check if mobile css and formatting should be used or not
if(isset($_SERVER['HTTP_USER_AGENT'])) {
    $agent = $_SERVER['HTTP_USER_AGENT'];
    if((stristr($agent, "mobile") || stristr($agent, "fennec")) &&
        !stristr($agent, "ipad") ) {
        define("MOBILE", true);
    } else {
        define("MOBILE", false);
    }
} else {
    define("MOBILE", false);
}
/*
 * Various groups and user ids. These must be defined before the
 * profile check and return below
 */
/** ID of the root user */
define('ROOT_ID', 1);
/** Role of the root user */
define('ADMIN_ROLE', 1);
/** Default role of an active user */
define('USER_ROLE', 2);
/** ID of the group to which all Yioop users belong */
define('PUBLIC_GROUP_ID', 2);
/** ID of the group to which all Yioop users belong */
define('PUBLIC_USER_ID', 2);
/** ID of the group to which all Yioop Help Wiki articles belong */
define('HELP_GROUP_ID', 3);
if(!PROFILE) {
    return;
}
/*+++ End machine generated code, feel free to edit the below as desired +++*/
/** this is the User-Agent names the crawler provides
 * a web-server it is crawling
 */
define('USER_AGENT',
    'Mozilla/5.0 (compatible; '.USER_AGENT_SHORT.'; +'.NAME_SERVER.'bot.php)');
/**
 * To change the Open Search Tool bar name overrride the following variable
 * in your local_config.php file
 */
if(!defined('SEARCHBAR_PATH')) {
    define('SEARCHBAR_PATH', NAME_SERVER."yioopbar.xml");
}
/**
 * @global array addresses of memcached servers to use assuming memcached is
 * available
 */
if(USE_MEMCACHE) {
    $memcache_hosts = explode("|Z|", MEMCACHE_SERVERS);
    foreach($memcache_hosts as $host)
        $MEMCACHES[] = array("host" => $host, "port" => "11211"
    );
    unset($memcache_hosts);
    unset($host);
}
/** maximum size of a log file before it is rotated */
define("MAX_LOG_FILE_SIZE", 5000000);
/** number of log files to rotate amongst */
define("NUMBER_OF_LOG_FILES", 5);
/**
 * how long in seconds to keep a cache of a robot.txt
 * file before re-requesting it
 */
define('CACHE_ROBOT_TXT_TIME', 86400);
/**
 * Whether the scheduler should track ETag and Expires headers.
 * If you want to turn this off set the variable to false in
 * local_config.php
 */
if(!defined('USE_ETAG_EXPIRES')) {
    define('USE_ETAG_EXPIRES', true);
}
/**
 * if the robots.txt has a Crawl-delay larger than this
 * value don't crawl the site.
 * maximum value for this is 255
 */
define('MAXIMUM_CRAWL_DELAY', 64);
/** maximum number of active crawl-delayed hosts */
define('MAX_WAITING_HOSTS', 250);
/** Minimum weight in priority queue before rebuilt */
define('MIN_QUEUE_WEIGHT', 1/100000);
/**  largest sized object allowed in a web archive (used to sanity check
 *  reading data out of a web archive)
 */
define('MAX_ARCHIVE_OBJECT_SIZE', 100000000);
/** Treat earlier timestamps as being an indexes of format version 0 */
if(!defined('VERSION_0_TIMESTAMP')) {
    define('VERSION_0_TIMESTAMP', 1369754208);
}
/**
 * Code to determine how much memory current machine has
 */
$memory = 4000000000; //assume have at least 4GB on a Mac (could use vm_stat)
if(strstr(PHP_OS, "WIN")) {
    exec('wmic memorychip get capacity', $memory_array);
    $memory = array_sum($memory_array);
} else if(stristr(PHP_OS, "LINUX")) {
    $data = preg_split("/\s+/", file_get_contents("/proc/meminfo"));
    $memory = 1024 * intval($data[1]);
}
/**
 * Factor to multiply sizes of Yioop data structures with in low ram memory
 * setting (2GB)
 */
define('MEMORY_LOW', 1);
/**
 * Factor to multiply sizes of Yioop data structures with if have more than
 * (2GB)
 */
define('MEMORY_STANDARD', 4);
if($memory < 2200000000) {
    /**
     * Based on system memory, either the low or high memory factor
     */
    define('MEMORY_PROFILE', MEMORY_LOW);
} else {
    /**
     * @ignore
     */
    define('MEMORY_PROFILE', MEMORY_STANDARD);
}
/**
 * bloom filters are used to keep track of which urls are visited,
 * this parameter determines up to how many
 * urls will be stored in a single filter. Additional filters are
 * read to and from disk.
 */
define('URL_FILTER_SIZE', MEMORY_PROFILE * 5000000);
/**
 * maximum number of urls that will be held in ram
 * (as opposed to in files) in the priority queue
 */
define('NUM_URLS_QUEUE_RAM', MEMORY_PROFILE * 80000);
/** number of documents before next gen */
define('NUM_DOCS_PER_GENERATION', MEMORY_PROFILE *10000);
/** precision to round floating points document scores */
define('PRECISION', 10);
/** maximum number of links to extract from a page on an initial pass*/
define('MAX_LINKS_TO_EXTRACT', MEMORY_PROFILE * 80);
/** maximum number of links to keep after initial extraction*/
define('MAX_LINKS_PER_PAGE', 50);
/** Estimate of the average number of links per page a document has*/
define('AVG_LINKS_PER_PAGE', 24);
/** maximum number of links to consider from a sitemap page */
define('MAX_LINKS_PER_SITEMAP', MEMORY_PROFILE * 80);
/**  maximum number of words from links to consider on any given page */
define('MAX_LINKS_WORD_TEXT', 100);
/**  maximum length of urls to try to queue, this is important for
 *  memory when creating schedule, since the amount of memory is
 *  going to be greater than the product MAX_URL_LEN*MAX_FETCH_SIZE
 *  text_processors need to promise to implement this check or rely
 *  on the base class which does implement it in extractHttpHttpsUrls
 */
define('MAX_URL_LEN', 512);
/** request this many bytes out of a page -- this is the default value to
 * use if the user doesn't set this value in the page options GUI
 */
define('PAGE_RANGE_REQUEST', 50000);
/**
 * Max number of chars to extract for description from a page to index.
 * Only words in the description are indexed.
 */
define('MAX_DESCRIPTION_LEN', 2000);
/**
 * Allow pages to be recrawled after this many days -- this is the
 * default value to use if the user doesn't set this value in the page options
 * GUI. What this controls is how often the page url filter is deleted.
 * A nonpositive value means the filter will never be deleted.
 */
define('PAGE_RECRAWL_FREQUENCY', -1);
/** number of multi curl page requests in one go */
define('NUM_MULTI_CURL_PAGES', 100);
/** number of pages to extract from an archive in one go */
define('ARCHIVE_BATCH_SIZE', 100);
/** time in seconds before we give up on multi page requests*/
define('PAGE_TIMEOUT', 30);
/** time in seconds before we give up on a single page request*/
define('SINGLE_PAGE_TIMEOUT', 60);
/** max time in seconds in a process before write a log message if
    crawlTimeoutLog is called repeatedly from a loop
 */
define('LOG_TIMEOUT', 30);
/**
 * Maximum time a crawl daemon process can go before calling
 * @see CrawlDaemon::processHandler
 */
define('PROCESS_TIMEOUT', 240);
/**
 * Number of error page 400 or greater seen from a host before crawl-delay
 * host and dump remainder from current schedule
 */
define('DOWNLOAD_ERROR_THRESHOLD', 50);
/** Crawl-delay to set in the event that DOWNLOAD_ERROR_THRESHOLD exceeded*/
define('ERROR_CRAWL_DELAY', 20);
/** how often should we make in OPIC the sum of weights totals MAX_URLS */
define('NORMALIZE_FREQUENCY', 10000);
/**
 * @global array file extensions which can be handled by the search engine,
 * other extensions will be ignored. This array is populated in the individual
 * lib/processors page processors
 */
$INDEXED_FILE_TYPES = array("unknown");
/**
 * @global array filetypes which should be considered images. This
 *     array is populated in the individual lib/processors page processors
 */
$IMAGE_TYPES = array();
/**
 * Default edge size of square image thumbnails in pixels
 */
define('THUMB_DIM', 128);
/**
 * Maximum size of a user thumb file that can be uploaded
 */
define('THUMB_SIZE', 1000000);
/**
 * @global array associates mimetypes that can be processed by the search
 * engine with the processor class that can process them. This
 * array is populated in the individual lib/processors page processors
 */
$PAGE_PROCESSORS = array();
/**
 * @global array of indexing plugins, array itself is populated in the plugins
 *     after the plugin checks if it can run.
 */
$INDEXING_PLUGINS = array();
/** get any indexing plugins */
$plugin_dir = BASE_DIR."/lib/indexing_plugins/";
$plugin_dir_len = strlen($plugin_dir);
$plugin_ext_len = strlen("_plugin.php");
foreach(glob("$plugin_dir*_plugin.php") as $filename) {
    $tmp_plug_name = substr($filename, $plugin_dir_len, -$plugin_ext_len);
    if($tmp_plug_name != "indexing") {
        $INDEXING_PLUGINS[] = $tmp_plug_name;
    }
}
/** get locally defined indexing plugins */
$plugin_dir = APP_DIR."/lib/indexing_plugins/";
$plugin_dir_len = strlen($plugin_dir);
foreach(glob("$plugin_dir*_plugin.php") as $filename) {
    $INDEXING_PLUGINS[] = substr($filename, $plugin_dir_len, -$plugin_ext_len);
}
/**
 * Used in Modified 9 encoding. The ith array entry represents the number of
 * i bit elements that can be stored in a word using modified 9 (0th index
 * location is a dummy value 0 as can't store 0 bit numbers)
 * @global array
 */
$MOD9_PACK_POSSIBILITIES = array(
    0, 24, 12, 7, 6, 5, 4, 3, 3, 3, 2, 2, 2, 2,
    2,  1,  1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1,
    1, 1, 1, 1);
/**
 * Used in Modified 9 encoding. Key values are the number of elements we would
 * like to store in the current word. Values are the bit prefix to use on first
 * byte of word. Notices bits 7 and 6 (128 and 64) are not parts of prefixes
 * as used for continuation bits.
 * @global array
 */
$MOD9_NUM_ELTS_CODES = array(
    24 => 63, 12 => 62, 7 => 60, 6 => 56, 5 => 52, 4 => 48, 3 => 32,
    2 => 16, 1 => 0);
/**
 * Keys of this array are prefix codes from the high order byte of a word
 * encoded using Modified 9, values are the number of bits used to encode
 * an element if that prefix code was used.
 * @global array
 */
$MOD9_NUM_BITS_CODES = array( 63 => 1, 62 => 2, 60 => 3, 56 => 4, 52 => 5,
    48 => 6, 32 => 9, 16 => 14, 0 => 28);
/**
 * Keys of this array are prefix codes from the high order byte of a word
 * encoded using Modified 9, values are the number of elts stored in the
 * remaining bits of the word
 * @global array
 */
$MOD9_NUM_ELTS_DECODES = array(
    63 => 24, 62 => 12, 60=> 7, 56 => 6, 52 => 5, 48 => 4, 32 => 3,
    16 => 2, 0 => 1);
/** Characters we view as not part of words, not same as POSIX [:punct:]*/
define ('PUNCT', "\.|\,|\:|\;|\"|\'|\[|\/|\%|\?|-|".
    "\]|\{|\}|\(|\)|\!|\||\&|\`|\’|\‘|©|®|™|℠|…|\/|\>|，|\=|。|）|：|、|".
    "”|“|《|》|（|「|」|★|【|】|·|\+|\*|；|！|—|―|？|！");
/** Percentage ASCII text before guess we dealing with english*/
define ('EN_RATIO', 0.9);
/** Number of total description deemed title */
define ('AD_HOC_TITLE_LENGTH', 50);
/** Used to say number of bytes in histogram bar (stats page) for file
    download sizes
 */
define('DOWNLOAD_SIZE_INTERVAL', 5000);
/** Used to say number of secs in histogram bar for file download times*/
define('DOWNLOAD_TIME_INTERVAL', 0.5);
/**
 * How many non robot urls the fetcher successfully downloads before
 * between times data sent back to queue server
 */
define ('SEEN_URLS_BEFORE_UPDATE_SCHEDULER', MEMORY_PROFILE * 95);
/** maximum number of urls to schedule to a given fetcher in one go */
define ('MAX_FETCH_SIZE', MEMORY_PROFILE * 1000);
/** fetcher must wait at least this long between multi-curl requests */
define ('MINIMUM_FETCH_LOOP_TIME', 5);
/** an idling fetcher sleeps this long between queue_server pings*/
define ('FETCH_SLEEP_TIME', 15);
/** an a queue_server minimum loop idle time*/
define ('QUEUE_SLEEP_TIME', 5);
/** How often mirror script tries to synchronize with machine it is mirroring*/
define ('MIRROR_SYNC_FREQUENCY', 3600);
/** How often mirror script tries to notify machine it is mirroring that it
is still alive*/
define ('MIRROR_NOTIFY_FREQUENCY', 60);
/** Max time before dirty index (queue_server) and
    filters (fetcher) will be force saved in seconds*/
define('FORCE_SAVE_TIME', 3600);
/** Number of seconds of no fetcher contact before crawl is deemed dead*/
define("CRAWL_TIME_OUT", 1800);
/** maximum number of terms allowed in a conjunctive search query */
define ('MAX_QUERY_TERMS', 10);
/** When to switch to using suffice tree approach */
define ('SUFFIX_TREE_THRESHOLD', 3);
/** default number of search results to display per page */
define ('NUM_RESULTS_PER_PAGE', 10);
/** Number of recently crawled urls to display on admin screen */
define ('NUM_RECENT_URLS_TO_DISPLAY', 10);
/** Maximum time a set of results can stay in query cache before it is
    invalidated */
define ('MAX_QUERY_CACHE_TIME', 2 * 86400); //two days
/** Minimum time a set of results can stay in query cache before it is
    invalidated */
define ('MIN_QUERY_CACHE_TIME', 3600); //one hour
/**
 * Default number of items to page through for users,roles, mixes, etc
 * on the admin screens
 */
define ('DEFAULT_ADMIN_PAGING_NUM', 50);
/** Maximum number of bytes that the file that the suggest-a-url form
 * send data to can be.
 */
define ('MAX_SUGGEST_URL_FILE_SIZE', 100000);
/** Maximum number of a user can suggest to the suggest-a-url form in one day
 */
define ('MAX_SUGGEST_URLS_ONE_DAY', 10);
/**
 * Maximum number of search result fragments in a crawl mix
 */
define('MAX_MIX_FRAGMENTS', 10);
/**
 * Length after which to truncate names for users/groups/roles when
 * they are displayed (not in DB)
 */
define ('NAME_TRUNCATE_LEN', 7);
/** USER STATUS value used for a user who can log in and perform activities */
define('ACTIVE_STATUS', 1);
/**
 * USER STATUS value used for a user whose account is created, but which
 * still needs to undergo admin or email verification/activation
 */
define('INACTIVE_STATUS', 2);
/**
 * USER STATUS used to indicate an account which can no longer perform
 * activities but which might be retained to preserve old blog posts.
 */
define('BANNED_STATUS', 3);
/** Group status used to indicate a user that has been invited to join
 * a group but who has not yet accepted
 */
define('INVITED_STATUS', 4);
/**
 * Group registration type that only allows people to join a group by
 * invitation
 */
define('NO_JOIN', 1);
/**
 * Group registration type that only allows people to request a membership
 * in a group from the group's owner
 */
define('REQUEST_JOIN', 2);
/**
 * Group registration type that only allows people to request a membership
 * in a group from the group's owner, but allows people to browse the groups
 * content without join
 */
define('PUBLIC_BROWSE_REQUEST_JOIN', 3);
/**
 * Group registration type that allows anyone to obtain membership
 * in the group
 */
define('PUBLIC_JOIN', 4);
/**
 *  Group access code signifying only the group owner can
 *  read items posted to the group or post new items
 */
define('GROUP_PRIVATE', 1);
/**
 *  Group access code signifying members of the group can
 *  read items posted to the group but only the owner can post
 *   new items
 */
define('GROUP_READ', 2);
/**
 *  Group access code signifying members of the group can
 *  read items posted to the group but only the owner can post
 *   new items
 */
define('GROUP_READ_COMMENT', 3);
/**
 *  Group access code signifying members of the group can both
 *  read items posted to the group as well as post new items
 */
define('GROUP_READ_WRITE', 4);
/**
 *  Group access code signifying members of the group can both
 *  read items posted to the group as well as post new items
 *  and can edit the group's wiki
 */
define('GROUP_READ_WIKI', 5);
/**
 * Indicates a group where people can't up and down vote threads
 */
define("NON_VOTING_GROUP", 0);
/**
 * Indicates a group where people can vote up threads (but not down)
 */
define("UP_VOTING_GROUP", 1);
/**
 * Indicates a group where people can vote up and down threads
 */
define("UP_DOWN_VOTING_GROUP", 2);
/**
 *  Typical posts to a group feed are on user created threads and
 *  so are of this type
 */
define('STANDARD_GROUP_ITEM', 0);
/**
 *  Indicates the thread was created to go alongside the creation of a wiki
 *  page so that people can discuss the pages contents
 */
define('WIKI_GROUP_ITEM', 1);

/** Constant used to indicate lasting an arbitrary number of seconds */
define('FOREVER', -2);
/** Number of seconds in a day*/
define('ONE_DAY', 86400);
/** Number of seconds in a week*/
define('ONE_WEEK', 604800);
/** Number of seconds in a 30 day month */
define('ONE_MONTH', 2592000);
/** Number of seconds in an hour */
define('ONE_HOUR', 3600);
/** Number of seconds in a minute */
define('ONE_MINUTE', 60);
/*
 * Database Field Sizes
 */
/* Length for names of things like first name, last name, etc */
define('NAME_LEN', 32);
/* Used for lengths of media sources, passwords, and emails */
define('LONG_NAME_LEN', 64);
/* Length for names of things like group names, etc */
define('SHORT_TITLE_LEN', 128);
/* Length for names of things like titles of blog entries, etc */
define('TITLE_LEN', 512);
/* Length of a feed item or post, etc */
define('MAX_GROUP_POST_LEN', 8192);
/* Length for for the contents of a wiki_page */
define('MAX_GROUP_PAGE_LEN', 524288);
/* Length for base 64 encode timestamps */
define('TIMESTAMP_LEN', 11);
/* Length for timestamps down to microseconds */
define('MICROSECOND_TIMESTAMP_LEN', 20);
/* Length for a CAPTCHA */
define('CAPTCHA_LEN', 6);
/* Length for a number field */
define('MAX_IP_ADDRESS_AS_STRING_LEN', 39);
/* Length for a number field */
define('NUM_FIELD_LEN', 4);
/* Length for writing mode in locales */
define('WRITING_MODE_LEN', 5);
/* Length of zero knowledge password string */
define('ZKP_PASSWORD_LEN', 200);
?>