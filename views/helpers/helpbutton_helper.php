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
        $this->backParams = NULL;
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
        if ($this->isHelpInitialized == false) {
            //Set only once.
            $this->isHelpInitialized = true;
            $this->localizationdata = "{" .
                    'wiki_view_edit :"' . tl('wiki_view_edit') . '",' .
                    'wiki_view_read :"' . tl('wiki_view_read') . '"' .
                    "}";
            $this->backParams = "{";
            $back_params_array = array_diff_key($_REQUEST, array_flip(
                array("a", "c", "u", "p", CSRF_TOKEN, "open_help_page")
            ));
            array_walk($back_params_array, array($this, 'clean'));
            $back_params_only_keys = array_keys($back_params_array);
            $last_key = end($back_params_only_keys);
            foreach ($back_params_array as $key => $value) {
                $this->backParams .= $key . ' : "' . $value . '"';
                if ($key != $last_key) {
                    $this->backParams .= ',';
                }
            }
            $this->backParams .= "}";
        }
        $is_mobile = MOBILE ? "true" : "false";
        $wiki_group_id = HELP_GROUP_ID;
        $api_controller = "api";
        $api_wiki_action = "wiki";
        $api_wiki_mode = "read";
        return '<button type="button"
                    class="help-button default"
                    data-tl=\'' . $this->localizationdata . '\'
                    data-back-params=\'' . $this->backParams . '\'
                    onclick="javascript:displayHelpForId(this,'
                . $is_mobile . ',\''
                . $_REQUEST['c'] . '\',\''
                . $_REQUEST['a'] . '\',\''
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
    private function clean($value, $default = NULL) {
        if (isset($value)) {
            $value2 = str_replace("&amp;", "&", $value);
            $clean_value = @htmlentities($value2, ENT_QUOTES, "UTF-8");
        } else {
            $clean_value = $default;
        }
    }

}
?>