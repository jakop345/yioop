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
 *  Load base controller class, if needed
 */
require_once BASE_DIR."/controllers/controller.php";
/**
 * Used to manage the process of sending emails to users
 */
require_once BASE_DIR."/lib/mail_server.php";


/**
 * Controller used to handle account registration and retrieval for
 * the Yioop website. Also handles data for suggest a url
 *
 * @author Mallika Perepa (Creator), Chris Pollett (extensive rewrite)
 * @package seek_quarry
 * @subpackage controller
 */
class RegisterController extends Controller implements CrawlConstants
{
    /**
     * Holds a list of the allowed activities. These encompass various
     * stages of the account creation and account recovery processes
     * @var array
     */
    var $activities = array("createAccount", "emailVerification",
        "processAccountData", "processRecoverData", "recoverPassword",
        "recoverComplete", "suggestUrl");

    /**
     * Non-recovery question fields needed to register a Yioop account.
     * @var array
     */
    var $register_fields = array("first", "last", "user", "email", "password",
        "repassword");

    /**
     * An array of triples, each triple consisting of a question of the form
     * Which is the most..? followed by one of the form Which is the least ..?
     * followed by a string which is a comma separated list of possibilities
     * arranged from least to most. The values for these triples are determined
     * via the translate function tl. So can be set under Manage Locales
     * by editing their values for the desired locale.
     * @var array
     */
    var $captchas_qa;

    /**
     * An array of triples, each triple consisting of a question of the form
     * Which is your favorite..? followed by one of the form
     * Which is your like the least..? followed by a string which is a comma
     * separated choices. The values for these triples are determined
     * via the translate function tl. So can be set under Manage Locales
     * by editing their values for the desired locale.
     * @var array
     */
    var $recovery_qa;

    /**
     * Number of captcha questions from the complete set of questions to
     * present someone when register for an account
     * @var int
     */
    const NUM_CAPTCHA_QUESTIONS = 5;
    /**
     * For each captcha question how many items from least-most list to
     * present to a user to pick from. Which NUM_CAPTCHA_CHOICEs many items
     * to use is chosen randomly
     * @var int
     */
    const NUM_CAPTCHA_CHOICES = 5;
    /**
     * Number of recovery questions from the complete set of questions to
     * present someone when register for an account
     * @var int
     */
    const NUM_RECOVERY_QUESTIONS = 3;
    /**
     * Define the number of seconds till hash code is valid
     * @var int
     */
    const HASH_TIMESTAMP_TIMEOUT = 300;
    /**
     * Use to match the leading zero in the sha1 of the string
     * @var int
     */
    const HASH_CAPTCHA_LEVEL = 2;
    /**
     *  Besides invoking the base controller, sets up in field variables
     *  the captcha and recovery question and possible answers.
     */
    function __construct()
    {
        $locale = $this->model("locale");
        $textcaptchasettings_model = $this->model("textcaptchasettings");
        $tid_data = $textcaptchasettings_model->getTranslationIdMethodNameMap(
            $locale->getLocaleTag());
        $recovery_tid_list = $tid_data['recovery_tids'];
        $captcha_tid_list = $tid_data['captcha_tids'];
        $least_tid_list = $tid_data['least_tids'];
        $most_tid_list = $tid_data['most_tids'];
        $tid_method_name_map = $tid_data['map'];
        $question_choices_map = 
            $textcaptchasettings_model->getQuestionChoicesMap();
        $this->captchas_qa = array(
            'least' => array(),
            'most' => array()
        );
        $this->recovery_qa = array(
            LEAST => array(),
            MOST => array()
        );
        foreach($question_choices_map as $question_translation_id => 
            $choices_translation_id) {
            $question_method_name = 
                $tid_method_name_map[$question_translation_id];
            $choices_method_name = 
                $tid_method_name_map[$choices_translation_id];
            $least_or_most = (in_array($question_translation_id, 
                $least_tid_list))? LEAST: MOST;
            if(in_array($question_translation_id, $recovery_tid_list)) {
                $this->recovery_qa[$least_or_most][] = array(
                    'question' => $question_method_name, 'choices' => 
                        $choices_method_name);
            } else {
                $this->captchas_qa[$least_or_most][] = array(
                    'question' => $question_method_name, 'choices' => 
                        $choices_method_name);           
            }
        }
        parent::__construct();
    }

    /**
     *  Main entry method for this controller. Determine which account
     *  creation/recovery activity needs to be performed. Calls the
     *  appropriate method, then sends the return $data to a view
     *  determined by that activity. $this->displayView then renders that
     *  view
     */
    function processRequest()
    {
        $visitor_model = $this->model("visitor");
        if(isset($_SESSION['USER_ID'])) {
            $user = $_SESSION['USER_ID'];
        } else {
            $user = $_SERVER['REMOTE_ADDR'];
        }
        $data = array();
        $data['REFRESH'] = "register";
        $activity = isset($_REQUEST['a']) ?
            $this->clean($_REQUEST['a'], 'string') : 'createAccount';
        $token_okay = $this->checkCSRFToken(CSRF_TOKEN, $user);

        if(!in_array($activity, $this->activities) || (!$token_okay
            && in_array($activity, array("processAccountData",
            "processRecoverData")) )) {
            $activity = 'createAccount';
        }
        $data["check_user"] = true;
        $this->preactivityPrerequisiteCheck($activity,
            'processAccountData', 'createAccount', $data);
        $data["check_fields"] = array("user");
        unset($data["check_user"]);
        if(CAPTCHA_MODE != GRAPHICAL_CAPTCHA && 
                isset($_SESSION["graphical_captcha_string"])) {
            unset($_SESSION["graphical_captcha_string"]);
        }
        if(CAPTCHA_MODE == TEXT_CAPTCHA) {
            for($i = 0; $i < self::NUM_CAPTCHA_QUESTIONS; $i++) {
                $data["check_fields"][] = "question_$i";
            }
        }
        $this->preactivityPrerequisiteCheck($activity,
            'processRecoverData', 'recoverPassword', $data);
        unset($data["check_fields"]);
        $new_data = $this->call($activity);
        $data = array_merge($new_data, $data);
        if(isset($new_data['REFRESH'])) {
            $data['REFRESH'] = $new_data['REFRESH'];
        }
        if(isset($new_data['SCRIPT']) && $new_data['SCRIPT'] != "") {
            $data['SCRIPT'] .= $new_data['SCRIPT'];
        }
        $data[CSRF_TOKEN] = $this->generateCSRFToken($user);
        $view = $data['REFRESH'];
        if(!isset($_SESSION['REMOTE_ADDR'])) {
            if($_REQUEST['a'] != 'createAccount' && !(
                $_REQUEST['a'] == 'suggestUrl' && !isset($_REQUEST['arg']))) {
                $view = "signin";
                $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_need_cookies')."</h1>');";
            }
            $visitor_model->updateVisitor(
                $_SERVER['REMOTE_ADDR'], "captcha_time_out");
        }
        if(AUTHENTICATION_MODE == ZKP_AUTHENTICATION) {
            $_SESSION['SALT_VALUE'] = rand(0, 1);
            $data['AUTH_ITERATION'] = FIAT_SHAMIR_ITERATIONS;
            $data['FIAT_SHAMIR_MODULUS'] = FIAT_SHAMIR_MODULUS;
            $data['INCLUDE_SCRIPTS'] = array("sha1", "zkp", "big_int");
        } else {
            unset($_SESSION['SALT_VALUE']);
        }
        if(CAPTCHA_MODE == HASH_CAPTCHA) {
            if(isset($data['INCLUDE_SCRIPTS'])) {
                array_push($data['INCLUDE_SCRIPTS'], "hash_captcha");
            } else {
               $data['INCLUDE_SCRIPTS'] = array("sha1", "hash_captcha");
            }
        }
        //used to ensure that we have sessions active
        $_SESSION['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'];
        $this->displayView($view, $data);
    }

    /**
     *  Sets up the form variables need to present the initial account creation
     *  form. If this form is submitted with missing fields, this method
     *  would also be called to set up an appropriate MISSING field
     *
     *  @return array $data field correspond to values needed for account
     *      creation form
     */
    function createAccount()
    {
        $data = $this->setupQuestionViewData();
        return $data;
    }

    /**
     *  Used to process account data from completely filled in create account
     *  forms. Depending on the registration type: no_activation,
     *  email registration, or admin activation, either the account is
     *  immediately activated or it is created in an active state and an email
     *  to the person who could activate it is sent.
     *
     *  @return array $data will contain a SCRIPT field with the
     *      Javascript doMessage call saying whether this step was successful
     *      or not
     */
    function processAccountData()
    {
        $data = array();
        $this->getCleanFields($data);
        $data['SCRIPT'] = "";
        $user_model = $this->model("user");
        switch(REGISTRATION_TYPE)
        {
            case 'no_activation':
                $data['REFRESH'] = "signin";
                if(AUTHENTICATION_MODE == NORMAL_AUTHENTICATION) {
                    $user_model->addUser($data['USER'], $data['PASSWORD'],
                        $data['FIRST'], $data['LAST'], $data['EMAIL']);
                } else {
                    $user_model->addUser($data['USER'], '',
                        $data['FIRST'], $data['LAST'], $data['EMAIL'],
                        ACTIVE_STATUS, $data['PASSWORD']);
                }
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_account_created')."</h1>')";
            break;
            case 'email_registration':
                $data['REFRESH'] = "signin";
                if(AUTHENTICATION_MODE == NORMAL_AUTHENTICATION) {
                    $user_model->addUser($data['USER'], $data['PASSWORD'],
                        $data['FIRST'], $data['LAST'], $data['EMAIL'],
                        INACTIVE_STATUS);
                } else {
                    $user_model->addUser($data['USER'], '',
                        $data['FIRST'], $data['LAST'], $data['EMAIL'],
                        INACTIVE_STATUS, $data['PASSWORD']);
                }
                $user = $user_model->getUser($data['USER']);
                $server = new MailServer(MAIL_SENDER, MAIL_SERVER,
                    MAIL_SERVERPORT, MAIL_USERNAME, MAIL_PASSWORD,
                    MAIL_SECURITY);
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_registration_email_sent').
                    "</h1>');";
                $subject = tl('register_controller_admin_activation_request');
                $message = tl('register_controller_admin_email_salutation',
                    $data['FIRST'], $data['LAST'])."\n";
                $message .= tl('register_controller_email_body')."\n";
                $creation_time = vsprintf('%d.%06d', gettimeofday());
                $message .= BASE_URL.
                    "?c=register&a=emailVerification&email=".
                    $user['EMAIL']."&time=".$user['CREATION_TIME'].
                    "&hash=".urlencode(crawlCrypt($user['HASH']));
                $server->send($subject, MAIL_SENDER, $data['EMAIL'], $message);
                if(AUTHENTICATION_MODE == NORMAL_AUTHENTICATION) {
                    $num_questions = self::NUM_CAPTCHA_QUESTIONS +
                        self::NUM_RECOVERY_QUESTIONS;
                    $start = self::NUM_CAPTCHA_QUESTIONS;
                } else {
                    $num_questions =  self::NUM_RECOVERY_QUESTIONS;
                    $start = 0;
                }
                for($i = $start; $i < $num_questions; $i++) {
                    $j = $i - $start;
                    $_SESSION["RECOVERY_ANSWERS"][$j] =
                        $this->clean($_REQUEST["question_$i"],"string");
                }
            break;
            case 'admin_activation':
                $data['REFRESH'] = "signin";
                if(AUTHENTICATION_MODE == NORMAL_AUTHENTICATION) {
                    $user_model->addUser($data['USER'], $data['PASSWORD'],
                        $data['FIRST'], $data['LAST'], $data['EMAIL'],
                        INACTIVE_STATUS);
                } else {
                    $user_model->addUser($data['USER'], '',
                        $data['FIRST'], $data['LAST'], $data['EMAIL'],
                        INACTIVE_STATUS, $data['PASSWORD']);
                }
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_account_request_made')."</h1>');";
                $server = new MailServer(MAIL_SENDER, MAIL_SERVER,
                    MAIL_SERVERPORT, MAIL_USERNAME, MAIL_PASSWORD,
                    MAIL_SECURITY);
                $subject = tl('register_controller_admin_activation_request');
                $message = tl('register_controller_admin_activation_message',
                    $data['FIRST'], $data['LAST'], $data['USER']);
                $server->send($subject, MAIL_SENDER, MAIL_SENDER, $message);
            break;
        }
        $user = $user_model->getUser($data['USER']);
        if(isset($user['USER_ID'])) {
            $user_model->setUserSession($user['USER_ID'], $_SESSION);
        }
        unset($_SESSION['CAPTCHA_ANSWERS']);
        unset($_SESSION['CAPTCHA']);
        unset($_SESSION['RECOVERY_ANSWERS']);
        unset($_SESSION['RECOVERY']);
        return $data;
    }


    /**
     *  Used to verify the email sent to a user try to set up an account.
     *  If the email is legit the account is activated
     *
     *  @return array $data will contain a SCRIPT field with the
     *      Javascript doMessage call saying whether verification was
     *      successful or not
     */
    function emailVerification()
    {
        $data = array();
        $data['REFRESH'] = "signin";
        $data['SCRIPT'] = "";
        $user_model = $this->model("user");
        $clean_fields = array("email", "time", "hash");
        $verify = array();
        $error = false;
        foreach($clean_fields as $field) {
            if(isset($_REQUEST[$field])) {
                $verify[$field] = $this->clean($_REQUEST[$field], "string");
            } else {
                $error = true;
                break;
            }
        }
        if($error) {
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                tl('register_controller_email_verification_error')."</h1>');";
        } else {
            $user = $user_model->getUserByEmailTime($verify["email"],
                $verify["time"]);
            if(isset($user['STATUS']) && $user['STATUS'] == ACTIVE_STATUS) {
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_already_activated')."</h1>');";
            } else {
                $hash = crawlCrypt($user["HASH"], $verify["hash"]);
                if(isset($user["HASH"]) && $hash == $verify["hash"]) {
                    $user_model->updateUserStatus($user["USER_ID"],
                        ACTIVE_STATUS);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('register_controller_account_activated')."</h1>');";
                } else {
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('register_controller_email_verification_error').
                        "</h1>');";
                    $this->model("visitor")->updateVisitor(
                        $_SERVER['REMOTE_ADDR'], "captcha_time_out");
                }
            }
        }
        unset($_SESSION['CAPTCHA_ANSWERS']);
        unset($_SESSION['CAPTCHA']);
        unset($_SESSION['RECOVERY_ANSWERS']);
        unset($_SESSION['RECOVERY']);
        $_SESSION['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'];
        return $data;
    }

    /**
     *  Sets up the form variables need to present the initial recover account
     *  form. If this form is submitted with missing fields, this method
     *  would also be called to set up an appropriate MISSING field
     *
     *  @return array $data field correspond to values needed for account
     *      recovery form
     */
    function recoverPassword()
    {
        $data = $this->setupQuestionViewData();
        $data['REFRESH'] = "recover";
        return $data;
    }

    /**
     *  Called with the data from the initial recover form was completely
     *  provided and captcha was correct. This method
     *  sends the recover email provided the account had
     *  recover questions set otherwise sets up an error message.
     *
     *  @return array $data will contain a SCRIPT field with the
     *      Javascript doMessage call saying whether email sent or if there
     *      was a problem
     */
    function processRecoverData()
    {
        $data = array();
        $this->getCleanFields($data);
        $data['SCRIPT'] = "";
        $user_model = $this->model("user");
        $data["REFRESH"] = "signin";
        $user = $user_model->getUser($data['USER']);
        if(!$user) {
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                tl('register_controller_account_recover_fail')."</h1>');";
            $this->model("visitor")->updateVisitor(
                $_SERVER['REMOTE_ADDR'], "captcha_time_out");
            return $data;
        }
        $session = $user_model->getUserSession($user["USER_ID"]);
        if(CAPTCHA_MODE == TEXT_CAPTCHA) {
            if(!isset($session['RECOVERY']) ||
                !isset($session['RECOVERY_ANSWERS'])) {
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                tl('register_controller_account_recover_fail')."</h1>');";
                return $data;
            }
        }
        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
            tl('register_controller_account_recover_email')."</h1>');";
        $server = new MailServer(MAIL_SENDER, MAIL_SERVER,
            MAIL_SERVERPORT, MAIL_USERNAME, MAIL_PASSWORD,
            MAIL_SECURITY);
        $subject = tl('register_controller_recover_request');
        $message = tl('register_controller_admin_email_salutation',
            $user['FIRST_NAME'], $user['LAST_NAME'])."\n";
        $message .= tl('register_controller_recover_body')."\n";
        $time = time();
        $message .= BASE_URL.
            "?c=register&a=recoverComplete&user=".
            $user['USER_NAME']."&time=".$time.
            "&hash=".urlencode(crawlCrypt(
                $user['HASH'].$time.$user['USER_NAME'].AUTH_KEY));
        $server->send($subject, MAIL_SENDER, $user['EMAIL'], $message);
        unset($_SESSION['CAPTCHA_ANSWERS']);
        unset($_SESSION['CAPTCHA']);
        unset($_SESSION['RECOVERY_ANSWERS']);
        unset($_SESSION['RECOVERY']);
        return $data;
    }

    /**
     *  This activity either verifies the recover email and sets up the
     *  appropriate  data for a change password form or it verifies the
     *  change password form data and changes the password. If verifications
     *  error messages are set up
     *
     *  @return array form data to be used by recover or signin views
     */
    function recoverComplete()
    {
        $data = array();
        $data['REFRESH'] = "signin";
        $user_model = $this->model("user");
        $visitor_model = $this->model("visitor");
        $fields = array("user", "hash", "time");
        if(isset($_REQUEST['finish_hash'])) {
            $fields = array("user", "finish_hash", "time", "password",
                "repassword");
        }
        $recover_fail = "doMessage('<h1 class=\"red\" >".
            tl('register_controller_account_recover_fail')."</h1>');";
        foreach($fields as $field) {
            if(isset($_REQUEST[$field])) {
                $data[$field] = $this->clean($_REQUEST[$field], "string");
            } else {
                $data['SCRIPT'] = $recover_fail;
                return $data;
            }
        }
        $user = $user_model->getUser($data["user"]);
        if(!$user) {
            $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                tl('register_controller_account_recover_fail')."</h1>');";
            return $data;
        }
        $user_session = $user_model->getUserSession($user["USER_ID"]);
        if(isset($data['finish_hash'])) {
            $finish_hash = urlencode(crawlCrypt($user['HASH'].$data["time"].
                $user['CREATION_TIME'] . AUTH_KEY,
                urldecode($data['finish_hash'])));
            if($finish_hash != $data['finish_hash'] ||
                !$this->checkRecoveryQuestions($user)) {
                $visitor_model->updateVisitor(
                    $_SERVER['REMOTE_ADDR'], "captcha_time_out");
                $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_account_recover_fail')."</h1>');";
                return $data;
            }
            if($data["password"] == $data["repassword"]) {
                if(isset($user_session['LAST_RECOVERY_TIME']) &&
                    $user_session['LAST_RECOVERY_TIME'] > $data["time"]) {
                    $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                        tl('register_controller_recovered_already')."</h1>');";
                    return $data;
                } else if(time() - $data["time"] > CrawlConstants::ONE_DAY) {
                    $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                        tl('register_controller_recovery_expired')."</h1>');";
                    return $data;
                } else {
                    $user["PASSWORD"] = crawlCrypt($data["password"]);
                    $user_model->updateUser($user);
                    $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                        tl('register_controller_password_changed')."</h1>');";
                    $user_session['LAST_RECOVERY_TIME'] = time();
                    $user_model->setUserSession($user["USER_ID"],
                        $user_session);
                    return $data;
                }
            } else {
                $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_passwords_dont_match')."</h1>');";
            }
        } else {
            $hash = crawlCrypt(
                $user['HASH'].$data["time"].$user['USER_NAME'].AUTH_KEY,
                $data['hash']);
            if($hash != $data['hash']) {
                $visitor_model->updateVisitor(
                    $_SERVER['REMOTE_ADDR'], "captcha_time_out");
                $data['SCRIPT'] = $recover_fail;
                return $data;
            } else if(isset($user_session['LAST_RECOVERY_TIME']) &&
                    $user_session['LAST_RECOVERY_TIME'] > $data["time"]) {
                $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_recovered_already')."</h1>');";
                return $data;
            } else if(time() - $data["time"] > CrawlConstants::ONE_DAY) {
                $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_recovery_expired')."</h1>');";
                return $data;
            }
        }
        if(!isset($user_session['RECOVERY']) ||
            !isset($user_session['RECOVERY_ANSWERS'])) {
            $data['SCRIPT'] = $recover_fail;
            return $data;
        }
        $data['PASSWORD'] = "";
        $data['REPASSWORD'] = "";
        for($i = 0; $i < self::NUM_RECOVERY_QUESTIONS; $i++) {
            $data["question_$i"] = "";
        }
        $data["RECOVERY"] = $user_session['RECOVERY'];
        $data["REFRESH"] = "recover";
        $data["RECOVER_COMPLETE"] = true;
        $data['finish_hash'] = urlencode(crawlCrypt($user['HASH'].$data["time"].
                $user['CREATION_TIME'] . AUTH_KEY));
        return $data;
    }

    /**
     *  Used to handle data from the suggest-a-url to crawl form
     *  (suggest_view.php). Basically, it saves any data submitted to
     *  a file which can then be imported in manageCrawls
     *
     *  @return array $data contains fields with the current value for
     *      the url (if set but not submitted) as well as for a captcha
     */
    function suggestUrl()
    {
        $data["REFRESH"] = "suggest";
        $visitor_model = $this->model("visitor");
        $clear = false;
        if(CAPTCHA_MODE != GRAPHICAL_CAPTCHA && 
                isset($_SESSION["graphical_captcha_string"])) {
            unset($_SESSION["graphical_captcha_string"]);
        }
        if(CAPTCHA_MODE == HASH_CAPTCHA) {
            $data['INCLUDE_SCRIPTS'] = array("sha1", "hash_captcha");
        }
        if(CAPTCHA_MODE == TEXT_CAPTCHA) {
            $num_captchas = self::NUM_CAPTCHA_QUESTIONS;
            unset($_SESSION["request_time"]);
            unset($_SESSION["level"] );
            unset($_SESSION["random_string"] );
        }
        if(!isset($_SESSION['BUILD_TIME']) || !isset($_REQUEST['build_time'])||
            $_SESSION['BUILD_TIME'] != $_REQUEST['build_time']) {
            if(CAPTCHA_MODE == HASH_CAPTCHA) {
                $time = time();
                $_SESSION["request_time"] = $time;
                $_SESSION["level"] = self::HASH_CAPTCHA_LEVEL;
                $_SESSION["random_string"] = md5( $time . AUTH_KEY );
            }
            $clear = true;
            if(isset($_REQUEST['url'])) {
                unset($_REQUEST['url']);
            }
            if(isset($_REQUEST['arg'])) {
                unset($_REQUEST['arg']);
            }
            $data['build_time'] = time();
            $_SESSION['BUILD_TIME'] = $data['build_time'];
        }
        if(CAPTCHA_MODE == TEXT_CAPTCHA) {
            for($i = 0; $i < $num_captchas; $i++) {
                $data["question_$i"] = "-1";
                if($clear && isset($_REQUEST["question_$i"])) {
                    unset($_REQUEST["question_$i"]);
                }
            }
        }
        $data['url'] = "";
        if(isset($_REQUEST['url'])) {
            $data['url'] = $this->clean($_REQUEST['url'], "string");
        }
        if(CAPTCHA_MODE == TEXT_CAPTCHA) {
            if(!isset($_SESSION['CAPTCHA']) ||
               !isset($_SESSION['CAPTCHA_ANSWERS'])){
                list($captchas, $answers) = $this->selectQuestionsAnswers(
                    $this->captchas_qa, $num_captchas, 
                    self::NUM_CAPTCHA_CHOICES);
                $data['CAPTCHA'] = $captchas;
                $data['build_time'] = time();
                $_SESSION['BUILD_TIME'] = $data['build_time'];
                $_SESSION['CAPTCHA_ANSWERS'] = $answers;
                $_SESSION['CAPTCHA'] = $data['CAPTCHA'];
            } else {
                $data['CAPTCHA'] = $_SESSION['CAPTCHA'];
            }
        }
        $missing = array();
        $save = isset($_REQUEST['arg']) && $_REQUEST['arg'];
        if(CAPTCHA_MODE == GRAPHICAL_CAPTCHA && !$save) {
            $this->setupGraphicalCaptchaViewData();
        }
        if(CAPTCHA_MODE == TEXT_CAPTCHA) {
            for($i = 0; $i < $num_captchas; $i++) {
            $field = "question_$i";
            $captchas = isset($_SESSION['CAPTCHA'][$i]) ?
                $_SESSION['CAPTCHA'][$i] : array();
            if($save) {
                if(!isset($_REQUEST[$field]) || $_REQUEST[$field] == "-1" ||
                    !in_array($_REQUEST[$field], $captchas)) {
                    $missing[] = $field;
                } else {
                    $data[$field] = $_REQUEST[$field];
                }
            }
          }
        }
        $data['MISSING'] = $missing;
        if($save && isset($_REQUEST['url'])) {
			print("suggest url saved?");
            $url = $this->clean($_REQUEST['url'], "string");
            $url_parts = @parse_url($url);
            if(!isset($url_parts['scheme'])) {
                $url = "http://".$url;
            }
            $suggest_host = UrlParser::getHost($url);
            $scheme = UrlParser::getScheme($url);
            if(!$suggest_host || !in_array($scheme, array("http", "https"))) {
                $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_invalid_url')."</h1>');";
                return $data;
            }
            if($missing != array()) {
                $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_error_fields')."</h1>');";
                return $data;
            }
           if(CAPTCHA_MODE == HASH_CAPTCHA) {
                if(!$this->validateHashCode()) {
                    $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_failed_hashcode')."</h1>');";
                    $visitor_model->updateVisitor(
                    $_SERVER['REMOTE_ADDR'], "captcha_time_out");
                    return $data;
                }
            }
            if(CAPTCHA_MODE == TEXT_CAPTCHA) {
                if(!$this->checkCaptchaAnswers()) {
                    $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_failed_human')."</h1>');";
                for($i = 0; $i < $num_captchas; $i++) {
                    $data["question_$i"] = "-1";
                
                 }
                unset($_SESSION['CAPTCHA']);
                unset($_SESSION['CAPTCHA_ANSWERS']);
                $visitor_model->updateVisitor(
                    $_SERVER['REMOTE_ADDR'], "captcha_time_out");
                return $data;
                }
            }
            if(CAPTCHA_MODE == GRAPHICAL_CAPTCHA) {
                if(isset ($_SESSION['graphical_captcha_string']) && 
                        $_SESSION['graphical_captcha_string'] != 
                        $_REQUEST['user_entered_graphical_captcha_string']) {
                    $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                        tl('register_controller_failed_graphical_human').
                        "</h1>');";
                    unset($_SESSION['graphical_captcha_string']);
                    $this->setupGraphicalCaptchaViewData();
                    $visitor_model->updateVisitor(
                        $_SERVER['REMOTE_ADDR'], "captcha_time_out");
                    return $data;
                }
                $this->setupGraphicalCaptchaViewData();
            }
            if(!$this->model("crawl")->appendSuggestSites($url)) {
                $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                tl('register_controller_suggest_full')."</h1>');";
                return $data;
            }
            $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                tl('register_controller_url_submitted')."</h1>');";
            $visitor_model->updateVisitor(
                $_SERVER['REMOTE_ADDR'], "suggest_day_exceeded",
                self::ONE_DAY, self::ONE_DAY, MAX_SUGGEST_URLS_ONE_DAY);
            if(CAPTCHA_MODE == TEXT_CAPTCHA) {
                for($i = 0; $i < $num_captchas; $i++) {
                $data["question_$i"] = "-1";
                }
                unset($_SESSION['CAPTCHA']);
                unset($_SESSION['CAPTCHA_ANSWERS']);
                list($captchas, $answers) = $this->selectQuestionsAnswers(
                    $this->captchas_qa, $num_captchas, 
                    self::NUM_CAPTCHA_CHOICES);
                $data['CAPTCHA'] = $captchas;
                $_SESSION['CAPTCHA_ANSWERS'] = $answers;
                $_SESSION['CAPTCHA'] = $data['CAPTCHA'];
            }
            $data['build_time'] = time();
            $_SESSION['BUILD_TIME'] = $data['build_time'];
            $data['url'] =""; 
        } 
        return $data;
    }

    /**
     *  Sets up the captcha question and or recovery questions in a $data
     *  associative array so that they can be drawn by the register or recover
     *  views.
     *
     *  @return array $data associate array with field to help the register and
     *      recover view draw themselves
     */
    function setupQuestionViewData()
    {
        $data = array();
        $fields = $this->register_fields;
        foreach($fields as $field) {
            $data[strtoupper($field)] = "";
        }
        for($i = 0; $i < self::NUM_CAPTCHA_QUESTIONS +
            self::NUM_CAPTCHA_CHOICES; $i++) {
            $data["question_$i"] = "-1";
        }
        if(AUTHENTICATION_MODE == ZKP_AUTHENTICATION) {
            $data['AUTHENTICATION_MODE'] = ZKP_AUTHENTICATION;
            $data['INCLUDE_SCRIPTS'] = array("sha1", "zkp"," big_int");
         } else {
            $data['AUTHENTICATION_MODE'] = NORMAL_AUTHENTICATION;
         }
        if(CAPTCHA_MODE == HASH_CAPTCHA) {
            if(isset($data['INCLUDE_SCRIPTS'])) {
                array_push($data['INCLUDE_SCRIPTS'], "hash_captcha");
            } else {
                $data['INCLUDE_SCRIPTS'] = array("hash_captcha", "sha1");
            }
            $time = time();
            $_SESSION["request_time"] = $time;
            $_SESSION["level"] = self::HASH_CAPTCHA_LEVEL;
            $_SESSION["random_string"] = md5( $time . AUTH_KEY );
        }
        if(CAPTCHA_MODE == TEXT_CAPTCHA) {
            unset($_SESSION["request_time"]);
            unset($_SESSION["level"] );
            unset($_SESSION["random_string"] );
            if(empty($_SESSION['CAPTCHA'])) {
                unset($_SESSION['CAPTCHA']);
            }
            if(!isset($_SESSION['CAPTCHA']) || !isset(
                    $_SESSION['CAPTCHA_ANSWERS'])){
                list($captchas, $answers) = $this->selectQuestionsAnswers(
                    $this->captchas_qa, self::NUM_CAPTCHA_QUESTIONS,
                    self::NUM_CAPTCHA_CHOICES);
                $data['CAPTCHA'] = $captchas;
                $_SESSION['CAPTCHA_ANSWERS'] = $answers;
                $_SESSION['CAPTCHA'] = $data['CAPTCHA'];
            } else {
                $data['CAPTCHA'] = $_SESSION['CAPTCHA'];
            }
        }
        if(CAPTCHA_MODE == GRAPHICAL_CAPTCHA) {
            $this->setupGraphicalCaptchaViewData();
        }
        if(!isset($_SESSION['RECOVERY'])) {
            list($data['RECOVERY'], ) = $this->selectQuestionsAnswers(
                $this->recovery_qa, self::NUM_RECOVERY_QUESTIONS);
            $_SESSION['RECOVERY'] = $data['RECOVERY'];
        } else {
            $data['RECOVERY'] = $_SESSION['RECOVERY'];
        }
        return $data;
    }
    
    /**
     * Sets up the graphical captcha view
     * Draws the string for graphical captcha
     */
    
    function setupGraphicalCaptchaViewData() {
        unset($_SESSION["graphical_captcha_string"]);
        // defines captcha text
        $characters_for_captcha = '1234567890'.
            'abcdefghijklmnopqrstuvwxyz'.
            'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $len = strlen($characters_for_captcha);
        // selecting letters for captcha
        $captcha_letter = $characters_for_captcha[rand(0, $len - 1)]; 
        $word = "";
        for ($i = 0; $i < 6; $i++) {
            // selecting letters for captcha
            $captcha_letter = $characters_for_captcha[rand(0, $len - 1)];
            $word = $word.$captcha_letter;
         }
         // stores the captcha in a session variable 'graphical_captcha_string'
         $_SESSION['graphical_captcha_string'] = $word;
    }
    
    /**
     *  Picks $num_select most/least questions from an associative array
     * which contains 2 other arrays one for most and one for least.
     * The arrays for most or least in turn contains and array of
     * question-choices pair; question: Which is the most ..? and 
     * choices: which contains a comma separated list of choices ranked from 
     * least to most. For each question pick, $num_choices many items from 
     * the choices would be selected.
     * An example associative array would like this.
     * $this->captchas_qa = array(
     *  MOST => array(
     *      array(
     *          "question"=>"register_controller_question0_most",
     *          "choices"=>"register_controller_question0_choices"
     *      ),
     *      array(
     *          "question"=>"register_controller_question1_most",
     *          "choices"=>"register_controller_question1_choices"
     *      )
     *
     *  ),
     *  LEAST => array(
     *      array(
     *          "question"=>"register_controller_question0_least",
     *          "choices"=>"register_controller_question0_choices"
     *      ),
     *      array(
     *          "question"=>"register_controller_question0_least",
     *          "choices"=>"register_controller_question0_choices"
     *      )
     *    )
     *   );
     *
     *  @param array $questions_answers an array t_1, t_2, t_3, t_4, where
     *      each t_i is an associative array containing the most
     *      and least arrays as described above
     *  @param int $num_select number of triples from the list to pick
     *      for each triple pick either the most question or the least
     *      question
     *  @param int $num_choices from the list component of a triplet we
     *      we pick this many elements
     *  @return array a pair consisting of an array of questions and possible
     *      choice for least/most, and another array of the correct answers
     *      to the lest most problem.
     */
    function selectQuestionsAnswers($question_answers, $num_select,
        $num_choices = -1)
    {
        $questions = array();
        $answers = array();
        // Clone arrays so you can unset values
        $qa_least_arr = array_merge(array(), $question_answers[LEAST]);
        $qa_most_arr = array_merge(array(), $question_answers[MOST]);

        for($i = 0; $i < $num_select && (count($qa_most_arr) > 0 && 
                count($qa_least_arr) > 0); $i++) {
            $least_or_most = (rand(0, 1))?MOST:LEAST;
            if(count($qa_most_arr) == 0 && count($qa_least_arr) > 0) {
                $least_or_most = LEAST;
            } elseif (count($qa_most_arr) >= 0 && count($qa_least_arr) == 0) {
                $least_or_most = MOST;
            }
            
            $selected_question_choice = Null;
            $most_question_choice_index = -1;
            $least_question_choice_index = -1;
            if($least_or_most == MOST) {
              $size_qa = count($qa_most_arr);
              $most_question_choice_index = mt_rand(0, $size_qa - 1);
              $selected_question_choice = 
                  $qa_most_arr[$most_question_choice_index];
            } else {
              $size_qa = count($qa_least_arr);
              $least_question_choice_index = mt_rand(0, $size_qa - 1);
              $selected_question_choice = 
                  $qa_least_arr[$least_question_choice_index];
            }
            
            $answer_possibilities =
                explode(",", 
                    $selected_question_choice["choices"]);
            $selected_possibilities = array();
            $size_possibilities = count($answer_possibilities);
            if($num_choices < 0) {
                $num = $size_possibilities;
            } else {
                $num = $num_choices;
            }
            for($j = 0; $j < $num; $j++) {
              do {
                  $selected_possibility = mt_rand(0, $size_possibilities - 1);
              } while(isset($selected_possibilities[$selected_possibility]));
              $selected_possibilities[$selected_possibility] =
                  $answer_possibilities[$selected_possibility];
            }
            $questions[$i] =  array("-1" =>
                    $selected_question_choice["question"]);
            $tmp = array_values($selected_possibilities);
            $questions[$i] +=  array_combine($tmp, $tmp);
            if($least_or_most == MOST) {
                ksort($selected_possibilities);
                $selected_possibilities = 
                    array_values($selected_possibilities);
                $answers[$i] = $selected_possibilities[0];
                unset($qa_most_arr[$most_question_choice_index]);
                $qa_most_arr = array_values($qa_most_arr);
            } else {
                krsort($selected_possibilities);
                $selected_possibilities = 
                    array_values($selected_possibilities);
                $answers[$i] = $selected_possibilities[0];
                unset($qa_least_arr[$least_question_choice_index]);
                $qa_least_arr = array_values($qa_least_arr);
            }
        }
        return array($questions, $answers);
    }

    /**
     *  Used to select which activity a controller will do. If the $activity
     *  is $activity_success, then this method checks the prereqs for
     *  $activity_success. If they are not met then the view $data array is
     *  updated with an error message and $activity_fail is set to be the
     *  next activity. If the prereq is met then the $activity is left as
     *  $activity_success. If $activity was not initially equal to
     *  $activity_success then this method does nothing.
     *
     *  @param string &$activity current tentative activity
     *  @param string $activity_success activity to test for and to test prereqs
     *      for.
     *  @param string $activity_fail if prereqs not met which acitivty to switch
     *      to
     *  @param array &$data data to help render the view this controller draws
     */
    function preactivityPrerequisiteCheck(&$activity,
        $activity_success, $activity_fail, &$data)
    {
        $profile_model = $this->model("profile");
        $profile = $profile_model->getProfile(WORK_DIRECTORY);
        if($activity == $activity_success) {
            $this->dataIntegrityCheck($data);
            if(!$data["SUCCESS"]) {
                $activity = $activity_fail;
            }
            if($activity == $activity_success) {
                if(CAPTCHA_MODE == TEXT_CAPTCHA) {
                    if(!isset($_SESSION['CAPTCHA_ANSWERS']) ) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('register_controller_need_cookies')."</h1>');";
                        $activity = 'createAccount';
                        $this->model("visitor")->updateVisitor(
                        $_SERVER['REMOTE_ADDR'], "captcha_time_out");
                    } else if(!$this->checkCaptchaAnswers()) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('register_controller_failed_human')."</h1>');";
                        for($i = 0; $i < self::NUM_CAPTCHA_QUESTIONS; $i++) {
                            $data["question_$i"] = "-1";
                        }
                    unset($_SESSION['CAPTCHA']);
                    unset($_SESSION['CAPTCHA_ANSWERS']);
                    $this->model("visitor")->updateVisitor(
                        $_SERVER['REMOTE_ADDR'], "captcha_time_out");
                    $activity = $activity_fail;
                    }
                } elseif (CAPTCHA_MODE == GRAPHICAL_CAPTCHA) {
                    if($_SESSION['graphical_captcha_string'] != 
                            $_REQUEST[
                            'user_entered_graphical_captcha_string']) {
                        $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                        tl('register_controller_failed_graphical_human')."
                            </h1>');";
                        unset($_SESSION['graphical_captcha_string']);
                        $this->model("visitor")->updateVisitor(
                            $_SERVER['REMOTE_ADDR'], "captcha_time_out");
                        $activity = $activity_fail;
                    }
                } else {
                    if(!$this->validateHashCode()) {
                        $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                        tl('register_controller_failed_hashcode')."</h1>');";
                        $this->model("visitor")->updateVisitor(
                        $_SERVER['REMOTE_ADDR'], "captcha_time_out");
                        $activity = $activity_fail;
                    }
                }
            }
        }
    }


    /**
     *  Add SCRIPT tags for errors to the view $data array if there were any
     *  missing fields on a create account or recover account form.
     *  also adds error info if try to create an existing using.
     *
     *  @param array &$data contains info for the view on which the above
     *      forms are to be drawn.
     */
    function dataIntegrityCheck(&$data)
    {
        if(!isset($data['SCRIPT'])) {
            $data['SCRIPT'] = "";
        }
        $data['SUCCESS'] = true;
        $this->getCleanFields($data);
        if($data['MISSING'] != array()) {
            $data['SUCCESS'] = false;
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
            tl('register_controller_error_fields')."</h1>');";
        } else if(isset($data["check_user"]) &&
            $this->model("user")->getUserId($data['USER'])) {
            $data['SUCCESS'] = false;
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
            tl('register_controller_user_already_exists')."</h1>');";
        }
    }

    /**
     *  Checks whether the answers to the captcha question presented to a user
     *  are all correct or if any were mis-answered
     *
     *  @return bool true if only if all were correct
     */
    function checkCaptchaAnswers()
    {
        $captcha_passed = true;
        
        for($i = 0; $i < self::NUM_CAPTCHA_QUESTIONS; $i++) {
	
            $field = "question_".$i;
            if($_REQUEST[$field] != $_SESSION['CAPTCHA_ANSWERS'][$i]) {
                $captcha_passed = false;
                break;
              
            }
        }
        return $captcha_passed;
    }

    /**
     *  Checks whether the answers to the account recovery questions match 
     * those provided earlier by an account user
     *
     *  @param array $user who to check recovery answers for
     *  @return bool true if only if all were correct
     */
    function checkRecoveryQuestions($user)
    {
        $user_session = $this->model("user")->getUserSession($user["USER_ID"]);
        if(!isset($user_session['RECOVERY_ANSWERS'])) {
            return false;
        }
        $recovery_passed = true;
        for($i = 0; $i < self::NUM_RECOVERY_QUESTIONS; $i++) {
            $field = "question_".$i;
            if($_REQUEST[$field] != $user_session['RECOVERY_ANSWERS'][$i]) {
                $recovery_passed = false;
                $this->model("visitor")->updateVisitor(
                    $_SERVER['REMOTE_ADDR'], "captcha_time_out");
                break;
            }
        }
        return $recovery_passed;
    }

    /**
     *  Used to clean the inputs for form variables
     *  for creating/recovering an account. It also puts
     *  in blank values for missing fields into a "MISSING"
     *  array
     *
     *  @param array &$data an array of data to be sent to the view
     *      After this method is done it will have cleaned versions
     *      of the $_REQUEST variables from create or recover account
     *      forms as well as a "MISSING" field which is an array of
     *      those items which did not have values on the create/recover
     *      account form
     */
    function getCleanFields(&$data)
    {
        $fields = $this->register_fields;
        if(isset($data["check_fields"])) {
            $fields = $data["check_fields"];
        }
        if(!isset($data["SCRIPT"])) {
            $data["SCRIPT"] = "";
        }
        $missing = array();
        $regex_email=
            '/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+'.
            '(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/';
        foreach($fields as $field) {
            if(!isset($_REQUEST[$field]) || empty($_REQUEST[$field])) {
                $error = true;
                $missing[] = $field;
                $data[strtoupper($field)] = "";
            } else if($field == "email" &&
                !preg_match($regex_email,
                $this->clean($_REQUEST['email'], "string" ))) {
                $error = true;
                $missing[] = "email";
                $data[strtoupper($field)] = "";
            } else {
                $data[strtoupper($field)] = $this->clean($_REQUEST[$field],
                    "string");
            }
        }
        if(isset($_REQUEST['password'])
            && isset($_REQUEST['repassword'])
            && $this->clean($_REQUEST['password'], "string" ) !=
            $this->clean($_REQUEST['repassword'], "string" )) {
            $error = true;
            $missing[] = "password";
            $missing[] = "repassword";
            $data["PASSWORD"] = "";
            $data["REPASSWORD"] = "";
        }
        if(CAPTCHA_MODE == TEXT_CAPTCHA) {
            $num_questions = self::NUM_CAPTCHA_QUESTIONS +
                self::NUM_RECOVERY_QUESTIONS;
            $num_captchas = self::NUM_CAPTCHA_QUESTIONS;
            for($i = 0; $i < $num_questions; $i++) {
                $field = "question_$i";
                if(!in_array($field, $fields)) {
                    continue;
                }
                $captchas = isset($_SESSION['CAPTCHAS'][$i]) ?
                    $_SESSION['CAPTCHAS'][$i] : array();
                $recovery = isset($_SESSION['RECOVERY'][$i  - $num_captchas]) ?
                    $_SESSION['RECOVERY'][$i  - $num_captchas] : array();
                $current_dropdown = ($i< $num_captchas) ?
                    $captchas : $recovery;
                if(!isset($_REQUEST[$field]) || $_REQUEST[$field] == "-1" ||
                    !in_array($_REQUEST[$field], $current_dropdown)) {
                    $missing[] = $field;
                } else {
                    $data[$field] = $_REQUEST[$field];
                }
            }
        }
        $data['MISSING'] = $missing;
    }

     /**
     *  Calculates the sha1 of a string consist of a randomString,request_time
     *  send by a server and the nonce send by a client.It checks
     *  whether the sha1 produces expected number of a leading zeroes
     *
     *  @return bool true if the sha1 produces expected number
     *  of a leading zeroes.
     */
    function validateHashCode()
    {
        $hex_key = $_SESSION["random_string"].':'.$_SESSION["request_time"].
                   ':'.$_REQUEST['nonce_for_string'];
        $pattern = '/^0{'.$_SESSION['level'].'}/';
        $time = time();
        $_SESSION["request_time"] = $time;
        $_SESSION["random_string"] =  md5( $time . AUTH_KEY );
        if((time()- $_SESSION["request_time"] < self::HASH_TIMESTAMP_TIMEOUT)
           && (preg_match($pattern, sha1($hex_key) ))){
            return true;
        }
        return false;
    }
}

?>
