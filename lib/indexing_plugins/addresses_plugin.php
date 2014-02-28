<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2013 - 2014 Chris Pollett chris@pollett.org
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
 * @subpackage indexing_plugin
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** Loads processor used for */
require_once BASE_DIR."/lib/processors/text_processor.php";
/** Base indexing plugin class*/
require_once BASE_DIR."/lib/indexing_plugins/indexing_plugin.php";
/** Get the crawlHash function */
require_once BASE_DIR."/lib/utility.php";
/** Loads common constants for web crawling */
require_once BASE_DIR."/lib/crawl_constants.php";

/**
 *
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage indexing_plugin
 */
class AddressesPlugin extends IndexingPlugin implements CrawlConstants
{
    /**
     * This method is called by a PageProcessor in its handle() method
     * just after it has processed a web page. This method allows
     * an indexing plugin to do additional processing on the page
     * such as adding sub-documents, before the page summary is
     * handed back to the fetcher.
     *
     *  @param string $page web-page contents
     *  @param string $url the url where the page contents came from,
     *     used to canonicalize relative links
     *
     *  @return array consisting of a sequence of subdoc arrays found
     *      on the given page.
     */
    function pageProcessing($page, $url)
    {
        $page = preg_replace("/\<br\s*(\/)?\s*\>/", "\n", $page);
        $page = preg_replace("/\<\/(h1|h2|h3|h4|h5|h6|table|tr|td|div|".
            "p|address|section)\s*\>/", "\n", $page);
        $page = preg_replace("/\&\#\d{3}\;|\&\w+\;/", " ", $page);
        $page = preg_replace("/((\r|\t| )*\n){2}/", "\n", $page);
        $page = strip_tags($page);
        $addresses = $this->parseAddresses($page);
        print_r($addresses);
    }


    /**
     * Which mime type page processors this plugin should do additional
     * processing for
     *
     * @return array an array of page processors
     */
    static function getProcessors()
    {
        return array("TextProcessor"); //will apply to all subclasses
    }

    /**
     *
     */
    function parseAddresses($text)
    {
        $lines = explode("\n", $text);
        $lines[] = "";
        $state = "dont";
        $addresses = array();
        $current_candidate = array();
        $num_lines = 0;
        $max_len = 45;
        $min_len = 2;
        $max_lines = 8;
        $min_lines = 2;
        foreach($lines as $line) {
            $line = trim($line);
            print $line."\n";
            $len = strlen($line);
            $len_about_right = $len < $max_len && $len >= $min_len;
            switch($state)
            {
                case "dont":
                    if($len_about_right) {
                        $state = "maybe";
                        $current_candidate[] = $line;
                        $num_lines = 1;
                    }
                break;
                case "maybe":
                    if($len_about_right){
                        if($num_lines < $max_lines) {
                            $current_candidate[] = $line;
                            $num_lines++;
                        } else { //too many short lines, probably not address
                            $current_candidate = array();
                            $num_lines = 0;
                            $state = "advance";
                        }
                    } else {
                        $state = "dont";
                        if($num_lines <= $max_lines &&$num_lines >= $min_lines){
                            $current_candidate = $this->checkAndTagCandidate(
                                $current_candidate);
                            if($current_candidate) {
                                $addresses[] = $current_candidate;
                            }
                        }
                        $current_candidate = array();
                        $num_lines = 0;
                    }
                break;
                case "advance":
                    if($len >= $max_len) {
                        $state = "dont";
                    }
                break;
            }
        }
        return $addresses;
    }

    /**
     *
     */
    function checkAndTagCandidate($pre_address)
    {
        $out_address = array();
        $last_line = count($pre_address) - 1;
        list($fields, $optional_fields, $repeat_fields, $skip_if_no_previous) =
            $this->getFieldsCountry();
        $last_field = count($fields) - 1;
        $active_field = 0;
        $num_fields = count($fields);
        $real_last_line = $last_line;
        for($i = $last_line; $i >= 0; $i--) {
            if($i == 0 && $real_last_line <= 1 &&
                !isset($out_address["ESTABLISHMENT"])) {
                $active_field = $last_field;
            }
            $pre_address_line = mb_strtoupper(trim($pre_address[$i],
                " \t\n\r\0\x0B,"));
            while($active_field < $num_fields) {
                $parser_field = $fields[$active_field];
                $address_line = $this->$parser_field($pre_address_line, $i);
                if(in_array($parser_field, $optional_fields)) {
                    $real_last_line--;
                }
                if($address_line) {
                    if(isset($address_line["COUNTRY_CODE"])) {
                        list($fields, $optional_fields, $repeat_fields,
                            $skip_if_no_previous) =
                                $this->getFieldsCountry(
                                    $address_line["COUNTRY_CODE"]);
                        $last_field = count($fields) - 1;
                    }
                    $out_address = array_merge($address_line, $out_address);
                    if(!in_array($parser_field, $repeat_fields) ||
                        ($parser_field == "parseCityLine" &&
                        isset($out_address['CITY']))) {
                        $active_field++;
                    }
                    break;
                } else {
                    $repeating = false;
                    $active_field++;
                    if(isset($fields[$active_field]) &&
                        in_array($fields[$active_field], $skip_if_no_previous)) {
                        $active_field++; // only parse office if had Department
                    }
                }
            }
            if($active_field >= $num_fields && $i > 0) {
                return false;
            }
        }
        if(!isset($out_address["NAME"]) &&
            !isset($out_address["ESTABLISHMENT"])) {
            return false;
        }
        return $out_address;
    }

    /**
     *
     */
    function getFieldsCountry($country = "US")
    {
        switch($country)
        {
            case "GB":
                $fields = array("parseIgnorable",
                    "parseOfficeOpenHours", "parsePhoneOrEmail",
                    "parseCountry", "parsePostalCode",
                    "parseLocality", "parseStreetLine",
                    "parseInstitution", "parseDepartmentOrCareof",
                    "parseOfficeResidenceNumber", "parseName");
                $optional_fields = array("parseOfficeOpenHours",
                    "parsePhoneOrEmail");
                $repeat_fields = array("parsePhoneOrEmail","parseLocality",
                    "parseStreetLine", "parseOfficeResidenceNumber");
                $skip_if_no_previous = array( "parseOfficeNumber");
            break;
            case "US":
            default:
                $fields = array("parseIgnorable",
                    "parseOfficeOpenHours", "parsePhoneOrEmail",
                    "parseCountry", "parseCityLine",
                    "parseStreetLine", "parseInstitution","parseDepartmentOrCareof",
                    "parseOfficeResidenceNumber", "parseName");
                $optional_fields = array("parseOfficeOpenHours",
                    "parsePhoneOrEmail");
                $repeat_fields = array("parsePhoneOrEmail","parseCityLine",
                    "parseStreetLine", "parseOfficeResidenceNumber");
                $skip_if_no_previous = array( "parseOfficeNumber");
            break;
        }
        return array($fields, $optional_fields, $repeat_fields,
            $skip_if_no_previous);
    }

    /**
     *
     */
    function parseIgnorable($line)
    {
        $out = false;
        if(preg_match("/STOCK/i", $line)) {
            $out = array("EXTRA" => $line);
        }
        return $out;
    }

    /**
     *
     */
    function parseOfficeOpenHours($line)
    {
        $out = false;
        if(preg_match("/OH|Office\s+Hours|Hours|Open/i", $line)) {
            $out = array("HOURS" => $line);
        }
        return $out;
    }

    /**
     *
     */
    function parsePhoneOrEmail($line, $i)
    {
        $email_regex = '/[_a-zA-Z0-9-]+(\.[_a-zA-Z0-9-]+)*@[a-zA-Z0-9-]+'.
                '(\.[a-zA-Z0-9-]+)*(\.[a-zA-Z]{2,3})/';
        preg_match_all($email_regex, $line, $emails);
        if(isset($emails[0]) && count($emails[0]) > 0) {
            $out = array("EMAIL"=>$emails[0]);
            return $out;
        }
        $out_line = preg_replace('/[^\da-zA-Z]/',"", $line);
        $parts = preg_split('/[a-zA-Z]/', $out_line);
        $out = false;
        $j = 0;
        foreach($parts as $part) {
            if(strlen($part) >= 10) {
                $out = array("PHONE$i" => $part);
                break;
            }
            $j++;
        }
        return $out;
    }

    /**
     *
     */
    function parseCountry($line)
    {
        $countries = array("ANDORRA" => "AD","UNITED ARAB EMIRATES" => "AE",
            "AFGHANISTAN" => "AF","ANTIGUA AND BARBUDA" => "AG",
            "ANGUILLA" => "AI","ALBANIA" => "AL","ARMENIA" => "AM","ANGOLA" => "AO",
            "ANTARCTICA" => "AQ","ARGENTINA" => "AR","AMERICAN SAMOA" => "AS",
            "AUSTRIA" => "AT","AUSTRALIA" => "AU","ARUBA" => "AW",
            "ÅLAND ISLANDS" => "AX","AZERBAIJAN" => "AZ",
            "BOSNIA AND HERZEGOVINA" => "BA","BARBADOS" => "BB",
            "BANGLADESH" => "BD","BELGIUM" => "BE","BURKINA FASO" => "BF",
            "BULGARIA" => "BG","BAHRAIN" => "BH","BURUNDI" => "BI","BENIN" => "BJ",
            "SAINT BARTHELEMY" => "BL","BERMUDA" => "BM",
            "BRUNEI DARUSSALAM" => "BN", "BOLIVIA" => "BO",
            "BONAIRE, SINT EUSTATIUS AND SABA" => "BQ", "BRAZIL" => "BR",
            "BAHAMAS" => "BS","BHUTAN" => "BT",
            "BOUVET ISLAND" => "BV","BOTSWANA" => "BW","BELARUS" => "BY",
            "BELIZE" => "BZ","CANADA" => "CA","COCOS ISLANDS" => "CC",
            "DEMOCRATIC REPUBLIC OF THE CONGO" => "CD",
            "CENTRAL AFRICAN REPUBLIC" => "CF","CONGO" => "CG",
            "SWITZERLAND" => "CH", "COTE D'IVOIRE" => "CI",
            "COOK ISLANDS" => "CK","CHILE" => "CL",
            "CAMEROON" => "CM","CHINA" => "CN","COLOMBIA" => "CO",
            "COSTA RICA" => "CR", "CUBA" => "CU","CAPE VERDE" => "CV",
            "CURACAO" => "CW", "CHRISTMAS ISLAND" => "CX","CYPRUS" => "CY",
            "CZECH REPUBLIC" => "CZ", "GERMANY" => "DE","DJIBOUTI" => "DJ",
            "DENMARK" => "DK","DOMINICA" => "DM",
            "DOMINICAN REPUBLIC" => "DO","ALGERIA" => "DZ","ECUADOR" => "EC",
            "ESTONIA" => "EE","EGYPT" => "EG","WESTERN SAHARA" => "EH",
            "ERITREA" => "ER","SPAIN" => "ES","ETHIOPIA" => "ET","FINLAND" => "FI",
            "FIJI" => "FJ","FALKLAND ISLANDS (MALVINAS)" => "FK",
            "MICRONESIA, FEDERATED STATES OF" => "FM","FAROE ISLANDS" => "FO",
            "FRANCE" => "FR","GABON" => "GA","UNITED KINGDOM" => "GB",
            "GRENADA" => "GD","GEORGIA" => "GE","FRENCH GUIANA" => "GF",
            "GUERNSEY" => "GG","GHANA" => "GH","GIBRALTAR" => "GI",
            "GREENLAND" => "GL", "GAMBIA" => "GM","GUINEA" => "GN",
            "GUADELOUPE" => "GP", "EQUATORIAL GUINEA" => "GQ","GREECE" => "GR",
            "SOUTH GEORGIA AND THE SOUTH SANDWICH ISLANDS" => "GS",
            "GUATEMALA" => "GT", "GUAM" => "GU","GUINEA-BISSAU" => "GW",
            "GUYANA" => "GY", "HONG KONG" => "HK",
            "HEARD ISLAND AND MCDONALD ISLANDS" => "HM",
            "HONDURAS" => "HN","CROATIA" => "HR","HAITI" => "HT","HUNGARY" => "HU",
            "INDONESIA" => "ID","IRELAND" => "IE","ISRAEL" => "IL",
            "ISLE OF MAN" => "IM","INDIA" => "IN",
            "BRITISH INDIAN OCEAN TERRITORY" => "IO","IRAQ" => "IQ",
            "IRAN" => "IR","ICELAND" => "IS","ITALY" => "IT","JERSEY" => "JE",
            "JAMAICA" => "JM","JORDAN" => "JO","JAPAN" => "JP","KENYA" => "KE",
            "KYRGYZSTAN" => "KG","CAMBODIA" => "KH","KIRIBATI" => "KI",
            "COMOROS" => "KM","SAINT KITTS AND NEVIS" => "KN",
            "NORTH KOREA" => "KP","SOUTH KOREA" => "KR","KUWAIT" => "KW",
            "CAYMAN ISLANDS" => "KY","KAZAKHSTAN" => "KZ",
            "LAOS" => "LA","LEBANON" => "LB","SAINT LUCIA" => "LC",
            "LIECHTENSTEIN" => "LI","SRI LANKA" => "LK","LIBERIA" => "LR",
            "LESOTHO" => "LS","LITHUANIA" => "LT","LUXEMBOURG" => "LU",
            "LATVIA" => "LV","LIBYA" => "LY","MOROCCO" => "MA","MONACO" => "MC",
            "MOLDOVA, REPUBLIC OF" => "MD","MONTENEGRO" => "ME",
            "SAINT MARTIN" => "MF","MADAGASCAR" => "MG","MARSHALL ISLANDS" => "MH",
            "MACEDONIA, THE FORMER YUGOSLAV REPUBLIC OF" => "MK","MALI" => "ML",
            "MYANMAR" => "MM","MONGOLIA" => "MN","MACAO" => "MO",
            "NORTHERN MARIANA ISLANDS" => "MP","MARTINIQUE" => "MQ",
            "MAURITANIA" => "MR","MONTSERRAT" => "MS","MALTA" => "MT",
            "MAURITIUS" => "MU","MALDIVES" => "MV","MALAWI" => "MW",
            "MEXICO" => "MX","MALAYSIA" => "MY","MOZAMBIQUE" => "MZ",
            "NAMIBIA" => "NA","NEW CALEDONIA" => "NC","NIGER" => "NE",
            "NORFOLK ISLAND" => "NF","NIGERIA" => "NG","NICARAGUA" => "NI",
            "NETHERLANDS" => "NL","NORWAY" => "NO","NEPAL" => "NP","NAURU" => "NR",
            "NIUE" => "NU","NEW ZEALAND" => "NZ","OMAN" => "OM","PANAMA" => "PA",
            "PERU" => "PE","FRENCH POLYNESIA" => "PF","PAPUA NEW GUINEA" => "PG",
            "PHILIPPINES" => "PH","PAKISTAN" => "PK","POLAND" => "PL",
            "SAINT PIERRE AND MIQUELON" => "PM","PITCAIRN" => "PN",
            "PUERTO RICO" => "PR","PALESTINE, STATE OF" => "PS",
            "PORTUGAL" => "PT","PALAU" => "PW","PARAGUAY" => "PY",
            "QATAR" => "QA","REUNION" => "RE","ROMANIA" => "RO",
            "SERBIA" => "RS","RUSSIA" => "RU","RWANDA" => "RW",
            "SAUDI ARABIA" => "SA","SOLOMON ISLANDS" => "SB","SEYCHELLES" => "SC",
            "SUDAN" => "SD","SWEDEN" => "SE","SINGAPORE" => "SG",
            "SAINT HELENA, ASCENSION AND TRISTAN DA CUNHA" => "SH",
            "SLOVENIA" => "SI","SVALBARD AND JAN MAYEN" => "SJ","SLOVAKIA" => "SK",
            "SIERRA LEONE" => "SL","SAN MARINO" => "SM","SENEGAL" => "SN",
            "SOMALIA" => "SO","SURINAME" => "SR","SOUTH SUDAN" => "SS",
            "SAO TOME AND PRINCIPE" => "ST","EL SALVADOR" => "SV",
            "SINT MAARTEN" => "SX","SYRIAN ARAB REPUBLIC" => "SY",
            "SWAZILAND" => "SZ","TURKS AND CAICOS ISLANDS" => "TC","CHAD" => "TD",
            "FRENCH SOUTHERN TERRITORIES" => "TF","TOGO" => "TG","THAILAND" => "TH",
            "TAJIKISTAN" => "TJ","TOKELAU" => "TK","TIMOR-LESTE" => "TL",
            "TURKMENISTAN" => "TM","TUNISIA" => "TN","TONGA" => "TO",
            "TURKEY" => "TR","TRINIDAD AND TOBAGO" => "TT","TUVALU" => "TV",
            "TAIWAN" => "TW","TANZANIA, UNITED REPUBLIC OF" => "TZ",
            "UKRAINE" => "UA","UGANDA" => "UG",
            "UNITED STATES MINOR OUTLYING ISLANDS" => "UM",
            "UNITED STATES" => "US","URUGUAY" => "UY","UZBEKISTAN" => "UZ",
            "VATICAN CITY" => "VA","SAINT VINCENT AND THE GRENADINES" => "VC",
            "VENEZUELA, BOLIVARIAN REPUBLIC OF" => "VE",
            "BRITISH VIRGIN ISLANDS" => "VG","U.S. VIRGIN ISLANDS" => "VI",
            "VIETNAM" => "VN","VANUATU" => "VU","WALLIS AND FUTUNA" => "WF",
            "SAMOA" => "WS","YEMEN" => "YE", "MAYOTTE" => "YT",
            "SOUTH AFRICA" => "ZA","ZAMBIA" => "ZM", "ZIMBABWE" => "ZW");

        $country_codes = array_flip($countries);
        $out = false;
        if(strlen($line) == 3) {
            $line = substr($line, 0, 2);
        }
        if(isset($country_codes[$line])) {
            $line = $country_codes[$line];
        }
        if(isset($countries[$line])) {
            $out = array("COUNTRY" => $line, "COUNTRY_CODE" =>
                $countries[$line]);
        }
        return $out;
    }

    /**
     *
     */
    function parsePostalCode($line)
    {
        $line = preg_replace("/\./", "", $line);
        $parts = preg_split("/\s+/", $line);
        $parts = array_values(array_filter($parts));
        $num = count($parts);
        $out = array();
        if($num == 1) {
            if(preg_match("/\d/", $parts[0])) {
                $out = array("ZIP/POSTAL CODE" => $parts[0]);
            }
            return $out;
        } else {
            $last = $num - 1;
            $len1 = strlen($parts[$last]);
            $len2 = strlen($parts[$last - 1]);
            $allowed = array(2,3,4);
            $found_zip = false;
            if(in_array($len1, $allowed) && in_array($len2, $allowed)) {
                if(preg_match("/\d/", $parts[$last])) {
                    $out["ZIP/POSTAL CODE"] = $parts[$last - 1]." ".
                        $parts[$last];
                    $last = $num - 3;
                    $found_zip = true;
                }
            }
        }
        return $out;
    }

    /**
     *
     */
    function parseLocality($line)
    {
        $out = array("LOCALITY" => $line);
        if(preg_match("/\d+|AVE|AVENUE|BOULEVARD|BLVD|".
            "ROAD|RD|STREET|WAY|WY|LANE|LN/", $line)) {
            $out = false;
        }
        return $out;
    }

    /**
     *
     */
    function parseCityLine($line)
    {
        $line = preg_replace("/\./", "", $line);
        $parts = preg_split("/\s+|\,/", $line);
        $parts = array_values(array_filter($parts));
        $num = count($parts);
        $out = array();
        if($num == 1) {
            if(preg_match("/\d/", $parts[0])) {
                $out = array("ZIP/POSTAL CODE" => $parts[0]);
            }
            return $out;
        } else {
            $last = $num - 1;
            if($last - 1 < 0) {return false;}
            $len1 = strlen($parts[$last]);
            $len2 = strlen($parts[$last - 1]);
            $allowed = array(3,4);
            $found_zip = false;
            if(in_array($len1, $allowed) && in_array($len2, $allowed)) {
                if(preg_match("/\d/", $parts[$last])) {
                    $out["ZIP/POSTAL CODE"] = $parts[$last - 1]." ".
                        $parts[$last];
                    $last = $num - 3;
                    $found_zip = true;
                }
            } else if(preg_match("/\d{5}|\d{5}\-\{4}/", $parts[$num - 1])) {
                $out["ZIP/POSTAL CODE"] = $parts[$num - 1];
                $last = $num - 2;
                $found_zip = true;
            }
            if($last < 0) {
                return $out;
            }
            if(strlen($parts[$last]) > 1) {
                $out["COUNTY/STATE/PROVINCE"] = preg_replace("/\./", "",
                    $parts[$last]);
                $last--;
                if($last < 0) {
                    if($found_zip) {
                        return false;
                    }
                    ksort($out);
                    return $out;
                }
            } else {
                return false;
            }
            if(preg_match("/\d+/", $parts[0])) {
                return false;
            }
            $city = "";
            for($i = 0 ; $i <= $last; $i++) {
                $city .= " ".$parts[$i];
            }
            $out["CITY"] = $city;
        }
        ksort($out);
        return $out;
    }

    /**
     *
     */
    function parseStreetLine($line, $i)
    {
        $line = preg_replace("/\./", "", $line);
        $parts = preg_split("/\s+|\,/", $line);
        $num = count($parts);
        $out = false;
        if($num < 2) {
            return $out;
        }
        if(preg_match(
            "/(\#)?\d+|PO|APT|ONE|TWO|THREE|FOUR|FIVE|SIX|SEVEN|EIGHT|NINE/",
            $parts[0])) {
            $out = array("STREET LINE$i" => $line);
        } else if(preg_match(
            "/AVE|AVENUE|BOULEVARD|BLVD|ROAD|STREET|HOUSE|ST|WAY|LN|WY|LANE/",
            $parts[$num - 1])) {
            $out = array("STREET LINE$i" => $line);
        }
        return $out;
    }

    /**
     *
     */
    function parseInstitution($line)
    {
        $out = false;
        $check_line = preg_replace("/\.|\//", "", $line);
        if(preg_match("/LLC|PLC|INC|LTD|CORP|CORPORATION"."
            |LIMITED|INST|ORG|HOSP|UNIV|COLLEGE/",
            $line)) {
            $out = array("ESTABLISHMENT" => $check_line);;
        }
        return $out;
    }

    /**
     *
     */
    function parseDepartmentOrCareof($line)
    {
        $line = preg_replace("/\.|\//", "", $line);
        $out = false;
        if(preg_match("/^CO/", $line)) {
            $out = array("CARE OF" => substr($line, 3));
        } else if(preg_match("/^DEP/i", $line)) {
            $out = array("DEPARTMENT" => $line);
        }
        return $out;
    }

    /**
     *
     */
    function parseOfficeResidenceNumber($line)
    {
        $out = false;
        if(preg_match("/ROOM|RM\BLDG|BUILDING|HALL|FLOOR|(\#)?\d+/", $line)) {
            $out = array("OFFICE/RESIDENCE NUMBER" => $line);
        }
        return $out;
    }

    /**
     *
     */
    function parseName($line)
    {
        $line = preg_replace("/\.[^com|^COM]/", " ", $line);
        $out = array("NAME" => $line);
        if(preg_match("/\d+/", $line)) {
            $out = false;
        }
        return $out;
    }

}

?>