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
     * To create an new account the register view is used, for password
     * reset/recovery the recover view is used, and on completion of forms/
     * on errors the signin page is returned.
     * @var array
     */
    var $views = array("recover", "register", "signin", "suggest");
    /**
     * List of models used by this controller
     * User model is used to add/update users; the Visitor model is
     * used to keep track of ip address of failed captcha or recovery questions
     * attempts
     * @var array
     */
    var $models = array("user", "visitor", "crawl");

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
     *  Besides invoking the base controller, sets up in field variables
     *  the captcha and recovery question and possible answers.
     */
    function __construct()
    {
        $this->captchas_qa = array(
            array(tl('register_controller_question0_most'),
                tl('register_controller_question0_least'),
                tl('register_controller_question0_choices')),
            array(tl('register_controller_question1_most'),
                tl('register_controller_question1_least'),
                tl('register_controller_question1_choices')),
            array(tl('register_controller_question2_most'),
                tl('register_controller_question2_least'),
                tl('register_controller_question2_choices')),
            array(tl('register_controller_question3_most'),
                tl('register_controller_question3_least'),
                tl('register_controller_question3_choices')),
            array(tl('register_controller_question4_most'),
                tl('register_controller_question4_least'),
                tl('register_controller_question4_choices')),
            array(tl('register_controller_question5_most'),
                tl('register_controller_question5_least'),
                tl('register_controller_question5_choices')),
            array(tl('register_controller_question6_most'),
                tl('register_controller_question6_least'),
                tl('register_controller_question6_choices')),
            array(tl('register_controller_question7_most'),
                tl('register_controller_question7_least'),
                tl('register_controller_question7_choices')),
            array(tl('register_controller_question8_most'),
                tl('register_controller_question8_least'),
                tl('register_controller_question8_choices')),
            array(tl('register_controller_question9_most'),
                tl('register_controller_question9_least'),
                tl('register_controller_question9_choices')),
            );
        $this->recovery_qa = array(
            array(tl('register_controller_recovery1_more'),
                tl('register_controller_recovery1_less'),
                tl('register_controller_recovery1_choices')),
            array(tl('register_controller_recovery2_more'),
                tl('register_controller_recovery2_less'),
                tl('register_controller_recovery2_choices')),
            array(tl('register_controller_recovery3_more'),
                tl('register_controller_recovery3_less'),
                tl('register_controller_recovery3_choices')),
            array(tl('register_controller_recovery4_more'),
                tl('register_controller_recovery4_less'),
                tl('register_controller_recovery4_choices')),
            array(tl('register_controller_recovery5_more'),
                tl('register_controller_recovery5_less'),
                tl('register_controller_recovery5_choices')),
            array(tl('register_controller_recovery6_more'),
                tl('register_controller_recovery6_less'),
                tl('register_controller_recovery6_choices'))
            );
        parent::__construct();
    }

    /**
     *  Main entry method for this controller. Determine which account
     *  creation/recovery activity needs to be performed. Calls the
     *  appropriate method, then sends the return $data to aview
     *  determined by that activity. $this->displayView then renders that
     *  view
     */
    function processRequest()
    {
        if(isset($_SESSION['USER_ID'])) {
            $user = $_SESSION['USER_ID'];
        } else {
            $user = $_SERVER['REMOTE_ADDR'];
        }
        $visitor_check_names = array('captcha_time_out', 
            'suggest_day_exceeded');
        foreach($visitor_check_names as $name) {
            $visitor = $this->visitorModel->getVisitor($_SERVER['REMOTE_ADDR'],
                $name);
            if(isset($visitor['END_TIME']) && $visitor['END_TIME'] > time()) {
                $_SESSION['value'] = date('Y-m-d H:i:s', $visitor['END_TIME']);
                $url = BASE_URL."?c=static&p=".$visitor['PAGE_NAME'];
                header("Location:".$url);
                exit();
            }
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
        for($i = 0; $i < self::NUM_CAPTCHA_QUESTIONS; $i++) {
            $data["check_fields"][] = "question_$i";
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
            $this->visitorModel->updateVisitor(
                $_SERVER['REMOTE_ADDR'], "captcha_time_out");
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
        switch(REGISTRATION_TYPE)
        {
            case 'no_activation':
                $data['REFRESH'] = "signin";
                $this->userModel->addUser($data['USER'], $data['PASSWORD'],
                    $data['FIRST'], $data['LAST'], $data['EMAIL']);
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_account_created')."</h1>')";
            break;
            case 'email_registration':
                $data['REFRESH'] = "signin";
                $this->userModel->addUser($data['USER'], $data['PASSWORD'],
                    $data['FIRST'], $data['LAST'], $data['EMAIL'],
                    INACTIVE_STATUS);
                $user = $this->userModel->getUser($data['USER']);
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
                $num_questions = self::NUM_CAPTCHA_QUESTIONS +
                    self::NUM_RECOVERY_QUESTIONS;
                $start = self::NUM_CAPTCHA_QUESTIONS;
                for($i = $start; $i < $num_questions; $i++) {
                    $j = $i - $start;
                    $_SESSION["RECOVERY_ANSWERS"][$j] =
                        $this->clean($_REQUEST["question_$i"],"string");
                }
            break;
            case 'admin_activation':
                $data['REFRESH'] = "signin";
                $this->userModel->addUser($data['USER'], $data['PASSWORD'],
                    $data['FIRST'], $data['LAST'], $data['EMAIL'],
                    INACTIVE_STATUS);
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
        $user = $this->userModel->getUser($data['USER']);
        if(isset($user['USER_ID'])) {
            $this->userModel->setUserSession($user['USER_ID'], $_SESSION);
        }
        unset($_SESSION['CAPTCHA_ANSWERS']);
        unset($_SESSION['CAPTCHAS']);
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
            $user = $this->userModel->getUserByEmailTime($verify["email"],
                $verify["time"]);
            if(isset($user['STATUS']) && $user['STATUS'] == ACTIVE_STATUS) {
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_already_activated')."</h1>');";
            } else {
                $hash = crawlCrypt($user["HASH"], $verify["hash"]);
                if(isset($user["HASH"]) && $hash == $verify["hash"]) {
                    $this->userModel->updateUserStatus($user["USER_ID"],
                        ACTIVE_STATUS);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('register_controller_account_activated')."</h1>');";
                } else {
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('register_controller_email_verification_error').
                        "</h1>');";
                    $this->visitorModel->updateVisitor(
                        $_SERVER['REMOTE_ADDR'], "captcha_time_out");
                }
            }
        }
        unset($_SESSION['CAPTCHA_ANSWERS']);
        unset($_SESSION['CAPTCHAS']);
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
        $data["REFRESH"] = "signin";
        $user = $this->userModel->getUser($data['USER']);
        if(!$user) {
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                tl('register_controller_account_recover_fail')."</h1>');";
            $this->visitorModel->updateVisitor(
                $_SERVER['REMOTE_ADDR'], "captcha_time_out");
            return $data;
        }
        $session = $this->userModel->getUserSession($user["USER_ID"]);
        if(!isset($session['RECOVERY']) || 
            !isset($session['RECOVERY_ANSWERS'])) {
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                tl('register_controller_account_recover_fail')."</h1>');";
            return $data;
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
        unset($_SESSION['CAPTCHAS']);
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
        $user = $this->userModel->getUser($data["user"]);
        if(!$user) {
            $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                tl('register_controller_account_recover_fail')."</h1>');";
            return $data;
        }
        $user_session = $this->userModel->getUserSession($user["USER_ID"]);
        if(isset($data['finish_hash'])) {
            $finish_hash = urlencode(crawlCrypt($user['HASH'].$data["time"].
                $user['CREATION_TIME'] . AUTH_KEY,
                urldecode($data['finish_hash'])));
            if($finish_hash != $data['finish_hash'] ||
                !$this->checkRecoveryQuestions($user)) {
                $this->visitorModel->updateVisitor(
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
                    $this->userModel->updateUser($user);
                    $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                        tl('register_controller_password_changed')."</h1>');";
                    $user_session['LAST_RECOVERY_TIME'] = time();
                    $this->userModel->setUserSession($user["USER_ID"],
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
                $this->visitorModel->updateVisitor(
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
        $num_captchas = self::NUM_CAPTCHA_QUESTIONS;
        for($i = 0; $i < $num_captchas; $i++) {
            $data["question_$i"] = "-1";
        }
        $data['url'] = "";
        if(isset($_REQUEST['url'])) {
            $data['url'] = $this->clean($_REQUEST['url'], "string");
        }
        if(!isset($_SESSION['CAPTCHAS'])||!isset($_SESSION['CAPTCHA_ANSWERS'])){
            list($captchas, $answers) = $this->selectQuestionsAnswers(
                $this->captchas_qa, $num_captchas, self::NUM_CAPTCHA_CHOICES);
            $data['CAPTCHAS'] = $captchas;
            $_SESSION['CAPTCHA_ANSWERS'] = $answers;
            $_SESSION['CAPTCHAS'] = $data['CAPTCHAS'];
        } else {
            $data['CAPTCHAS'] = $_SESSION['CAPTCHAS'];
        }
        $missing = array();
        $save = isset($_REQUEST['arg']) && $_REQUEST['arg'];
        for($i = 0; $i < $num_captchas; $i++) {
            $field = "question_$i";
            $captchas = isset($_SESSION['CAPTCHAS'][$i]) ?
                $_SESSION['CAPTCHAS'][$i] : array();
            if($save) {
                if(!isset($_REQUEST[$field]) || $_REQUEST[$field] == "-1" ||
                    !in_array($_REQUEST[$field], $captchas)) {
                    $missing[] = $field;
                } else {
                    $data[$field] = $_REQUEST[$field];
                }
            }
        }
        $data['MISSING'] = $missing;
        if($save && isset($_REQUEST['url'])) {
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
            if(!$this->checkCaptchaAnswers()) {
                $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_failed_human')."</h1>');";
                for($i = 0; $i < $num_captchas; $i++) {
                    $data["question_$i"] = "-1";
                }
                unset($_SESSION['CAPTCHAS']);
                unset($_SESSION['CAPTCHA_ANSWERS']);
                $this->visitorModel->updateVisitor(
                    $_SERVER['REMOTE_ADDR'], "captcha_time_out");
                return $data;
            }
            if(!$this->crawlModel->appendSuggestSites($url)) {
                $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_suggest_full')."</h1>');";
                return $data;
            }
            $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                tl('register_controller_url_submitted')."</h1>');";
            $this->visitorModel->updateVisitor(
                $_SERVER['REMOTE_ADDR'], "suggest_day_exceeded", 
                self::ONE_DAY, self::ONE_DAY, MAX_SUGGEST_URLS_ONE_DAY);
            for($i = 0; $i < $num_captchas; $i++) {
                $data["question_$i"] = "-1";
            }
            unset($_SESSION['CAPTCHAS']);
            unset($_SESSION['CAPTCHA_ANSWERS']);
            list($captchas, $answers) = $this->selectQuestionsAnswers(
                $this->captchas_qa, $num_captchas, self::NUM_CAPTCHA_CHOICES);
            $data['CAPTCHAS'] = $captchas;
            $_SESSION['CAPTCHA_ANSWERS'] = $answers;
            $_SESSION['CAPTCHAS'] = $data['CAPTCHAS'];
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
        if(!isset($_SESSION['CAPTCHAS'])||!isset($_SESSION['CAPTCHA_ANSWERS'])){
            list($captchas, $answers) = $this->selectQuestionsAnswers(
                $this->captchas_qa, self::NUM_CAPTCHA_QUESTIONS,
                self::NUM_CAPTCHA_CHOICES);
            $data['CAPTCHAS'] = $captchas;
            $_SESSION['CAPTCHA_ANSWERS'] = $answers;
            $_SESSION['CAPTCHAS'] = $data['CAPTCHAS'];
        } else {
            $data['CAPTCHAS'] = $_SESSION['CAPTCHAS'];
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
     *  Picks $num_select most/least questions from an array of triplets of
     *  the form a string question: Which is the most ..?, a string
     *  question: Which is the least ..?, followed by a comma separated list 
     *  of choices ranked from least to most. For each question pick, 
     *  $num_choices many items from the last element of the triplet are 
     *  chosen.
     *
     *  @param array $questions_answers an array t_1, t_2, t_3, t_4, where
     *      each t_i is a triplet as described above
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
        $size_qa = count($question_answers);
        for($i = 0; $i < $num_select; $i++) {
            do {
                $question_choice = mt_rand(0, $size_qa - 1);
            }while(isset($questions[$question_choice]));
            $more_less = rand(0, 1);
            $answer_possibilities =
                explode(",", $question_answers[$question_choice][2]);
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
            $questions[$question_choice] = array("-1" =>
                    $question_answers[$question_choice][$more_less]);
            $tmp = array_values($selected_possibilities);
            $questions[$question_choice] +=  array_combine($tmp, $tmp);
            if($more_less) {
                ksort($selected_possibilities);
                $selected_possibilities = array_values($selected_possibilities);
                $answers[$i] = $selected_possibilities[0];
            } else {
                krsort($selected_possibilities);
                $selected_possibilities = array_values($selected_possibilities);
                $answers[$i] = $selected_possibilities[0];
            }
        }
        $questions = array_values($questions);
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
        if($activity == $activity_success) {
            $this->dataIntegrityCheck($data);
            if(!$data["SUCCESS"]) {
                $activity = $activity_fail;
            }
            if($activity == $activity_success) {
                if(!isset($_SESSION['CAPTCHA_ANSWERS']) ) {
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('register_controller_need_cookies')."</h1>');";
                    $activity = 'createAccount';
                    $this->visitorModel->updateVisitor(
                        $_SERVER['REMOTE_ADDR'], "captcha_time_out");
                } else if(!$this->checkCaptchaAnswers()) {
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('register_controller_failed_human')."</h1>');";
                    for($i = 0; $i < self::NUM_CAPTCHA_QUESTIONS; $i++) {
                        $data["question_$i"] = "-1";
                    }
                    unset($_SESSION['CAPTCHAS']);
                    unset($_SESSION['CAPTCHA_ANSWERS']);
                    $this->visitorModel->updateVisitor(
                        $_SERVER['REMOTE_ADDR'], "captcha_time_out");
                    $activity = $activity_fail;
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
            $this->userModel->getUserId($data['USER'])) {
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
     *  Checks whether the answers to the account recovery questions match those
     *  provided earlier by an account user
     *
     *  @param array $user who to check recovery answers for
     *  @return bool true if only if all were correct
     */
    function checkRecoveryQuestions($user)
    {
        $user_session = $this->userModel->getUserSession($user["USER_ID"]);
        if(!isset($user_session['RECOVERY_ANSWERS'])) {
            return false;
        }
        $recovery_passed = true;
        for($i = 0; $i < self::NUM_RECOVERY_QUESTIONS; $i++) {
            $field = "question_".$i;
            if($_REQUEST[$field] != $user_session['RECOVERY_ANSWERS'][$i]) {
                $recovery_passed = false;
                $this->visitorModel->updateVisitor(
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
        $data['MISSING'] = $missing;
    }


}

?>
