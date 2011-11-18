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
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage element
 */

class FileOptionsElement extends Element
{

    /**
     *
     * @param array $data keys used to store disallowed_sites
     */
    public function render($data) 
    {
        global $INDEXED_FILE_TYPES;
    ?>
        <div class="currentactivity">
        <form id="fileoptionsForm" method="post" action='?'>
        <h2><?php e(tl('fileoptions_element_crawl_time'))?></h2>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" />
        <input type="hidden" name="a" value="fileOptions" />
        <input type="hidden" name="arg" value="options" />
        <div class="topmargin"><b><label for="select-role"><?php 
            e(tl('fileoptions_element_max_size'))?></label></b>
            <?php $this->view->optionsHelper->render("select-size", 
            "selectsize", $data['SIZE_VALUE'], $data['SELECT_SIZE']); 
            ?></div>
        <div class="topmargin"><b><?php 
            e(tl('fileoptions_element_file_types'))?></b>
       </div>
       <table class="ftypesall"><tr>
       <?php $cnt = 0;
             foreach ($data['INDEXED_FILE_TYPES'] as $filetype => $checked) { 
                 if($cnt % 10 == 0) {
                    ?><td><table class="filetypestable" ><?php
                 }
       ?>
            <tr><td><label for="<?php e($filetype); ?>-id"><?php 
                e($filetype); ?>
            </label></td><td><input type="checkbox" <?php e($checked) ?>
                name="<?php  e($filetype); ?>" value="true" /></td>
            </tr>
       <?php 
                $cnt++;
                if($cnt % 10 == 0) {
                    ?></table></td><?php
                }
            }?>
        <?php
            if($cnt % 10 != 0) {
                ?></table></td><?php
            }
        ?>
        </tr></table>
        <h2><?php e(tl('fileoptions_element_search_time'))?></h2>
        <table class="weightstable" >
        <tr><th><label for="title-weight"><?php 
            e(tl('fileoptions_element_title_weight'))?></label></th><td>
            <input type="text" id="title-weight" <
                name="TITLE_WEIGHT" 
                value="<?php  e($data['TITLE_WEIGHT']); ?>" /></td></tr>
        <tr><th><label for="description-weight"><?php 
            e(tl('fileoptions_element_description_weight'))?></label></th><td>
            <input type="text" id="description-weight" <
                name="DESCRIPTION_WEIGHT" 
                value="<?php  e($data['DESCRIPTION_WEIGHT']); ?>" /></td></tr>
        <tr><th><label for="link-weight"><?php 
            e(tl('fileoptions_element_link_weight'))?></label></th><td>
            <input type="text" id="link-weight" <
                name="LINK_WEIGHT" 
                value="<?php  e($data['LINK_WEIGHT']); ?>" /></td></tr>
        </table>
        <div class="center slightpad"><button class="buttonbox" 
            type="submit"><?php e(tl('fileoptions_element_save_options')); 
            ?></button></div>
        </form>
        </div>

    <?php
    }
}
?>
