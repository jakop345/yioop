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
 * @subpackage bin
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(php_sapi_name() != 'cli') {echo "BAD REQUEST"; exit();}
/*
   Calculate base directory of script
 */
define("BASE_DIR", substr(
    dirname(realpath($_SERVER['PHP_SELF'])), 0,
    -strlen("/bin")));
/*
   We must specify that we want logging enabled
 */
define("NO_LOGGING", false);
/*
   Load in global configuration settings
 */
require_once BASE_DIR.'/configs/config.php';
if(!PROFILE) {
    echo "Please configure the search engine instance by visiting" .
        "its web interface on localhost.\n";
    exit();
}
/** Used to initialize and terminate the daemon */
require_once BASE_DIR."/lib/crawl_daemon.php";
/** Used to create, update, and delete user-trained classifiers. */
require_once BASE_DIR."/lib/classifiers/classifier.php";
/*
    We'll set up multi-byte string handling to use UTF-8
 */
mb_internal_encoding("UTF-8");
mb_regex_encoding("UTF-8");
/*
   If possible, set the memory limit high enough to fit all of the features and
   training documents into memory.
 */
ini_set("memory_limit", "500M");
/**
 * This class is used to finalize a classifier via the web interface.
 *
 * Because finalizing involves training a logistic regression classifier on a
 * potentially-large set of training examples, it can take much longer than
 * would be allowed by the normal web execution time limit. So instead of
 * trying to finalize a classifier directly in the controller that handles the
 * web request, the controller kicks off a daemon that simply loads the
 * classifier, finalizes it, and saves it back to disk.
 *
 * The classifier to finalize is specified by its class label, passed as the
 * second command-line argument. The following command would be used to run
 * this script directly from the command-line:
 *
 *     $ php bin/classifier_trainer.php terminal LABEL
 *
 * @author Shawn Tice
 * @package seek_quarry
 */
class ClassifierTrainer
{
    /**
     *  This is the function that should be called to get the
     *  classifier_trainer to start training a logistic regression instance for
     *  a particular classifier. The class label corresponding to the
     *  classifier to be finalized should be passed as the second command-line
     *  argument.
     */
    function start()
    {
        global $argv;
        CrawlDaemon::init($argv, "classifier_trainer");
        $label = $argv[2];
        crawlLog("Initializing classifier trainer log..",
            $label.'-classifier_trainer', true);
        $classifier = Classifier::getClassifier($label);
        $classifier->prepareToFinalize();
        $classifier->finalize();
        Classifier::setClassifier($classifier);
        crawlLog("Training complete.\n");
        CrawlDaemon::stop('classifier_trainer', $label);
    }
}
$classifier_trainer = new ClassifierTrainer();
$classifier_trainer->start();
?>
