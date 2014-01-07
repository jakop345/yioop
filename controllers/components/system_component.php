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
 * @subpackage component
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * 
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage component
 */
class SystemComponent extends Component
{
    var $activities = array("manageMachines", "manageLocales", "configure");

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
        $data = array();
        $data["ELEMENT"] = "managemachinesElement";
        $possible_arguments = array("addmachine", "deletemachine",
            "newsmode", "log", "update");
        $data['SCRIPT'] = "doUpdate();";
        $data["leftorright"]=(getLocaleDirection() == 'ltr') ? "right": "left";
        $data['MACHINES'] = array();
        $data['MACHINE_NAMES'] = array();
        $urls = array();
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
        $machines = $parent->machineModel->getMachineList();
        $tmp = tl('system_component_select_machine');
        $data['DELETABLE_MACHINES'] = array(
            $tmp => $tmp
        );
        $data['REPLICATABLE_MACHINES'] = array(
            $tmp => $tmp
        );
        foreach($machines as $machine) {
            $data['MACHINE_NAMES'][] = $machine["NAME"];
            $urls[] = $machine["URL"];
            $data['DELETABLE_MACHINES'][$machine["NAME"]] = $machine["NAME"];
            if(!isset($machine["PARENT"]) || $machine["PARENT"] == "") {
                $data['REPLICATABLE_MACHINES'][$machine["NAME"]]
                    = $machine["NAME"];
            }
        }

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
                if($field == "url" && $r[$field][strlen($r[$field])-1]
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
        $machine_exists = (isset($r["name"]) && in_array($r["name"],
            $data['MACHINE_NAMES']) ) || (isset($r["url"]) &&
            in_array($r["url"], $urls));

        if(isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch($_REQUEST['arg'])
            {
                case "addmachine":
                    if($allset == true && !$machine_exists) {
                        $parent->machineModel->addMachine(
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
                    if(!isset($r["name"]) ||
                        !in_array($r["name"], $data['MACHINE_NAMES'])) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('system_component_machine_doesnt_exists').
                            "</h1>');";
                    } else {
                        $machines = $parent->machineModel->getMachineStatuses();
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
                        $parent->machineModel->deleteMachine($r["name"]);
                        $tmp_array = array($r["name"]);
                        $diff =
                            array_diff($data['MACHINE_NAMES'],  $tmp_array);
                        $data['MACHINE_NAMES'] = array_merge($diff);
                        $tmp_array = array($r["name"] => $r["name"]);
                        $diff =
                            array_diff($data['DELETABLE_MACHINES'], $tmp_array);
                        $data['DELETABLE_MACHINES'] = array_merge($diff);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('system_component_machine_deleted')."</h1>');";
                    }
                break;

                case "newsmode":
                    $profile =  $parent->profileModel->getProfile(
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
                        $parent->profileModel->updateProfile(
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
                    $data["ELEMENT"] = "machinelogElement";
                    $filter= "";
                    if(isset($_REQUEST['f'])) {
                        $filter =
                            $parent->clean($_REQUEST['f'], "string");
                    }
                    $data['filter'] = $filter;
                    $data["REFRESH_LOG"] = "&time=". $data["time"];
                    $data["LOG_TYPE"] = "";
                    if(isset($r['fetcher_num']) && isset($r['name'])) {
                        $data["LOG_FILE_DATA"] = $parent->machineModel->getLog(
                            $r["name"], $r["fetcher_num"], $filter);
                        $data["LOG_TYPE"] = $r['name'].
                            " fetcher ".$r["fetcher_num"];
                        $data["REFRESH_LOG"] .= "&arg=log&name=".$r['name'].
                            "&fetcher_num=".$r['fetcher_num'];
                    } else if(isset($r["mirror_name"])) {
                        $data["LOG_TYPE"] = $r['mirror_name']." mirror";
                        $data["LOG_FILE_DATA"] = $parent->machineModel->getLog(
                            $r["mirror_name"], NULL, $filter,  true);
                    } else if(isset($r['name'])) {
                        $data["LOG_TYPE"] = $r['name']." queue_server";
                        if($r['name'] == "news") {
                            $data["LOG_TYPE"] = "Name Server News Updater";
                        }
                        $data["LOG_FILE_DATA"] = $parent->machineModel->getLog(
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
                        $parent->machineModel->update($r["name"],
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
        $possible_arguments = array("addlocale", "deletelocale", "editlocale");

        $data['SCRIPT'] = "";
        $data["ELEMENT"] = "managelocalesElement";

        $data["LOCALES"] = $parent->localeModel->getLocaleList();
        $data['LOCALE_NAMES'][-1] = tl('system_component_select_localename');

        $locale_ids = array();

        foreach ($data["LOCALES"] as $locale) {
            $data["LOCALE_NAMES"][$locale["LOCALE_TAG"]] =
                $locale["LOCALE_NAME"];
            $locale_ids[] = $locale["LOCALE_TAG"];
        }

        if(isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            if(isset($_REQUEST['localename'])) {
                $localename = $parent->clean($_REQUEST['localename'], "string");
            } else {
                $localename = "";
            }
            if(isset($_REQUEST['localetag'])) {
                $localetag = $parent->clean($_REQUEST['localetag'], "string" );
            } else {
                $localetag = "";
            }
            if(isset($_REQUEST['writingmode'])) {
                $writingmode =
                    $parent->clean($_REQUEST['writingmode'], "string" );
            } else {
                $writingmode = "";
            }
            if(isset($_REQUEST['selectlocale'])) {
                $select_locale =
                    $parent->clean($_REQUEST['selectlocale'], "string" );
            } else {
                $select_locale = "";
            }

            switch($_REQUEST['arg'])
            {
                case "addlocale":
                    $parent->localeModel->addLocale(
                        $localename, $localetag, $writingmode);
                    $parent->localeModel->extractMergeLocales();
                    $data["LOCALES"] = $parent->localeModel->getLocaleList();
                    $data['LOCALE_NAMES'][$localetag] = $localename;
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('system_component_locale_added')."</h1>')";
                break;

                case "deletelocale":

                    if(!in_array($select_locale, $locale_ids)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('system_component_localename_doesnt_exists').
                            "</h1>')";
                        return $data;
                    }
                    $parent->localeModel->deleteLocale($select_locale);
                    $data["LOCALES"] = $parent->localeModel->getLocaleList();
                    unset($data['LOCALE_NAMES'][$select_locale]);

                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('system_component_localename_deleted')."</h1>')";
                break;

                case "editlocale":
                    if(!isset($select_locale)) break;
                    $data["leftorright"] =
                        (getLocaleDirection() == 'ltr') ? "right": "left";
                    $data["ELEMENT"] = "editlocalesElement";
                    $data['STATIC_PAGES'][-1]=
                        tl('system_component_select_staticpages');
                    $data['STATIC_PAGES'] +=
                        $parent->localeModel->getStaticPageList($select_locale);
                    $data['CURRENT_LOCALE_NAME'] =
                        $data['LOCALE_NAMES'][$select_locale];
                    $data['CURRENT_LOCALE_TAG'] = $select_locale;
                    $tmp_pages = $data['STATIC_PAGES'];
                    array_shift($tmp_pages);
                    $page_keys = array_keys($tmp_pages);
                    if(isset($_REQUEST['static_page']) &&
                        in_array($_REQUEST['static_page'], $page_keys)) {
                        $data["ELEMENT"] = "editstaticElement";
                        $data['STATIC_PAGE'] = $_REQUEST['static_page'];
                        if(isset($_REQUEST['PAGE_DATA'])) {
                            $parent->localeModel->setStaticPage(
                                $_REQUEST['static_page'],
                                $data['CURRENT_LOCALE_TAG'],
                                $_REQUEST['PAGE_DATA']);
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                                tl('system_component_staticpage_updated').
                                "</h1>')";
                        }
                        $data['PAGE_NAME'] =
                            $data['STATIC_PAGES'][$data['STATIC_PAGE']];
                        $data['PAGE_DATA'] =
                            $parent->localeModel->getStaticPage(
                                $_REQUEST['static_page'],
                                $data['CURRENT_LOCALE_TAG']);
                        /*since page data can contain tags we clean it
                          htmlentities it just before displaying*/
                        $data['PAGE_DATA'] = $parent->clean($data['PAGE_DATA'],
                            "string");
                        break;
                    }
                    $data['SCRIPT'] .= "selectPage = elt('static-pages');".
                        "selectPage.onchange = submitStaticPageForm;";
                    if(isset($_REQUEST['STRINGS'])) {
                        $safe_strings = array();
                        foreach($_REQUEST['STRINGS'] as $key => $value) {
                            $clean_key = $parent->clean($key, "string" );
                            $clean_value = $parent->clean($value, "string");
                            $safe_strings[$clean_key] = $clean_value;
                        }
                        $parent->localeModel->updateStringData(
                            $select_locale, $safe_strings);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('system_component_localestrings_updated').
                            "</h1>')";
                    } else {
                        $parent->localeModel->extractMergeLocales();
                    }
                    $data['STRINGS'] =
                        $parent->localeModel->getStringData($select_locale);
                    $data['DEFAULT_STRINGS'] =
                        $parent->localeModel->getStringData(DEFAULT_LOCALE);
                break;
            }
        }
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
        $data = array();
        $profile = array();

        $data['SYSTEM_CHECK'] = $this->systemCheck();
        $languages = $parent->localeModel->getLocaleList();
        foreach($languages as $language) {
            $data['LANGUAGES'][$language['LOCALE_TAG']] =
                $language['LOCALE_NAME'];
        }
        if(isset($_REQUEST['lang']) && $_REQUEST['lang']) {
            $data['lang'] = $parent->clean($_REQUEST['lang'], "string");
            $profile['DEFAULT_LOCALE'] = $data['lang'];
            setLocaleObject($data['lang']);
        }

        $data["ELEMENT"] = "configureElement";
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
                    $data = array_merge($data,
                        $parent->profileModel->getProfile(
                            $data['WORK_DIRECTORY']));
                    $parent->profileModel->setWorkDirectoryConfigFile(
                        $data['WORK_DIRECTORY']);
                    $data["MESSAGE"] =
                        tl('system_component_configure_work_dir_set');
                    $data['SCRIPT'] .=
                        "doMessage('<h1 class=\"red\" >".
                        $data["MESSAGE"]. "</h1>');setTimeout(".
                        "'window.location.href=window.location.href', 3000);";
                } else if ($data['PROFILE'] &&
                    strlen($data['WORK_DIRECTORY']) > 0) {
                    if($parent->profileModel->makeWorkDirectory(
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
                        $robot_instance = str_replace(".", "_",
                            $_SERVER['SERVER_NAME'])."-".time();
                        $profile['ROBOT_INSTANCE'] = $robot_instance;
                        $data['ROBOT_INSTANCE'] = $profile['ROBOT_INSTANCE'];
                        if($parent->profileModel->updateProfile(
                            $data['WORK_DIRECTORY'], array(), $profile)) {
                            if((defined('WORK_DIRECTORY') &&
                                $data['WORK_DIRECTORY'] == WORK_DIRECTORY) ||
                                $parent->profileModel->
                                    setWorkDirectoryConfigFile(
                                        $data['WORK_DIRECTORY'])) {
                                $data["MESSAGE"] =
                            tl('system_component_configure_work_profile_made');
                                $data['SCRIPT'] .=
                                    "doMessage('<h1 class=\"red\" >".
                                    $data["MESSAGE"]. "</h1>');" .
                                    "setTimeout('window.location.href= ".
                                    "window.location.href', 3000);";
                                $data = array_merge($data,
                                    $parent->profileModel->getProfile(
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
                            $parent->profileModel->setWorkDirectoryConfigFile(
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
                        $parent->profileModel->setWorkDirectoryConfigFile(
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
                    $parent->profileModel->setWorkDirectoryConfigFile(
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
                    array('USE_FILECACHE', 'USE_MEMCACHE', "REGISTRATION_TYPE",
                        "WEB_ACCESS", 'RSS_ACCESS', 'API_ACCESS'));
                $data['DEBUG_LEVEL'] = 0;
                $data['DEBUG_LEVEL'] |=
                    (isset($_REQUEST["ERROR_INFO"])) ? ERROR_INFO : 0;
                $data['DEBUG_LEVEL'] |=
                    (isset($_REQUEST["QUERY_INFO"])) ? QUERY_INFO : 0;
                $data['DEBUG_LEVEL'] |=
                    (isset($_REQUEST["TEST_INFO"])) ? TEST_INFO : 0;
                $profile['DEBUG_LEVEL'] = $data['DEBUG_LEVEL'];

                $old_profile =
                    $parent->profileModel->getProfile($data['WORK_DIRECTORY']);

                $db_problem = false;
                if((isset($profile['DBMS']) &&
                    $profile['DBMS'] != $old_profile['DBMS']) ||
                    (isset($profile['DB_NAME']) &&
                    $profile['DB_NAME'] != $old_profile['DB_NAME']) ||
                    (isset($profile['DB_HOST']) &&
                    $profile['DB_HOST'] != $old_profile['DB_HOST'])) {
                    if(!$parent->profileModel->migrateDatabaseIfNecessary(
                        $profile)) {
                        $db_problem = true;
                    }
                } else if ((isset($profile['DB_USER']) &&
                    $profile['DB_USER'] != $old_profile['DB_USER']) ||
                    (isset($profile['DB_PASSWORD']) &&
                    $profile['DB_PASSWORD'] != $old_profile['DB_PASSWORD'])) {

                    if($parent->profileModel->testDatabaseManager(
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

                if($parent->profileModel->updateProfile(
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
                        $parent->profileModel->getProfile(
                            $data['WORK_DIRECTORY']));
                    $data['MEMCACHE_SERVERS'] = str_replace(
                        "|Z|","\n", $data['MEMCACHE_SERVERS']);
                } else {
                    $data['WORK_DIRECTORY'] = "";
                    $data['PROFILE'] = false;
                }
        }
        $data['advanced'] = "false";
        if($data['PROFILE']) {
            $data['DBMSS'] = array();
            $data['SCRIPT'] .= "logindbms = Array();\n";
            foreach($parent->profileModel->getDbmsList() as $dbms) {
                $data['DBMSS'][$dbms] = $dbms;
                if($parent->profileModel->loginDbms($dbms)) {
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
            if(isset($_REQUEST['REGISTRATION_TYPE']) &&
                in_array($_REQUEST['REGISTRATION_TYPE'], array(
                'email_registration', 'admin_activation'))) {
                $data['show_mail_info'] = "true";
            }
            if(!isset($data['ROBOT_DESCRIPTION']) ||
                strlen($data['ROBOT_DESCRIPTION']) == 0) {
                $data['ROBOT_DESCRIPTION'] =
                    tl('system_component_describe_robot');
            } else {
                //since the description might contain tags we apply htmlentities
                $data['ROBOT_DESCRIPTION'] =
                    $parent->clean($data['ROBOT_DESCRIPTION'], "string");
            }
            if(!isset($data['MEMCACHE_SERVERS']) ||
                strlen($data['MEMCACHE_SERVERS']) == 0) {
                $data['MEMCACHE_SERVERS'] =
                    "localhost";
            }

            if(isset($_REQUEST['advanced']) && $_REQUEST['advanced']=='true') {
                $data['advanced'] = "true";
            }
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
    elt('database-system').onchange = function () {
        setDisplay('login-dbms', self.logindbms[elt('database-system').value]);
    };
    setDisplay('login-dbms', logindbms[elt('database-system').value]);
    setDisplay('advance-configure', {$data['advanced']});
    setDisplay('advance-robot', {$data['advanced']});
    function toggleAdvance() {
        var advanced = elt('a-settings');
        advanced.value = (advanced.value =='true')
            ? 'false' : 'true';
        var value = (advanced.value == 'true') ? true : false;
        setDisplay('advance-configure', value);
        setDisplay('advance-robot', value);
        elt('account-registration').onchange();
    }
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
        }
        $data['SCRIPT'] .=
            "elt('locale').onchange = ".
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
            array("name" => "SQLite3 Library",
                "check"=>"SQLite3|PDO", "type"=>"class"),
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