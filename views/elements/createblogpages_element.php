<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2014  Chris Pollett chris@pollett.org
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
 * @copyright 2009 - 2014
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 *  Element responsible for displaying the blog and pages feature
 *  that someone can modify for their own SeekQuarry/Yioop account.
 *  For now, you can only change your password
 *
 * @author Mallika Perepa
 * @package seek_quarry
 * @subpackage element
 */
class CreateBlogPagesElement extends Element
{
    /**
     *  @param array $data
     */
    function render($data)
    {
        $pre_base_url = "?".CSRF_TOKEN."=".$data[CSRF_TOKEN]."&amp;c=admin";
        $base_url = $pre_base_url . "&amp;a=blogPages";
        $localize_url = $pre_base_url . "&amp;a=manageLocales".
            "&amp;arg=editlocale&amp;selectlocale=".$data['LOCALE_TAG'];
        ?>
        <div class="current-activity">
            <h2> <?php e(tl('createblogpages_element_edit_blogpages')); ?></h2>
            <form method="post" action='#'>
                <input type="hidden" name="<?php e(CSRF_TOKEN); ?>"
                        value="<?php e($data[CSRF_TOKEN]); ?>"/>
                <input type="hidden" name="a" value="blogPages"/>
                <input type="hidden" name="arg" value="addblog"/>
                <table class="name-table">
                    <tr><th><label for="source-type"><?php
                        e(tl('createblogpages_element_sourcetype')); ?></label>
                        </th>
                        <td>
                            <?php $this->view->optionsHelper->
                            render("source-type","sourcetype",
                            $data['SOURCE_TYPES'],
                            $data['SOURCE_TYPE']); ?>
                        </td>
                    </tr>
                    <tr><th><label for="type-name"><?php
                        e(tl('createblogpages_element_typename')); ?></label>
                        </th>
                        <td><input type="text" id="type-name" name="title"
                            value = "<?php e($data['title']); ?>"
                            maxlength="80" class="wide-field"/>
                        </td>
                    </tr>
                    <tr>
                        <th id="locale-text">
                            <label for="source-locale-tag"><?php
                                e(tl('searchsources_element_locale_tag'));
                            ?></label>
                        </th>
                        <td>
                            <?php
                                $this->view->optionsHelper->
                                 render("source-locale-tag",
                                "sourcelocaletag", $data['LANGUAGES'],
                                $data['SOURCE_LOCALE_TAG']); ?>
                         </td>
                    </tr>
                </table>
                <table class="name-table">
                    <tr><th><label for="select-group"><?php
                        e(tl('createblogpages_element_add_group'))?></label>
                        </th>
                        <td>
                            <?php 
                            $this->view->optionsHelper->render("select-group",
                                "selectgroup", $data['GROUP_NAMES'],
                            $data['SELECT_GROUP']); ?>
                        </td>
                    </tr>
                </table>
                <div class="top-margin"><label for="descriptionfield"><b><?php
                    e(tl('createblogpages_element_description'));
                    ?></b></label>
                </div>
                <textarea class="tall-text-area" id="descriptionfield"
                    name="description"><?php e($data['description']);
                    ?></textarea>
                <div class="center slight-pad">
                    <button class="button-box" type="submit"><?php
                        e(tl('createblogpages_element_save_page'));?></button>
                </div>
            </form>
        </div>
    <?php
    }
}
?>
