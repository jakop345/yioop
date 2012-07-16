<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2012  Chris Pollett chris@pollett.org
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
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage element
 */

class SearchsourcesElement extends Element
{

    /**
     * Draws 
     *
     * @param array $data 
     */
    public function render($data) 
    { 
    ?>
        <div class="currentactivity">
        <h2><?php e(tl('searchsources_element_add_media_source'))?></h2>
        <form id="addSearchSourceForm" method="post" action='#'>
        <input type="hidden" name="c" value="admin" /> 
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" /> 
        <input type="hidden" name="a" value="searchSources" />
        <input type="hidden" name="arg" value="addsource" />
        <table class="nametable">
        <tr><td><label for="source-type"><b><?php 
            e(tl('searchsources_element_sourcetype'))?></b></label></td><td>
            <?php $this->view->optionsHelper->render("source-type", 
            "sourcetype", $data['SOURCE_TYPES'], 
                $data['SOURCE_TYPE']); ?></td></tr>
        <tr><td><label for="source-name"><b><?php 
            e(tl('searchsources_element_sourcename'))?></b></label></td><td>
            <input type="text" id="source-name" name="sourcename" 
                maxlength="80" class="narrowfield" /></td></tr>
        <tr><td><label for="source-url"><b><?php 
            e(tl('searchsources_element_url'))?></b></label></td><td>
            <input type="text" id="source-url" name="sourceurl" 
                maxlength="80" class="narrowfield" /></td></tr>
        <tr><td><label for="source-thumbnail"><b id="thumb-text"><?php
            e(tl('searchsources_element_thumbnail'))?></b></label></td><td>
            <input type="text" id="source-thumbnail" name="sourcethumbnail" 
                maxlength="80" class="narrowfield" /></td></tr>
        <tr><td></td><td class="center"><button class="buttonbox" 
            type="submit"><?php e(tl('manageusers_element_submit')); 
            ?></button></td></tr>
        </table>
        </form>
        <h2><?php e(tl('searchsources_element_media_sources'))?></h2>
        <h2><?php e(tl('searchsources_element_add_subsearch'))?></h2>
        <h2><?php e(tl('searchsources_element_subsearches'))?></h2>
        <form id="addSearchSourceForm" method="post" action='#'>
        <input type="hidden" name="c" value="admin" /> 
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" /> 
        <input type="hidden" name="a" value="searchSources" />
        <input type="hidden" name="arg" value="setsource" />
        </form>
        </div>
        <script type= "text/javascript">
        function switchSourceType()
        {
            stype = elt("source-type");
            if(stype.options[stype.selectedIndex].value == "video") {
                setDisplay("thumb-text", true);
                setDisplay("source-thumbnail", true);
            } else {
                setDisplay("thumb-text", false);
                setDisplay("source-thumbnail", false);
            }
        }
        </script>
    <?php
    }
}
?>