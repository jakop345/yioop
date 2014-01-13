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
    var $views = array("register", "signin");
    /**
     * LocaleModel used to get the available languages/locales, CrawlModel
     * is used to get a list of available crawls
     * @var array
     */
    var $models = array("user");

    /**
     * @var array
     */
    var $activities = array("createAccount", "processAccountData",
        "resetPassword", "emailVerification");

    /**
     * @var array
     */
    var $register_fields = array("first", "last", "user",
            "email", "password", "repassword");

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
        $activity = isset($_REQUEST['a']) ?
            $this->clean($_REQUEST['a'], 'string') : 'createAccount';
        $token_okay = $this->checkCSRFToken(CSRF_TOKEN, $user);

        if(!in_array($activity, $this->activities) || (!$token_okay
            && $activity != "emailVerification")) {
            $activity = 'createAccount';
        }

        $data = array();
        if($activity == 'processAccountData') {
            $data = $this->dataIntegrityCheck();
            if(isset($data["REFRESH"]) && $data["REFRESH"] == 'register') {
                $activity = 'createAccount';
            }
            if($activity == 'processAccountData') {
                if(!isset($_SESSION['CAPTCHA_ANSWERS']) ) {
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('register_controller_need_cookies')."</h1>')";
                    $activity = 'createAccount';
                } else if(!$this->checkCaptchaAnswers()) {
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('register_controller_failed_human')."</h1>')";
                    for($i = 0; $i < self::NUM_CAPTCHA_QUESTIONS; $i++) {
                        $data["question_$i"] = "-1";
                    }
                    unset($_SESSION['CAPTCHAS']);
                    $activity = 'createAccount';
                }
            }
        }
        $new_data = $this->call($activity);
        $data = array_merge($new_data,$data);
        if(isset($new_data['SCRIPT']) && $new_data['SCRIPT'] != "") {
            $data['SCRIPT'] .= ";".$new_data['SCRIPT'];
        }
        $data[CSRF_TOKEN] = $this->generateCSRFToken($user);
        $view = (isset($data['REFRESH'])) ? $data['REFRESH'] : 'register';
        $this->displayView($view, $data);
    }


    /**
     *
     */
    function dataIntegrityCheck()
    {
        $data['SCRIPT'] = "";
        $error = $this->getCleanFields($data);
        if($error) {
            $data['RESULT'] = "true";
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
            tl('register_controller_error_fields')."</h1>')";
            $data['REFRESH'] = "register";
        } else if($this->userModel->getUserId($data['USER'])) {
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
            tl('register_controller_user_already_exists')."</h1>')";
            $data['REFRESH'] = "register";
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
            }
        }
        return $captcha_passed;
    }

    /**
     *
     */
    function createAccount()
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
        $data = $this->dataIntegrityCheck();
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
                    tl('register_controller_registration_email_sent')."</h1>')";
                $subject = tl('register_controller_admin_activation_request');
                $message = tl('register_controller_admin_email_salutation',
                    $data['FIRST'], $data['LAST'])."\n";
                $message .= tl('register_controller_admin_email_test')."\n";
                $creation_time = vsprintf('%d.%06d', gettimeofday());
                $message .= NAME_SERVER.
                    "?c=register&a=emailVerification&email=".
                    $user['EMAIL']."&time=".$user['CREATION_TIME'].
                    "&hash=".$user['HASH'];
                $server->send($subject, MAIL_SENDER, $data['EMAIL'], $message);
            break;
            case 'admin_activation':
                $data['REFRESH'] = "signin";
                $this->userModel->addUser($data['USER'], $data['PASSWORD'],
                    $data['FIRST'], $data['LAST'], $data['EMAIL'],
                    INACTIVE_STATUS);
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_account_request_made')."</h1>')";
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
        $this->userModel->setUserSession($user['USER_ID'], $_SESSION);
        return $data;
    }

    /**
     *
     */
    function resetPassword()
    {

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
                tl('register_controller_email_verification_error')."</h1>')";
        } else {
            $user = $this->userModel->getUserByEmailTime($verify["email"],
                $verify["time"]);
            if(isset($user["HASH"]) && $user["HASH"] == $verify["hash"]) {
                $this->userModel->updateUserStatus($user["USER_ID"],
                    ACTIVE_STATUS);
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_account_activated')."</h1>')";
            } else {
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_email_verification_error').
                    "</h1>')";
            }
        }
        return $data;
    }

    function getCleanFields(&$data)
    {
        $fields = $this->register_fields;
        $missing = array();
        $regex_email=
            '/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+'.
            '(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/';
        $error = false;
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
            $captchas = isset($_SESSION['CAPTCHAS'][$i]) ?
                $_SESSION['CAPTCHAS'][$i] : array();
            $recovery = isset($_SESSION['RECOVERY'][$i  - $num_captchas]) ?
                $_SESSION['RECOVERY'][$i  - $num_captchas] : array();
            $current_dropdown = ($i< $num_captchas) ?
                $captchas : $recovery;
            if(!isset($_REQUEST[$field]) || $_REQUEST[$field] == "-1" ||
                !in_array($_REQUEST[$field], $current_dropdown)) {
                $error = true;
                $missing[] = $field;
            } else {
                $data[$field] = $_REQUEST[$field];
            }
        }
        $data['MISSING'] = $missing;
        return $error;
    }
}

?>
