<?php
require_once("rest.php");
date_default_timezone_set("Europe/Berlin");

//print_r($_SERVER);
class API extends REST
{
    public $data = "";
    private $browser = array();
    private $req = array();

    //Enter details of your database
    const DB_SERVER = "mysql.local";
    const DB_USER = "dbuser";
    const DB_PASSWORD = "dbpass";
    const DB = "db";

    private $db = NULL;

    public function __construct() {
        parent::__construct();              // Init parent contructor
        //$this->dbConnect();                 // Initiate Database connection
    }

    private function dbConnect() {
        $this->db = mysql_connect(self::DB_SERVER, self::DB_USER, self::DB_PASSWORD);
        if ($this->db)
            mysql_select_db(self::DB, $this->db);
    }

    /*
     * Public method for access api.
     * This method dynmically call the method based on the query string
     *
     */
    public function processApi() {
        $this->req = explode('/', $_SERVER["REQUEST_URI"]);
        $func = strtolower(trim($this->req[1]));
        $request = explode('/', trim($_SERVER["REQUEST_URI"], '/'));
        if ((int)method_exists($this, $func) > 0)
            $this->$func();
        else
            echo $this->$func;
        $this->response('Error code 404, Page not found', 404);   // If the method not exist with in this class, response would be "Page not found".
    }

    private function browser() {
        $obj["chrome"] = $this->chrome(1);
        $obj["firefox"] = $this->firefox(1);
        return $obj;
        $dataJ = $this->json($obj);
        $this->response($this->indent($dataJ), 200);
    }

    private function chrome() {
        $bat = shell_exec("scripts\browser_chrome.bat");
        return trim(str_replace("Version=", "", $bat));
    }

    private function firefox() {
        $bat = shell_exec("scripts\browser_firefox.bat");
        return trim(str_replace("Version=", "", $bat));
    }

    private function gdata() {
        $bat = shell_exec("scripts\antivirus_gdata.bat");
        $gdataInfo = explode("\n", $bat);
        foreach ($gdataInfo as $info) {
            if($info !="") {
                $obj["gdata"][] = trim($info);
            }
        }


        $dataJ = $this->json($obj);
        $this->response($this->indent($dataJ), 200);
    }

    private function usb() {
        $bat = shell_exec('scripts\windows_massstorage.bat');
        $usboption = explode("    ", $bat);

        return trim($usboption[3]);
    }

    private function AUOption() {
        $bat = shell_exec('scripts\windows_auoption.bat');
        $fields = explode("    ", $bat);
        switch (trim($fields[3])) {
            case "0x2":
                $ret = "Notify for download and notify for install";
                break;
            case "0x3":
                $ret = "Auto download and notify for install";
                break;
            case "0x4":
                $ret = "Auto download and schedule the install";
                break;


        }

        return $ret;
    }

    private  function systeminfo() {
        $file = "sysinfo.csv";
        $bat = shell_exec("systeminfo /FO CSV");
        if (file_exists($file)) {
            unlink($file);
        }
        file_put_contents($file,iconv("cp437", "utf-8", $bat));
        $csv = array_map('str_getcsv', file($file));
        array_walk($csv, function(&$a) use ($csv) {
            $a = array_combine($csv[0], $a);
        });
        array_shift($csv); # remove column header
        foreach ($csv[0] as $item => $c) {
            if (in_array($item, array("Hostname", "Systemtyp", "Betriebssystemname", "Betriebssystemversion","Gesamter physischer Speicher", "Host Name", "OS Name", "OS Version", "System Type", "Total Physical Memory"))) {
                $value = $c;
                $item = strtolower($item);
                $part = "OperatingSystem";

                if(in_array($item, array("gesamter physischer speicher","total physical memory"))) {
                    $item = "memory";
                    $value = intval($c*1024);
                }
                if (in_array($item, array("systemtyp","system type"))) {

                    $item = "architecture";
                    if(strstr($c, 'x64')) {
                        $value = "AMD64";
                    } else {
                        $value = "X86";
                    }
                }
                if (in_array($item, array("betriebssystemversion","os version"))) {
                    $item = "osversion";
                    $value = str_replace(" Nicht zutreffend Build 9600", "", $c);
                }
                if (in_array($item, array("betriebssystemname","os name"))) {
                    $item = "osname";
                }

                $data[$part][$item]=$value;
            }
        }
        //print_r($csv);
        $data[$part]["AUOption"] = trim($this->AUOption());
        $data[$part]["USB Mass storage"] = trim($this->usb());
        $data["browser"]["chrome"] = $this->chrome();
        $data["browser"]["firefox"] = $this->firefox();
        $this->response($this->indent($this->json($data)), 200);
    }

    private function agentupdate() {
        $bat = shell_exec('update.bat');
        $updateInfo = explode("\n", $bat);
        $obj["host"]["name"] = $this->getHostname();
        $obj["windows"]["os"] = $this->getOSVersion();
        foreach ($updateInfo as $info) {
            if($info != "") {
                $obj["sysagent"]["update"][] = trim($info);
            }
        }

        $dataJ = $this->json($obj);
        $this->response($this->indent($dataJ), 200);
    }

    private function getHostname() {
        $bat = shell_exec("wmic computersystem  get name");
        return str_replace("Name       \r\n","",trim($bat));
    }

    private function getOSVersion() {
        $bat = shell_exec("ver");
        return trim($bat);
    }
    /*
     *  Encode array into JSON
    */
    private function json($data) {
        if (is_array($data)) {
            return json_encode($data);
        }
    }

    /**
     * Indents a flat JSON string to make it more human-readable.
     *
     * @param string $json The original JSON string to process.
     *
     * @return string Indented version of the original JSON string.
     */
    private function indent($json) {
        $result = '';
        $pos = 0;
        $strLen = strlen($json);
        $indentStr = '  ';
        $newLine = "\n";
        $prevChar = '';
        $outOfQuotes = true;

        for ($i = 0; $i <= $strLen; $i++) {

            // Grab the next character in the string.
            $char = substr($json, $i, 1);

            // Are we inside a quoted string?
            if ($char == '"' && $prevChar != '\\') {
                $outOfQuotes = !$outOfQuotes;

                // If this character is the end of an element,
                // output a new line and indent the next line.
            } else if (($char == '}' || $char == ']') && $outOfQuotes) {
                $result .= $newLine;
                $pos--;
                for ($j = 0; $j < $pos; $j++) {
                    $result .= $indentStr;
                }
            }

            // Add the character to the result string.
            $result .= $char;

            // If the last character was the beginning of an element,
            // output a new line and indent the next line.
            if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
                $result .= $newLine;
                if ($char == '{' || $char == '[') {
                    $pos++;
                }

                for ($j = 0; $j < $pos; $j++) {
                    $result .= $indentStr;
                }
            }

            $prevChar = $char;
        }

        return $result;
    }
}

// Initiiate Library

$api = new API;
$api->processApi();
