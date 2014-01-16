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
 * Timing functions
 */
require_once BASE_DIR."/lib/mail_server.php";


/**
 * Controller used to handle search requests to SeekQuarry
 * search site. Used to both get and display
 * search results.
 *
 * @author Mallika Perepa
 * @package seek_quarry
 * @subpackage controller
 */
class RegisterController extends Controller
{
    /**
     * Load the RegisterView
     * @var array
     */
    var $views = array("recover", "register", "signin");
    /**
     * LocaleModel used to get the available languages/locales, CrawlModel
     * is used to get a list of available crawls
     * @var array
     */
    var $models = array("user", "visitor");

    /**
     * @var array
     */
    var $activities = array("createAccount", "processAccountData",
        "recoverPassword", "recoverComplete",
        "processRecoverData", "emailVerification");

    /**
     * @var array
     */
    var $register_fields = array("first", "last", "user", "email", "password",
        "repassword");

    /**
     * @var array
     */
    var $captchas_qa;

    /**
     * @var array
     */
    var $recovery_qa;

    /**
     * @var int
     */
    const NUM_CAPTCHA_QUESTIONS = 5;
    /**
     * @var int
     */
    const NUM_CAPTCHA_CHOICES = 5;
    /**
     * @var int
     */
    const NUM_RECOVERY_QUESTIONS = 3;

    /**
     *
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
     *  Allows users to create accounts.
     *  Validates the input form when creating an account
     *
     */
    function processRequest()
    {
        if(isset($_SESSION['USER_ID'])) {
            $user = $_SESSION['USER_ID'];
        } else {
            $user = $_SERVER['REMOTE_ADDR'];
        }
        $visitor = $this->visitorModel->getVisitor($_SERVER['REMOTE_ADDR']);
        if(isset($visitor['END_TIME']) && $visitor['END_TIME'] > time()) {
            $_SESSION['value'] = date('Y-m-d H:i:s', $visitor['END_TIME']);
            $url = BASE_URL."?c=static&p=".$visitor['PAGE_NAME'];
            header("Location:".$url);
            exit();
        }
        $data = array();
        $data['REFRESH'] = "register";
        $activity = isset($_REQUEST['a']) ?
            $this->clean($_REQUEST['a'], 'string') : 'createAccount';
        $token_okay = $this->checkCSRFToken(CSRF_TOKEN, $user);

        if(!in_array($activity, $this->activities) || (!$token_okay
            && !in_array($activity, array("emailVerification",
            "recoverPassword", "recoverComplete")) )) {
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
            $view = "signin";
            $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                tl('register_controller_need_cookies')."</h1>');";
            $this->visitorModel->updateVisitor(
                $_SERVER['REMOTE_ADDR'], "register_time_out");
        }
        //used to ensure that we have sessions active
        $_SESSION['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'];
        $this->displayView($view, $data);
    }

    /**
     *
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
                    $_SERVER['REMOTE_ADDR'], "register_time_out");
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
                    $_SERVER['REMOTE_ADDR'], "register_time_out");
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
     *
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
                        $_SERVER['REMOTE_ADDR'], "register_time_out");
                } else if(!$this->checkCaptchaAnswers()) {
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('register_controller_failed_human')."</h1>');";
                    for($i = 0; $i < self::NUM_CAPTCHA_QUESTIONS; $i++) {
                        $data["question_$i"] = "-1";
                    }
                    unset($_SESSION['CAPTCHAS']);
                    unset($_SESSION['CAPTCHA_ANSWERS']);
                    $this->visitorModel->updateVisitor(
                        $_SERVER['REMOTE_ADDR'], "register_time_out");
                    $activity = $activity_fail;
                }
            }
        }
    }


    /**
     *
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
        return $data;
    }

    /**
     *
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
     *
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
                    $_SERVER['REMOTE_ADDR'], "register_time_out");
                break;
            }
        }
        return $recovery_passed;
    }


    /**
     *
     */
    function createAccount()
    {
        $data = $this->setupQuestionViewData();
        return $data;
    }

    /**
     *
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
     *
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
     *
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
     *
     */
    function recoverPassword()
    {
        $data = $this->setupQuestionViewData();
        $data['REFRESH'] = "recover";
        return $data;
    }

    /**
     *
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
                $_SERVER['REMOTE_ADDR'], "register_time_out");
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
     *
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
                        $_SERVER['REMOTE_ADDR'], "register_time_out");
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
            if(empty($_REQUEST[$field]) || !isset($_REQUEST[$field])) {
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
