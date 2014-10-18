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
 * Element responsible for displaying info to allow a user to create
 * a crawl mix or edit an existing one
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
    function render($data)
    {
        $base_url = "?c=admin&amp;a=mixCrawls&amp;".CSRF_TOKEN."=".
            $data[CSRF_TOKEN]."&amp;arg=";
        ?>
        <div class="current-activity">
        <?php
        $mixes_exist = isset($data['available_mixes']) &&
            count($data['available_mixes']) > 0;
        if($data['FORM_TYPE'] == "search") {
            $this->renderSearchForm($data);
        } else {
            $this->renderMixForm($data);
        }
        if($mixes_exist) {
        $data['TABLE_TITLE'] = tl('mixcrawls_element_available_mixes');
        $data['ACTIVITY'] = 'mixCrawls';
        $data['VIEW'] = $this->view;
        $data['NO_FLOAT_TABLE'] = true;
        $this->view->helper("pagingtable")->render($data);
        ?>
        <table class="mixes-table">
        <tr><th><?php e(tl('mixcrawls_view_name'));?></th><?php
        if(!MOBILE) { ?>
            <th><?php e(tl('mixcrawls_view_definition'));?></th>
        <?php
        }
        ?>
        <th colspan="4"><?php e(tl('mixcrawls_view_actions'));?></th></tr>
        <?php
        foreach($data['available_mixes'] as $mix) {
        ?>
            <tr><td><b><?php e($mix['NAME']); ?></b><br />
                <?php e($mix['TIMESTAMP']); ?><br /><?php
                e("<small>".date("d M Y H:i:s", $mix['TIMESTAMP']).
                    "</small>"); ?></td>
            <?php
            if(!MOBILE) {
                e("<td>");
                if(isset($mix['FRAGMENTS'])
                    && count($mix['FRAGMENTS'])  > 0) {
                    foreach($mix['FRAGMENTS'] as
                        $fragment_id=>$fragment_data) {
                        if(!isset($fragment_data['RESULT_BOUND']) ||
                           !isset($fragment_data['COMPONENTS']) ||
                           count($fragment_data['COMPONENTS']) == 0) {
                           continue;
                        }
                        e(" #".$fragment_data['RESULT_BOUND']."[");
                        $plus = "";
                        foreach($fragment_data['COMPONENTS'] as $component){
                            $crawl_timestamp =
                                $component['CRAWL_TIMESTAMP'];
                            e($plus.$component['WEIGHT']." * (".
                                $data['available_crawls'][
                                $crawl_timestamp]." + K:".
                                $component['KEYWORDS'].")");
                            $plus = "<br /> + ";
                        }
                        e("]<br />");
                    }
                } else {
                    e(tl('mixcrawls_view_no_components'));
                }
                e("</td>");
            }
            ?>
            <td><a href="javascript:share_form(<?php
                e($mix['TIMESTAMP']); ?>, '<?php e($mix['NAME']);?>')"><?php
                e(tl('mixcrawls_view_share'));?></a></td>
            <td><a href="<?php e($base_url); ?>editmix&timestamp=<?php
                e($mix['TIMESTAMP']); ?>"><?php
                e(tl('mixcrawls_view_edit'));?></a></td>
            <td>
            <?php
            if( $mix['TIMESTAMP'] != $data['CURRENT_INDEX']) { ?>
                <a href="<?php e($base_url); ?>index&timestamp=<?php
                    e($mix['TIMESTAMP']); ?>"><?php
                    e(tl('mixcrawls_set_index')); ?></a>
            <?php
            } else { ?>
                <?php e(tl('mixcrawl_search_index')); ?>
            <?php
            }
            ?>
            </td>
            <td><a href="<?php e($base_url); ?>deletemix&timestamp=<?php
                e($mix['TIMESTAMP']); ?>"><?php
                e(tl('mixcrawls_view_delete'));?></a></td>
            </tr>
        <?php
        }
        ?></table>
        <?php } ?>
        </div>
        <div class="share-lightbox" id="share-mix" >
        <div class="light-content">
        <div class="float-opposite"><a  href="javascript:setDisplay(
            'share-mix',false);"><?php e(tl('mixcrawls_view_back'));?></a></div>
        <h2><?php e(tl('mixcrawls_element_share_mix_group')); ?></h2>
        <form action="./" >
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="a" value="mixCrawls" />
        <input type="hidden" name="arg" value="sharemix" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>"
            value="<?php e($data[CSRF_TOKEN]); ?>" />
        <input type="hidden" id="time-stamp" name="timestamp" value="" />
        <table>
        <tr><th><label for="share-mix-name" ><?php
            e(tl("mixcrawls_element_mixname")); ?></label></th>
        <td><input type="text" name="smixname" value="" disabled="disabled"
            id="share-mix-name" maxlength="<?php e(NAME_LEN);
            ?>" class="wide-field"/></td>
        </tr>
        <tr><th><label for="share-group" ><?php
            e(tl("mixcrawls_element_group")); ?></label></th>
        <td><input type="text" name="group_name"
            id="share-group" maxlength="<?php e(SHORT_TITLE_LEN);
            ?>" class="wide-field"/></td>
        </tr>
        <tr><td></td><td><button class="button-box" type="submit"><?php
            e(tl("mixcrawls_element_share")); ?></button></td>
        </tr>
        </table>
        </form>
        </div>
        </div>
        <script type="text/javascript">
        function share_form(timestamp, mix_name)
        {
            elt('time-stamp').value = timestamp;
            elt('share-mix-name').value = mix_name;
            setDisplay('share-mix', true);
        }
        </script>
    <?php
    }
    /**
     * Draws the create mix form
     *
     * @param array $data used for CSRF_TOKEN
     */
    function renderMixForm($data)
    {
        ?>
        <h2><?php e(tl('mixcrawls_element_make_mix'))?></h2>
        <form id="mixForm" method="get" action=''>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
        <input type="hidden" name="a" value="mixCrawls" />
        <input type="hidden" name="arg" value="createmix" />
        <div class="top-margin"><label for="mix-name"><?php
            e(tl('mixcrawls_element_mix_name')); ?></label>:
            <input type="text" id="mix-name" name="NAME"
                value="" maxlength="<?php e(NAME_LEN); ?>"
                    class="wide-field"/>
           <button class="button-box"  type="submit"><?php
                e(tl('mixcrawls_element_create_button')); ?></button></div>
        </form>
        <?php
    }
    /**
     * Draws the search for mixes forms
     *
     * @param array $data consists of values of mix fields set
     *     so far as well as values of the drops downs on the form
     */
    function renderSearchForm($data)
    {
        $controller = "admin";
        $activity = "mixCrawls";
        $view = $this->view;
        $title = tl('mixcrawls_element_search_mix');
        $return_form_name = tl('mixcrawls_element_createmix_form');
        $fields = array(
            tl('mixcrawls_element_mixname') => "name",
        );
        $view->helper("searchform")->render($data, $controller, $activity,
                $view, $title, $return_form_name, $fields);
    }
}
?>