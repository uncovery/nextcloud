<?php

class nextcloud {
    private $hostname;
    private $username;
    private $password;
    private $debug = false; //options: web console log false

    /**
     * class constructor to get variables set only.
     *
     * @param type $in_host
     * @param type $in_user
     * @param type $in_pass
     * @param type $in_debug
     */
    public function __construct($in_host, $in_user, $in_pass, $in_debug) {
        $this->hostname = $in_host;
        $this->username = $in_user;
        $this->password = $in_pass;
        $this->debug = $in_debug;
    }

    /**
     * Reads a Nextcloud folder recursively to a given depth and returns all the contents.
     *
     * @return type The contents of the Nextcloud folder.
     */
    public function read_folder($folder, $depth) {

        $url = "remote.php/dav/files/$this->username/$folder";

        $headers = array(
            CURLOPT_CUSTOMREQUEST => "PROPFIND",
            CURLOPT_HTTPHEADER => array("Depth: $depth"),
        );

        $this->debug("looking for files $depth folder deep:");

        $output = $this->nc_curl($url, $headers);
        $xml = simplexml_load_string($output);

        if ($xml === false) {
            throw new Exception("No files could be found on nextcloud");
        }

        $ns = $xml->getNamespaces(true);
        $files = $xml->children($ns['d']);

        $this->debug("found files:" . count($files));

        return $files;
    }

    /**
     * Filters all files from read_folder() that are matching given content types.
     *
     * @param array $files The array of files to be filtered.
     * @return array The filtered array of files.
     */
    public function filter_files($files, array $content_types) {

        $files_copy = array();
        foreach ($files as $F) {
            $P = $F->propstat->prop;
            // let's skip directories
            if (isset($P->resourcetype->collection))  {
                $this->debug("skipping folder", 'nc_filter_files');
                continue;
            }
            // if we have a content type, check it against the config list
            if (isset($content_types[$P->getcontenttype->__toString()])) {
                $files_copy[] = $F;
            } else {
                $this->debug("skipping file of content type" . $P->getcontenttype->__toString(), 'filter_files');
            }
        }

        $this->debug("files left after filtering: " . count($files_copy));
        return $files_copy;
    }

    /**
     * Delete a file on the Nextcloud storage
     *
     * @global \ums\type $UMS
     * @param type $file
     */
    public function delete_file($file) {
        // then, file to be deleted
        $url_file = "remote.php/dav/files/$this->username/$file";

        $this->debug("deleting file on NC instance: $url_file");

        $this->nc_curl($url_file, array(CURLOPT_CUSTOMREQUEST => "DELETE"));
    }

    /**
     * Move a file on the Nextcloud storage. Replaces spaces with underscores for all files.
     *
     * @global string $nc_auth
     * @global string $nc_url
     * @param string $file The name of the file to be moved.
     * @param string $target_folder The target folder where the file will be moved to.
     */
    public function move_file($source_path, $target_folder) {

        $fixed_target_folder = str_replace( ' ', '%20', trim($target_folder));

        // first, we create the folder
        $url = "remote.php/dav/files/$this->username/$fixed_target_folder";
        $this->nc_curl($url , array(CURLOPT_CUSTOMREQUEST => "MKCOL"));

        // make sure we replace spaces in the file
        $str_arr = array(' ', '%20');
        $fixed_source_path = str_replace($str_arr, "_", $source_path);

        $url_dest = "{$this->hostname}remote.php/dav/files/$this->username/$fixed_target_folder/$fixed_source_path";

        $this->debug("moving $fixed_source_path to $target_folder", 'move_file');

        $headers = array(
            CURLOPT_CUSTOMREQUEST => "MOVE",
            CURLOPT_HTTPHEADER => "Destination: $url_dest",
        );

        // then, move the file to the folder
        $url_file = "remote.php/dav/files/$this->username/$fixed_source_path";

        $this->nc_curl($url_file, $headers);
    }

    /**
     * Downloads a file from Nextcloud WEBDAV via PHP CURL.
     *
     * @param string $path The path of the file to download.
     * @param string|false $target The target file path to save the downloaded file. If false, the file content will be returned.
     * @return string|false The file content if $target is false, otherwise returns true on successful download or false on failure.
     */
    public function download_file($path, $target = false) {
        $url = "remote.php/dav/files/$this->username/$path";
        $output = $this->nc_curl($url);

        if (strlen($output) == 0) {
            return false;
        }

        if ($target) {
            $fp = fopen($target, "w");
            if ($fp) {
                fwrite($fp, $output);
                fclose($fp);
            } else {
                throw new Exception("Error creating file: $target!");
            }
        } else {
            return $output;
        }
    }

    /**
     * upload a file from a path to a folder
     * @param type $path
     */
    public function upload_file($target_path, $file_path) {

        $fixed_path = str_replace( ' ', '%20', trim($target_path));
        $this->debug("Uploading file from path $file_path to $fixed_path");
        $url = "remote.php/dav/files/$this->username/$fixed_path";

        $this->debug("File size to upload: " . filesize($file_path));

        $headers = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_PUT => true,
            CURLOPT_INFILESIZE => filesize($file_path),
            CURLOPT_INFILE => fopen($file_path, "r"),
        );
        $output = $this->nc_curl($url, $headers);
        return $output;
    }


    /**
     * Create a share on Nextcloud and return the share URL.
     *
     * @global type $UMS
     * @param type $path The path of the file or folder to be shared.
     * @param type $expiry The expiry date of the share.
     * @return type The share URL.
     */
    public function create_share($path, $expiry) {
        $url = "ocs/v2.php/apps/files_sharing/api/v1/shares";

        // make sure we replace spaces in the file
        $str_arr = array(' ', '%20');
        $final_path = "/" .  str_replace($str_arr, "_", $path);

        $this->debug("Creating share for file $final_path with expiry $expiry");

        $headers = array(
            CURLOPT_HTTPHEADER => array('OCS-APIRequest: true'),
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => "path=$final_path&shareType=3&Permission=1&expireDate=$expiry",
        );

        $output = $this->nc_curl($url, $headers);

        $this->debug($output, 'create_share -> output');

        // convert the resulting XML String to XML objects
        $xml = simplexml_load_string($output);

        $this->debug($xml, 'create_share -> xml');
        // convert it to JSON
        $json = json_encode($xml);
        // convert JSON to array
        $array = json_decode($json,TRUE);

        $this->debug($json, 'create_share -> json');

        return $array['data']['url'];
    }

    private function nc_curl(string $url, array $headers = []) {
        // open the connection
        $ch = curl_init();

        $this->debug("Sending request to URL $this->hostname$url");
        $this->debug("cURL Headers:");
        $this->debug($headers);

        curl_setopt($ch, CURLOPT_URL, $this->hostname . $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->username:$this->password");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

        foreach ($headers as $key => $value) {
            if (is_string($key)) {
                echo "$key $value invalid";
                die();
            }
            curl_setopt($ch, $key, $value);
        }

        $output = curl_exec($ch);
        $this->debug("CURL POUTPUT: ");
        $this->debug($output);

        // close the connection
        curl_close($ch);

        // check for error
        if ($output === false) {
            throw new Exception("The Nextcloud connection failed. Please check your URL.");
        } else if (strstr($output, 'Sabre\\DAV\\Exception')) {
            throw new Exception("There was an error connecting to Nextcloud. The returned error was:<br><pre>$output</pre>");
        }
        return $output;
    }

    /**
     * debug info
     *
     * @param type $info
     * @param type $target
     */
    private function debug($info) {
        // check where debug was called
        $trace = debug_backtrace();
        $source = "{$trace[1]['function']}";
        if (isset($trace[1]['class'])) {
            $source . " in class {$trace[1]['class']}";
        }

        $text = "NextCloud Debug: " . var_export($info, true) . " Source: $source";

        switch ($this->debug) {
            case 'web':
                echo "$text<br>";
                break;
            case 'console':
                echo "$text\n";
                break;
            case 'log':
                error_log($text);
                break;
            case false:
                return;
            default:
                throw new Exception("Invalid debug format: $this->debug");
        }
    }
}