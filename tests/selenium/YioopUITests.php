<?php

/*
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
 *  @author Eswara Rajesh Pinapala epinapala@live.com
 *  @package seek_quarry
 *  @subpackage element
 *  @license http://www.gnu.org/licenses/ GPL3
 *  @link http://www.seekquarry.com/
 *  @copyright 2009 - 2014
 *  @filesource
 */

/**
 * Description of WebTest
 *
 * @author epinapala
 */
class WebTest extends PHPUnit_Extensions_Selenium2TestCase {

    private $home_url = "http://localhost/yioop/";

    protected function setUp() {
        $this->setBrowser('phantomjs');
        $this->setBrowserUrl('http://localhost/yioop/');
    }

    /**
     * Asserts correct Page title.
     */
    public function testTitle() {
        $this->url($this->home_url);
        $this->assertEquals(
                'PHP Search Engine - Yioop!', 
                $this->title());
    }

    /**
     * Tests navigation of the user from homepage
     * using nav bar.
     */
    public function testUserNav() {

        $this->url($this->home_url);
        $sign_in = $this
                ->byCssSelector('div.user-nav > ul > li:nth-of-type(2)');
        $this->assertEquals('Sign In', $sign_in->text());
        $settings = $this
                ->byCssSelector('div.user-nav > ul > li');
        $this->assertEquals('Settings', $settings->text());
    }

    /**
     * Login page and username/password
     * submission. 
     * Also asserts for an error message when username/password is 
     * invalid. 
     * Asserts proper nav bar elements on successful login.
     */
    public function testLogin() {
        //Go to home page
        $this->url($this->home_url);
        //Search for 2nd element in nav bar, which is signin
        $sign_in = $this
                ->byCssSelector('div.user-nav > ul > li:nth-of-type(2)');
        $sign_in_url = $this
                ->byLinkText($sign_in->text());
        $sign_in_url->click();
        //try with onvalid username/password.
        $this->byId('username')->value('root');
        $this->byId('password')->value('damn');
        $this->byXPath("//button[@type='submit']")->click();
        //If the username/ password is invalid, assert that message div will
        //show an error message.
        $message = $this->byCssSelector('div#message');
        $this->assertRegExp('/Username or Password Incorrect!/', 
                $message->text());

        //enter username = root
        $this->byId('username')->value('root');
        //enter password = ''
        $this->byId('password')->value('');
        //click on Login button
        $this->byXPath("//button[@type='submit']")->click();

        $message_after_login = $this->byCssSelector('div#message');
        $this->assertRegExp('//', $message_after_login->text());

        //Now If the user is Logged in, The nav bar should display Settings, 
        //Admin, Sign Out
        $this->assertEquals('Settings', 
                $this->byCssSelector('div.user-nav > ul > li:nth-of-type(1)')
                ->text());
        $this->assertEquals('Admin', 
                $this->byCssSelector('div.user-nav > ul > li:nth-of-type(2)')
                ->text());
        $this->assertEquals('Sign Out', 
                $this->byCssSelector('div.user-nav > ul > li:nth-of-type(3)')
                ->text());
    }

    /**
     * This test validates the Help frame visibility Mechanism.
     * First login will be performed using valid credentials,
     * Once logged in, This will check the help frame behaviour.
     * @param type none 
     */
    public function testHelpFrame() {
        //Get login url
        $this->url($this->home_url . "?c=admin");
        
        //enter username = root
        $this->byId('username')->value('root');
        //enter password = ''
        $this->byId('password')->value('');
        //click on Login button
        $this->byXPath("//button[@type='submit']")->click();
        
        //User should now be logged in, if not, the tests that 
        //follow will be failed.
        //Check if the help div element exists and the text is eq to 'Help'
        $this->assertEquals('Help', $this->byId('help')->text());

        //get the help link
        $help_a = $this->byId('help')->byCssSelector('a:nth-of-type(1)');
        //should be hidden by default
        $this->assertEquals('none', $this->byId('help-frame')->css('display'));
        //click once 
        $help_a->click();
        //helpframe should now be displayed
        $this->assertEquals('block',$this->byId('help-frame')->css('display'));
        
        //while being displayed, click help link again
        $help_a->click();
        //helpframe should now be hidden again
        $this->assertEquals('none',$this->byId('help-frame')->css('display'));        
    }

    /**
     * Asserts that an element contains exactly a given string.
     *
     * @param  string $locator
     * @param  string $text
     * @param  string $message
     */
    public function assertElementTextEquals($locator, $text, $message = '') {
        $this->assertEquals($text, $this->getText($locator), $message);
    }

}
