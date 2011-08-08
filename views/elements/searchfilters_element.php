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
 * @subpackage element
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010, 2011
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**

 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage element
 */

class SearchFiltersElement extends Element
{

    /**
     * Draws 
     *
     * @param array $data keys are generally the different setting that can 
     *      be set in the crawl.ini file
     */
    public function render($data) 
    {
    ?>
        <div class="currentactivity">

        <h2><?php e(tl('searchfilters_element_filter_websites'))?></h2>
        <form id="searchfiltersForm" method="post" action='?'>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" />
        <input type="hidden" name="a" value="searchFilters" />
        <input type="hidden" name="arg" value="options" />
        <input type="hidden" name="posted" value="posted" />

        <div class="topmargin"><label for="disallowed-sites"><b><?php 
            e(tl('searchfilters_element_sites_to_filter')); 
                ?></b></label></div>
        <textarea class="shorttextarea" id="disallowed-sites" 
            name="disallowed_sites" ><?php e($data['disallowed_sites']);
        ?></textarea>

        <div class="center slightpad"><button class="buttonbox" 
            type="submit"><?php e(tl('searchfilters_element_save_options')); 
            ?></button></div>
        </form>
        </div>

    <?php
    }
}
?>
