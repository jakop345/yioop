<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009, 2010, 2011  Chris Pollett chris@pollett.org
 *
 *  LICENSE:
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *  END LICENSE
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage library
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010, 2011
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Shared constants and enums used by components that are involved in the
 * crawling process
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage library
 */ 
 
interface CrawlConstants
{
    const queue_base_name = "QueueBundle";
    const archive_base_name = "Archive";
    const schedule_data_base_name = "ScheduleData";
    const robot_data_base_name = "RobotData";
    const index_data_base_name = "IndexData";
    const data_base_name = "At";
    const schedule_start_name = "ScheduleDataStartCrawl.txt";

    const MAX = 1;
    const MIN = -1;

    const STOP_STATE = -1;
    const CONTINUE_STATE = 1;
    const NO_DATA_STATE = 2;
    const WAITING_START_MESSAGE_STATE = 3; 
    
    const STATUS = 'a';
    const CRAWL_TIME = 'b';

    const HTTP_CODE = 'c';
    const TIMESTAMP = 'd';
    const TYPE = 'e';
    const ENCODING = 'f';

    const SEEN_URLS = 'g';
    const MACHINE = 'h';
    const INVERTED_INDEX = 'i';

    const SAVED_CRAWL_TIMES= 'j';
    const SCHEDULE_TIME = 'k';
    const URL = 'l';
    const WEIGHT = 'm';
    const ROBOT_PATHS = 'n';
    const HASH = 'o';
    const GOT_ROBOT_TXT = 'p';
    const PAGE = 'q';
    const DOC_INFO = 'r';
    const TITLE = 's';
    const DESCRIPTION = 't';
    const THUMB = 'u';
    const CRAWL_DELAY = 'v';
    const LINKS = 'w';
    const ROBOT_TXT = 'x';
    const TO_CRAWL = 'y';
    const INDEX = 'z';

    const AVERAGE_TITLE_LENGTH = 'A';
    const AVERAGE_DESCRIPTION_LENGTH = 'B';
    const AVERAGE_TOTAL_LINK_TEXT_LENGTH = 'C';
    const TITLE_LENGTH = 'D';
    const DESCRIPTION_LENGTH = 'E';
    const LINK_LENGTH = 'F';
    const TITLE_WORDS = 'G';
    const DESCRIPTION_WORDS = 'H';
    const LINK_WORDS = 'I';
    const TITLE_WORD_SCORE = 'J';
    const DESCRIPTION_WORD_SCORE = 'K';
    const LINK_WORD_SCORE = 'L';
    const DOC_DEPTH = 'M';
    const DOC_RANK = 'N';
    const URL_WEIGHT = 'O';
    const INLINKS = 'P';

    const NEW_CRAWL = 'Q';
    const OFFSET = 'R';
    const PATHS = 'S';
    const HASH_URL = 'T';
    const SUMMARY_OFFSET = 'U';
    const DUMMY = 'V';
    const SITES = 'W';
    const SCORE = 'X';

    const CRAWL_ORDER = 'Y';
    const RESTRICT_SITES_BY_URL = 'Z';
    const ALLOWED_SITES = 'aa';
    const DISALLOWED_SITES = 'ab';
    const BREADTH_FIRST = 'ac';
    const PAGE_IMPORTANCE = 'ad';

    const MACHINE_URI = 'ae';
    const SITE_INFO = 'af';
    const FILETYPE = 'ag';
    const SUMMARY = 'ah';
    const URL_INFO = 'ai';
    const HASH_SEEN_URLS ='aj';
    const RECENT_URLS ='ak';
    const MEMORY_USAGE ='al';
    const DOC_ID ='am';
    const RELEVANCE ='an';
    const META_WORDS ='ao';
    const CACHE_PAGE_PARTITION = 'ap';
    const GENERATION = 'aq';
    const HASH_SUM_SCORE = 'ar';
    const HASH_URL_COUNT = 'as';
    const IS_DOC = 'at';
    const BOOST = 'av';


    const NEEDS_OFFSET_FLAG = 0x7FFFFFFF;

} 
?>
