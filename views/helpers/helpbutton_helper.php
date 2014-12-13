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
 * @author Eswara Rajesh Pinapala epinapala@live.com
 * @package seek_quarry
 * @subpackage helper
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/**
 * Load base helper class if needed
 */
require_once BASE_DIR . "/views/helpers/helper.php";
/**
 * This is a helper class is used to
 * draw help button for context sensitive
 * help.
 *
 * @author Eswara Rajesh Pinapala
 * @package seek_quarry
 * @subpackage helper
 */
class HelpbuttonHelper extends Helper
{
    /**
     * The constructor at this point initializes the
     * all the required code for Wiki Help initialization.
     */
    function __construct()
    {
        $this->isHelpInitialized = false;
        $this->localizationdata = NULL;
        $this->back_params = NULL;
        $this->script = NULL;
        parent::__construct();
    }
    /**
     * This method is used to render the help button,
     * given a help point  CSRF token and target controller name.
     *
     * @param  $help_point_id - used to set as help button id
     * @param  $csrf_token_value - CSRF token to make api call/open edit link
     * @param  $target_controller - target controller to remember the view.
     * @return String button html.
     */
    public function render($help_point_id, $csrf_token_value)
    {
        if($this->isHelpInitialized == false) {
            $this->setupHelpParams();
        }
        $is_mobile = MOBILE ? "true" : "false";
        $wiki_group_id = HELP_GROUP_ID;
        $api_controller = "api";
        $api_wiki_action = "wiki";
        $api_wiki_mode = "read";
        return '<button type="button"
                    class="help-button default"
                    data-tl=\'' . $this->localizationdata . '\'
                    data-back-params=\'' . $this->back_params . '\'
                    onclick="javascript:displayHelpForId(this,'
        . $is_mobile . ',\''
        . $this->clean($_REQUEST['c']) . '\',\''
        . $this->clean($_REQUEST['a']) . '\',\''
        . CSRF_TOKEN . '\',\''
        . $csrf_token_value . "','$wiki_group_id','$api_controller',"
        . "'$api_wiki_action','$api_wiki_mode" . '\')" '
        . 'data-pagename="' . $help_point_id . '"> '
        . tl('wiki_question_mark') . '</button>';
    }
    /**
     * Used to clean strings that might be tainted as originate from the user
     *
     * @param mixed $value tainted data
     * @param mixed $default if $value is not set default value is returned,
     *     this isn't used much since if the error_reporting is E_ALL
     *     or -1 you would still get a Notice.
     * @return string the clean input matching the type provided
     */
    private function clean($value, $default = NULL)
    {
        $clean_value = NULL;
        if(isset($value)) {
            $value2 = str_replace("&amp;", "&", $value);
            $clean_value = @htmlentities($value2, ENT_QUOTES, "UTF-8");
        } else {
            $clean_value = $default;
        }
        return $clean_value;
    }
    /**
     * This Helper method is used to setup params needed for Context-Sensitive
     * help to work. This gets executed if there is atleast one help button
     * rendered on the page. This is executed only once with the help of
     * "isHelpInitialized" variable.

     */
    private function setupHelpParams()
    {
        $this->isHelpInitialized = true;
        $this->localizationdata = "{" .
            'wiki_view_edit :"' . tl('wiki_view_edit') . '",' .
            'wiki_view_not_available :"' . tl('wiki_view_not_available') .
            '",' .
            'wiki_view_read :"' . tl('wiki_view_read') . '"' .
            "}";
        $this->back_params = "{";
        /**
         * Use all the GET params to fille the back_params attr
         * this ensures that the user can come back to the exact same url
         * he originated from.
         */
        $back_params_array = array_diff_key($_GET, array_flip(
            array("a", "c", CSRF_TOKEN, "open_help_page")
        ));
        array_walk($back_params_array, array($this, 'clean'));
        $back_params_only_keys = array_keys($back_params_array);
        $last_key = end($back_params_only_keys);
        foreach($back_params_array as $key => $value) {
            $this->back_params .= $key . ' : "' . $value . '"';
            if($key != $last_key) {
                $this->back_params .= ', ';
            }
        }
        $this->back_params .= "}";
        if(isset($_REQUEST['open_help_page'])) {
            $help_page_to_open = $this->clean($_REQUEST['open_help_page']);
            $this->script = 'var matches = '
                . 'document.querySelectorAll(\'[data-pagename="'
                . $help_page_to_open
                . '"]\');' . "\n\t\t"
                . "matches[0].click();"
                . "\n";
        }
    }
}
?>