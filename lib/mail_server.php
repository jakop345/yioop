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
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage lib
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
/**
 * To record exception messages from the mail server
 */
require_once BASE_DIR.'/lib/analytics_manager.php';
/**
 * Timing functions
 */
require_once BASE_DIR."/lib/utility.php";
/**
 * A small class for communicating with an SMTP server. Used to avoid
 * configuration issues that might be needed with PHP's built-in mail()
 * function. Here is an example of how one might use this class:
 *
 * $server = new MailServer('somewhere.com', 587, 'someone', 'pword', 'tls');
 * $to = "cool@place.com";
 * $from = "someone@somewhere.com";
 * $subject = "Test Mail";
 * $message = "This is a test";
 * $server->send($subject, $from, $to, $message);
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage lib
 */
class MailServer
{
    /**
     * Email address of default mail sender
     * @var string
     */
    var $sender_email;
    /**
     * Hostname of default mail sender
     * @var string
     */
    var $sender_host;
    /**
     * Domain name of the SMTP server
     * @var string
     */
    var $server;
    /**
     * Port number the mail server is running on
     * @var int
     */
    var $port;
    /**
     * If auth is used, the username to log into the SMTP server with
     * @var string
     */
    var $login;
    /**
     * If auth is used, the password to log into the SMTP server with
     * @var string
     */
    var $password;
    /**
     * Either false if no security/auth used or ssl or tls
     * @var mixed
     */
    var $secure;
    /**
     * End of line string for an SMTP server
     */
    const EOL = "\r\n";
    /**
     * How long before timeout when making a connection to an SMTP server
     */
    const SMTP_TIMEOUT = 10;
    /**
     * Length of an SMTP response code
     */
    const SMTP_CODE_LEN = 3;
    /**
     * Service ready for requests
     */
    const SERVER_READY = 220;
    /**
     * SMTP last action okay
     */
    const OKAY = 250;
    /**
     * authentication successful
     */
    const GO_AHEAD = 235;
    /**
     * Send next authentication item
     */
    const CONT_REQ = 334;
    /**
     * Ready for the actual mail input
     */
    const START_INPUT = 354;
    /**
     * Encapuslates the domain and credentials of a SMTP server
     * in a MailServer object
     *
     * @param string $sender_email who mail will be sent from (can be
     *     overwritten)
     * @param string $server domain name of machine will connect to
     * @param int $port port on that machine
     * @param string $login username to use for authentication ("" if no
     *     auth)
     * @param string $password password to use for authentication ("" if no
     *     auth)
     * @param mixed $secure false is SSL and TLS not used, otherwise SSL or TLS
     */
    function __construct($sender_email, $server, $port, $login, $password,
        $secure = false)
    {
        $this->sender_email = $sender_email;
        $mail_parts = explode("@", $this->sender_email);
        $this->sender_host = $mail_parts[1];
        $this->server = $server;
        if($secure == "ssl") {
            'ssl://'.$server;
        }
        $this->port = $port;
        $this->login = $login;
        $this->password = $password;
        $this->secure = $secure;
        $this->connection = NULL;
        $this->messages = "";
    }
    /**
     * Connects to and if needs be authenticates with a SMTP server
     *
     * @return bool whether the session was successfully established
     */
    function startSession()
    {
        $this->connection = fsockopen($this->server, $this->port, $errno,
            $errstr, self::SMTP_TIMEOUT);
        if(!$this->connection) {
            $this->messages .= "Could not connect to smtp server\n";
            return false;
        }
        if($this->readResponseGetCode() != self::SERVER_READY) {
            $this->messages .= "SMTP error\n";
            return false;
        }
        $hostname = $this->sender_host;
        $this->smtpCommand("HELO $hostname");
        if($this->secure == 'tls') {
            if($this->smtpCommand('STARTTLS') != self::SERVER_READY) {
                $this->messages .= "Cannot start TLS\n";
                return false;
            }
            stream_socket_enable_crypto($this->connection, true,
                STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if($this->smtpCommand("HELO $hostname") != self::OKAY) {
                $this->messages .= "TLS HELO error\n";
                return false;
            }
        }
        if($this->login != "" && $this->password != "") {
            if($this->smtpCommand('AUTH LOGIN') != self::CONT_REQ) {
                $this->messages .= "Authentication Error Auth Login\n";
                return false;
            }
            if($this->smtpCommand(base64_encode($this->login))
                != self::CONT_REQ) {
                $this->messages .= "Authentication Error Username Transition\n";
                return false;
            }
            if($this->smtpCommand(base64_encode($this->password)) !=
                self::GO_AHEAD) {
                $this->messages .= "Authentication Error Password Transition\n";
                return false;
            }
        }
        return true;
    }
    /**
     * Closes the currently active SMTP session
     */
    function endSession()
    {
        $this->smtpCommand('QUIT');
        fclose($this->connection);
    }
    /**
     * Reads data from an SMTP server until a command response code detected
     *
     * @return string three byte response code
     */
    function readResponseGetCode()
    {
        $data = "";
        while($line = fgets($this->connection)) {
            $data .= $line;
            if($line[self::SMTP_CODE_LEN] == ' ') { break; }
        }
        $this->messages .= $data;
        return substr($data, 0, self::SMTP_CODE_LEN);
    }
    /**
     * Sends a single SMTP command to the current SMTP server and
     * then returns the SMTP response code
     *
     * @param string $command the command to execute
     * @return string three character integer response code
     */
    function smtpCommand($command)
    {
        $this->messages .= htmlentities($command)."\n";
        fputs($this->connection, $command . self::EOL);
        return $this->readResponseGetCode();
    }
    /**
     * Sends an email (much like PHP's mail command, but not requiring
     * a configured smtp server on the current machine)
     *
     * @param string $subject subject line of the email
     * @param string $from sender email address
     * @param string $to recipient email address
     * @param string $message message body for the email
     */
    function send($subject, $from, $to, $message)
    {
        $start_time = microtime();
        if($from == "") {
            $from = $this->sender_email;
        }
        if(USE_MAIL_PHP) {
            $header = "From: " . $from . $eol;
            mail($to, $subject, $message, $header);
            return;
        }
        $this->messages = "";
        $eol = self::EOL;
        $mail  = "Date: " . date(DATE_RFC822) . $eol;
        $mail .= "Subject: " . $subject . $eol;
        $mail .= "From: " . $from . $eol;
        $mail .= "To: ". $to . $eol;
        $mail .= $eol . $eol . $message. $eol . ".";
        $commands = array(
            "MAIL FROM: <$from>" => self::OKAY,
            "RCPT TO: <$to>" => self::OKAY,
            "DATA" => self::START_INPUT,
            $mail => self::OKAY
        );
        if($this->startSession()) {
            foreach($commands as $command => $good_response) {
                $response = $this->smtpCommand($command);
                if($response != $good_response) {
                    $this->messages .=
                        "$command failed!! $response $good_response\n";
                    break;
                }
            }
            $this->endSession();
        }
        if(QUERY_STATISTICS) {
            $current_messages = AnalyticsManager::get("MAIL_MESSAGES");
            if(!$current_messages) {
                $current_messages = array();
            }
            $total_time = AnalyticsManager::get("MAIL_TOTAL_TIME");
            if(!$total_time) {
                $total_time = 0;
            }
            $elapsed_time = changeInMicrotime($start_time);
            $total_time += $elapsed_time;
            $current_messages[] = array(
                "QUERY" => "<p>Send Mail</p>".
                    "<pre>" . wordwrap($this->messages, 60, "\n", true) .
                    "</pre>",
                "ELAPSED_TIME" => $elapsed_time
            );
            AnalyticsManager::set("MAIL_MESSAGES", $current_messages);
            AnalyticsManager::set("MAIL_TOTAL_TIME", $total_time);
        }
    }
}
?>
