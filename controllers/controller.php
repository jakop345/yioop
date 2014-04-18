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
 * @subpackage controller
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Load crawlHash  and timing functions
 */
require_once BASE_DIR."/lib/utility.php";

/**
 * For getting mail message timing statistics if present
 */
require_once BASE_DIR.'/lib/analytics_manager.php';

/**
 * Base class for models which might be used by a Controller
 */
require_once BASE_DIR."/models/model.php";

/**
 * Base class for components which might be used by a Controller
 */
require_once BASE_DIR."/controllers/components/component.php";

/**
 * Base class for views which might be used by a View
 */
require_once BASE_DIR."/views/view.php";
/**
 * Base controller class for all controllers on
 * the SeekQuarry site.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage controller
 */
abstract class Controller
{

    /**
     * Array of instances of views  used by this controller
     * @var array
     */
    var $view_instances = array();

    /**
     * Array of instances of models used by this controller
     * @var array
     */
    var $model_instances;

    /**
     * Says which activities (roughly methods invoke from the web) this
     * controller will respond to
     * @var array
     */
    var $activities = array();

    /**
     * Components are collections of activities (a little like traits) which
     * can be reused.
     *
     * @var array
     */
    var $component_activities = array();

    /**
     *
     */
    var $activity_component = array();

    /**
     * Says which post processing indexing plugins are available
     * @var array
     */
    var $indexing_plugins = array();

    function __construct($indexing_plugins = array())
    {
        global $INDEXED_FILE_TYPES, $COMPONENT_ACTIVITIES;

        foreach($COMPONENT_ACTIVITIES as $component => $activities) {
            foreach($activities as $activity) {
                $this->activity_component[$activity] = $component;
                $this->activities[] = $activity;
            }
        }
        $this->component_activities = $COMPONENT_ACTIVITIES;
        $this->component_instances = array();
        $this->indexing_plugins = $indexing_plugins;
        $this->model_instances = array();
        $this->view_instances = array();
    }

    /**
     * Dynamic loader for Component objects which might live on the current
     * Component
     *
     * @param string $component name of model to return
     */
    function component($component)
    {
        if(!isset($this->component_instances[$component])) {
            if(file_exists(APP_DIR . "/controllers/components/" .
                $component."_component.php")) {
                require_once APP_DIR . "/controllers/components/" .
                    $component."_component.php";
            }  else {
                require_once BASE_DIR . "/controllers/components/" .
                    $component."_component.php";
            }
            $component_name = ucfirst($component)."Component";
            $this->component_instances[$component] = new $component_name($this);
        }
        return $this->component_instances[$component];
    }

    /**
     * Dynamic loader for Model objects which might live on the current
     * Controller
     *
     * @param string $model name of model to return
     */
    function model($model)
    {
        if(!isset($this->model_instances[$model])) {
            if(file_exists(APP_DIR."/models/".$model."_model.php")){
                require_once APP_DIR."/models/".$model."_model.php";
            } else {
                require_once BASE_DIR."/models/".$model."_model.php";
            }
            $model_name = ucfirst($model)."Model";
            $this->model_instances[$model] = new $model_name();
        }
        return $this->model_instances[$model];
    }

    /**
     * Dynamic loader for Plugin objects which might live on the current
     * View
     *
     * @param string $plugin name of Plugin to return
     */
    function plugin($plugin)
    {
        if(!isset($this->plugin_instances[$plugin])) {
            if(file_exists(APP_DIR.
                "/lib/indexing_plugins/".$plugin."_plugin.php")){
                require_once APP_DIR.
                "/lib/indexing_plugins/".$plugin."_plugin.php";
            } else {
                require_once BASE_DIR .
                    "/lib/indexing_plugins/".$plugin."_plugin.php";
            }
            $plugin_name = ucfirst($plugin)."Plugin";
            $this->plugin_instances[$plugin] = new $plugin_name();
        }
        return $this->plugin_instances[$plugin];
    }

    /**
     * Dynamic loader for View objects which might live on the current
     * Controller
     *
     * @param string $view name of view to return
     */
    function view($view)
    {
        if(!isset($this->view_instances[$view])) {
            if(file_exists(APP_DIR."/views/".$view."_view.php")){
                require_once APP_DIR."/views/".$view."_view.php";
            } else {
                require_once BASE_DIR."/views/".$view."_view.php";
            }
            $view_name = ucfirst($view)."View";
            $this->view_instances[$view] = new $view_name();
        }
        return $this->view_instances[$view];
    }

    /**
     *  This function should be overriden to web handle requests
     */
    public abstract function processRequest();

    /**
     *  Send the provided view to output, drawing it with the given
     *  data variable, using the current locale for translation, and
     *  writing mode
     *
     *  @param string $view   the name of the view to draw
     *  @param array $data   an array of values to use in drawing the view
     */
    function displayView($view, $data)
    {
        $data['LOCALE_TAG'] = getLocaleTag();
        $data['LOCALE_DIR'] = getLocaleDirection();
        $data['BLOCK_PROGRESSION'] = getBlockProgression();
        $data['WRITING_MODE'] = getWritingMode();
        if(QUERY_STATISTICS) {
            $data['QUERY_STATISTICS'] = array();
            $machine =  isset($_SERVER["HTTP_HOST"]) ?
                htmlentities($_SERVER["HTTP_HOST"]) : "localhost";
            $machine_uri = isset($_SERVER['REQUEST_URI']) ?
                htmlentities($_SERVER['REQUEST_URI']) : "/";
            $protocol = (isset($_SERVER["HTTPS"])) ? "https://" : "http://";
            if($machine == '::1') { //IPv6 :(
                $machine = "[::1]/";
                //used if the fetching and queue serving on the same machine
            }
            $data['YIOOP_INSTANCE'] = $protocol . $machine . $machine_uri;
            $data['TOTAL_ELAPSED_TIME'] = 0;
            foreach($this->model_instances as $model_name => $model) {
                $data['QUERY_STATISTICS'] = array_merge(
                    $model->db->query_log,
                    $data['QUERY_STATISTICS']
                    );
                $data['TOTAL_ELAPSED_TIME'] +=
                    $model->db->total_time;
            }
            $locale_info = getLocaleQueryStatistics();
            $data['QUERY_STATISTICS'] = array_merge(
                    $locale_info['QUERY_LOG'],
                    $data['QUERY_STATISTICS']
                    );
            $data['TOTAL_ELAPSED_TIME'] +=
                    $locale_info['TOTAL_ELAPSED_TIME'];
            $mail_total_time = AnalyticsManager::get("MAIL_TOTAL_TIME");
            $mail_messages = AnalyticsManager::get("MAIL_MESSAGES");
            if($mail_total_time && $mail_messages) {
                $data['QUERY_STATISTICS'] = array_merge($mail_messages,
                    $data['QUERY_STATISTICS']
                    );
                $data['TOTAL_ELAPSED_TIME'] += $mail_total_time;
            }
        }
        $this->view($view)->render($data);
    }

    /**
     *
     */
     function pagingLogic(&$data, $field_or_model, $output_field,
        $default_show, $search_array = array(), $var_prefix = "", $args = NULL)
     {
        $data_fields = array();
        $r = array();
        $request_fields = array('num_show', 'start_row', 'end_row');
        foreach($request_fields as $field) {
            if(isset($_REQUEST[$var_prefix . $field])) {
                $r[$field] = $_REQUEST[$var_prefix . $field];
            }
        }
        $d = array();
        $data_fields = array('NUM_TOTAL', 'NUM_SHOW', 'START_ROW', 'END_ROW',
            'NEXT_START', 'NEXT_END', 'PREV_START', 'PREV_END');
        $var_field = strtoupper($var_prefix);
        foreach($data_fields as $field) {
            $d[$field] = $var_prefix . $field;
        }
        $num_show = (isset($r['num_show']) &&
            isset($this->view("admin")->helper("pagingtable")->show_choices[
                $r['num_show']])) ? $r['num_show'] : $default_show;
        $data[$d['NUM_SHOW']] = $num_show;
        $data[$d['START_ROW']] = isset($r['start_row']) ?
             max(0, $this->clean($r['start_row'],"int")) : 0;
        if(is_object($field_or_model)) {
            $data[$output_field] = $field_or_model->getRows(
                $data[$d['START_ROW']], $num_show, $num_rows, $search_array,
                $args);
        } else {
            $num_rows = count($data[$field_or_model]);
            if($search_array != array()) {
                $out_data = array();
                foreach($data[$field_or_model] as $name => $field_data) {
                    $checks_passed = true;
                    foreach($search_array as $search_data) {
                        list($column_name, $comparison, $search_value, $sort) =
                            $search_data;
                        if($search_value == "") {continue; }
                        if(isset($args[$column_name])) {
                            $column_name = $args[$column_name];
                        }
                        $row_value = is_object($field_data) ? 
                            $field_data->$column_name:
                            $field_data[$column_name];
                        $cmp = strcmp($search_value, $row_value);
                        if(($cmp == 0 && $comparison == "=") ||
                            ($cmp != 0 && $comparison == "!=")
                            ) {
                            continue;
                        }
                        $pos = strpos($row_value, $search_value);
                        $len_row = strlen($row_value);
                        $len_search = strlen($search_value);
                        if(($comparison == "CONTAINS" && $pos !== false) ||
                            ($comparison == "BEGINS WITH" && $pos === 0) ||
                            ($comparison == "ENDS WITH" && $pos === $len_row -
                            $len_search)) {
                            continue;
                        }
                        $checks_passed = false;
                        break;
                    }
                    if($checks_passed) {
                        $out_data[$name] = $field_data;
                    }
                }
                foreach($search_array as $search_data) {
                    list($column_name, $comparison, $search_value, $sort) =
                        $search_data;
                    if($sort == "NONE") { continue; }
                    if(isset($args[$column_name])) {
                        $column_name = $args[$column_name];
                    }
                    $values = array();
                    foreach($out_data as $name => $field_data) {
                        $values[$name] = is_object($field_data) ? 
                            $field_data->$column_name:
                            $field_data[$column_name];
                    }
                    $sort = ($sort=="DESC") ? SORT_DESC: SORT_ASC;
                    array_multisort($values, $sort, $out_data);
                }
            } else {
                $out_data = $data[$field_or_model];
            }
            $data[$output_field] = array_slice($out_data,
                $data[$d['START_ROW']], $num_show);
        }
        $data[$d['START_ROW']] = min($data[$d['START_ROW']], $num_rows);
        $data[$d['END_ROW']] = min($data[$d['START_ROW']] + $num_show,
            $num_rows);
        if(isset($r['start_row'])) {
            $data[$d['END_ROW']] = max($data[$d['START_ROW']],
                    min($this->clean($r['end_row'],"int"), $num_rows));
        }
        $data[$d['NEXT_START']] = $data[$d['END_ROW']];
        $data[$d['NEXT_END']] = min($data[$d['NEXT_START']] + $num_show,
            $num_rows);
        $data[$d['PREV_START']] = max(0, $data[$d['START_ROW']] - $num_show);
        $data[$d['PREV_END']] = $data[$d['START_ROW']];
        $data['NUM_TOTAL'] = $num_rows;
     }


    /**
     *  Used to invoke an activity method of the current controller or one
     *  its components
     *
     * @param $activity method to invoke
     */
     function call($activity)
     {
        if(isset($this->activity_component[$activity])) {
            return $this->component(
                $this->activity_component[$activity])->$activity();
        }
        return $this->$activity();
     }

    /**
     * Generates a cross site request forgery preventing token based on the
     * provided user name, the current time and the hidden AUTH_KEY
     *
     * @param string $user   username to use to generate token
     * @return string   a csrf token
     */
    function generateCSRFToken($user)
    {
        $time = time();
        $_SESSION['OLD_CSRF_TIME'] = (isset($_SESSION['CSRF_TIME'])) ?
            $_SESSION['CSRF_TIME'] : 0;
        $_SESSION['CSRF_TIME'] = $time;
        return crawlHash($user.$time.AUTH_KEY)."|$time";
    }

    /**
     * Checks if the form CSRF (cross-site request forgery preventing) token
     * matches the given user and has not expired (1 hour till expires)
     *
     * @param string $token_name attribute of $_REQUEST containing CSRFToken
     * @param string $user  username
     * @return bool  whether the CSRF token was valid
     */
    function checkCSRFToken($token_name, $user)
    {
        $token_okay = false;
        if(isset($_REQUEST[$token_name]) &&
            strlen($_REQUEST[$token_name]) == 22) {
            $token_parts = explode("|", $_REQUEST[$token_name]);
            if(isset($token_parts[1]) &&
                $token_parts[1] + Model::ONE_HOUR > time() &&
                crawlHash($user.$token_parts[1].AUTH_KEY) == $token_parts[0]) {
                $token_okay = true;
            }
        }

        return $token_okay;
    }

    /**
     *  @param string $token_name
     */
     function checkCSRFTime($token_name)
     {
        $token_okay = false;
        if(isset($_REQUEST[$token_name])) {
            $token_parts = explode("|", $_REQUEST[$token_name]);
            if(isset($token_parts[1]) && isset($_SESSION['OLD_CSRF_TIME']) &&
                $token_parts[1] == $_SESSION['OLD_CSRF_TIME']) {
                $token_okay = true;
            }
        }

        return $token_okay;
     }

    /**
     * Used to clean strings that might be tainted as originate from the user
     *
     * @param mixed $value tainted data
     * @param string $type type of data in value: one of int, hash, or string
     * @param mixed $default if $value is not set default value is returned,
     *      this isn't used much since if the error_reporting is E_ALL
     *      or -1 you would still get a Notice.
     * @return string the clean input matching the type provided
     */
    function clean($value, $type, $default = NULL)
    {
        $clean_value = NULL;
        switch($type)
        {
            case "boolean":
            case "bool":
                if(isset($value)) {
                    if(!is_bool($value)) {
                        $clean_value = false;
                        if($value == "true" || $value != 0) {
                            $clean_value = true;
                        }
                    }
                } else if ($default != NULL) {
                    $clean_value = $default;
                } else {
                    $clean_value = false;
                }
            break;

            case "int":
                if(isset($value)) {
                    $clean_value = intval($value);
                } else if ($default != NULL) {
                    $clean_value = $default;
                } else {
                    $clean_value = 0;
                }
            break;

            case "float":
                if(isset($value)) {
                    $clean_value = floatval($value);
                } else if ($default != NULL) {
                    $clean_value = $default;
                } else {
                    $clean_value = 0;
                }
            break;

            case "double":
                if(isset($value)) {
                    $clean_value = doubleval($value);
                } else if ($default != NULL) {
                    $clean_value = $default;
                } else {
                    $clean_value = 0;
                }
            break;

            case "hash";
                if(isset($value)) {
                    if(strlen($value) == strlen(crawlHash("A")) &&
                        base64_decode($value)) {
                        $clean_value = $value;
                    }
                } else {
                    $clean_value = $default;
                }
            break;

            case "string":
                if(isset($value)) {
                    $value2 = str_replace("&amp;", "&", $value);
                    $clean_value = @htmlentities($value2, ENT_QUOTES, "UTF-8");
                } else {
                    $clean_value = $default;
                }
            break;
        }

        return $clean_value;
    }
    /**
     * Converts an array of lines of strings into a single string with
     * proper newlines, each line having been trimmed and potentially
     * cleaned
     *
     * @param array $arr the array of lines to be process
     * @param string $endline_string what string should be used to indicate
     *      the end of a line
     * @param bool $clean whether to clean each line
     * @return string a concatenated string of cleaned lines
     */
    function convertArrayLines($arr, $endline_string="\n", $clean = false)
    {
        $output = "";
        $eol = "";
        foreach($arr as $line) {
            $output .= $eol;
            $out_line = trim($line);
            if($clean) {
                $out_line = $this->clean($out_line, "string");
            }
            $output .= trim($out_line);
            $eol = $endline_string;
        }
        return $output;
    }
    /**
     * Cleans a string consisting of lines, typically of urls into an array of
     * clean lines. This is used in handling data from the crawl options
     * text areas.
     *
     * @param string $str contains the url data
     * @param string $line_type does additional cleaning depending on the type
     *      of the lines. For instance, if is "url" then a line not beginning
     *      with a url scheme will have http:// prepended.
     * @return $lines an array of clean lines
     */
    function convertStringCleanArray($str, $line_type="url")
    {
        if($line_type == "url") {
            $pre_lines = preg_split("/(\s)+/", $str);
        } else {
            $pre_lines = preg_split('/\n+/', $str);
        }
        $lines = array();
        foreach($pre_lines as $line) {
            $pre_line = trim($this->clean($line, "string"));
            if(strlen($pre_line) > 0) {
                if($line_type == "url") {
                    $start_line = substr($pre_line, 0, 6);
                    if(!in_array($start_line,
                        array("file:/", "http:/", "domain", "https:"))) {
                        $pre_line = "http://". $pre_line;
                    }
                }
                $lines[] = $pre_line;
            }
        }
        return $lines;
    }
    /**
     * Checks the request if a request is for a valid activity and if it uses
     * the correct authorization key
     *
     * @return bool whether the request was valid or not
     */
    function checkRequest()
    {
        if(!isset($_REQUEST['time']) ||
            !isset($_REQUEST['session']) ||
            !in_array($_REQUEST['a'], $this->activities)) { return; }

        $time = $_REQUEST['time'];
            // request must be within an hour of this machine's clock

        if(abs(time() - $time) > Model::ONE_HOUR) { return false;}

        $session = $_REQUEST['session'];

        if(md5($time . AUTH_KEY) != $session) { return false; }

        return true;
    }
}
?>
