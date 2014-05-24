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
 * This element is used to render the Page Options admin activity
 * This activity lets a user control the amount of web pages downloaded,
 * the recrawl frequency, the file types, etc of the pages crawled
 *
 * @author Chris Pollett
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
        <input type="hidden" name="posted" value="posted" />
        <input type="hidden" id='option-type' name="option_type" value="<?php
            e($data['option_type'])?>" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
        <input type="hidden" name="a" value="pageOptions" />
        <div id='crawltimetab'>
        <div class="top-margin"><label for="load-options"><b><?php
            e(tl('pageoptions_element_load_options'))?></b></label><?php
            $this->view->helper("options")->render("load-options","load_option",
                $data['available_options'], $data['options_default']);
        ?></div>
        <div class="top-margin"><b><label for="page-range-request"><?php
            e(tl('pageoptions_element_page_range'))?></label></b>
            <?php $this->view->helper("options")->render("page-range-request",
            "page_range_request", $data['SIZE_VALUES'], $data['PAGE_SIZE']);
            ?></div>
        <div class="top-margin"><label for="summarizer"><b><?php
            e(tl('pageoptions_element_summarizer'))?></b></label><?php
                $this->view->helper("options")->render("summarizer",
                "summarizer_option",$data['available_summarizers'],
                $data['summarizer_option']);
            ?>
        </div>
        <div class="top-margin"><b><label for="max-description-len"><?php
            e(tl('pageoptions_element_max_description'))?></label></b>
            <?php $this->view->helper("options")->render("max-description-len",
            "max_description_len", $data['LEN_VALUES'], $data['MAX_LEN']);
            ?></div>
        <div class="top-margin"><b><label for="cache-pages"><?php
            e(tl('pageoptions_element_save_cache'))?>
            </label></b><input
            id='cache-pages' type="checkbox" name="cache_pages"
            value="true"
            <?php if(isset($data['CACHE_PAGES']) && $data['CACHE_PAGES']) {
                e("checked='checked'");
             }?>
            />
        </div>
        <div class="top-margin"><b><label for="allow-recrawl"><?php
            e(tl('pageoptions_element_allow_recrawl'))?></label></b>
            <?php $this->view->helper("options")->render(
                "page-recrawl-frequency",
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
            <tr><td><label for="filetype-<?php e($filetype); ?>-id"><?php
                e($filetype); ?>
            </label></td><td><input type="checkbox" <?php e($checked) ?>
                name="filetype[<?php  e($filetype); ?>]"
                id="filetype-<?php  e($filetype); ?>-id"
                value="true" /></td>
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
        <div class="top-margin"><b><?php
            e(tl('pageoptions_element_classifiers_rankers')) ?></b>
       </div>
       <?php if (!empty($data['CLASSIFIERS'])) {
            $data['TABLE_TITLE'] ="";
            $data['ACTIVITY'] = 'pageOptions';
            $data['VIEW'] = $this->view;
            $data['NO_SEARCH'] = true;
            $data['NO_FORM_TAGS'] = true;
            $data['FORM_TYPE'] = "";
            $data['NO_FLOAT_TABLE'] = true;
            $this->view->helper("pagingtable")->render($data);
       ?>
            <table class="classifiers-table" >
            <tr><th></th>
                <th><?php e(tl('pageoptions_element_use_classify'));
                    ?></th>
                <th><?php e(tl('pageoptions_element_use_rank'));
                    ?></th>
            </tr>
            <?php
            foreach ($data['CLASSIFIERS'] as $label => $class_checked) {
                if(isset($data['RANKERS'][$label])) {
                    $rank_checked = $data['RANKERS'][$label];
                } else {
                    $rank_checked = "";
                }
                ?>
                <tr><td><label for="classifier-<?php e($label); ?>-id"><?php
                    e($label); ?>
                </label></td><td class="check"><input type="checkbox"
                    <?php e($class_checked) ?>
                    name="classifier[<?php  e($label); ?>]"
                    id="classifier-<?php e($label) ?>-id" value="true" /></td>
                    <td class="check"><input type="checkbox"
                    <?php e($rank_checked) ?>
                    name="ranker[<?php  e($label); ?>]"
                    id="ranker-<?php e($label) ?>-id" value="true" /></td>
                </tr>
            <?php
            }
            ?>
            </table>
        <?php
        } else {
            e("<p class='red'>".
                tl('pageoptions_element_no_classifiers').'</p>');
        } ?>
        <div class="top-margin"><b><?php
            e(tl("pageoptions_element_indexing_plugins"));?></b></div>
        <?php if(isset($data['INDEXING_PLUGINS']) &&
            count($data['INDEXING_PLUGINS']) > 0) { ?>
            <table class="indexing-plugin-table">
                <tr><th><?php e(tl('pageoptions_element_plugin'));
                    ?></th>
                <th><?php
                    e(tl('pageoptions_element_plugin_include'));
                        ?></th></tr>
                <?php
                $k = 0;
                foreach($data['INDEXING_PLUGINS'] as
                    $plugin => $plugin_data) {
                ?>
                <tr><td><?php e($plugin. "Plugin"); ?></td>
                <td class="check"><input type="checkbox"
                    name="INDEXING_PLUGINS[<?php e($k); ?>]"
                    value = "<?php e($plugin) ?>"
                    <?php e($plugin_data['checked']); ?>
                    /><?php
                    if($plugin_data['configure']) { ?>
                        [<a href="javascript:setDisplay('plugin-<?php
                            e($plugin) ?>', true);" ><?php
                            e(tl('pageoptions_element_configure'));
                        ?></a>]<?php
                    }
                ?></td></tr>
            <?php
                $k++;
            }
            ?>
            </table>
        <?php
        } else {
            e("<p class='red'>".
                tl('pageoptions_element_no_compatible_plugins')."</p>");
        } ?>
        <div class="top-margin"><label for="page-rules"><b><?php
            e(tl('pageoptions_element_page_rules'));?></b></label>
        </div>
        <textarea class="short-text-area" id="page-rules"
            name="page_rules" ><?php e($data['page_rules']);
        ?></textarea>
        </div>

        <div id='searchtimetab'>
        <h2><?php e(tl('page_element_search_page'))?></h2>
        <table class="search-page-all"><tr><td>
        <table class="search-page-table">
        <tr>
        <td><label for="wd-suggest"><?php
            e(tl('pageoptions_element_wd_suggest')); ?></label></td>
            <td><input id='wd-suggest' type="checkbox"
            name="WORD_SUGGEST" value="true"
            <?php if(isset($data['WORD_SUGGEST']) &&
                $data['WORD_SUGGEST']){
                e("checked='checked'");}?>
            /></td></tr>
        <tr><td><label for="subsearch-link"><?php
            e(tl('pageoptions_element_subsearch_link'));?></label></td><td>
            <input id='subsearch-link'
            type="checkbox" name="SUBSEARCH_LINK" value="true"
            <?php if(isset($data['SUBSEARCH_LINK']) &&
                $data['SUBSEARCH_LINK']){
                e("checked='checked'");}?>
            /></td>
        </tr>
        <tr><td><label for="signin-link"><?php
            e(tl('pageoptions_element_signin_link')); ?></label></td><td>
            <input id='signin-link' type="checkbox"
            name="SIGNIN_LINK" value="true"
            <?php if(isset($data['SIGNIN_LINK']) &&
                $data['SIGNIN_LINK']){ e("checked='checked'");}?>
            />
        </td></tr>
        <tr><td><label for="cache-link"><?php
            e(tl('pageoptions_element_cache_link')); ?></label>
        </td><td><input id='cache-link' type="checkbox"
            name="CACHE_LINK" value="true"
            <?php if(isset($data['CACHE_LINK']) && $data['CACHE_LINK']){
                e("checked='checked'");}?>
            /></td></tr>
        </table></td>
        <td><table class="search-page-table">
        <tr><td><label for="similar-link"><?php
            e(tl('pageoptions_element_similar_link')); ?></label></td>
        <td><input id='similar-link'
            type="checkbox" name="SIMILAR_LINK" value="true"
            <?php if(isset($data['SIMILAR_LINK']) &&
                $data['SIMILAR_LINK']){
                e("checked='checked'");}?>
            /></td>
        </tr>
        <tr><td><label for="in-link"><?php
            e(tl('pageoptions_element_in_link')); ?></label></td>
        <td><input id='in-link' type="checkbox"
            name="IN_LINK" value="true"
            <?php if(isset($data['IN_LINK']) && $data['IN_LINK']){
                e("checked='checked'");}?>
            /></td></tr>
        <tr><td><label for="ip-link"><?php
            e(tl('pageoptions_element_ip_link')); ?></label></td>
        <td><input id='ip-link' type="checkbox"
            name="IP_LINK" value="true"
            <?php if(isset($data['IP_LINK']) && $data['IP_LINK']){
                e("checked='checked'");}?>
            /></td>
        </tr>
        <tr><td><label for="use-wordnet"><?php
            e(tl('pageoption_element_wordnet_feature')); ?></label></td>
        <td><input id='use-wordnet' type="checkbox"
            name="USE_WORDNET" value="true"
            onclick="document.getElementById('wordnet-exec')
            .disabled=!this.checked;"
            <?php if(isset($data['USE_WORDNET']) && $data['USE_WORDNET']){
                e("checked='checked'");}?>
            /></td>
        </tr>
        </table></td>
        </tr></table>
        <h2><label for="wordnet-exec"><?php
            e(tl('pageoption_element_wordnet_exec')); ?></label>
        <br/><input type="text" id="wordnet-exec"
            name="WORDNET_EXEC" class="extra-wide-field"
            value="<?php e($data['WORDNET_EXEC']); ?>" />
        </h2>
        <h2><?php e(tl('pageoptions_element_ranking_factors'))?></h2>
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
            $this->view->helper("options")->render("page-type",
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
        <div id="test-results">
        <?php if($data['test_options_active'] != "") { ?>
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
        <?php
        } ?>
        </div>
        </div>
        <?php
        foreach($data['INDEXING_PLUGINS'] as
            $plugin => $plugin_data) {
            $class_name = $plugin."Plugin";
            if($plugin_data['configure']) {
            ?>
                <div class="indexing-plugin-lightbox" id="plugin-<?php
                    e("$plugin"); ?>" >
                <div class="light-content">
                <div class="float-opposite"><a  href="javascript:setDisplay(
                    'plugin-<?php
                    e($plugin) ?>', false);"><?php
                    e(tl('page_element_plugin_back'));
                ?></a></div>
                <?php
                    $plugin_object = new $class_name();
                    $plugin_object->configureView($data);
                ?>
            </div>
            </div>
        <?php
            }
        }
        ?>
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
