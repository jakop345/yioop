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
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 *  END LICENSE
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage component
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * This component is used to handle activities related to the configuration
 * of a Yioop installation, translations of text appearing in the installation,
 * as well as control of specifying what machines make up the installation
 * and which processes they run.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage component
 */
class SystemComponent extends Component
{
    /**
     * Handles admin request related to the managing the machines which perform
     *  crawls
     *
     * With this activity an admin can add/delete machines to manage. For each
     * managed machine, the admin can stop and start fetchers/queue_servers
     * as well as look at their log files
     *
     * @return array $data MACHINES, their MACHINE_NAMES, data for
     *      FETCHER_NUMBERS drop-down
     */
    function manageMachines()
    {
        $parent = $this->parent;
        $machine_model = $parent->model("machine");
        $profile_model = $parent->model("profile");
        $data = array();
        $data["ELEMENT"] = "managemachines";
        $possible_arguments = array("addmachine", "deletemachine",
            "newsmode", "log", "update");
        $data['SCRIPT'] = "doUpdate();";
        $data["leftorright"]=(getLocaleDirection() == 'ltr') ? "right": "left";
        $data['MACHINE_NAMES'] = array();
        $data['FETCHER_NUMBERS'] = array(
            0 => 0,
            1 => 1,
            2 => 2,
            3 => 3,
            4 => 4,
            5 => 5,
            6 => 6,
            7 => 7,
            8 => 8,
            16 => 16
        );

        $tmp = tl('system_component_select_machine');

        if(!isset($_REQUEST["has_queue_server"]) ||
            isset($_REQUEST['is_replica'])) {
            $_REQUEST["has_queue_server"] = false;
        }
        if(isset($_REQUEST['is_replica'])) {
            $_REQUEST['num_fetchers'] = 0;
        } else {
            $_REQUEST['parent'] = "";
        }
        $request_fields = array(
            "name" => "string",
            "url" => "string",
            "has_queue_server" => "bool",
            "num_fetchers" => "int",
            "parent" => "string"
        );
        $r = array();

        $allset = true;
        foreach($request_fields as $field => $type) {
            if(isset($_REQUEST[$field])) {
                $r[$field] = $parent->clean($_REQUEST[$field], $type);
                if($field == "url" && isset($r[$field][strlen($r[$field])-1]) &&
                    $r[$field][strlen($r[$field])-1]
                    != "/") {
                    $r[$field] .= "/";
                }
            } else {
                $allset = false;
            }
        }
        if(isset($r["num_fetchers"]) &&
            in_array($r["num_fetchers"], $data['FETCHER_NUMBERS'])) {
            $data['FETCHER_NUMBER'] = $r["num_fetchers"];
        } else {
            $data['FETCHER_NUMBER'] = 0;
            if(isset($r["num_fetchers"])) {
                $r["num_fetchers"] = 0;
            }
        }
        $machine_exists = (isset($r["name"]) &&
            $machine_model->checkMachineExists("NAME", $r["name"]) ) ||
            (isset($r["url"]) && $machine_model->checkMachineExists("URL",
            $r["url"]) );

        if(isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch($_REQUEST['arg'])
            {
                case "addmachine":
                    if($allset == true && !$machine_exists) {
                        $machine_model->addMachine(
                            $r["name"], $r["url"], $r["has_queue_server"],
                            $r["num_fetchers"], $r["parent"]);

                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('system_component_machine_added').
                            "</h1>');";
                        $data['MACHINE_NAMES'][] = $r["name"];
                        $data['DELETABLE_MACHINES'][$r["name"]] = $r["name"];
                        sort($data['MACHINE_NAMES']);
                    } else if ($allset && $machine_exists ) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('system_component_machine_exists').
                            "</h1>');";
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('system_component_machine_incomplete').
                            "</h1>');";
                    }
                break;

                case "deletemachine":
                    if(!$machine_exists) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('system_component_machine_doesnt_exists').
                            "</h1>');";
                    } else {
                        $machines = $machine_model->getRows(0, 1,
                            $total_rows, array(
                                array("name", "=", $r["name"], "")));
                        $service_in_use = false;
                        foreach($machines as $machine) {
                            if($machine['NAME'] == $r["name"]) {
                                if($machine['STATUSES'] != array()) {
                                    $service_in_use = true;
                                    break;
                                } else {
                                    break;
                                }
                            }
                        }
                        if($service_in_use) {
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                           tl('system_component_stop_service_first')."</h1>');";
                            break;
                        }
                        $machine_model->deleteMachine($r["name"]);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('system_component_machine_deleted')."</h1>');";
                    }
                break;

                case "newsmode":
                    $profile =  $profile_model->getProfile(
                        WORK_DIRECTORY);
                    $news_modes = array("news_off", "news_web", "news_process");
                    if(isset($_REQUEST['news_mode']) && in_array(
                        $_REQUEST['news_mode'], $news_modes)) {
                        $profile["NEWS_MODE"] = $_REQUEST['news_mode'];
                        if($profile["NEWS_MODE"] != "news_process") {
                            CrawlDaemon::stop("news_updater", "", false);
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                                tl('system_component_news_mode_updated').
                                "</h1>');";
                        } else {
                            CrawlDaemon::start("news_updater", 'none', "",
                                -1);
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                                tl('system_component_news_mode_updated').
                                "</h1>');";
                        }
                        $profile_model->updateProfile(
                            WORK_DIRECTORY, array(), $profile);
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('system_component_news_update_failed').
                            "</h1>');";
                    }
                break;

                case "log":
                    if(isset($_REQUEST["fetcher_num"])) {
                        $r["fetcher_num"] =
                            $parent->clean($_REQUEST["fetcher_num"], "int");
                    }
                    if(isset($_REQUEST["mirror_name"])) {
                        $r["mirror_name"] =
                            $parent->clean($_REQUEST["mirror_name"], "string");
                    }
                    if(isset($_REQUEST["time"])) {
                        $data["time"] =
                            $parent->clean($_REQUEST["time"], "int") + 30;
                    } else {
                        $data["time"] = 30;
                    }
                    if(isset($_REQUEST["NO_REFRESH"])) {
                        $data["NO_REFRESH"] = $parent->clean(
                            $_REQUEST["NO_REFRESH"], "bool");
                    } else {
                        $data["NO_REFRESH"] = false;
                    }
                    $data["ELEMENT"] = "machinelog";
                    $filter= "";
                    if(isset($_REQUEST['f'])) {
                        $filter =
                            $parent->clean($_REQUEST['f'], "string");
                    }
                    $data['filter'] = $filter;
                    $data["REFRESH_LOG"] = "&time=". $data["time"];
                    $data["LOG_TYPE"] = "";
                    if(isset($r['fetcher_num']) && isset($r['name'])) {
                        $data["LOG_FILE_DATA"] = $machine_model->getLog(
                            $r["name"], $r["fetcher_num"], $filter);
                        $data["LOG_TYPE"] = $r['name'].
                            " fetcher ".$r["fetcher_num"];
                        $data["REFRESH_LOG"] .= "&arg=log&name=".$r['name'].
                            "&fetcher_num=".$r['fetcher_num'];
                    } else if(isset($r["mirror_name"])) {
                        $data["LOG_TYPE"] = $r['mirror_name']." mirror";
                        $data["LOG_FILE_DATA"] = $machine_model->getLog(
                            $r["mirror_name"], NULL, $filter,  true);
                    } else if(isset($r['name'])) {
                        $data["LOG_TYPE"] = $r['name']." queue_server";
                        if($r['name'] == "news") {
                            $data["LOG_TYPE"] = "Name Server News Updater";
                        }
                        $data["LOG_FILE_DATA"] = $machine_model->getLog(
                            $r["name"], NULL, $filter);
                        $data["REFRESH_LOG"] .=
                            "&arg=log&name=".$r['name'];
                    }
                    if($data["time"] >= 1200) {
                        $data["REFRESH_LOG"] = "";
                    }

                    if(!isset($data["LOG_FILE_DATA"])
                        || $data["LOG_FILE_DATA"] == ""){
                        $data["LOG_FILE_DATA"] =
                            tl('system_component_no_machine_log');
                    }
                    $lines =array_reverse(explode("\n",$data["LOG_FILE_DATA"]));
                    $data["LOG_FILE_DATA"] = implode("\n", $lines);
                break;

                case "update":
                    if(isset($_REQUEST["fetcher_num"])) {
                        $r["fetcher_num"] =
                            $parent->clean($_REQUEST["fetcher_num"], "int");
                    } else {
                        $r["fetcher_num"] = NULL;
                    }
                    $available_actions = array("start", "stop",
                        "mirror_start", "mirror_stop");
                    if(isset($r["name"]) && isset($_REQUEST["action"]) &&
                        in_array($_REQUEST["action"], $available_actions)) {
                        $action = $_REQUEST["action"];
                        $is_mirror = false;
                        if($action == "mirror_start") {
                            $action = "start";
                            $is_mirror = true;
                        } else if ($action == "mirror_stop") {
                            $action = "stop";
                            $is_mirror = true;
                        }
                        $machine_model->update($r["name"],
                            $action, $r["fetcher_num"], $is_mirror);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('system_component_machine_servers_updated').
                            "</h1>');";
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('system_component_machine_no_action').
                            "</h1>');";
                    }
                break;
            }
        }
        $parent->pagingLogic($data, $machine_model, "MACHINE",
            DEFAULT_ADMIN_PAGING_NUM);
        if(!isset($_REQUEST['arg']) || $_REQUEST['arg'] != 'log') {
            $data['SCRIPT'] .= "toggleReplica(false);";
        }
        return $data;
    }
    /**
     * Handles admin request related to the manage locale activity
     *
     * The manage locale activity allows a user to add/delete locales, view
     * statistics about a locale as well as edit the string for that locale
     *
     * @return array $data info about current locales, statistics for each
     *      locale as well as potentially the currently set string of a
     *      locale and any messages about the success or failure of a
     *      sub activity.
     */
    function manageLocales()
    {
        $parent = $this->parent;
        $locale_model = $parent->model("locale");
        $possible_arguments = array("addlocale", "deletelocale", "editlocale",
            "editstrings", "search");
        $search_array = array(array("tag", "", "", "ASC"));
        $data['SCRIPT'] = "";
        $data["ELEMENT"] = "managelocales";
        $data['CURRENT_LOCALE'] = array("localename" => "",
            'localetag' => "", 'writingmode' => '-1');
        $data['WRITING_MODES'] = array(
            -1 => tl('system_component_select_mode'),
            "lr-tb" => "lr-tb",
            "rl-tb" => "rl-tb",
            "tb-rl" => "tb-rl",
            "tb-lr" => "tb-lr"
        );
        $data['FORM_TYPE'] = "addlocale";
        $paging = true;
        if(isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            $clean_fields = array('localename', 'localetag', 'writingmode',
                'selectlocale');
            foreach($clean_fields as $field) {
                $$field = "";
                if(isset($_REQUEST[$field])) {
                    $tmp = $parent->clean($_REQUEST[$field], "string");
                    if($field == "writingmode" && ($tmp == -1 ||
                        !isset($data['WRITING_MODES'][$tmp]))) {
                        $tmp = "lr-tb";
                    }
                    $$field = $tmp;
                }
            }
            switch($_REQUEST['arg'])
            {
                case "addlocale":
                    $locale_model->addLocale(
                        $localename, $localetag, $writingmode);
                    $locale_model->extractMergeLocales();
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('system_component_locale_added')."</h1>')";
                break;

                case "deletelocale":
                    if(!$locale_model->checkLocaleExists($selectlocale)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('system_component_localename_doesnt_exists').
                            "</h1>')";
                        return $data;
                    }
                    $locale_model->deleteLocale($selectlocale);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('system_component_localename_deleted')."</h1>')";
                break;

                case "editlocale":
                    if(!$locale_model->checkLocaleExists($selectlocale)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('system_component_localename_doesnt_exists').
                            "</h1>')";
                        return $data;
                    }
                    $data['FORM_TYPE'] = "editlocale";
                    $info = $locale_model->getLocaleInfo($selectlocale);
                    $change = false;
                    if(isset($localetag) && $localetag != "") {
                        $info["LOCALE_TAG"] = $localetag;
                        $change = true;
                    }
                    if(isset($writingmode) && $writingmode != "") {
                        $info["WRITING_MODE"] = $writingmode;
                        $change = true;
                    }
                    if(isset($localetag))
                    $data['CURRENT_LOCALE']['localename'] =
                        $info["LOCALE_NAME"];
                    $data['CURRENT_LOCALE']['localetag'] =
                        $selectlocale;
                    $data['CURRENT_LOCALE']['writingmode'] =
                        $info["WRITING_MODE"];
                    if($change) {
                        $locale_model->updateLocaleInfo($info);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('system_component_locale_updated').
                            "</h1>')";
                    }
                break;

                case "editstrings":
                    if(!isset($selectlocale)) break;
                    $paging = false;
                    $data["leftorright"] =
                        (getLocaleDirection() == 'ltr') ? "right": "left";
                    $data["ELEMENT"] = "editlocales";
                    $data['CURRENT_LOCALE_NAME'] =
                        $locale_model->getLocaleName($selectlocale);
                    $data['CURRENT_LOCALE_TAG'] = $selectlocale;
                    if(isset($_REQUEST['STRINGS'])) {
                        $safe_strings = array();
                        foreach($_REQUEST['STRINGS'] as $key => $value) {
                            $clean_key = $parent->clean($key, "string" );
                            $clean_value = $parent->clean($value, "string");
                            $safe_strings[$clean_key] = $clean_value;
                        }
                        $locale_model->updateStringData(
                            $selectlocale, $safe_strings);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('system_component_localestrings_updated').
                            "</h1>')";
                    } else {
                        $locale_model->extractMergeLocales();
                    }
                    $data['STRINGS'] =
                        $locale_model->getStringData($selectlocale);
                    $data['DEFAULT_STRINGS'] =
                        $locale_model->getStringData(DEFAULT_LOCALE);
                    $data['show'] = "all";
                    $data["show_strings"] =
                        array("all" => tl('system_component_all_strings'),
                            "missing" => tl('system_component_missing_strings')
                        );
                    if(isset($_REQUEST['show']) &&
                        $_REQUEST['show'] == "missing") {
                        $data["show"]= "missing";
                        foreach($data['STRINGS'] as $string_id => $translation){
                            if($translation != "") {
                                unset($data['STRINGS'][$string_id]);
                                unset($data['DEFAULT_STRINGS'][$string_id]);
                            }
                        }
                    }
                    $data["filter"] = "";
                    if(isset($_REQUEST['filter']) && $_REQUEST['filter']) {
                        $filter = $parent->clean($_REQUEST['filter'], "string");
                        $data["filter"] = $filter;
                        foreach($data['STRINGS'] as $string_id => $translation){
                            if(strpos($string_id, $filter) === false) {
                                unset($data['STRINGS'][$string_id]);
                                unset($data['DEFAULT_STRINGS'][$string_id]);
                            }
                        }
                    }
                break;
                case "search":
                    $search_array = $parent->tableSearchRequestHandler($data,
                        array('name', 'tag', 'mode'));
                break;
            }
        }
        if($paging) {
            $parent->pagingLogic($data, $locale_model,
                "LOCALES", DEFAULT_ADMIN_PAGING_NUM, $search_array);
        }
        return $data;
    }
    /**
     *  Handles admin panel requests for mail, database, tor, proxy server
     *  settings
     *
     *  @return array $data data for the view concerning the current settings
     *      so they can be displayed
     */
    function serverSettings()
    {
        $parent = $this->parent;
        $profile_model = $parent->model("profile");
        $data = array();
        $profile = array();
        $arg = "";
        if(isset($_REQUEST['arg'])) {
            $arg = $_REQUEST['arg'];
        }
        $data['SCRIPT'] = "";
        $data["ELEMENT"] = "serversettings";
        switch($arg)
        {
            case "update":
                $parent->updateProfileFields($data, $profile,
                    array('USE_FILECACHE', 'USE_MEMCACHE', 'USE_MAIL_PHP',
                        'USE_PROXY'));
                $old_profile =
                    $profile_model->getProfile(WORK_DIRECTORY);
                $db_problem = false;
                if((isset($profile['DBMS']) &&
                    $profile['DBMS'] != $old_profile['DBMS']) ||
                    (isset($profile['DB_NAME']) &&
                    $profile['DB_NAME'] != $old_profile['DB_NAME']) ||
                    (isset($profile['DB_HOST']) &&
                    $profile['DB_HOST'] != $old_profile['DB_HOST'])) {
                    if(!$profile_model->migrateDatabaseIfNecessary(
                        $profile)) {
                        $db_problem = true;
                    }
                } else if ((isset($profile['DB_USER']) &&
                    $profile['DB_USER'] != $old_profile['DB_USER']) ||
                    (isset($profile['DB_PASSWORD']) &&
                    $profile['DB_PASSWORD'] != $old_profile['DB_PASSWORD'])) {

                    if($profile_model->testDatabaseManager(
                        $profile) !== true) {
                        $db_problem = true;
                    }
                }
                if($db_problem) {
                    $data['MESSAGE'] =
                        tl('system_component_configure_no_change_db');
                    $data['SCRIPT'] .=
                        "doMessage('<h1 class=\"red\" >". $data['MESSAGE'].
                        "</h1>');";
                    $data['DBMS'] = $old_profile['DBMS'];
                    $data['DB_NAME'] = $old_profile['DB_NAME'];
                    $data['DB_HOST'] = $old_profile['DB_HOST'];
                    $data['DB_USER'] = $old_profile['DB_USER'];
                    $data['DB_PASSWORD'] = $old_profile['DB_PASSWORD'];
                    break;
                }
                if($profile_model->updateProfile(
                    WORK_DIRECTORY, $profile, $old_profile)) {
                    $data['MESSAGE'] =
                        tl('system_component_configure_profile_change');
                    $data['SCRIPT'] =
                        "doMessage('<h1 class=\"red\" >". $data['MESSAGE'].
                        "</h1>');";
                } else {
                    $data['PROFILE'] = false;
                    $data["MESSAGE"] =
                        tl('system_component_configure_no_change_profile');
                    $data['SCRIPT'] .=
                        "doMessage('<h1 class=\"red\" >". $data["MESSAGE"].
                        "</h1>');";
                    break;
                }
            break;
        }
        $data = array_merge($data,
            $profile_model->getProfile(WORK_DIRECTORY));
        $data['MEMCACHE_SERVERS'] = str_replace(
            "|Z|","\n", $data['MEMCACHE_SERVERS']);
        $data['PROXY_SERVERS'] = str_replace(
            "|Z|","\n", $data['PROXY_SERVERS']);
        $data['DBMSS'] = array();
        $data['SCRIPT'] .= "logindbms = Array();\n";
        foreach($profile_model->getDbmsList() as $dbms) {
            $data['DBMSS'][$dbms] = $dbms;
            if($profile_model->loginDbms($dbms)) {
                $data['SCRIPT'] .= "logindbms['$dbms'] = true;\n";
            } else {
                $data['SCRIPT'] .= "logindbms['$dbms'] = false;\n";
            }
        }
        $data['REGISTRATION_TYPES'] = array (
                'disable_registration' =>
                    tl('system_component_configure_disable_registration'),
                'no_activation' =>
                    tl('system_component_configure_no_activation'),
                'email_registration' =>
                    tl('system_component_configure_email_activation'),
                'admin_activation' =>
                    tl('system_component_configure_admin_activation'),
            );
        $data['show_mail_info'] = "false";
        if(isset($data['REGISTRATION_TYPE']) &&
            in_array($data['REGISTRATION_TYPE'], array(
            'email_registration', 'admin_activation'))) {
            $data['show_mail_info'] = "true";
        }
        $data['no_mail_php'] =  ($data["USE_MAIL_PHP"]) ? "false" :"true";
        $data['SCRIPT'] .= <<< EOD
    elt('account-registration').onchange = function () {
        var show_mail_info = false;
        no_mail_registration = ['disable_registration', 'no_activation'];
        if(no_mail_registration.indexOf(elt('account-registration').value)
            < 0) {
            show_mail_info = true;
        }
        setDisplay('registration-info', show_mail_info);
    };
    setDisplay('registration-info', {$data['show_mail_info']});
    elt('use-php-mail').onchange = function () {
        setDisplay('smtp-info', (elt('use-php-mail').checked == false));
    };
    setDisplay('smtp-info', {$data['no_mail_php']});

    elt('database-system').onchange = function () {
        setDisplay('login-dbms', self.logindbms[elt('database-system').value]);
    };
    setDisplay('login-dbms', logindbms[elt('database-system').value]);
    elt('use-proxy').onchange = function () {
        setDisplay('proxy', (elt('use-proxy').checked) ? true : false);
    };
    setDisplay('proxy', (elt('use-proxy').checked) ? true : false);
EOD;
        if(class_exists("Memcache")) {
            $data['SCRIPT'] .= <<< EOD
    elt('use-memcache').onchange = function () {
        setDisplay('filecache', (elt('use-memcache').checked) ? false: true);
        setDisplay('memcache', (elt('use-memcache').checked) ? true : false);
    };
    setDisplay('filecache', (elt('use-memcache').checked) ? false : true);
    setDisplay('memcache', (elt('use-memcache').checked) ? true : false);
EOD;
        }
        return $data;
    }
    /**
     * Responsible for the Captcha Settings and managing Captcha/Recovery
     * questions.
     */
    function security()
    {
        $parent = $this->parent;
        $captcha_model = $parent->model("captcha");
        $possible_arguments = array("updatequestions", "updatetypes");
        $data = array();
        $profile_model = $parent->model("profile");
        $profile = $profile_model->getProfile(WORK_DIRECTORY);
        $data['SCRIPT'] = "";
        $data["ELEMENT"] = "security";
        $data["CURRENT_LOCALE"] = getLocaleTag();
        $data['CAPTCHA_MODES'] = array (
           TEXT_CAPTCHA =>
               tl('captchasettings_element_text_captcha'),
           HASH_CAPTCHA =>
               tl('captchasettings_element_hash_captcha'),
           IMAGE_CAPTCHA =>
               tl('captchasettings_element_image_captcha'),
            );
        $data['AUTHENTICATION_MODES'] = array (
                NORMAL_AUTHENTICATION =>
                   tl('serversettings_element_normal_authentication'),
                ZKP_AUTHENTICATION =>
                   tl('serversettings_element_zkp_authentication'),
            );
        if(isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch($_REQUEST['arg'])
            {
                case "updatetypes":
                    $change = false;
                    if(in_array($_REQUEST['CAPTCHA_MODE'],
                        array_keys($data['CAPTCHA_MODES']))) {
                        $profile["CAPTCHA_MODE"] = $_REQUEST['CAPTCHA_MODE'];
                        $change = true;
                    }
                    if(in_array($_REQUEST['AUTHENTICATION_MODE'],
                        array_keys($data['AUTHENTICATION_MODES']))) {
                        $profile["AUTHENTICATION_MODE"] =
                            $_REQUEST['AUTHENTICATION_MODE'];
                        $change = true;
                    }
                    if($change) {
                        $profile_model->updateProfile(WORK_DIRECTORY,
                            array(), $profile);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('security_element_settings_updated').
                            "</h1>');";
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('security_element_no_update_settings').
                            "</h1>');";
                    }
                break;
                case "updatequestions":
                break;
            }
        }
        $data = array_merge($data,
            $profile_model->getProfile(WORK_DIRECTORY));
        $data["CAPTCHA_MODE"] = $profile["CAPTCHA_MODE"];
        return $data;
    }
    /**
     * Responsible for handling admin request related to the configure activity
     *
     * The configure activity allows a user to set the work directory for
     * storing data local to this SeekQuarry/Yioop instance. It also allows one
     * to set the default language of the installation, dbms info, robot info,
     * test info, as well as which machine acts as the queue server.
     *
     * @return array $data fields for available language, dbms, etc as well as
     *      results of processing sub activity if any
     */
    function configure()
    {
        $parent = $this->parent;
        $profile_model = $parent->model("profile");
        $group_model = $parent->model("group");
        $data = array();
        $profile = array();

        $data['SYSTEM_CHECK'] = $this->systemCheck();
        $languages = $parent->model("locale")->getLocaleList();
        foreach($languages as $language) {
            $data['LANGUAGES'][$language['LOCALE_TAG']] =
                $language['LOCALE_NAME'];
        }
        if(isset($_REQUEST['lang']) && $_REQUEST['lang']) {
            $data['lang'] = $parent->clean($_REQUEST['lang'], "string");
            $profile['DEFAULT_LOCALE'] = $data['lang'];
            setLocaleObject($data['lang']);
        }

        $data["ELEMENT"] = "configure";
        $data['SCRIPT'] = "";

        $data['PROFILE'] = false;
        if(isset($_REQUEST['WORK_DIRECTORY']) || (defined('WORK_DIRECTORY') &&
            defined('FIX_NAME_SERVER') && FIX_NAME_SERVER) ) {
            if(defined('WORK_DIRECTORY') && defined('FIX_NAME_SERVER')
                && FIX_NAME_SERVER && !isset($_REQUEST['WORK_DIRECTORY'])) {
                $_REQUEST['WORK_DIRECTORY'] = WORK_DIRECTORY;
                $_REQUEST['arg'] = "directory";
                @unlink($_REQUEST['WORK_DIRECTORY']."/profile.php");
            }
            $dir =
                $parent->clean($_REQUEST['WORK_DIRECTORY'], "string");
            $data['PROFILE'] = true;
            if(strstr(PHP_OS, "WIN")) {
                //convert to forward slashes so consistent with rest of code
                $dir = str_replace("\\", "/", $dir);
                if($dir[0] != "/" && $dir[1] != ":") {
                    $data['PROFILE'] = false;
                }
            } else if($dir[0] != "/") {
                    $data['PROFILE'] = false;
            }
            if($data['PROFILE'] == false) {
                $data["MESSAGE"] =
                    tl('system_component_configure_use_absolute_path');
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    $data["MESSAGE"]. "</h1>');" .
                    "setTimeout('window.location.href= ".
                    "window.location.href', 3000);";
                $data['WORK_DIRECTORY'] = $dir;
                return $data;
            }

            if(strstr($dir."/", BASE_DIR."/")) {
                $data['PROFILE'] = false;
                $data["MESSAGE"] =
                    tl('system_component_configure_configure_diff_base_dir');
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    $data["MESSAGE"]. "</h1>');" .
                    "setTimeout('window.location.href= ".
                    "window.location.href', 3000);";
                $data['WORK_DIRECTORY'] = $dir;
                return $data;
            }
            $data['WORK_DIRECTORY'] = $dir;

        } else if (defined("WORK_DIRECTORY") &&  strlen(WORK_DIRECTORY) > 0 &&
            strcmp(realpath(WORK_DIRECTORY), realpath(BASE_DIR)) != 0 &&
            (is_dir(WORK_DIRECTORY) || is_dir(WORK_DIRECTORY."../"))) {
            $data['WORK_DIRECTORY'] = WORK_DIRECTORY;
            $data['PROFILE'] = true;
        }

        $arg = "";
        if(isset($_REQUEST['arg'])) {
            $arg = $_REQUEST['arg'];
        }
        switch($arg)
        {
            case "directory":
                if(!isset($data['WORK_DIRECTORY'])) {break;}
                if($data['PROFILE'] &&
                    file_exists($data['WORK_DIRECTORY']."/profile.php")) {
                    $data = array_merge($data, $profile_model->getProfile(
                            $data['WORK_DIRECTORY']));
                    $profile_model->setWorkDirectoryConfigFile(
                        $data['WORK_DIRECTORY']);
                    $data["MESSAGE"] =
                        tl('system_component_configure_work_dir_set');
                    $data['SCRIPT'] .=
                        "doMessage('<h1 class=\"red\" >".
                        $data["MESSAGE"]. "</h1>');setTimeout(".
                        "'window.location.href=window.location.href', 3000);";
                } else if ($data['PROFILE'] &&
                    strlen($data['WORK_DIRECTORY']) > 0) {
                    if($profile_model->makeWorkDirectory(
                        $data['WORK_DIRECTORY'])) {
                        $profile['DBMS'] = 'sqlite3';
                        $data['DBMS'] = 'sqlite3';
                        $profile['DB_NAME'] = 'default';
                        $data['DB_NAME'] = 'default';
                        $profile['USER_AGENT_SHORT'] =
                            tl('system_component_name_your_bot');
                        $data['USER_AGENT_SHORT'] =
                            $profile['USER_AGENT_SHORT'];
                        $uri = UrlParser::getPath($_SERVER['REQUEST_URI']);
                        $http = (isset($_SERVER['HTTPS'])) ? "https://" :
                            "http://";
                        $profile['NAME_SERVER'] =
                            $http . $_SERVER['SERVER_NAME'] . $uri;
                        $data['NAME_SERVER'] = $profile['NAME_SERVER'];
                        $profile['AUTH_KEY'] = crawlHash(
                            $data['WORK_DIRECTORY'].time());
                        $data['AUTH_KEY'] = $profile['AUTH_KEY'];
                        $profile['FIAT_SHAMIR_MODULUS'] =
                            generateFiatShamirModulus();
                        $robot_instance = str_replace(".", "_",
                            $_SERVER['SERVER_NAME'])."-".time();
                        $profile['ROBOT_INSTANCE'] = $robot_instance;
                        $data['ROBOT_INSTANCE'] = $profile['ROBOT_INSTANCE'];
                        if($profile_model->updateProfile(
                            $data['WORK_DIRECTORY'], array(), $profile)) {
                            if((defined('WORK_DIRECTORY') &&
                                $data['WORK_DIRECTORY'] == WORK_DIRECTORY) ||
                                $profile_model->setWorkDirectoryConfigFile(
                                    $data['WORK_DIRECTORY'])) {
                                $data["MESSAGE"] =
                            tl('system_component_configure_work_profile_made');
                                $data['SCRIPT'] .=
                                    "doMessage('<h1 class=\"red\" >".
                                    $data["MESSAGE"]. "</h1>');" .
                                    "setTimeout('window.location.href= ".
                                    "window.location.href', 3000);";
                                $data = array_merge($data,
                                    $profile_model->getProfile(
                                        $data['WORK_DIRECTORY']));
                                $data['PROFILE'] = true;
                            } else {
                                $data['PROFILE'] = false;
                        $data["MESSAGE"] =
                            tl('system_component_configure_no_set_config');
                                $data['SCRIPT'] .=
                                    "doMessage('<h1 class=\"red\" >".
                                    $data["MESSAGE"] . "</h1>');" .
                                    "setTimeout('window.location.href= ".
                                    "window.location.href', 3000);";
                            }
                        } else {
                            $profile_model->setWorkDirectoryConfigFile(
                                $data['WORK_DIRECTORY']);
                            $data['PROFILE'] = false;
                        $data["MESSAGE"] =
                            tl('system_component_configure_no_create_profile');
                            $data['SCRIPT'] .=
                                "doMessage('<h1 class=\"red\" >".
                                $data["MESSAGE"].
                                "</h1>'); setTimeout('window.location.href=".
                                "window.location.href', 3000);";
                        }
                    } else {
                        $profile_model->setWorkDirectoryConfigFile(
                            $data['WORK_DIRECTORY']);
                        $data["MESSAGE"] =
                            tl('system_component_configure_work_dir_invalid');
                        $data['SCRIPT'] .=
                            "doMessage('<h1 class=\"red\" >". $data["MESSAGE"].
                                "</h1>');".
                            "setTimeout('window.location.href=".
                            "window.location.href', 3000);";
                        $data['PROFILE'] = false;
                    }
                } else {
                    $profile_model->setWorkDirectoryConfigFile(
                        $data['WORK_DIRECTORY']);
                    $data["MESSAGE"] =
                        tl('system_component_configure_work_dir_invalid');
                    $data['SCRIPT'] .=
                        "doMessage('<h1 class=\"red\" >". $data["MESSAGE"] .
                            "</h1>');" .
                        "setTimeout('window.location.href=".
                        "window.location.href', 3000);";
                    $data['PROFILE'] = false;
                }
            break;
            case "profile":
                $parent->updateProfileFields($data, $profile,
                    array('WEB_ACCESS', 'RSS_ACCESS', 'API_ACCESS'));
                $data['DEBUG_LEVEL'] = 0;
                $data['DEBUG_LEVEL'] |=
                    (isset($_REQUEST["ERROR_INFO"])) ? ERROR_INFO : 0;
                $data['DEBUG_LEVEL'] |=
                    (isset($_REQUEST["QUERY_INFO"])) ? QUERY_INFO : 0;
                $data['DEBUG_LEVEL'] |=
                    (isset($_REQUEST["TEST_INFO"])) ? TEST_INFO : 0;
                $profile['DEBUG_LEVEL'] = $data['DEBUG_LEVEL'];

                $old_profile =
                    $profile_model->getProfile($data['WORK_DIRECTORY']);


                if($profile_model->updateProfile(
                    $data['WORK_DIRECTORY'], $profile, $old_profile)) {
                    $data['MESSAGE'] =
                        tl('system_component_configure_profile_change');
                    $data['SCRIPT'] =
                        "doMessage('<h1 class=\"red\" >". $data['MESSAGE'].
                        "</h1>');";

                        if($old_profile['DEBUG_LEVEL'] !=
                            $profile['DEBUG_LEVEL']) {
                            $data['SCRIPT'] .=
                                "setTimeout('window.location.href=\"".
                                "?c=admin&amp;a=configure&amp;".CSRF_TOKEN."=".
                                $_REQUEST[CSRF_TOKEN]."\"', 3*sec);";
                        }
                } else {
                    $data['PROFILE'] = false;
                    $data["MESSAGE"] =
                        tl('system_component_configure_no_change_profile');
                    $data['SCRIPT'] .=
                        "doMessage('<h1 class=\"red\" >". $data["MESSAGE"].
                        "</h1>');";
                    break;
                }

            break;

            default:
                if(isset($data['WORK_DIRECTORY']) &&
                    file_exists($data['WORK_DIRECTORY']."/profile.php")) {
                    $data = array_merge($data,
                        $profile_model->getProfile($data['WORK_DIRECTORY']));
                } else {
                    $data['WORK_DIRECTORY'] = "";
                    $data['PROFILE'] = false;
                }
        }
        $data['advanced'] = "false";
        if($data['PROFILE']) {
            $locale_tag = getLocaleTag();
            if(isset($_REQUEST['ROBOT_DESCRIPTION'])) {
                $robot_description =
                    $parent->clean($_REQUEST['ROBOT_DESCRIPTION'], "string");
                $group_model->setPageName(ROOT_ID, PUBLIC_GROUP_ID,
                    "bot", $robot_description, $locale_tag, "", "", "", "");
            }
            $robot_info = $group_model->getPageInfoByName(
                 PUBLIC_GROUP_ID, "bot", $locale_tag, "edit");
            $data['ROBOT_DESCRIPTION'] = isset($robot_info["PAGE"]) ?
                $robot_info["PAGE"] : tl('system_component_describe_robot');
            if(isset($_REQUEST['advanced']) && $_REQUEST['advanced']=='true') {
                $data['advanced'] = "true";
            }
            $data['SCRIPT'] .= <<< EOD
    setDisplay('advance-configure', {$data['advanced']});
    setDisplay('advance-robot', {$data['advanced']});
    function toggleAdvance() {
        var advanced = elt('a-settings');
        advanced.value = (advanced.value =='true')
            ? 'false' : 'true';
        var value = (advanced.value == 'true') ? true : false;
        setDisplay('advance-configure', value);
        setDisplay('advance-robot', value);
    }
EOD;
        }
        $data['SCRIPT'] .=
            "\nelt('locale').onchange = ".
            "function () { elt('configureProfileForm').submit();};\n";

        return $data;
    }
    /**
     * Checks to see if the current machine has php configured in a way
     * Yioop! can run.
     *
     * @return string a message indicatign which required and optional
     *      components are missing; or "Passed" if nothing missing.
     */
     function systemCheck()
     {
        $parent = $this->parent;
        $required_items = array(
            array("name" => "Multi-Curl",
                "check"=>"curl_multi_init", "type"=>"function"),
            array("name" => "GD Graphics Library",
                "check"=>"imagecreate", "type"=>"function"),
            array("name" => "PDO SQLite3 Library",
                "check"=>"PDO", "type"=>"class"),
            array("name" => "Multibyte Character Library",
                "check"=>"mb_internal_encoding", "type"=>"function"),
        );
        $optional_items = array(
         /* as an example of what this array could contain...
            array("name" => "Memcache", "check" => "Memcache",
                "type"=> "class"), */
        );

        $missing_required = "";
        $comma = "";
        foreach($required_items as $item) {
            $check_function = $item["type"]."_exists";
            $check_parts = explode("|", $item["check"]);
            $check_flag = true;
            foreach($check_parts as $check) {
                if($check_function($check)) {
                    $check_flag = false;
                }
            }
            if($check_flag) {
                $missing_required .= $comma.$item["name"];
                $comma = ", ";
            }
        }
        if(!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50300) {
            $missing_required .= $comma.tl("system_component_php_version");
            $comma = ", ";
        }

        $out = "";
        $br = "";

        if(!is_writable(BASE_DIR."/configs/config.php")) {
            $out .= tl('system_component_no_write_config_php');
            $br = "<br />";
        }

        if(defined(WORK_DIRECTORY) && !is_writable(WORK_DIRECTORY)) {
            $out .= $br. tl('system_component_no_write_work_dir');
            $br = "<br />";
        }

        if(intval(ini_get("post_max_size")) < 2) {
            $out .= $br. tl('system_component_post_size_small');
            $br = "<br />";
        }

        if($missing_required != "") {
            $out .= $br.
                tl('system_component_missing_required', $missing_required);
            $br = "<br />";
        }

        $missing_optional = "";
        $comma = "";
        foreach($optional_items as $item) {
            $check_function = $item["type"]."_exists";
            $check_parts = explode("|", $item["check"]);
            $check_flag = true;
            foreach($check_parts as $check) {
                if($check_function($check)) {
                    $check_flag = false;
                }
            }
            if($check_flag) {
                $missing_optional .= $comma.$item["name"];
                $comma = ", ";
            }
        }

        if($missing_optional != "") {
            $out .= $br.
                tl('system_component_missing_optional', $missing_optional);
            $br = "<br />";
        }

        if($out == "") {
            $out = tl('system_component_check_passed');
        } else {
            $out = "<span class='red'>$out</span>";
        }
        if(file_exists(BASE_DIR."/configs/local_config.php")) {
            $out .= "<br />".tl('system_component_using_local_config');
        }
        return $out;
     }
}
