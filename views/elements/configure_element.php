<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2009 - 2014 Chris Pollett chris@pollett.org
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
 * Element responsible for drawing the screen used to set up the search engine
 *
 * This element has form fields to set up the work directory for crawls,
 * the default language, the debug settings, the database, and the robot
 * identifier information.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage element
 */
class ConfigureElement extends Element
{
    /**
     * Draws the forms used to configure the search engine.
     *
     * This element has two forms on it: One for setting the working directory
     * for crawls, the other to set-up profile information which is mainly
     * stored in the profile.php file in the working directory. The exception
     * is longer data concerning the crawl robot description which is stored
     * in bot.txt. Some elements on forms are not displayed if they are not
     * relevant (for instance, there is no notion of a username for a sqlite
     * database system, but there is for other DBMSs). Also, if the work
     * directory is not properly configured then only the language portion of
     * the profile form is displayed since there is no real place to store data
     * from the latter form until a proper working directory is established.
     *
     * @param array $data holds data on the profile elements which have been
     *     filled in as well as data about which form fields to display
     */
    function render($data)
    {
    ?>
        <div class="current-activity">
        <form id="configureDirectoryForm" method="post"
            action='?c=admin&amp;a=configure&amp;<?php
            e(CSRF_TOKEN."=".$data[CSRF_TOKEN]); ?>' >
        <?php
        if(isset($data['lang'])) { ?>
            <input type="hidden" name="lang" value="<?php
                e($data['lang']); ?>" />
        <?php
        } ?>
        <input type="hidden" name="arg" value="directory" />
        <h2><label for="directory-path"><?php
            e(tl('configure_element_work_directory'))?></label></h2>
        <div  class="top-margin"><input type="text" id="directory-path"
            name="WORK_DIRECTORY"  class="extra-wide-field" value='<?php
                e($data["WORK_DIRECTORY"]); ?>' /><button
                    class="button-box"
                    type="submit"><?php
                    e(tl('configure_element_load_or_create')); ?></button>
        </div>
        </form>
        <form id="configureProfileForm" method="post" action=''>
        <?php if(isset($data['WORK_DIRECTORY'])) { ?>
            <input type="hidden" name="WORK_DIRECTORY" value="<?php
                e($data['WORK_DIRECTORY']); ?>" />
        <?php }?>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="advanced" id='a-settings' value="<?php
            e($data['advanced']); ?>" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
        <input type="hidden" name="a" value="configure" />
        <input type="hidden" name="arg" value="profile" />
        <h2><?php e(tl('configure_element_component_check'))?></h2>
        <div  class="top-margin">
        <?php e($data['SYSTEM_CHECK']); ?>
        </div>
        <h2><?php e(tl('configure_element_profile_settings'))?></h2>
        <?php if($data['PROFILE']) { ?>
        <div class="top-margin">[<a href="javascript:toggleAdvance()"><?php
            e(tl('configure_element_toggle_advanced')); ?></a>]</div>
        <?php } ?>
        <div class="bold">
        <div class="top-margin"><span <?php if(!MOBILE &&
            count($data["LANGUAGES"]) > 3) { ?>
            style="position:relative; top:-3.2em;" <?php } ?>><label
            for="locale"><?php
            e(tl('configure_element_default_language')); ?></label></span>
        <?php $this->view->element("language")->render($data); ?>
        </div>
        <?php if($data['PROFILE']) { ?>
            <div id="advance-configure">
            <div class="top-margin">
            <fieldset class="extra-wide-field"><legend><?php
                e(tl('configure_element_debug_display'))?></legend>
                <label for="error-info"><input id='error-info' type="checkbox"
                    name="ERROR_INFO" value="<?php e(ERROR_INFO);?>"
                    <?php if(($data['DEBUG_LEVEL'] & ERROR_INFO)==ERROR_INFO){
                        e("checked='checked'");}?>
                    /><?php e(tl('configure_element_error_info')); ?></label>
                <label for="query-info"><input id='query-info' type="checkbox"
                    name="QUERY_INFO" value="<?php e(QUERY_INFO);?>"
                    <?php if(($data['DEBUG_LEVEL'] & QUERY_INFO)==QUERY_INFO){
                        e("checked='checked'");}?>/><?php
                        e(tl('configure_element_query_info')); ?></label>
                <label for="test-info"><input id='test-info' type="checkbox"
                    name="TEST_INFO" value="<?php e(TEST_INFO);?>"
                    <?php if(($data['DEBUG_LEVEL'] & TEST_INFO) == TEST_INFO){
                        e("checked='checked'");}?>/><?php
                        e(tl('configure_element_test_info')); ?></label>
            </fieldset>
            </div>
            <div class="top-margin">
            <fieldset class="extra-wide-field"><legend><?php
                e(tl('configure_element_site_access'))?></legend>
                <label for="web-access"><input id='error-info' type="checkbox"
                    name="WEB_ACCESS" value="true"
                    <?php if( $data['WEB_ACCESS']==true){
                        e("checked='checked'");}?>
                    /><?php e(tl('configure_element_web_access')); ?></label>
                <label for="rss-access"><input id='rss-access' type="checkbox"
                    name="RSS_ACCESS" value="true"
                    <?php if($data['RSS_ACCESS']==true){
                        e("checked='checked'");}?>/><?php
                        e(tl('configure_element_rss_access')); ?></label>
                <label for="api-access"><input id='api-access' type="checkbox"
                    name="API_ACCESS" value="true"
                    <?php if($data['API_ACCESS'] == true){
                        e("checked='checked'");}?>/><?php
                        e(tl('configure_element_api_access')); ?></label>
            </fieldset>
            </div>
            </div>
            <div class="top-margin">
            <fieldset><legend><?php
                e(tl('configure_element_crawl_robot'))?></legend>
                <div><b><label for="crawl-robot-name"><?php
                    e(tl('configure_element_robot_name'))?></label></b>
                    <input type="text" id="crawl-robot-name"
                        name="USER_AGENT_SHORT"
                        value="<?php e($data['USER_AGENT_SHORT']); ?>"
                        class="extra-wide-field" />
                </div>
                <div class="top-margin" id='advance-robot'><b><label
                    for="crawl-robot-instance"><?php
                    e(tl('configure_element_robot_instance'))?></label></b>
                    <input type="text" id="crawl-robot-instance"
                        name="ROBOT_INSTANCE"
                        value="<?php e($data['ROBOT_INSTANCE']); ?>"
                        class="extra-wide-field" />
                </div>
                <div class="top-margin"><label for="robot-description"><b><?php
                    e(tl('configure_element_robot_description'));
                    ?></b></label>
                </div>
                <textarea class="tall-text-area" name="ROBOT_DESCRIPTION" ><?php
                    e($data['ROBOT_DESCRIPTION']);
                ?></textarea>
            </fieldset>
            </div>
            <div class="top-margin center">
            <button class="button-box" type="submit"><?php
                e(tl('serversettings_element_submit')); ?></button>
            </div>
            </div>
        <?php } ?>
        </form>
        </div>

    <?php
    }
}
?>
