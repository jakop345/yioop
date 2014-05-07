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
 * This component is used to provide activities for the admin controller related
 * to configuring and performing a web or archive crawl
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage component
 */
class CrawlComponent extends Component implements CrawlConstants
{
    /**
     * Used to handle the manage crawl activity.
     *
     * This activity allows new crawls to be started, statistics about old
     * crawls to be seen. It allows a user to stop the current crawl or
     * restart an old crawl. It also allows a user to configure the options
     * by which a crawl is conducted
     *
     * @return array $data information and statistics about crawls in the system
     *      as well as status messages on performing a given sub activity
     */
    function manageCrawls()
    {
        $parent = $this->parent;
        $crawl_model = $parent->model("crawl");
        $possible_arguments =
            array("start", "resume", "delete", "stop", "index", "options");

        $data["ELEMENT"] = "managecrawls";
        $data['SCRIPT'] = "doUpdate();";
        $request_fields = array('start_row', 'num_show', 'end_row');
        $flag = 0;
        foreach($request_fields as $field) {
            $data[strtoupper($field)] = isset($_REQUEST[$field]) ? max(0,
                $parent->clean($_REQUEST[$field], 'int')) :
                (isset($data['NUM_SHOW']) ? $data['NUM_SHOW'] :
                $flag * DEFAULT_ADMIN_PAGING_NUM);
            $flag = 1;
        }
        if(isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {

            $machine_urls = $parent->model("machine")->getQueueServerUrls();
            $num_machines = count($machine_urls);
            if($num_machines <  1 || ($num_machines ==  1 &&
                UrlParser::isLocalhostUrl($machine_urls[0]))) {
                $machine_urls = NULL;
            }
            switch($_REQUEST['arg'])
            {
                case "start":
                    $this->startCrawl($data, $machine_urls);
                break;

                case "stop":
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('crawl_component_stop_crawl')."</h1>')";
                    $crawl_param_file = CRAWL_DIR."/schedules/crawl_params.txt";
                    if(file_exists($crawl_param_file)) {
                        unlink($crawl_param_file);
                    }

                    $info = array();
                    $info[self::STATUS] = "STOP_CRAWL";
                    $filename = CRAWL_DIR.
                        "/schedules/name_server_messages.txt";
                    file_put_contents($filename, serialize($info));

                    $crawl_model->sendStopCrawlMessage($machine_urls);
                break;

                case "resume":
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('crawl_component_resume_crawl')."</h1>')";
                    $crawl_params = array();
                    $crawl_params[self::STATUS] = "RESUME_CRAWL";
                    $crawl_params[self::CRAWL_TIME] =
                        $parent->clean($_REQUEST['timestamp'], "int");
                    $seed_info = $crawl_model->getCrawlSeedInfo(
                        $crawl_params[self::CRAWL_TIME], $machine_urls);
                    $this->getCrawlParametersFromSeedInfo($crawl_params,
                        $seed_info);
                    $crawl_params[self::TOR_PROXY] = TOR_PROXY;
                    if(USE_PROXY) {
                        $crawl_params[self::PROXY_SERVERS] =
                            explode("|Z|", PROXY_SERVERS);
                    }
                   /*
                       Write the new crawl parameters to the name server, so
                       that it can pass them along in the case of a new archive
                       crawl.
                    */
                    $filename = CRAWL_DIR.
                        "/schedules/name_server_messages.txt";
                    file_put_contents($filename, serialize($crawl_params));
                    chmod($filename, 0777);
                    $crawl_model->sendStartCrawlMessage($crawl_params,
                        NULL, $machine_urls);
                break;

                case "delete":
                    if(isset($_REQUEST['timestamp'])) {
                         $timestamp =
                            $parent->clean($_REQUEST['timestamp'], "int");
                         $crawl_model->deleteCrawl($timestamp,
                            $machine_urls);

                         $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('crawl_component_delete_crawl_success').
                            "</h1>'); crawlStatusUpdate(); ";
                     } else {
                        $data['SCRIPT'] .= "crawlStatusUpdate(); ".
                            "doMessage('<h1 class=\"red\" >".
                            tl('crawl_component_delete_crawl_fail').
                            "</h1>')";
                     }
                break;

                case "index":
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('crawl_component_set_index')."</h1>')";

                    $timestamp = $parent->clean($_REQUEST['timestamp'], "int");
                    $crawl_model->setCurrentIndexDatabaseName($timestamp);
                break;

                case "options":
                    $this->editCrawlOption($data, $machine_urls);
                break;
            }
        }
        return $data;
    }

    /**
     * Called from @see manageCrawls to start a new crawl on the machines
     * $machine_urls. Updates $data array with crawl start message
     *
     * @param array &$data an array of info to supply to AdminView
     * @param array $machine_urls string urls of machines managed by this
     *  Yioop name server on which to perform the crawl
     */
    function startCrawl(&$data, $machine_urls, $seed_info = NULL)
    {
        $parent = $this->parent;
        $crawl_model = $parent->model("crawl");
        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
            tl('crawl_component_starting_new_crawl')."</h1>')";

        $crawl_params = array();
        $crawl_params[self::STATUS] = "NEW_CRAWL";
        $crawl_params[self::CRAWL_TIME] = time();
        $seed_info = $crawl_model->getSeedInfo();
        $this->getCrawlParametersFromSeedInfo($crawl_params, $seed_info);
        if(isset($_REQUEST['description'])) {
            $description = $parent->clean($_REQUEST['description'], "string");
        } else {
            $description = tl('crawl_component_no_description');
        }
        $crawl_params['DESCRIPTION'] = $description;
        $crawl_params[self::TOR_PROXY] = TOR_PROXY;
        if(USE_PROXY) {
            $crawl_params[self::PROXY_SERVERS] = explode("|Z|", PROXY_SERVERS);
        }
        $crawl_params[self::VIDEO_SOURCES] = array();
        $sources =
            $parent->model("source")->getMediaSources('video');
        foreach($sources as $source) {
            $url = $source['SOURCE_URL'];
            $url_parts = explode("{}", $url);
            $crawl_params[self::VIDEO_SOURCES][] = $url_parts[0];
        }
        if(isset($crawl_params[self::INDEXING_PLUGINS]) &&
            is_array($crawl_params[self::INDEXING_PLUGINS])) {
            foreach($crawl_params[self::INDEXING_PLUGINS] as $plugin) {
                $plugin_class = $plugin."Plugin";
                $plugin_obj = $parent->plugin(lcfirst($plugin));
                if(method_exists($plugin_class, "loadConfiguration")) {
                    $crawl_params[self::INDEXING_PLUGINS_DATA][$plugin] =
                        $plugin_obj->loadConfiguration();
                }
            }
        }

        /*
           Write the new crawl parameters to the name server, so
           that it can pass them along in the case of a new archive
           crawl.
        */
        $filename = CRAWL_DIR.
            "/schedules/name_server_messages.txt";
        file_put_contents($filename, serialize($crawl_params));
        chmod($filename, 0777);

        $crawl_model->sendStartCrawlMessage($crawl_params,
            $seed_info, $machine_urls);
    }

    /**
     * Reads the parameters for a crawl from an array gotten from a crawl.ini
     * file
     *
     * @param array &$crawl_params parameters to write to queue_server
     * @param array $seed_info data from crawl.ini file
     */
    function getCrawlParametersFromSeedInfo(&$crawl_params, $seed_info)
    {
        $parent = $this->parent;
        $crawl_params[self::CRAWL_TYPE] = $seed_info['general']['crawl_type'];
        $crawl_params[self::CRAWL_INDEX] =
            (isset($seed_info['general']['crawl_index'])) ?
            $seed_info['general']['crawl_index'] : '';
        $crawl_params[self::ARC_DIR]=(isset($seed_info['general']['arc_dir'])) ?
            $seed_info['general']['arc_dir'] : '';
        $crawl_params[self::ARC_TYPE] =
            (isset($seed_info['general']['arc_type'])) ?
            $seed_info['general']['arc_type'] : '';
        $crawl_params[self::CACHE_PAGES] =
            (isset($seed_info['general']['cache_pages'])) ?
            intval($seed_info['general']['cache_pages']) :
            true;
        $crawl_params[self::PAGE_RANGE_REQUEST] =
            (isset($seed_info['general']['page_range_request'])) ?
            intval($seed_info['general']['page_range_request']) :
            PAGE_RANGE_REQUEST;
        $crawl_params[self::MAX_DESCRIPTION_LEN] =
            (isset($seed_info['general']['max_description_len'])) ?
            intval($seed_info['general']['max_description_len']) :
            MAX_DESCRIPTION_LEN;
        $crawl_params[self::PAGE_RECRAWL_FREQUENCY] =
            (isset($seed_info['general']['page_recrawl_frequency'])) ?
            intval($seed_info['general']['page_recrawl_frequency']) :
            PAGE_RECRAWL_FREQUENCY;
        $crawl_params[self::TO_CRAWL] = $seed_info['seed_sites']['url'];
        $crawl_params[self::CRAWL_ORDER] = $seed_info['general']['crawl_order'];
        $crawl_params[self::RESTRICT_SITES_BY_URL] =
            $seed_info['general']['restrict_sites_by_url'];
        $crawl_params[self::ALLOWED_SITES] =
            isset($seed_info['allowed_sites']['url']) ?
            $seed_info['allowed_sites']['url'] : array();
        $crawl_params[self::DISALLOWED_SITES] =
            isset($seed_info['disallowed_sites']['url']) ?
            $seed_info['disallowed_sites']['url'] : array();
        if(isset($seed_info['indexed_file_types']['extensions'])) {
            $crawl_params[self::INDEXED_FILE_TYPES] =
                $seed_info['indexed_file_types']['extensions'];
        }
        if(isset($seed_info['active_classifiers']['label'])) {
            // Note that 'label' is actually an array of active class labels.
            $crawl_params[self::ACTIVE_CLASSIFIERS] =
                $seed_info['active_classifiers']['label'];
        }
        if(isset($seed_info['active_rankers']['label'])) {
            // Note that 'label' is actually an array of active class labels.
            $crawl_params[self::ACTIVE_RANKERS] =
                $seed_info['active_rankers']['label'];
        }
        if(isset($seed_info['indexing_plugins']['plugins'])) {
            $crawl_params[self::INDEXING_PLUGINS] =
                $seed_info['indexing_plugins']['plugins'];
        }
        $crawl_params[self::PAGE_RULES] =
            isset($seed_info['page_rules']['rule']) ?
            $seed_info['page_rules']['rule'] : array();
    }

    /**
     * Called from @see manageCrawls to edit the parameters for the next
     * crawl (or current crawl) to be carried out by the machines
     * $machine_urls. Updates $data array to be supplied to AdminView
     *
     * @param array &$data an array of info to supply to AdminView
     * @param array $machine_urls string urls of machines managed by this
     *  Yioop name server on which to perform the crawl
     */
    function editCrawlOption(&$data, $machine_urls)
    {
        $parent = $this->parent;
        $crawl_model= $parent->model("crawl");
        $data["leftorright"] = (getLocaleDirection() == 'ltr') ?
            "right": "left";
        $data["ELEMENT"] = "crawloptions";
        $crawls = $crawl_model->getCrawlList(false, false,
            $machine_urls);
        $indexes = $crawl_model->getCrawlList(true, true, $machine_urls);
        if(isset($_SESSION['USER_ID'])) {
            $user = $_SESSION['USER_ID'];
        } else {
            $user = $_SERVER['REMOTE_ADDR'];
        }
        $mixes = $crawl_model->getMixList($user, false);
        foreach($mixes as $mix) {
            $tmp = array();
            $tmp["DESCRIPTION"] = "MIX::".$mix["NAME"];
            $tmp["CRAWL_TIME"] = $mix["TIMESTAMP"];
            $tmp["ARC_DIR"] = "MIX";
            $tmp["ARC_TYPE"] = "MixArchiveBundle";
            $indexes[] = $tmp;
        }
        $add_message = "";
        $indexes_by_crawl_time = array();
        $update_flag = false;
        $data['available_options'] = array(
            tl('crawl_component_use_below'),
            tl('crawl_component_use_defaults'));
        $data['available_crawl_indexes'] = array();
        $data['options_default'] = tl('crawl_component_use_below');
        foreach($crawls as $crawl) {
            if(strlen($crawl['DESCRIPTION']) > 0 ) {
                $data['available_options'][$crawl['CRAWL_TIME']] =
                    tl('crawl_component_previous_crawl')." ".
                    $crawl['DESCRIPTION'];
            }
        }
        foreach($indexes as $i => $crawl) {
            $data['available_crawl_indexes'][$crawl['CRAWL_TIME']]
                = $crawl['DESCRIPTION'];
            $indexes_by_crawl_time[$crawl['CRAWL_TIME']] =& $indexes[$i];
        }
        $no_further_changes = false;
        $seed_current = $crawl_model->getSeedInfo();
        if(isset($_REQUEST['load_option']) &&
            $_REQUEST['load_option'] == 1) {
            $seed_info = $crawl_model->getSeedInfo(true);
            if(isset(
                $seed_current['general']['page_range_request'])) {
                $seed_info['general']['page_range_request'] =
                    $seed_current['general']['page_range_request'];
            }
            if(isset(
                $seed_current['general']['page_recrawl_frequency'])
                ){
                $seed_info['general']['page_recrawl_frequency'] =
                $seed_current['general']['page_recrawl_frequency'];
            }
            if(isset(
                $seed_current['general']['max_description_len'])) {
                $seed_info['general']['max_description_len'] =
                    $seed_current['general']['max_description_len'];
            }
            $update_flag = true;
            $no_further_changes = true;
        } else if (isset($_REQUEST['load_option']) &&
            $_REQUEST['load_option'] > 1 ) {
            $timestamp =
                $parent->clean($_REQUEST['load_option'], "int");
            $seed_info = $crawl_model->getCrawlSeedInfo(
                $timestamp, $machine_urls);
            $update_flag = true;
            $no_further_changes = true;
        } else if(isset($_REQUEST['ts'])) {
            $timestamp =
                $parent->clean($_REQUEST['ts'], "int");
            $seed_info = $crawl_model->getCrawlSeedInfo(
                $timestamp, $machine_urls);
            $data['ts'] = $timestamp;
        } else {
            $seed_info = $crawl_model->getSeedInfo();
        }
        if(isset($_REQUEST['suggest']) && $_REQUEST['suggest']=='add') {
            $suggest_urls = $crawl_model->getSuggestSites();
            $seed_info['seed_sites']['url'] =
            $tmp_urls = array_merge(
                $seed_info['seed_sites']['url'], $suggest_urls);
            $urls = array();
            foreach($tmp_urls as $url) {
                $trim_url = trim($url);
                if(strlen($trim_url) > 0) {
                    $urls[$trim_url] = "";
                }
            }
            $seed_info['seed_sites']['url'] = array_keys($urls);
            if(!isset($_REQUEST['ts'])) {
                $crawl_model->setSeedInfo($seed_info);
            }
            $add_message= tl('crawl_component_add_suggest');
            $update_flag = true;
        }
        if(isset($_REQUEST['suggest']) && $_REQUEST['suggest']=='clear') {
            $crawl_model->clearSuggestSites();
            $add_message= tl('crawl_component_clear_suggest');
            $update_flag = true;
        }
        $page_options_properties = array('indexed_file_types',
            'active_classifiers', 'page_rules', 'indexing_plugins');
        //these properties should be changed under page_options not here
        foreach($page_options_properties as $property) {
            if(isset($seed_current[$property])) {
                $seed_info[$property] = $seed_current[$property];
            }
        }

        if(!$no_further_changes && isset($_REQUEST['crawl_indexes'])
            && in_array($_REQUEST['crawl_indexes'],
            array_keys($data['available_crawl_indexes']))) {
            $seed_info['general']['crawl_index'] = $_REQUEST['crawl_indexes'];
            $index_data = $indexes_by_crawl_time[$_REQUEST['crawl_indexes']];
            if(isset($index_data['ARC_DIR'])) {
                $seed_info['general']['arc_dir'] = $index_data['ARC_DIR'];
                $seed_info['general']['arc_type'] = $index_data['ARC_TYPE'];
            } else {
                $seed_info['general']['arc_dir'] = '';
                $seed_info['general']['arc_type'] = '';
            }
            $update_flag = true;
        }
        $data['crawl_index'] =  (isset($seed_info['general']['crawl_index'])) ?
            $seed_info['general']['crawl_index'] : '';
        $data['available_crawl_types'] = array(self::WEB_CRAWL,
            self::ARCHIVE_CRAWL);
        if(!$no_further_changes && isset($_REQUEST['crawl_type']) &&
            in_array($_REQUEST['crawl_type'], $data['available_crawl_types'])) {
            $seed_info['general']['crawl_type'] =
                $_REQUEST['crawl_type'];
            $update_flag = true;
        }
        $data['crawl_type'] = $seed_info['general']['crawl_type'];
        if($data['crawl_type'] == self::WEB_CRAWL) {
            $data['web_crawl_active'] = "active";
            $data['archive_crawl_active'] = "";
        } else {
            $data['archive_crawl_active'] = "active";
            $data['web_crawl_active'] = "";
        }

        $data['available_crawl_orders'] = array(
            self::BREADTH_FIRST =>
                tl('crawl_component_breadth_first'),
            self::PAGE_IMPORTANCE =>
                tl('crawl_component_page_importance'));
        $data['available_summarizers'] = array(
            self::BASIC_SUMMARIZER =>
                tl('crawl_component_basic'),
            self::CENTROID_SUMMARIZER =>
                tl('crawl_component_centroid'));
        if(!$no_further_changes && isset($_REQUEST['summarizer_option'])
            &&  in_array($_REQUEST['summarizer_option'],
            array_keys($data['available_summarizers']))) {
            $seed_info['general']['summarizer_option'] =
                $_REQUEST['summarizer_option'];
            $update_flag = true;
        }
        $data['summarizer_option'] = $seed_info['general']['summarizer_option'];

        if(!$no_further_changes && isset($_REQUEST['crawl_order'])
            &&  in_array($_REQUEST['crawl_order'],
                array_keys($data['available_crawl_orders']))) {
            $seed_info['general']['crawl_order'] =
                $_REQUEST['crawl_order'];
            $update_flag = true;
        }
        $data['crawl_order'] = $seed_info['general']['crawl_order'];

        if(!$no_further_changes && isset($_REQUEST['posted'])) {
            $seed_info['general']['restrict_sites_by_url'] =
                (isset($_REQUEST['restrict_sites_by_url'])) ?
                true : false;
            $update_flag = true;
        }
        $data['restrict_sites_by_url'] =
            $seed_info['general']['restrict_sites_by_url'];
        $site_types =
            array('allowed_sites' => 'url', 'disallowed_sites' => 'url',
                'seed_sites' => 'url');
        foreach($site_types as $type => $field) {
            if(!$no_further_changes && isset($_REQUEST[$type])) {
                $seed_info[$type][$field] =
                    $parent->convertStringCleanArray(
                    $_REQUEST[$type], $field);
                    $update_flag = true;
            }
            if(isset($seed_info[$type][$field])) {
                $data[$type] = $parent->convertArrayLines(
                    $seed_info[$type][$field]);
            } else {
                $data[$type] = "";
            }
        }
        $data['TOGGLE_STATE'] =
            ($data['restrict_sites_by_url']) ?
            "checked='checked'" : "";

        $data['SCRIPT'] = "setDisplay('toggle', ".
            "'{$data['restrict_sites_by_url']}');";
        if(!isset($_REQUEST['ts'])) {
            $data['SCRIPT'] .=
            " elt('load-options').onchange = ".
            "function() { if(elt('load-options').selectedIndex !=".
            " 0) { elt('crawloptionsForm').submit();  }};";
        }
        if($data['crawl_type'] == CrawlConstants::WEB_CRAWL) {
            $data['SCRIPT'] .=
                "switchTab('webcrawltab', 'archivetab');";
        } else {
            $data['SCRIPT'] .=
                "switchTab('archivetab', 'webcrawltab');";
        }
        if(isset($_REQUEST['ts']) &&
            isset($_REQUEST['inject_sites'])) {
                $timestamp = $parent->clean($_REQUEST['ts'],
                    "string");
                $inject_urls =
                    $parent->convertStringCleanArray(
                    $_REQUEST['inject_sites']);
                if($crawl_model->injectUrlsCurrentCrawl(
                    $timestamp, $inject_urls, $machine_urls)) {
                    $add_message = "<br />".
                        tl('crawl_component_urls_injected');
                }
        }
        if($update_flag) {
            if(isset($_REQUEST['ts'])) {
                $crawl_model->setCrawlSeedInfo($timestamp,
                    $seed_info, $machine_urls);
            } else {
                $crawl_model->setSeedInfo($seed_info);
            }
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                tl('crawl_component_update_seed_info').
                " $add_message</h1>');";
        }
        return $data;
    }


    /**
     * Handles admin requests for creating, editing, and deleting classifiers.
     *
     * This activity implements the logic for the page that lists existing
     * classifiers, including the actions that can be performed on them.
     */
    function manageClassifiers()
    {
        $parent = $this->parent;
        $crawl_model = $parent->model("crawl");
        $possible_arguments = array('createclassifier', 'editclassifier',
            'finalizeclassifier', 'deleteclassifier', 'search');

        $data['ELEMENT'] = 'manageclassifiers';
        $data['SCRIPT'] = '';
        $data['FORM_TYPE'] = '';
        $search_array = array();

        $machine_urls = $parent->model("machine")->getQueueServerUrls();
        $num_machines = count($machine_urls);
        if ($num_machines < 1 || ($num_machines == 1 &&
            UrlParser::isLocalhostUrl($machine_urls[0]))) {
            $machine_urls = NULL;
        }
        $data['leftorright'] =
            (getLocaleDirection() == 'ltr') ? 'right': 'left';

        $classifiers = Classifier::getClassifierList();
        $start_finalizing = false;
        if(isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            if(isset($_REQUEST['name'])) {
                $name = $parent->clean($_REQUEST['name'], 'string');
                $name = Classifier::cleanLabel($name);
            } else {
                $name = "";
            }
            switch ($_REQUEST['arg'])
            {
                case 'createclassifier':
                    if (!isset($classifiers[$name])) {
                        $classifier = new Classifier($name);
                        Classifier::setClassifier($classifier);
                        $classifiers[$name] = $classifier;
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\">".
                            tl('crawl_component_new_classifier').'</h1>\');';
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\">".
                            tl('crawl_component_classifier_exists').
                            '</h1>\');';
                    }
                break;

                case 'deleteclassifier':
                    /*
                       In addition to deleting the classifier, we also want to
                       delete the associated crawl mix (if one exists) used to
                       iterate over existing indexes in search of new training
                       examples.
                     */
                    if (isset($classifiers[$name])) {
                        unset($classifiers[$name]);
                        Classifier::deleteClassifier($name);
                        $mix_name = Classifier::getCrawlMixName($name);
                        $mix_time = $crawl_model->getCrawlMixTimestamp(
                            $mix_name);
                        if ($mix_time) {
                            $crawl_model->deleteCrawlMixIteratorState(
                                $mix_time);
                            $crawl_model->deleteCrawlMix($mix_time);
                        }
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\">".
                            tl('crawl_component_classifier_deleted').
                            '</h1>\');';
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\">".
                            tl('crawl_component_no_classifier').
                            '</h1>\');';
                    }
                break;

                case 'editclassifier':
                    if (isset($classifiers[$name])) {
                        $data['class_label'] = $name;
                        $this->editClassifier($data, $classifiers,
                            $machine_urls);
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\">".
                            tl('crawl_component_no_classifier').
                            '</h1>\');';
                    }
                break;

                case 'finalizeclassifier':
                    /*
                       Finalizing is too expensive to be done directly in the
                       controller that responds to the web request. Instead, a
                       daemon is launched to finalize the classifier
                       asynchronously and save it back to disk when it's done.
                       In the meantime, a flag is set to indicate the current
                       finalizing state.
                     */
                    CrawlDaemon::start("classifier_trainer", $name, '', -1);
                    $classifier = $classifiers[$name];
                    $classifier->finalized = Classifier::FINALIZING;
                    $start_finalizing = true;
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\">".
                        tl('crawl_component_finalizing_classifier').
                        '</h1>\');';
                break;

                case 'search':
                    $search_array = 
                        $parent->tableSearchRequestHandler($data,
                        array('name'));
                break;
            }
        }

        $data['classifiers'] = $classifiers;
        $parent->pagingLogic($data, 'classifiers', 'classifiers',
            DEFAULT_ADMIN_PAGING_NUM, $search_array, "",
            array('name' => 'class_label'));
        $data['reload'] = false;
        foreach($classifiers as $label => $classifier) {
            if($classifier->finalized == Classifier::FINALIZING) {
                $data['reload'] = true;
                break;
            }
        }
        if($data['reload'] && !$start_finalizing) {
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\">".
                tl('crawl_component_finalizing_classifier'). '</h1>\');';
        }
        return $data;
    }

    /**
     * Handles the particulars of editing a classifier, which includes changing
     * its label and adding training examples.
     *
     * This activity directly handles changing the class label, but not adding
     * training examples. The latter activity is done interactively without
     * reloading the page via XmlHttpRequests, coordinated by the classifier
     * controller dedicated to that task.
     *
     * @param array $data data to be passed on to the view
     * @param array $classifiers map from class labels to their associated
     *     classifiers
     * @param array $machine_urls string urls of machines managed by this
     *     Yioop name server
     */
    function editClassifier(&$data, $classifiers, $machine_urls)
    {
        $parent = $this->parent;
        $data['ELEMENT'] = 'editclassifier';
        $data['INCLUDE_SCRIPTS'] = array('classifiers');

        // We want recrawls, but not archive crawls.
        $crawls = $parent->model("crawl")->getCrawlList(false, true,
            $machine_urls);
        $data['CRAWLS'] = $crawls;

        $classifier = $classifiers[$data['class_label']];

        if (isset($_REQUEST['update']) && $_REQUEST['update'] == 'update') {
            if (isset($_REQUEST['rename_label'])) {
                $new_label = $parent->clean($_REQUEST['rename_label'],
                    'string');
                $new_label = preg_replace('/[^a-zA-Z0-9_]/', '', $new_label);
                if (!isset($classifiers[$new_label])) {
                    $old_label = $classifier->class_label;
                    $classifier->class_label = $new_label;
                    Classifier::setClassifier($classifier);
                    Classifier::deleteClassifier($old_label);
                    $data['class_label'] = $new_label;
                } else {
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\">".
                        tl('crawl_component_classifier_exists').
                        '</h1>\');';
                }
            }
        }

        $data['classifier'] = $classifier;

        // Translations for the classification javascript.
        $data['SCRIPT'] .= "window.tl = {".
            'crawl_component_load_failed:"'.
                tl('crawl_component_load_failed').'",'.
            'crawl_component_loading:"'.
                tl('crawl_component_loading').'",'.
            'crawl_component_added_examples:"'.
                tl('crawl_component_added_examples').'",'.
            'crawl_component_label_update_failed:"'.
                tl('crawl_component_label_update_failed').'",'.
            'crawl_component_updating:"'.
                tl('crawl_component_updating').'",'.
            'crawl_component_acc_update_failed:"'.
                tl('crawl_component_acc_update_failed').'",'.
            'crawl_component_na:"'.
                tl('crawl_component_na').'",'.
            'crawl_component_no_docs:"'.
                tl('crawl_component_no_docs').'",'.
            'crawl_component_num_docs:"'.
                tl('crawl_component_num_docs').'",'.
            'crawl_component_in_class:"'.
                tl('crawl_component_in_class').'",'.
            'crawl_component_not_in_class:"'.
                tl('crawl_component_not_in_class').'",'.
            'crawl_component_skip:"'.
                tl('crawl_component_skip').'",'.
            'crawl_component_prediction:"'.
                tl('crawl_component_prediction').'",'.
            'crawl_component_scores:"'.
                tl('crawl_component_scores').'"'.
            '};';

        /*
           We pass along authentication information to the client, so that it
           can authenticate any XmlHttpRequests that it makes in order to label
           documents.
         */
        $time = strval(time());
        $session = md5($time.AUTH_KEY);
        $data['SCRIPT'] .=
            "Classifier.initialize(".
                "'{$data['class_label']}',".
                "'{$session}',".
                "'{$time}');";
    }


    /**
     * Handles admin request related to controlling file options to be used
     * in a crawl
     *
     * This activity allows a user to specify the page range size to be
     * be used during a crawl as well as which file types can be downloaded
     */
    function pageOptions()
    {
        global $INDEXED_FILE_TYPES;

        $parent = $this->parent;
        $crawl_model = $parent->model("crawl");
        $profile_model = $parent->model("profile");
        $data["ELEMENT"] = "pageoptions";
        $data['SCRIPT'] = "";
        $machine_urls = $parent->model("machine")->getQueueServerUrls();
        $num_machines = count($machine_urls);
        if($num_machines <  1 || ($num_machines ==  1 &&
            UrlParser::isLocalhostUrl($machine_urls[0]))) {
            $machine_urls = NULL;
        }
        $data['available_options'] = array(
            tl('crawl_component_use_below'),
            tl('crawl_component_use_defaults'));
        $crawls = $crawl_model->getCrawlList(false, true, $machine_urls);
        $data['options_default'] = tl('crawl_component_use_below');
        foreach($crawls as $crawl) {
            if(strlen($crawl['DESCRIPTION']) > 0 ) {
                $data['available_options'][$crawl['CRAWL_TIME']] =
                    $crawl['DESCRIPTION'];
            }
        }
        $seed_info = $crawl_model->getSeedInfo();
        $data['RECRAWL_FREQS'] = array(-1=>tl('crawl_component_recrawl_never'),
            1=>tl('crawl_component_recrawl_1day'),
            2=>tl('crawl_component_recrawl_2day'),
            3=>tl('crawl_component_recrawl_3day'),
            7=>tl('crawl_component_recrawl_7day'),
            14=>tl('crawl_component_recrawl_14day'));
        $data['SIZE_VALUES'] = array(10000=>10000, 50000=>50000,
            100000=>100000, 500000=>500000, 1000000=>1000000,
            5000000=>5000000, 10000000=>10000000);
        $data['LEN_VALUES'] = array(2000=>2000, 10000=>10000, 50000=>50000,
            100000=>100000, 500000=>500000, 1000000=>1000000,
            5000000=>5000000, 10000000=>10000000);
        if(!isset($seed_info["indexed_file_types"]["extensions"])) {
            $seed_info["indexed_file_types"]["extensions"] =
                $INDEXED_FILE_TYPES;
        }
        $loaded = false;
        if(isset($_REQUEST['load_option']) &&
            $_REQUEST['load_option'] > 0) {
            if($_REQUEST['load_option'] == 1) {
                $seed_loaded = $crawl_model->getSeedInfo(true);
            } else {
                $timestamp = $parent->clean($_REQUEST['load_option'], "int");
                $seed_loaded = $crawl_model->getCrawlSeedInfo(
                    $timestamp, $machine_urls);
            }
            $copy_options = array("general" => array("page_recrawl_frequency",
                "page_range_request", "max_description_len", "cache_pages"),
                "indexed_file_types" => array("extensions"),
                "indexing_plugins" => array("plugins"));
            foreach($copy_options as $main_option => $sub_options) {
                foreach($sub_options as $sub_option) {
                    if(isset($seed_loaded[$main_option][$sub_option])) {
                        $seed_info[$main_option][$sub_option] =
                            $seed_loaded[$main_option][$sub_option];
                    }
                }
            }
            if(isset($seed_loaded['page_rules'])) {
                $seed_info['page_rules'] =
                    $seed_loaded['page_rules'];
            }
            if(isset($seed_loaded['active_classifiers'])) {
                $seed_info['active_classifiers'] =
                    $seed_loaded['active_classifiers'];
            } else {
                $seed_info['active_classifiers'] = array();
                $seed_info['active_classifiers']['label'] = array();
            }
            $update_flag = true;
            $loaded = true;
        } else {
            $seed_info = $crawl_model->getSeedInfo();
            if(isset($_REQUEST["page_recrawl_frequency"]) &&
                in_array($_REQUEST["page_recrawl_frequency"],
                    array_keys($data['RECRAWL_FREQS']))) {
                $seed_info["general"]["page_recrawl_frequency"] =
                    $_REQUEST["page_recrawl_frequency"];
            }
            if(isset($_REQUEST["page_range_request"]) &&
                in_array($_REQUEST["page_range_request"],$data['SIZE_VALUES'])){
                $seed_info["general"]["page_range_request"] =
                    $_REQUEST["page_range_request"];
            }
            if(isset($_REQUEST["max_description_len"]) &&
                in_array($_REQUEST["max_description_len"],$data['LEN_VALUES'])){
                $seed_info["general"]["max_description_len"] =
                    $_REQUEST["max_description_len"];
            }
           if(isset($_REQUEST["cache_pages"]) ) {
                $seed_info["general"]["cache_pages"] = true;
           } else if(isset($_REQUEST['posted'])) {
                //form sent but check box unchecked
                $seed_info["general"]["cache_pages"] = false;
           }

           if(isset($_REQUEST['page_rules'])) {
                $seed_info['page_rules']['rule'] =
                    $parent->convertStringCleanArray(
                    $_REQUEST['page_rules'], 'rule');
                    $update_flag = true;
            }
        }
        if(!isset($seed_info["general"]["page_recrawl_frequency"])) {
            $seed_info["general"]["page_recrawl_frequency"] =
                PAGE_RECRAWL_FREQUENCY;
        }
        $data['PAGE_RECRAWL_FREQUENCY'] =
            $seed_info["general"]["page_recrawl_frequency"];
        if(!isset($seed_info["general"]["cache_pages"])) {
            $seed_info["general"]["cache_pages"] = false;
        }
        $data["CACHE_PAGES"] = $seed_info["general"]["cache_pages"];
        if(!isset($seed_info["general"]["page_range_request"])) {
            $seed_info["general"]["page_range_request"] = PAGE_RANGE_REQUEST;
        }
        $data['PAGE_SIZE'] = $seed_info["general"]["page_range_request"];
        if(!isset($seed_info["general"]["max_description_len"])) {
            $seed_info["general"]["max_description_len"] = MAX_DESCRIPTION_LEN;
        }
        $data['MAX_LEN'] = $seed_info["general"]["max_description_len"];

        $data['INDEXING_PLUGINS'] = array();
        $included_plugins = array();
        if(isset($_REQUEST["posted"])) {
            $seed_info['indexing_plugins']['plugins'] =
                (isset($_REQUEST["INDEXING_PLUGINS"])) ?
                $_REQUEST["INDEXING_PLUGINS"] : array();
        }
        $included_plugins =
            (isset($seed_info['indexing_plugins']['plugins'])) ?
                $seed_info['indexing_plugins']['plugins']
                : array();
        foreach($parent->indexing_plugins as $plugin) {
            $plugin_name = ucfirst($plugin);
            $data['INDEXING_PLUGINS'][$plugin_name]['checked'] =
                (in_array($plugin_name, $included_plugins)) ?
                "checked='checked'" : "";
            /* to use method_exists we want that the require_once for the plugin
               class has occurred so we instantiate the object via the plugin
               method call which will also do the require if needed.
             */
            $plugin_object = $parent->plugin(lcfirst($plugin_name));
            $class_name = $plugin_name."Plugin";
            if(method_exists($class_name, 'configureHandler') &&
                method_exists($class_name, 'configureView')) {
                $data['INDEXING_PLUGINS'][$plugin_name]['configure'] = true;
                $plugin_object->configureHandler($data);
            } else {
                $data['INDEXING_PLUGINS'][$plugin_name]['configure'] = false;
            }
        }

        $profile =  $profile_model->getProfile(WORK_DIRECTORY);
        if(!isset($_REQUEST['load_option'])) {
            $data = array_merge($data, $profile);
        } else {
            $parent->updateProfileFields($data, $profile,
                array('IP_LINK','CACHE_LINK', 'SIMILAR_LINK', 'IN_LINK',
                    'SIGNIN_LINK', 'SUBSEARCH_LINK','WORD_SUGGEST'));
        }
        $weights = array('TITLE_WEIGHT' => 4,
            'DESCRIPTION_WEIGHT' => 1, 'LINK_WEIGHT' => 2,
            'MIN_RESULTS_TO_GROUP' => 200, 'SERVER_ALPHA' => 1.6);
        $change = false;
        foreach($weights as $weight => $value) {
            if(isset($_REQUEST[$weight])) {
                $data[$weight] = $parent->clean($_REQUEST[$weight], 'float', 1
                    );
                $profile[$weight] = $data[$weight];
                $change = true;
            } else if(isset($profile[$weight]) && $profile[$weight] != ""){
                $data[$weight] = $profile[$weight];
            } else {
                $data[$weight] = $value;
                $profile[$weight] = $data[$weight];
                $change = true;
            }
        }
        if($change == true) {
            $profile_model->updateProfile(WORK_DIRECTORY, array(), $profile);
        }

        $data['INDEXED_FILE_TYPES'] = array();
        $filetypes = array();
        foreach($INDEXED_FILE_TYPES as $filetype) {
            $ison =false;
            if(isset($_REQUEST["filetype"]) && !$loaded) {
                if(isset($_REQUEST["filetype"][$filetype])) {
                    $filetypes[] = $filetype;
                    $ison = true;
                    $change = true;
                }
            } else {
                if(in_array($filetype,
                    $seed_info["indexed_file_types"]["extensions"])) {
                    $filetypes[] = $filetype;
                    $ison = true;
                }
            }
            $data['INDEXED_FILE_TYPES'][$filetype] = ($ison) ?
                "checked='checked'" :'';
        }
        $seed_info["indexed_file_types"]["extensions"] = $filetypes;

        $data['CLASSIFIERS'] = array();
        $data['RANKERS'] = array();
        $active_classifiers = array();
        $active_rankers = array();

        foreach (Classifier::getClassifierList() as $classifier) {
            $label = $classifier->class_label;
            $ison = false;
            if (isset($_REQUEST['classifier']) && !$loaded) {
                if (isset($_REQUEST['classifier'][$label])) {
                    $ison = true;
                }
            } else if ($loaded || !isset($_REQUEST['posted']) &&
                isset($seed_info['active_classifiers']['label'])) {
                if (in_array($label,
                    $seed_info['active_classifiers']['label'])) {
                    $ison = true;
                }
            }
            if ($ison) {
                $data['CLASSIFIERS'][$label] = 'checked="checked"';
                $active_classifiers[] = $label;
            } else {
                $data['CLASSIFIERS'][$label] = '';
            }
            $ison = false;
            if (isset($_REQUEST['ranker']) && !$loaded) {
                if (isset($_REQUEST['ranker'][$label])) {
                    $ison = true;
                }
            } else if ($loaded || !isset($_REQUEST['posted']) &&
                isset($seed_info['active_rankers']['label'])) {
                if (isset($seed_info['active_rankers']['label']) &&
                    in_array($label, $seed_info['active_rankers']['label'])) {
                    $ison = true;
                }
            }
            if ($ison) {
                $data['RANKERS'][$label] = 'checked="checked"';
                $active_rankers[] = $label;
            } else {
                $data['RANKERS'][$label] = '';
            }
        }
        $seed_info['active_classifiers']['label'] = $active_classifiers;
        $seed_info['active_rankers']['label'] = $active_rankers;

        if(isset($seed_info['page_rules']['rule'])) {
            if(isset($seed_info['page_rules']['rule']['rule'])) {
                $data['page_rules'] = $parent->convertArrayLines(
                    $seed_info['page_rules']['rule']['rule']);
            } else {
                $data['page_rules'] = $parent->convertArrayLines(
                    $seed_info['page_rules']['rule']);
            }
        } else {
            $data['page_rules'] = "";
        }
        $allowed_options = array('crawl_time', 'search_time', 'test_options');
        if(isset($_REQUEST['option_type']) &&
            in_array($_REQUEST['option_type'], $allowed_options)) {
            $data['option_type'] = $_REQUEST['option_type'];
        } else {
            $data['option_type'] = 'crawl_time';
        }
        if($data['option_type'] == 'crawl_time') {
            $data['crawl_time_active'] = "active";
            $data['search_time_active'] = "";
            $data['test_options_active'] = "";
            $data['SCRIPT'] .= "\nswitchTab('crawltimetab',".
                "'searchtimetab', 'testoptionstab')\n";
        } else if($data['option_type'] == 'search_time') {
            $data['search_time_active'] = "active";
            $data['crawl_time_active'] = "";
            $data['test_options_active'] = "";
            $data['SCRIPT'] .= "\nswitchTab('searchtimetab',".
                "'crawltimetab', 'testoptionstab')\n";
        } else {
            $data['search_time_active'] = "";
            $data['crawl_time_active'] = "";
            $data['test_options_active'] = "active";
            $data['SCRIPT'] .= "\nswitchTab('testoptionstab',".
                "'crawltimetab', 'searchtimetab');\n";
        }

        $crawl_model->setSeedInfo($seed_info);
        if($change == true && $data['option_type'] != 'test_options') {
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                tl('crawl_component_page_options_updated')."</h1>')";
        }
        $test_processors = array(
            "text/html" => "HtmlProcessor",
            "text/asp" => "HtmlProcessor",
            "text/xml" => "XmlProcessor",
            "text/robot" => "RobotProcessor",
            "application/xml" => "XmlProcessor",
            "application/xhtml+xml" => "HtmlProcessor",
            "application/rss+xml" => "RssProcessor",
            "application/atom+xml" => "RssProcessor",
            "text/rtf" => "RtfProcessor",
            "text/plain" => "TextProcessor",
            "text/csv" => "TextProcessor",
            "text/tab-separated-values" => "TextProcessor",
        );
        $data['MIME_TYPES'] = array_keys($test_processors);
        $data['page_type'] = "text/html";
        if(isset($_REQUEST['page_type']) && in_array($_REQUEST['page_type'],
            $data['MIME_TYPES'])) {
            $data['page_type'] = $_REQUEST['page_type'];
        }
        $data['TESTPAGE'] = (isset($_REQUEST['TESTPAGE'])) ?
            $parent->clean($_REQUEST['TESTPAGE'], 'string') : "";
        if($data['option_type'] == 'test_options' && $data['TESTPAGE'] !="") {
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                tl('crawl_component_page_options_running_tests')."</h1>')";
            $site = array();
            $site[self::ENCODING] = "UTF-8";
            $site[self::URL] = "http://test-site.yioop.com/";
            $site[self::IP_ADDRESSES] = array("1.1.1.1");
            $site[self::HTTP_CODE] = 200;
            $site[self::MODIFIED] = date("U", time());
            $site[self::TIMESTAMP] = time();
            $site[self::TYPE] = "text/html";
            $site[self::HEADER] = "page options test extractor";
            $site[self::SERVER] = "unknown";
            $site[self::SERVER_VERSION] = "unknown";
            $site[self::OPERATING_SYSTEM] = "unknown";
            $site[self::LANG] = 'en';
            $site[self::JUST_METAS] = false;
            if(isset($_REQUEST['page_type']) &&
                in_array($_REQUEST['page_type'], $data['MIME_TYPES'])) {
                $site[self::TYPE] = $_REQUEST['page_type'];
            }
            if($site[self::TYPE] == 'text/html') {
                $site[self::ENCODING] =
                    guessEncodingHtml($_REQUEST['TESTPAGE']);
            }
            $processor_name = $test_processors[$site[self::TYPE]];
            $plugin_processors = array();
            if (isset($seed_info['indexing_plugins']['plugins'])) {
                foreach($seed_info['indexing_plugins']['plugins'] as $plugin) {
                    $plugin_name = $plugin."Plugin";
                    $supported_processors = $plugin_name::getProcessors();
                    foreach($supported_processors as $supported_processor) {
                        $parent_processor = $processor_name;
                        do {
                            if ($supported_processor == $parent_processor) {
                                $plugin_processors[] = new $plugin_name();
                                break;
                            }
                        } while(($parent_processor =
                            get_parent_class($parent_processor)) &&
                            $parent_processor != "PageProcessor");
                    }
                }
            }
            $page_processor = new $processor_name($plugin_processors,
                $seed_info["general"]["max_description_len"]);
            restore_error_handler();
            $doc_info = $page_processor->handle($_REQUEST['TESTPAGE'],
                $site[self::URL]);
            set_error_handler("yioop_error_handler");

            if($page_processor != "RobotProcessor" &&
                !isset($doc_info[self::JUST_METAS])) {
                $doc_info[self::LINKS] = UrlParser::pruneLinks(
                    $doc_info[self::LINKS]);
            }
            foreach($doc_info as $key => $value) {
                $site[$key] = $value;
            }
            if(isset($site[self::PAGE])) {
                unset($site[self::PAGE]);
            }
            if(isset($site[self::ROBOT_PATHS])) {
                $site[self::JUST_METAS] = true;
            }
            $reflect = new ReflectionClass("CrawlConstants");
            $crawl_constants = $reflect->getConstants();
            $crawl_keys = array_keys($crawl_constants);
            $crawl_values = array_values($crawl_constants);
            $inverse_constants = array_combine($crawl_values, $crawl_keys);
            $after_process = array();
            foreach($site as $key => $value) {
                $out_key = (isset($inverse_constants[$key])) ?
                    $inverse_constants[$key] : $key;
                $after_process[$out_key] = $value;
            }
            $data["AFTER_PAGE_PROCESS"] = wordwrap($parent->clean(
                print_r($after_process, true), "string"), 75, "\n", true);
            $rule_string = implode("\n", $seed_info['page_rules']['rule']);
            $rule_string = html_entity_decode($rule_string, ENT_QUOTES);
            $page_rule_parser =
                new PageRuleParser($rule_string);
            $page_rule_parser->executeRuleTrees($site);
            $after_process = array();
            foreach($site as $key => $value) {
                $out_key = (isset($inverse_constants[$key])) ?
                    $inverse_constants[$key] : $key;
                $after_process[$out_key] = $value;
            }
            $data["AFTER_RULE_PROCESS"] = wordwrap($parent->clean(
                print_r($after_process, true), "string"), 75, "\n", true);
            $lang = NULL;
            if(isset($site[self::LANG])) {
                $lang = $site[self::LANG];
            }
            $meta_ids = PhraseParser::calculateMetas($site);
            if(!$site[self::JUST_METAS]) {
                $host_words = UrlParser::getWordsIfHostUrl($site[self::URL]);
                $path_words = UrlParser::getWordsLastPathPartUrl(
                    $site[self::URL]);
                $phrase_string = $host_words." ".$site[self::TITLE] .
                    " ". $path_words . " ". $site[self::DESCRIPTION];
                if($site[self::TITLE] != "" ) {
                    $lang = guessLocaleFromString($site[self::TITLE], $lang);
                } else {
                    $lang = guessLocaleFromString(
                        substr($site[self::DESCRIPTION], 0,
                        AD_HOC_TITLE_LENGTH), $lang);
                }
                $word_lists =
                    PhraseParser::extractPhrasesInLists($phrase_string,
                        $lang);
                $len = strlen($phrase_string);
                if(PhraseParser::computeSafeSearchScore($word_lists, $len) <
                    0.012) {
                    $meta_ids[] = "safe:true";
                    $safe = true;
                } else {
                    $meta_ids[] = "safe:false";
                    $safe = false;
                }
            }
            if(!isset($word_lists)) {
                $word_lists = array();
            }
            $data["EXTRACTED_WORDS"] = wordwrap($parent->clean(
                print_r($word_lists, true), "string"), 75, "\n", true);;
            $data["EXTRACTED_META_WORDS"] = wordwrap($parent->clean(
                print_r($meta_ids, true), "string"), 75, "\n", true);
        }
        return $data;
    }

    /**
     * Handles admin request related to the search filter activity
     *
     * This activity allows a user to specify hosts whose web pages are to be
     * filtered out the search results
     *
     * @return array $data info about the groups and their contents for a
     *      particular crawl mix
     */
    function resultsEditor()
    {
        $parent = $this->parent;
        $filters_model = $parent->model("searchfilters");
        $data["ELEMENT"] = "resultseditor";
        $data['SCRIPT'] = "";

        if(isset($_REQUEST['disallowed_sites'])) {
            $sites = $parent->convertStringCleanArray(
                $_REQUEST['disallowed_sites']);
            $disallowed_sites = array();
            foreach($sites as $site) {
                $site = UrlParser::getHost($site);
                if(strlen($site) > 0) {
                    $disallowed_sites[] = $site."/";
                }
            }
            $data['disallowed_sites'] = implode("\n", $disallowed_sites);
            $filters_model->set($disallowed_sites);
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                tl('crawl_component_results_editor_update')."</h1>')";
        }
        if(!isset($data['disallowed_sites'])) {
            $data['disallowed_sites'] =
                implode("\n", $filters_model->getUrls());
        }
        foreach (array("URL", "TITLE", "DESCRIPTION") as $field) {
            $data[$field] = (isset($_REQUEST[$field])) ?
                $parent->clean($_REQUEST[$field], "string") :
                 ((isset($data[$field]) ) ? $data[$field] : "");
        }
        if($data["URL"] != "") {
            $data["URL"] = UrlParser::canonicalLink($data["URL"],"");
        }
        $tmp = tl('crawl_component_edited_pages');
        $data["URL_LIST"] = array ($tmp => $tmp);
        $summaries = $filters_model->getEditedPageSummaries();
        foreach($summaries as $hash => $summary) {
            $data["URL_LIST"][$summary[self::URL]] = $summary[self::URL];
        }
        if(isset($_REQUEST['arg']) ) {
            switch($_REQUEST['arg'])
            {
                case "save_page":
                    $missing_page_field = ($data["URL"] == "") ? true: false;
                    if($missing_page_field) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('crawl_component_results_editor_need_url').
                            "</h1>')";
                    } else {
                        $filters_model->updateResultPage(
                            $data["URL"], $data["TITLE"], $data["DESCRIPTION"]);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('crawl_component_results_editor_page_updated').
                            "</h1>')";
                    }
                break;
                case "load_url":
                    $hash_url = crawlHash($_REQUEST['LOAD_URL'], true);
                    if(isset($summaries[$hash_url])) {
                        $data["URL"] = $parent->clean($_REQUEST['LOAD_URL'],
                            "string");
                        $data["TITLE"] = $summaries[$hash_url][self::TITLE];
                        $data["DESCRIPTION"] = $summaries[$hash_url][
                            self::DESCRIPTION];
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('crawl_component_results_editor_page_loaded').
                            "</h1>')";
                    }
                break;
            }
        }

        return $data;
    }



    /**
     * Handles admin request related to the search sources activity
     *
     * The search sources activity allows a user to add/delete search sources
     * for video and news, it also allows a user to control which subsearches
     * appear on the SearchView page
     *
     * @return array $data info about current search sources, and current
     *      sub-searches
     */
    function searchSources()
    {
        $parent = $this->parent;
        $crawl_model = $parent->model("crawl");
        $source_model = $parent->model("source");
        $possible_arguments = array("addsource", "deletesource",
            "addsubsearch", "deletesubsearch", "editsource", "editsubsearch");
        $data = array();
        $data["ELEMENT"] = "searchsources";
        $data['SCRIPT'] = "";
        $data['SOURCE_TYPES'] = array(-1 => tl('crawl_component_media_kind'),
            "video" => tl('crawl_component_video'),
            "rss" => tl('crawl_component_rss_feed'));
        $source_type_flag = false;
        if(isset($_REQUEST['type']) &&
            in_array($_REQUEST['type'],
            array_keys($data['SOURCE_TYPES']))) {
            $data['SOURCE_TYPE'] = $_REQUEST['type'];
            $source_type_flag = true;
        } else {
            $data['SOURCE_TYPE'] = -1;
        }
        $machine_urls = $parent->model("machine")->getQueueServerUrls();
        $search_lists = $crawl_model->getCrawlList(false, true,
            $machine_urls);
        $data["SEARCH_LISTS"] = array(-1 =>
            tl('crawl_component_sources_indexes'));
        foreach($search_lists as $item) {
            $data["SEARCH_LISTS"]["i:".$item["CRAWL_TIME"]] =
                $item["DESCRIPTION"];
        }
        if(isset($_SESSION['USER_ID'])) {
            $user = $_SESSION['USER_ID'];
        } else {
            $user = $_SERVER['REMOTE_ADDR'];
        }
        $search_lists= $crawl_model->getMixList($user);
        foreach($search_lists as $item) {
            $data["SEARCH_LISTS"]["m:".$item["TIMESTAMP"]] =
                $item["NAME"];
        }
        $n = NUM_RESULTS_PER_PAGE;
        $data['PER_PAGE'] =
            array($n => $n, 2*$n => 2*$n, 5*$n=> 5*$n, 10*$n=>10*$n);
        if(isset($_REQUEST['per_page']) &&
            in_array($_REQUEST['per_page'], array_keys($data['PER_PAGE']))) {
            $data['PER_PAGE_SELECTED'] = $_REQUEST['per_page'];
        } else {
            $data['PER_PAGE_SELECTED'] = NUM_RESULTS_PER_PAGE;
        }
        $locales = $parent->model("locale")->getLocaleList();
        $data["LANGUAGES"] = array();
        foreach($locales as $locale) {
            $data["LANGUAGES"][$locale['LOCALE_TAG']] = $locale['LOCALE_NAME'];
        }
        if(isset($_REQUEST['language']) &&
            in_array($_REQUEST['language'],
                array_keys($data["LANGUAGES"]))) {
            $data['SOURCE_LOCALE_TAG'] =
                $_REQUEST['language'];
        } else {
            $data['SOURCE_LOCALE_TAG'] = DEFAULT_LOCALE;
        }
        $data["CURRENT_SOURCE"] = array(
            "name" => "", "type"=> $data['SOURCE_TYPE'], "source_url" => "",
            "thumb_url" => "", "language" => $data['SOURCE_LOCALE_TAG']);
        $data["CURRENT_SUBSEARCH"] = array(
            "locale_string" => "", "folder_name" =>"",
            "index_identifier" => "",
            "per_page" => $data['PER_PAGE_SELECTED']);
        $data['SOURCE_FORM_TYPE'] = "addsource";
        $data["SEARCH_FORM_TYPE"] = "addsubsearch";
        if(isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch($_REQUEST['arg'])
            {
                case "addsource":
                    if(!$source_type_flag) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('crawl_component_no_source_type').
                            "</h1>');";
                        break;
                    }
                    $must_have = array("name", "type",
                        'source_url');
                    $to_clean = array_merge($must_have,
                        array('thumb_url','language'));
                    foreach ($to_clean as $clean_me) {
                        $r[$clean_me] = (isset($_REQUEST[$clean_me])) ?
                            $parent->clean($_REQUEST[$clean_me], "string" ):"";
                        if(in_array($clean_me, $must_have) &&
                            $r[$clean_me] == "" ) {
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                                tl('crawl_component_missing_fields').
                                "</h1>');";
                            break 2;
                        }
                    }
                    $source_model->addMediaSource(
                        $r['name'], $r['type'], $r['source_url'],
                        $r['source_url'], $r['language']);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('crawl_component_media_source_added').
                        "</h1>');";
                break;
                case "addsubsearch":
                    $to_clean = array("folder_name", 'index_identifier');
                    $must_have = $to_clean;
                    foreach ($to_clean as $clean_me) {
                        $r[$clean_me] = (isset($_REQUEST[$clean_me])) ?
                            $parent->clean($_REQUEST[$clean_me], "string" ):"";
                        if(in_array($clean_me, $must_have) &&
                            $r[$clean_me] == "" ) {
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                                tl('crawl_component_missing_fields').
                                "</h1>');";
                            break 2;
                        }
                    }
                    $source_model->addSubsearch(
                        $r['folder_name'], $r['index_identifier'],
                        $data['PER_PAGE_SELECTED']);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('crawl_component_subsearch_added').
                        "</h1>');";
                break;
                case "deletesource":
                    if(!isset($_REQUEST['ts'])) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('crawl_component_no_delete_source').
                            "</h1>');";
                        break;
                    }
                    $timestamp = $parent->clean($_REQUEST['ts'], "string");
                    $source_model->deleteMediaSource($timestamp);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('crawl_component_media_source_deleted').
                        "</h1>');";
                break;

                case "deletesubsearch":
                    if(!isset($_REQUEST['fn'])) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('crawl_component_no_delete_source').
                            "</h1>');";
                        break;
                    }
                    $folder_name = $parent->clean($_REQUEST['fn'], "string");
                    $source_model->deleteSubsearch($folder_name);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('crawl_component_subsearch_deleted').
                        "</h1>');";
                break;
                case "editsubsearch":
                    $data['SEARCH_FORM_TYPE'] = "editsubsearch";
                    $subsearch = false;
                    $folder_name = (isset($_REQUEST['fn'])) ?
                        $parent->clean($_REQUEST['fn'], "string") : "";
                    if($folder_name) {
                        $subsearch = $source_model->getSubsearch($folder_name);
                    }
                    if(!$subsearch) {
                        $data['SOURCE_FORM_TYPE'] = "addsubsearch";
                        break;
                    }
                    $data['fn'] = $folder_name;
                    $update = false;
                    foreach($data['CURRENT_SUBSEARCH'] as $field => $value) {
                        $upper_field = strtoupper($field);
                        if(isset($_REQUEST[$field]) && $field != 'name') {
                            $subsearch[$upper_field] = $parent->clean(
                                $_REQUEST[$field], "string");
                            $data['CURRENT_SUBSEARCH'][$field] =
                                $subsearch[$upper_field];
                            $update = true;
                        } else if (isset($subsearch[$upper_field])){
                            $data['CURRENT_SUBSEARCH'][$field] =
                                $subsearch[$upper_field];
                        }
                    }
                    if($update) {
                        $source_model->updateSubsearch($subsearch);
                        $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                            tl('crawl_component_subsearch_updated').
                            "</h1>');";
                    }
                break;
                case "editsource":
                    $data['SOURCE_FORM_TYPE'] = "editsource";
                    $source = false;
                    $timestamp = (isset($_REQUEST['ts'])) ?
                        $parent->clean($_REQUEST['ts'], "string") : "";
                    if($timestamp) {
                        $source = $source_model->getMediaSource($timestamp);
                    }
                    if(!$source) {
                        $data['SOURCE_FORM_TYPE'] = "addsource";
                        break;
                    }
                    $data['ts'] = $timestamp;
                    $update = false;
                    foreach($data['CURRENT_SOURCE'] as $field => $value) {
                        $upper_field = strtoupper($field);
                        if(isset($_REQUEST[$field]) && $field != 'name') {
                            $source[$upper_field] = $parent->clean(
                                $_REQUEST[$field], "string");
                            $data['CURRENT_SOURCE'][$field] =
                                $source[$upper_field];
                            $update = true;
                        } else if (isset($source[$upper_field])){
                            $data['CURRENT_SOURCE'][$field] =
                                $source[$upper_field];
                        }
                    }
                    if($update) {
                        $source_model->updateMediaSource($source);
                        $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                            tl('crawl_component_media_source_updated').
                            "</h1>');";
                    }
                break;
            }
        }
        $parent->pagingLogic($data, $source_model, "MEDIA_SOURCES",
            DEFAULT_ADMIN_PAGING_NUM/5);
        $parent->pagingLogic($data, $source_model,
            "SUBSEARCHES", DEFAULT_ADMIN_PAGING_NUM/5, array(), "SUB",
            "SUBSEARCH");
        foreach($data["SUBSEARCHES"] as $search) {
            if(!isset($data["SEARCH_LISTS"][trim($search['INDEX_IDENTIFIER'])])
                ) {
                $source_model->deleteSubsearch($search["FOLDER_NAME"]);
            }
        }
        $data['SCRIPT'] .= "source_type = elt('source-type');".
            "source_type.onchange = switchSourceType;".
            "switchSourceType()";
        return $data;
    }
}
