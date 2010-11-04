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
 * Element responsible for displaying info about starting, stopping, deleting, 
 * and using a crawl. It makes use of the CrawlStatusView
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage element
 */

class MixcrawlsElement extends Element
{

    /**
     * Draw form to start a new crawl, has div place holder and ajax code to 
     * get info about current crawl
     *
     * @param array $data  form about about a crawl such as its description
     */
    public function render($data) 
    {?>
        <div class="currentactivity">
        <h2><?php e(tl('mixcrawls_element_make_mix'))?></h2>
        <form id="mixForm" method="get" action=''>
        <input type="hidden" name="c" value="admin" /> 
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" /> 
        <input type="hidden" name="a" value="mixCrawls" />
        <input type="hidden" name="arg" value="update" />
        <div class="topmargin"><label for="load-options"><b><?php 
            e(tl('mixcrawls_element_load_mixes'))?></b></label><?php
            $this->view->optionsHelper->render("load-mixes", "LOAD_MIX", 
                $data['available_mixes'], $data['mix_default']);
        ?></div>
        <div class="topmargin"><label for="mix-name"><?php 
            e(tl('mixcrawls_element_mix_name')); ?></label> 
            <input type="text" id="mix-name" name="MIX_NAME" 
                value="<?php if(isset($data['MIX_NAME'])) {
                    e($data['MIX_NAME']); } ?>" maxlength="80" 
                    class="widefield"/>
        </div>
        <div class="topmargin"><label for="load-options"><b><?php 
            e(tl('crawloptions_element_load_options'))?></b></label><?php
            $this->view->optionsHelper->render("load-options", "load_option", 
                $data['available_options'], $data['options_default']);
        ?></div>
        <div class="center slightpad"><button class="buttonbox" 
            type="submit"><?php 
                e(tl('mixcrawls_element_save_button')); ?></button></div>
        </form>


        </div>
    <?php 
    }
}
?>
