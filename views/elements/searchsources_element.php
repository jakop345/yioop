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
        $pre_base_url = "?YIOOP_TOKEN=".$data['YIOOP_TOKEN']."&c=admin";
        $base_url = $pre_base_url . "&a=searchSources";
        $localize_url = $pre_base_url . "&a=manageLocales".
            "&arg=editlocale&selectlocale=".$data['LOCALE_TAG'];
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
                maxlength="80" class="widefield" /></td></tr>
        <tr><td><label for="source-url"><b><?php 
            e(tl('searchsources_element_url'))?></b></label></td><td>
            <input type="text" id="source-url" name="sourceurl" 
                maxlength="80" class="widefield" /></td></tr>
        <tr><td><label for="source-thumbnail"><b id="thumb-text"><?php
            e(tl('searchsources_element_thumbnail'))?></b></label></td><td>
            <input type="text" id="source-thumbnail" name="sourcethumbnail" 
                maxlength="80" class="widefield" /></td></tr>
        <tr><td></td><td class="center"><button class="buttonbox" 
            type="submit"><?php e(tl('searchsources_element_submit')); 
            ?></button></td></tr>
        </table>
        </form>
        <h2><?php e(tl('searchsources_element_media_sources'))?></h2>
        <table class="searchsourcestable">
        <tr><th><?php e(tl('searchsources_element_medianame'));?></th>
            <th><?php e(tl('searchsources_element_mediatype'));?></th><th><?php
            e(tl('searchsources_element_mediaurls')); ?></th>
            <th><?php e(tl('searchsources_element_action'));?></th></tr><?php
        foreach($data['MEDIA_SOURCES'] as $source) {
        ?>
        <tr><td><b><?php e($source['NAME']); ?></b></td>
            <td><?php e($data['SOURCE_TYPES'][$source['TYPE']]); ?></td>
            <td><?php e($source['SOURCE_URL']."<br />".
                    $source['THUMB_URL']); ?></td>
            <td><a href="<?php e($base_url.'&arg=deletesource&ts='.
                $source['TIMESTAMP']); ?>"><?php 
                e(tl('searchsources_element_deletemedia')); 
            ?></a></td>
        <?php
        } ?>
        </table>
        <h2><?php e(tl('searchsources_element_add_subsearch'))?></h2>
        <form id="addSearchSourceForm" method="post" action='#'>
        <input type="hidden" name="c" value="admin" /> 
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" /> 
        <input type="hidden" name="a" value="searchSources" />
        <input type="hidden" name="arg" value="addsubsearch" />
        <table class="nametable">
        <tr><td><label for="subsearch-folder-name"><b><?php 
            e(tl('searchsources_element_foldername'))?></b></label></td><td>
            <input type="text" id="subsearch-folder-name" name="foldername" 
                maxlength="80" class="widefield" /></td></tr>
        <tr><td><label for="index-source"><b><?php 
            e(tl('searchsources_element_indexsource'))?></b></label></td><td>
            <?php $this->view->optionsHelper->render("index-source", 
            "indexsource", $data['SEARCH_LISTS'], 
                ""); ?></td></tr>
        <tr><td></td><td class="center"><button class="buttonbox" 
            type="submit"><?php e(tl('searchsources_element_submit')); 
            ?></button></td></tr>
        </table>
        <h2><?php e(tl('searchsources_element_subsearches'))?></h2>
        <table class="searchsourcestable">
        <tr><th><?php e(tl('searchsources_element_dirname'));?></th>
            <th><?php
            e(tl('searchsources_element_index')); ?></th>
            <th><?php e(tl('searchsources_element_localestring'));
            ?></th>
            <th colspan="2"><?php 
                e(tl('searchsources_element_actions'));?></th></tr>
        <?php foreach($data['SUBSEARCHES'] as $search) {
        ?>
        <tr><td><b><?php e($search['FOLDER_NAME']); ?></b></td>
            <td><?php 
                e("<b>".$data["SEARCH_LISTS"][$search['INDEX_IDENTIFIER']].
                "</b><br />".$search['INDEX_IDENTIFIER']); ?></td>
            <td><?php e($search['LOCALE_STRING']);?></td>

            <td><a href='<?php e($localize_url."#".$search['LOCALE_STRING']); 
                ?>' ><?php 
                e(tl('searchsources_element_localize')); 
                ?></a></td>
            <td><a href="<?php e($base_url.'&arg=deletesubsearch&fn='.
                $search['FOLDER_NAME']); ?>"><?php 
                e(tl('searchsources_element_deletesubsearch')); 
            ?></a></td>
        <?php
        } ?>
        <table>
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
