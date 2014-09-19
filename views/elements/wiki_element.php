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
 * @subpackage view
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/** Loads common constants for web crawling*/
require_once BASE_DIR."/lib/crawl_constants.php";
/**
 * Element responsible for drawing wiki pages in either admin or wiki view
 * It is also responsible for rendering wiki history pages, and listings of
 * wiki pages available for a group
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage element
 */
class WikiElement extends Element implements CrawlConstants
{
    /**
     * Draw a wiki page for group, or, depending on $data['MODE'] a listing
     * of all pages for a group, or the history of revisions of a given page
     * or the edit page form
     *
     * @param array $data fields contain data about the page being
     * displayeed or edited, or the list of pages being displayed.
     */
    function render($data)
    {
        $logged_in = isset($data["ADMIN"]) && $data["ADMIN"];
        $can_edit = $logged_in && isset($data["CAN_EDIT"]) && $data["CAN_EDIT"];
        $is_admin = ($data["CONTROLLER"] == "admin");
        $arrows = ($is_admin) ? "&lt;&lt;" : "&gt;&gt;";
        $other_controller = ($is_admin) ? "group" : "admin";
        $base_query = "?c={$data['CONTROLLER']}";
        $csrf_token = "";
        if($logged_in) {
            $csrf_token = "&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN];
            $base_query .= $csrf_token;
        }
        $base_query .= "&amp;group_id=".$data["GROUP"]["GROUP_ID"];
        $other_base_query = "?c=$other_controller&amp;a=wiki&amp;group_id=".
            $data["GROUP"]["GROUP_ID"]."&amp;arg=".$data['MODE']."&amp;".
            "page_name=".$data['PAGE_NAME'];
        if($logged_in) {
            $other_base_query .= $csrf_token;
            $csrf_token = "&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN];
        }
        if(($is_admin || $logged_in) && (!isset($data['page_type']) ||
            $data['page_type'] != 'presentation')) { ?>
            <div class="float-same admin-collapse">[<a id='arrows-link'
            href="<?php e($other_base_query) ?>" onclick="
            arrows=elt('arrows-link');
            arrows_url = arrows.href;
            caret = (elt('wiki-page').selectionStart) ?
                elt('wiki-page').selectionStart : 0;
            edit_scroll = elt('scroll-top').value= (elt('wiki-page').scrollTop)?
                elt('wiki-page').scrollTop : 0;
            arrows_url += '&amp;caret=' + caret + '&amp;scroll_top=' +
                edit_scroll;
            arrows.href = arrows_url;" ><?php
            e($arrows); ?></a>]</div>
        <?php
        }
        ?>
        <?php
        if($is_admin) {
            e('<div class="current-activity">');
        } else if(isset($data['page_type']) && $data['page_type']
            == 'presentation') {
            e('<div class="presentation-activity">');
        } else {
            e('<div class="small-margin-current-activity">');
        }
        if(isset($data['MEDIA_NAME'])) {
            ?>
            <div class="top-margin"><b><a href="<?php e($base_query .
                '&amp;arg=read&amp;a=wiki&amp;page_name='.
                $data['PAGE_NAME']); ?>"><?php
                e($data['PAGE_NAME']); ?></a></b> : <?php
                $name_parts = pathinfo($data['MEDIA_NAME']);
                e($name_parts['filename']);?>
            </div>
            <?php
        } else if($is_admin) {
            ?>
            <h2><?php
            e($data['GROUP']['GROUP_NAME'].
                "[<a href='?c={$data['CONTROLLER']}".$csrf_token.
                "&amp;a=groupFeeds&amp;just_group_id=".
                $data['GROUP']['GROUP_ID']."'>".tl('groupfeed_element_feed').
                "</a>|".tl('wiki_view_wiki')."]" ); ?></h2>
            <div class="top-margin"><b>
            <?php
            e(tl('wiki_view_page', $data['PAGE_NAME']) . " - [");
            $modes = array();
            if($can_edit) {
                $modes = array(
                    "read" => tl('wiki_view_read'),
                    "edit" => tl('wiki_view_edit')
                );
            }
            $modes["pages"] = tl('wiki_view_pages');
            $bar = "";
            foreach($modes as $name => $translation) {
                if($data["MODE"] == $name) { 
                    e($bar); ?><b><?php e($translation); ?></b><?php
                } else if(!isset($data["PAGE_NAME"]) ||
                    $data["PAGE_NAME"]=="")
                    {
                    e($bar); ?><span class="gray"><?php e($translation);
                    ?></span><?php
                } else {
                    $append = "";
                    if($name != 'pages') {
                        $append = '&amp;page_name='. $data['PAGE_NAME'];
                    }
                    e($bar); ?><a href="<?php e($base_query .
                        '&amp;arg='.$name.'&amp;a=wiki'.$append); ?>"><?php
                        e($translation); ?></a><?php
                }
                $bar = "|";
            }
            ?>]</b>
            </div>
            <?php
        }
        switch($data["MODE"])
        {
            case "edit":
                $this->renderEditPageForm($data);
            break;
            case "pages":
                $this->renderPages($data, $can_edit, $logged_in);
            break;
            case "history":
                $this->renderHistory($data);
            break;
            case "read":
            case "show":
            default:
                $this->renderReadPage($data, $can_edit, $logged_in, $is_admin);
            break;
            case "resources":
                $this->renderResources($data);
            break;
        }
        e('</div>');
    }
    /**
     * Used to draw a Wiki Page for reading. If the page does not exist
     * various create/login-to-create etc messages are displayed depending
     * of it the user is logged in. and has write permissions on the group
     *
     * @param array $data fields PAGE used for page contents
     * @param bool $can_edit whether the current user has permissions to
     *     edit or create this page
     * @param bool $logged_in whethe current user is logged in or not
     */
    function renderReadPage($data, $can_edit, $logged_in, $is_admin)
    {
        if($is_admin &&
            isset($data['PAGE_HEADER']) && isset($data["HEAD"]['page_type']) &&
            $data["HEAD"]['page_type'] != 'presentation') {
            e($data['PAGE_HEADER']);
        }
        if(isset($data["HEAD"]['page_type']) && $data["HEAD"]['page_type'] ==
            'media_list') {
            $this->renderResources($data, true, $logged_in);
        } else if($data["PAGE"]) {
            e($data["PAGE"]);
        } else if($can_edit) {
            e("<h2>".tl("wiki_view_page_no_exist", $data["PAGE_NAME"]).
                "</h2>");
            e("<p>".tl("wiki_view_create_edit")."</p>");
            e("<p>".tl("wiki_view_use_form_below")."</p>");?>
            <form id="editpageForm" method="get" action='#'>
            <input type="hidden" name="c" value="<?php e($data['CONTROLLER']);
                ?>" />
            <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
                e($data[CSRF_TOKEN]); ?>" />
            <input type="hidden" name="a" value="wiki" />
            <input type="hidden" name="arg" value="edit" />
            <input type="hidden" name="group_id" value="<?php
                e($data['GROUP']['GROUP_ID']); ?>" />
            <input type="text" name="page_name" class="narrow-field"
                value="" />
            <button class="button-box" type="submit"><?php
                e(tl('wiki_element_submit')); ?></button>
            </form>
            <?php
            e("<p><a href='?c={$data['CONTROLLER']}&amp;".CSRF_TOKEN.
                "={$data[CSRF_TOKEN]}&amp;group_id=".
                PUBLIC_GROUP_ID. "&amp;arg=read&amp;a=wiki&amp;".
                "page_name=Syntax'>". tl("wiki_view_syntax_summary").
                "</a>.</p>");
        } else if(!$logged_in) {
            e("<h2>".tl("wiki_view_page_no_exist", $data["PAGE_NAME"]).
                "</h2>");
            e("<p>".tl("wiki_view_signin_edit")."</p>");
        } else {
            e("<h2>".tl("wiki_view_page_no_exist", $data["PAGE_NAME"]).
                "</h2>");
        }
        if($is_admin && 
            isset($data['PAGE_FOOTER']) && isset($data["HEAD"]['page_type']) &&
            $data["HEAD"]['page_type'] != 'presentation') {
            e($data['PAGE_FOOTER']);
        }
    }

    /**
     * Used to drawn the form that let's someone edit a wiki page
     *
     * @param array $data fields contain data about the page being
     * edited. In particular, PAGE contains the raw page data
     */
    function renderEditPageForm($data)
    {
        $base_url = "?c=".$data['CONTROLLER']."&amp;a=wiki&amp;".
            CSRF_TOKEN.'='.$data[CSRF_TOKEN]."&amp;group_id=".
            $data['GROUP']['GROUP_ID'];
        ?>
        <div class="float-opposite" style="position:relative; top:35px;">
        [<a href="<?php e($base_url); ?>&amp;<?php
            e('&amp;arg=history&amp;page_id='.$data['PAGE_ID']); ?>"
        ><?php e(tl('wiki_element_history'))?></a>]
        [<a href="?c=<?php e($data['CONTROLLER']); ?>&amp;a=groupFeeds&amp;<?php
            e(CSRF_TOKEN.'='.$data[CSRF_TOKEN].
            '&amp;just_thread='.$data['DISCUSS_THREAD']);?>" ><?php
            e(tl('wiki_element_discuss'))?></a>]
        </div>
        <form id="editpageForm" method="post" action='./'
            onsubmit="elt('caret-pos').value =
            (elt('wiki-page').selectionStart) ?
            elt('wiki-page').selectionStart : 0;
            elt('scroll-top').value= (elt('wiki-page').scrollTop) ?
            elt('wiki-page').scrollTop : 0;" >
            <input type="hidden" name="c" value="<?php e($data['CONTROLLER']); 
            ?>" />
            <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
                e($data[CSRF_TOKEN]); ?>" />
            <input type="hidden" name="a" value="wiki" />
            <input type="hidden" name="arg" value="edit" />
            <input type="hidden" name="group_id" value="<?php
                e($data['GROUP']['GROUP_ID']); ?>" />
            <input type="hidden" name="page_name" value="<?php
                e($data['PAGE_NAME']); ?>" />
            <input type="hidden" name="caret" id="caret-pos"/>
            <input type="hidden" name="scroll_top" id="scroll-top"/>
            <input type="hidden" id="p-settings" name="settings" value="<?php
                e($data['settings']); ?>"/>
            <div class="top-margin">
                <b><?php
                e(tl('wiki_element_locale_name',
                    $data['CURRENT_LOCALE_TAG']));
                ?></b><br />
                <label for="page-data"><b><?php
                e(tl('wiki_element_page', $data['PAGE_NAME']));
                ?></b></label> [<a href="javascript:toggleSettings()"><?php
            e(tl('configure_element_toggle_page_settings')); ?></a>]
            </div>
            <div id='page-settings'>
            <div class="top-margin">
            <label for="page-type"><b><?php
            e(tl('wiki_element_page_type'));
            ?></b></label><?php
            $this->view->helper("options")->render("page-type","page_type",
                $data['page_types'], $data['current_page_type']);
            ?>
            </div>
            <div class="top-margin">
            <label for="page-title"><b><?php
            e(tl('wiki_element_title'));
            ?></b></label><input type="text" id='page-title'
                name="title" value="<?php e($data['title']);?>"
                maxlength="<?php e(SHORT_TITLE_LEN); ?>" class="wide-field"/>
            </div>
            <div class="top-margin">
            <label for="meta-author"><b><?php
            e(tl('wiki_element_meta_author'));
            ?></b></label><input type="text" id='meta-author'
                name="author" value="<?php e($data['author']);?>"
                maxlength="<?php e(LONG_NAME_LEN); ?>" class="wide-field"/>
            </div>
            <div class="top-margin">
            <label for="meta-robots"><b><?php
            e(tl('wiki_element_meta_robots'));
            ?></b></label><input type="text" id='meta-robots'
                name="robots" value="<?php e($data['robots']);?>"
                maxlength="<?php e(LONG_NAME_LEN); ?>" class="wide-field"/>
            </div>
            <div class="top-margin">
            <label for="meta-description"><b><?php
            e(tl('wiki_element_meta_description'));
            ?></b></label>
            </div>
            <textarea id="meta-description" class="short-text-area"
                name="description" data-buttons='none'><?php
                e($data['description']);
            ?></textarea>
            <div class="top-margin">
            <label for="page-header"><b><?php
            e(tl('wiki_element_page_header'));
            ?></b></label><input type="text" id='page-header'
                name="page_header" value="<?php e($data['page_header']);?>"
                maxlength="<?php e(SHORT_TITLE_LEN); ?>" class="wide-field"/>
            </div>
            <div class="top-margin">
            <label for="page-footer"><b><?php
            e(tl('wiki_element_page_footer'));
            ?></b></label><input type="text" id='page-footer'
                name="page_footer" value="<?php e($data['page_footer']);?>"
                maxlength="<?php e(SHORT_TITLE_LEN); ?>" class="wide-field"/>
            </div>
            </div>
            <div id='page-container'><textarea id="wiki-page"
                class="tall-text-area" name="page"
                <?php 
                if((!isset($data['page_type']) ||
                        $data['page_type'] != 'presentation')){
                    $data_buttons = 'all,!wikibtn-slide';
                }else{
                    $data_buttons = 'all';
                }?>
                data-buttons='<?php e($data_buttons); ?>' ><?php
                e($data['PAGE']);
            ?></textarea>
            <div class="green"><?php
            e(tl('wiki_element_archive_info'));
            ?></div>
            <div class="top-margin">
            <label for="edit-reason"><b><?php
            e(tl('wiki_element_edit_reason'));
            ?></b></label><input type="text" id='edit-reason'
                name="edit_reason" value=""
                maxlength="<?php e(SHORT_TITLE_LEN); ?>" class="wide-field"/>
            </div>
            </div>
            <div id="save-container" class="top-margin center">
            <button class="button-box" type="submit"><?php
                e(tl('wiki_element_savebutton')); ?></button>
            </div>
        </form>
        <div class="top-margin" id="media-list-page">
        <h2><?php e(tl('wiki_element_media_list'))?></h2>
        <p><?php e(tl('wiki_element_ml_description'))?></p>
        </div>
        <div>
        <form id="resourceUploadForm" method="post" action='./'
            enctype="multipart/form-data">
        <input type="hidden" name="c" value="<?php e($data['CONTROLLER']);
            ?>" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
        <input type="hidden" name="a" value="wiki" />
        <input type="hidden" name="arg" value="edit" />
        <input type="hidden" name="group_id" value="<?php
            e($data['GROUP']['GROUP_ID']); ?>" />
        <input type="hidden" name="page_name" value="<?php
            e($data['PAGE_NAME']); ?>" />
        <input type="hidden" name="settings" value="<?php
            e($data['settings']); ?>" />
        <h3><?php e(tl('wiki_view_page_resources'));?></h3>
        <p><?php e(tl('wiki_view_resources_info'));?></p>
        <div><input type="file" class="slight-pad wide-field"
            id='page-resource' name='page_resource' />
            <button class="button-box" type="submit"><?php
            e(tl('wiki_view_upload')); ?></button></div>
        </form>
        <h3 id="progress-bar" class="red indent"></h3>
        <?php
            $this->renderResources($data, false);
        ?>
        </div>
        <script type="text/javascript">
        function addToPage(resource_name)
        {
            wikify("{{resource:","|<?php
                e(tl('wiki_element_resource_description'));
                ?>}}", resource_name, "wiki-page");
        }
        function checkUploadResource()
        {
            var max_resource_size = <?php e(metricToInt(
                ini_get('upload_max_filesize'))); ?>;
            var page_resource = elt('page-resource').files[0];
            if(page_resource.size > max_resource_size) {
                doMessage('<h1 class=\"red\" ><?php
                    e(tl("wiki_element_file_too_big", metricToInt(
                        ini_get('upload_max_filesize'))));
                    ?></h1>');
                return false;
            }
            return true;
        }
        function uploadForm(event)
        {
            if(!checkUploadResource()) {
                event.preventDefault();
                return;
            }
            var resource_form = elt('resourceUploadForm');
            var form_data = new FormData(resource_form);
            var request = new XMLHttpRequest();
            request.upload.addEventListener("progress", uploadProgress, false);
            request.addEventListener("load", uploadComplete, false);
            request.addEventListener("error", uploadFailed, false);
            request.addEventListener("abort", uploadCanceled, false);
            request.open("POST", "./");
            request.send(form_data);
            event.preventDefault();
        }
        function uploadProgress(event)
        {
            var progress = elt('progress-bar');
            if(event.lengthComputable) {
                var percent_complete =
                    Math.round(event.loaded * 100 / event.total);
                progress.innerHTML = '<?php
                    e(tl('wiki_element_upload_progress')) ?>' +
                    percent_complete.toString() + '%';
            } else {
                progress.innerHTML = '<?php
                    e(tl("wiki_element_progress_meter_disabled")); ?>';
            }
        }
        function uploadComplete(event)
        {
            /* This event is raised when the server send back a response */
            document.open();
            document.write(event.target.responseText);
            document.close();
        }

        function uploadFailed(event)
        {
            doMessage('<h1 class=\"red\" ><?php
                e(tl("wiki_element_upload_error")); ?></h1>');
        }

        function uploadCanceled(event) 
        {
            doMessage('<h1 class=\"red\" ><?php
                e(tl("wiki_element_upload_cancelled")); ?></h1>');
        }
        var resource_form = document.getElementById('resourceUploadForm');
        resource_form.addEventListener('submit', uploadForm);
        </script>
        <?php
    }
    /**
     *  Draws a list of media resources associated with a wiki page
     *
     *  @param array $data fields RESOURCES_INFO contains info on resources
     *  @param bool $read_mode whether the readering should be for a media
     *      list in read mode or for use on the edit task of any wiki page
     *  @param bool $logged_in whether the user is currently logged in or not
     */
    function renderResources($data, $read_mode, $logged_in = true)
    {
        if(isset($data['RESOURCES_INFO']) && $data['RESOURCES_INFO']) {
            $base_url = "?c=".$data['CONTROLLER']."&amp;a=wiki".
               "&amp;group_id=".$data['GROUP']['GROUP_ID'];
            if($logged_in) {
                $base_url .= "&amp;".CSRF_TOKEN.'='.$data[CSRF_TOKEN];
            }
            $url_prefix = $data['RESOURCES_INFO']['url_prefix'];
            if($read_mode) {
                $url_prefix = $base_url. "&amp;arg=media&amp;page_id=".
                    $data["PAGE_ID"];
            } else {
                $base_url .= "&amp;settings=".$data['settings'];
            }
            $thumb_prefix = $data['RESOURCES_INFO']['thumb_prefix'];
            $default_thumb = $data['RESOURCES_INFO']['default_thumb'];
            if(count($data['RESOURCES_INFO']['resources']) > 0) {
                e('<table >');
                $seen_resources = array();
                foreach($data['RESOURCES_INFO']['resources'] as $resource) {
                    $name = $resource['name'];
                    $name_parts = pathinfo($name);
                    $file_name = $name_parts['filename'];
                    if($read_mode && isset($seen_resources[$file_name])) {
                        continue;
                    }
                    $seen_resources[$file_name] = true;
                    if(!$read_mode) {
                        $file_name = $name;
                    }
                    e("<tr class='resource-list' >");
                    if($resource['has_thumb']) {
                        e("<td><img src='$thumb_prefix&amp;n=$name' alt='' />".
                            "</td>");
                    } else {
                        e("<td><img src='".$default_thumb."'  alt='' /></td>");
                    }
                    e("<td><a href='$url_prefix&amp;n=$name'>".
                        "$file_name</a></td>");
                    if(!$read_mode && (!isset($data['page_type']) ||
                        $data['page_type'] != 'media_list')) {
                        e("<td><button onclick='javascript:addToPage(\"".
                            $name."\")'>".tl('wiki_element_add_to_page').
                            "</button></td>");
                    }
                    if(!$read_mode) {
                        e("<td>[<a href='$base_url&amp;arg=edit&amp;page_name=".
                            $data['PAGE_NAME'].
                            "&amp;delete=$name'>X</a>]</td>");
                    }
                    e("</tr>");
                }
                e('</table>');
            }
        }
    }
    /**
     * Used to draw a list of Wiki Pages for the current group. It also
     * draws a search form and can be used to create pages
     *
     * @param array $data fields for the current controller, CSRF_TOKEN
     *     etc needed to render the search for and paging queries
     * @param bool $can_edit whether the current user has permissions to
     *     edit or create this page
     * @param bool $logged_in whethe current user is logged in or not
     */
    function renderPages($data, $can_edit, $logged_in)
    {
        $append_url = ($logged_in) ?
            "&amp;".CSRF_TOKEN."=". $data[CSRF_TOKEN] : "";
        $base_query = "?c={$data['CONTROLLER']}&amp;group_id=".
                $data["GROUP"]["GROUP_ID"]."&amp;a=wiki$append_url";
        $create_query = $base_query . "&amp;arg=edit&amp;page_name=" .
            $data["FILTER"];
        $base_query .= "&amp;arg=read";
        $paging_query = "?c={$data['CONTROLLER']}$append_url&amp;group_id=".
                $data["GROUP"]["GROUP_ID"]."&amp;a=wiki&amp;arg=pages";
        e("<h2>".tl("wiki_view_wiki_page_list",
            $data["GROUP"]["GROUP_NAME"]). "</h2>");
        ?>
        <form id="editpageForm" method="get" action='#'>
        <input type="hidden" name="c" value="<?php e($data['CONTROLLER']); 
            ?>" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
        <input type="hidden" name="a" value="wiki" />
        <input type="hidden" name="arg" value="pages" />
        <input type="hidden" name="group_id" value="<?php
            e($data['GROUP']['GROUP_ID']); ?>" />
        <input type="text" name="filter" class="extra-wide-field"
            maxlength="<?php e(SHORT_TITLE_LEN); ?>"
            placeholder="<?php e(tl("wiki_view_filter_or_create")); 
            ?>" value="<?php e($data['FILTER'])?>" />
        <button class="button-box" type="submit"><?php
            e(tl('wiki_element_go')); ?></button>
        </form>
        <?php
        if($data["FILTER"] != "") {
            e("<a href='$create_query'>".tl("wiki_view_create_page",
                $data['FILTER']) . "</a>");
        }
        ?>
        <div>&nbsp;</div>
        <?php
        if($data['PAGES'] != array()) {
            foreach($data['PAGES'] as $page) {
                ?>
                <div class='group-result'>
                <a href="<?php e($base_query.'&amp;page_name='.
                    $page['TITLE']);?>" ><?php e($page["TITLE"]); ?></a></br />
                <?php e(strip_tags($page["DESCRIPTION"])."..."); ?>
                </div>
                <div>&nbsp;</div>
                <?php
            }
            $this->view->helper("pagination")->render(
                $paging_query,
                $data['LIMIT'], $data['RESULTS_PER_PAGE'], $data['TOTAL_ROWS']);
        }
        if($data['PAGES'] == array()) {
            e('<div>'.tl('wiki_view_no_pages', "<b>".getLocaleTag()."</b>").
                '</div>');
        }
    }

    /**
     * Used to draw the revision history page for a wiki document
     * Has a form that can be used to draw the diff of two revisions
     *
     * @param array $data fields contain info about revisions of a Wiki page
     */
    function renderHistory($data)
    {
        $base_query = "?c={$data['CONTROLLER']}&amp;".CSRF_TOKEN."=".
            $data[CSRF_TOKEN] . "&amp;group_id=".
            $data["GROUP"]["GROUP_ID"]."&amp;a=wiki";
        ?>
        <div class="float-opposite"><a href="<?php e($base_query .
                    '&amp;arg=edit&amp;a=wiki&amp;page_name='.
                    $data['PAGE_NAME']); ?>"><?php
            e(tl("wiki_view_back")); ?></a></div>
        <?php
        if(count($data['HISTORY']) > 1) { ?>
            <div>
            <form id="differenceForm" method="get" action='#'>
            <input type="hidden" name="c" value="group" />
            <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
                e($data[CSRF_TOKEN]); ?>" />
            <input type="hidden" name="a" value="wiki" />
            <input type="hidden" name="arg" value="history" />
            <input type="hidden" name="group_id" value="<?php
                e($data['GROUP']['GROUP_ID']); ?>" />
            <input type="hidden" name="page_id" value="<?php 
                e($data["page_id"]); ?>" />
            <input type="hidden" name="diff" value="1" />
            <b><?php e(tl('wiki_view_difference')); ?></b>
            <input type="text" id="diff-1" name="diff1"
                value="<?php  e($data['diff1']); ?>" /> -
            <input type="text" id="diff-2" name="diff2"
                value="<?php  e($data['diff2']); ?>" />
            <button class="button-box" type="submit"><?php
                e(tl('wiki_view_go')); ?></button>
            </form>
            </div>
            <?php
        }
        ?>
        <div>&nbsp;</div>
        <?php
        $time = time();
        $feed_helper = $this->view->helper("feeds");
        $base_query .= "&amp;arg=history&amp;page_id=".$data["page_id"];
        $current = $data['HISTORY'][0]["PUBDATE"];
        $first = true;
        foreach($data['HISTORY'] as $item) {
            ?>
            <div class='group-result'>
            <?php
            if(count($data['HISTORY']) > 1) { ?>
                (<a href="javascript:updateFirst('<?php
                        e($item['PUBDATE']); ?>');" ><?php
                    e(tl("wiki_view_diff_first"));
                    ?></a> | <a href="javascript:updateSecond('<?php
                        e($item['PUBDATE']);?>');" ><?php
                    e(tl("wiki_view_diff_second"));
                    ?></a>)
                <?php
            } else { ?>
                (<b><?php e(tl("wiki_view_diff_first"));
                    ?></b> | <b><?php e(tl("wiki_view_diff_second"));
                    ?></b>)
                <?php
            }
            e("<a href='$base_query&show={$item['PUBDATE']}'>" .
                date("c",$item["PUBDATE"])."</a>. <b>{$item['PUBDATE']}</b>. ");
            e(tl("wiki_view_edited_by", $item["USER_NAME"]));
            if(strlen($item["EDIT_REASON"]) > 0) {
                e("<i>{$item["EDIT_REASON"]}</i>. ");
            }
            e(tl("wiki_view_page_len", $item["PAGE_LEN"])." ");
            if($first && $data['LIMIT'] == 0) {
                e("[<b>".tl("wiki_view_revert")."</b>].");
            } else { 
                e("[<a href='$base_query&amp;revert=".$item['PUBDATE'].
                "'>".tl("wiki_view_revert")."</a>].");
            }
            $first = false;
            $next = $item['PUBDATE'];
            ?>
            </div>
            <div>&nbsp;</div>
            <?php
        }
        $this->view->helper("pagination")->render(
            $base_query,
            $data['LIMIT'], $data['RESULTS_PER_PAGE'], $data['TOTAL_ROWS']);
        ?>
        <script type="text/javascript">
        function updateFirst(val)
        {
            elt('diff-1').value=val;
        }
        function updateSecond(val)
        {
            elt('diff-2').value=val;
        }
        </script>
        <?php
    }
}
