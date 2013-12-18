<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2013  Chris Pollett chris@pollett.org
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
 * @copyright 2009 - 2013
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 *  Element responsible for displaying the main blog and pages lookup feature
 *  that someone can modify for their own SeekQuarry/Yioop account.
 *  For now, you can only change your password
 *
 * @author Mallika Perepa
 * @package seek_quarry
 * @subpackage element
 */
class BlogPagesElement extends Element
{
    /**
     *  Draws a search blog or a page form.
     *
     *  @param array $data has a field for the anti-CSRF token
     */
    function render($data)
    {
        $pre_base_url = "?".CSRF_TOKEN."=".$data[CSRF_TOKEN]."&amp;c=admin";
        $base_url = $pre_base_url . "&amp;a=blogPages";
        $localize_url = $pre_base_url . "&amp;a=manageLocales".
            "&amp;arg=editlocale&amp;selectlocale=".$data['LOCALE_TAG']; ?>
        <div class="current-activity">
            <h2><?php e(tl('blogpages_element_lookup_page')); ?></h2>
            <form method="post" action='#'>
                <div class="top-margin">
                    <input type="hidden" name="a" value="blogPages"/>
                    <input type="hidden" name="arg" value="searchblog"/>
                    <input type="text" name="title" placeholder=
                        "<?php e(tl('blogpages_element_search_blog')); ?>"
                        class="extra-wide-field" value=''/>
                    <button class="button-box" type="submit"><?php
                        e(tl('blogpages_element_search'));
                    ?></button>
                </div>
            </form>
            <?php if(isset($data['BLOGS']) && !empty($data['BLOGS'])){ ?>
                <h2><?php e(tl('blogpages_element_related_blogs')); ?></h2>
                <table class="search-sources-table">
                    <tr><th><?php e(tl('blogpages_element_title'));?></th>
                        <th><?php e(tl('blogpages_element_sourcetype')); ?></th>
                        <th><?php e(tl('blogpages_element_action'));?></th>
                    </tr>
                    <?php foreach($data['BLOGS'] as $blogs) { ?>
                    <tr><th>
                        <a
                        href = "<?php e($base_url.'&amp;arg=editblog&amp;id='.
                        $blogs['TIMESTAMP']); ?>"><?php e($blogs['NAME']);?>
                        </a>
                        </th>
                        <td><?php e($blogs['TYPE']); ?></td>
                        <td><?php if($blogs['EDITABLE'] === true) { ?>
                            <a href="<?php e($base_url.'&arg=deleteblog&id='.
                                $blogs['TIMESTAMP']); ?>"><?php
                                e(tl('blogpages_element_deleteblog'));
                            ?></a>
                            <?php } ?>
                        </td>
                    </tr>
                <?php
                    } ?>
                </table>
                <?php
                }

                if(isset($data['RECENT_BLOGS'])
                    && !empty($data['RECENT_BLOGS'])) {
                    ?><h2><?php e(tl('blogpages_element_recent_blogs'))?></h2>
                    <table class="search-sources-table">
                        <tr>
                            <th><?php e(tl('blogpages_element_title'));?></th>
                            <th><?php 
                                e(tl('blogpages_element_sourcetype'));?></th>
                            <th><?php 
                                e(tl('blogpages_element_action'));?></th>
                        </tr>
                        <?php foreach($data['RECENT_BLOGS'] as $recent_blogs) {
                        ?><tr>
                            <td>
                                <a href =
                                "<?php e($base_url.'&amp;arg=editblog&amp;id='.
                                $recent_blogs['TIMESTAMP']); ?>">
                                <?php e($recent_blogs['NAME']);?></a>
                            </td>
                            <td><?php e($recent_blogs['TYPE']); ?></td>
                            <?php $delete_url = $base_url .
                                '&amp;arg=deleteblog&amp;id=' .
                                $recent_blogs['TIMESTAMP'];
                                ?><td> <?php
                                    if($recent_blogs['EDITABLE'] === true) {
                                ?><a href="<?php e($delete_url);?>"><?php
                                e(tl('blogpages_element_deleteblog'));
                                ?></a><?php
                                } ?></td>
                        </tr>
                        <?php } ?>
                    </table>
                <?php } ?>
            </div>
            <?php
        }
    }
?>


