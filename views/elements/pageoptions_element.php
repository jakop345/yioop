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
 * This element is used to render the Page Options admin activity
 * This activity lets a usercontrol the amount of web pages downloaded,
 * the recrawl frequency, the file types, etc of the pages crawled
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage element
 */

class PageOptionsElement extends Element
{

    /**
     * Draws the page options element to the output buffer
     *
     * @param array $data used to keep track of page range, recrawl frequency,
     *  and file types of the page
     */
    function render($data)
    {
        global $INDEXED_FILE_TYPES;
    ?>
        <div class="current-activity">
        <form id="pageoptionsForm" method="post" action='?'>
        <ul class='tab-menu-list'>
        <li><a href="javascript:
                switchTab('crawltimetab', 'searchtimetab', 'testoptionstab');"
            id='crawltimetabitem'
            class="<?php e($data['crawl_time_active']); ?>"><?php
            e(tl('pageoptions_element_crawl_time'))?></a></li>
        <li><a href="javascript:
                switchTab('searchtimetab', 'crawltimetab', 'testoptionstab');"
            id='searchtimetabitem'
            class="<?php e($data['search_time_active']); ?>"><?php
            e(tl('pageoptions_element_search_time'))?></a></li>
        <li><a href="javascript:
                switchTab('testoptionstab', 'crawltimetab', 'searchtimetab');"
            id='testoptionstabitem'
            class="<?php e($data['test_options_active']); ?>"><?php
            e(tl('pageoptions_element_test_options'))?></a></li>
        </ul>
        <div class='tab-menu-content'>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" id='option-type' name="option_type" value="<?php
            e($data['option_type'])?>" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
        <input type="hidden" name="a" value="pageOptions" />
        <div id='crawltimetab'>
        <div class="top-margin"><label for="load-options"><b><?php
            e(tl('pageoptions_element_load_options'))?></b></label><?php
            $this->view->optionsHelper->render("load-options", "load_option",
                $data['available_options'], $data['options_default']);
        ?></div>
        <div class="top-margin"><b><label for="page-range-request"><?php
            e(tl('pageoptions_element_page_range'))?></label></b>
            <?php $this->view->optionsHelper->render("page-range-request",
            "page_range_request", $data['SIZE_VALUES'], $data['PAGE_SIZE']);
            ?></div>
        <div class="top-margin"><b><label for="allow-recrawl"><?php
            e(tl('pageoptions_element_allow_recrawl'))?></label></b>
            <?php $this->view->optionsHelper->render("page-recrawl-frequency",
            "page_recrawl_frequency", $data['RECRAWL_FREQS'],
                $data['PAGE_RECRAWL_FREQUENCY']);
            ?></div>
        <div class="top-margin"><b><?php
            e(tl('pageoptions_element_file_types'))?></b>
       </div>
       <table class="file-types-all"><tr>
       <?php $cnt = 0;
             $num_types_per_column = ceil(count($data['INDEXED_FILE_TYPES'])/3);
             foreach ($data['INDEXED_FILE_TYPES'] as $filetype => $checked) {
                 if($cnt % $num_types_per_column == 0) {
                    ?><td><table class="file-types-table" ><?php
                 }
       ?>
            <tr><td><label for="<?php e($filetype); ?>-id"><?php
                e($filetype); ?>
            </label></td><td><input type="checkbox" <?php e($checked) ?>
                name="filetype[<?php  e($filetype); ?>]" value="true" /></td>
            </tr>
       <?php
                $cnt++;
                if($cnt % $num_types_per_column == 0) {
                    ?></table></td><?php
                }
            }?>
        <?php
            if($cnt % $num_types_per_column != 0) {
                ?></table></td><?php
            }
        ?>
        </tr></table>
        <div class="top-margin"><label for="page-rules"><b><?php
            e(tl('pageoptions_element_page_rules'));?></b></label>
        </div>
        <textarea class="short-text-area" id="page-rules"
            name="page_rules" ><?php e($data['page_rules']);
        ?></textarea>
        </div>

        <div id='searchtimetab'>
        <table class="weights-table" >
        <tr><th><label for="title-weight"><?php
            e(tl('pageoptions_element_title_weight'))?></label></th><td>
            <input type="text" id="title-weight" size="3" maxlength="6"
                name="TITLE_WEIGHT"
                value="<?php  e($data['TITLE_WEIGHT']); ?>" /></td></tr>
        <tr><th><label for="description-weight"><?php
            e(tl('pageoptions_element_description_weight'))?></label></th><td>
            <input type="text" id="description-weight" size="3" maxlength="6"
                name="DESCRIPTION_WEIGHT"
                value="<?php  e($data['DESCRIPTION_WEIGHT']); ?>" /></td></tr>
        <tr><th><label for="link-weight"><?php
            e(tl('pageoptions_element_link_weight'))?></label></th><td>
            <input type="text" id="link-weight" size="3" maxlength="6"
                name="LINK_WEIGHT"
                value="<?php  e($data['LINK_WEIGHT']); ?>" /></td></tr>
        </table>
        <h2><?php e(tl('pageoptions_element_results_grouping_options'))?></h2>
        <table class="weights-table" >
        <tr><th><label for="min-results-to-group"><?php
            e(tl('pageoptions_element_min_results_to_group'))?></label></th><td>
            <input type="text" id="min-results-to-group" size="3" maxlength="6"
                name="MIN_RESULTS_TO_GROUP"
                value="<?php  e($data['MIN_RESULTS_TO_GROUP']); ?>" /></td></tr>
        <tr><th><label for="server-alpha"><?php
            e(tl('pageoptions_element_server_alpha'))?></label></th><td>
            <input type="text" id="server-alpha" size="3" maxlength="6"
                name="SERVER_ALPHA"
                value="<?php e($data['SERVER_ALPHA']); ?>" /></td></tr>
        </table>
        </div>

        <div id='testoptionstab'>
         <h2><?php e(tl('pageoptions_element_test_page'))?></h2>
        <div class="top-margin"><b><label for="page-type"><?php
            e(tl('pageoptions_element_page_type'))?></label></b>
            <?php 
            $types = $data['MIME_TYPES'];
            $this->view->optionsHelper->render("page-type",
            "page_type", array_combine($types, $types),
            $data["page_type"]);
            ?></div>
        <textarea class="tall-text-area" id="testpage"
            name="TESTPAGE" ><?php e($data['TESTPAGE']);
        ?></textarea>
        </div>

        </div>

        <div class="center slight-pad"><button class="button-box" 
            id="page-button"
            type="submit"><?php if($data['test_options_active'] == "") {
                e(tl('pageoptions_element_save_options'));
            } else {
                e(tl('pageoptions_element_run_tests'));
            }
            ?></button></div>
        </form>
        <?php if($data['test_options_active'] != "") { ?>
            <div id="test-results">
            <h2><?php e(tl('pageoptions_element_test_results'))?></h2>
            <?php
            if(isset($data["AFTER_PAGE_PROCESS"])) {
                e("<h3>".tl('pageoptions_element_after_process')."</h3>");
                e("<pre>\n{$data['AFTER_PAGE_PROCESS']}\n</pre>");
            }
            if(isset($data["AFTER_RULE_PROCESS"])) {
                e("<h3>".tl('pageoptions_element_after_rules')."</h3>");
                e("<pre>\n{$data['AFTER_RULE_PROCESS']}\n</pre>");
            }
            if(isset($data["EXTRACTED_WORDS"])) {
                e("<h3>".tl('pageoptions_element_extracted_words')."</h3>");
                e("<pre>\n{$data['EXTRACTED_WORDS']}\n</pre>");
            }
            if(isset($data["EXTRACTED_META_WORDS"])) {
                e("<h3>".tl('pageoptions_element_extracted_metas')."</h3>");
                e("<pre>\n{$data['EXTRACTED_META_WORDS']}\n</pre>");
            } ?>
            </div>
        <?php 
        } ?>
        </div>

        <script type="text/javascript">

        function switchTab(newtab, oldtab, oldtab2)
        {
            setDisplay(newtab, true);
            setDisplay(oldtab, false);
            setDisplay(oldtab2, false);
            ntab = elt(newtab + "item");
            if(ntab) {
                ntab.className = 'active';
            }
            otab = elt(oldtab + "item");
            if(otab) {
                otab.className = '';
            }
            otab2 = elt(oldtab2 + "item");
            if(otab2) {
                otab2.className = '';
            }
            ctype = elt('option-type');
            if(ctype) {
                ctype.value = (newtab == 'crawltimetab')
                    ? 'crawl_time' : ((newtab == 'searchtimetab') ?
                    'search_time' : 'test_options' );
                if(ctype.value == 'test_options') {
                    elt('page-button').innerHTML =
                        '<?php e(tl('pageoptions_element_run_tests')); ?>';
                    elt('test-results').style.display = 'block';
                } else {
                    elt('page-button').innerHTML =
                        '<?php e(tl('pageoptions_element_save_options')); ?>';
                    elt('test-results').style.display = 'none';
                }
            }
        }
        </script>
    <?php
    }
}
?>
