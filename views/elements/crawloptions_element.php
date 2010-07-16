<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009, 2010  Chris Pollett chris@pollett.org
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
 * @copyright 2009, 2010
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Element responsible for displaying options about how a crawl will be
 * performed. For instance, what are the seed sites for the crawl, what
 * sites are allowed to be crawl what sites must not be crawled, etc.
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage element
 */

class CrawloptionsElement extends Element
{

    /**
     * Draws configurable options about how a web crawl should be conducted
     *
     * @param array $data keys are generally the different setting that can 
     *      be set in the crawl.ini file
     */
    public function render($data) 
    {
    ?>
        <div class="currentactivity">
        <div class="<?php e($data['leftorright']);?>">
        <a href="?c=admin&amp;a=manageCrawl&amp;YIOOP_TOKEN=<?php 
            e($data['YIOOP_TOKEN']) ?>"
        ><?php e(tl('crawloptions_element_back_to_manage'))?></a>
        </div>
        <h2><?php e(tl('crawloptions_element_edit_crawl_options'))?></h2>

        <form id="crawloptionsForm" method="post" action=''>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" />
        <input type="hidden" name="a" value="manageCrawl" />
        <input type="hidden" name="arg" value="options" />
        <input type="hidden" name="posted" value="posted" />
        <div class="topmargin"><label for="crawl-order"><b><?php 
            e(tl('crawloptions_element_crawl_order'))?></b></label><?php
            $this->view->optionsHelper->render("crawl-order", "crawl_order", 
                $data['available_crawl_orders'], $data['crawl_order']);
        ?></div>
        <div class="topmargin"><label for="restrict-sites-by-url"><b><?php 
            e(tl('crawloptions_element_restrict_by_url'))?></b></label>
                <input type="checkbox" id="restrict-sites-by-url" 
                    name="restrict_sites_by_url" value="true" 
                    onchange="setDisplay('toggle', this.checked)" <?php 
                    e($data['TOGGLE_STATE']); ?> /></div>
        <div id="toggle">
            <div class="topmargin"><label for="allowed-sites"><b><?php 
            e(tl('crawloptions_element_allowed_to_crawl'))?></b></label></div>
        <textarea class="shorttextarea" id="allowed-sites" 
            name="allowed_sites"><?php e($data['allowed_sites']);
        ?></textarea></div>
        <div class="topmargin"><label for="disallowed-sites"><b><?php 
            e(tl('crawloptions_element_disallowed_to_crawl')); 
                ?></b></label></div>
        <textarea class="shorttextarea" id="disallowed-sites" 
            name="disallowed_sites" ><?php e($data['disallowed_sites']);
        ?></textarea>
        <div class="topmargin"><label for="seed-sites"><b><?php 
            e(tl('crawloptions_element_seed_sites'))?></b></label></div>
        <textarea class="talltextarea"  name="seed_sites" ><?php 
            e($data['seed_sites']);
        ?></textarea>
        <div class="center slightpad"><button class="buttonbox" 
            type="submit"><?php e(tl('crawloptions_element_save_options')); 
            ?></button></div>
        </form>
        </div>

    <?php
    }
}
?>
