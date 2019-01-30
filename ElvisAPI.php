<?php

/**
 * Main functions for ElvisAPI.
 *
 * @package Main
 * @version 0.01
 * @since 2018-01-30
 * @author Syahrul Farhan and Law Wen Jing
 */
class ElvisAPI {

    /**
     * URL to Elvis
     */
    private $url;

    /**
     * Username for login
     */
    private $username;

    /**
     * Password for login
     */
    private $password;

    /**
     * Session ID
     */
    private $sessionID;

    /**
     * Cross-Site Request Forgery (CSRF) code
     */
    private $csrf;

    /**
     * CSRF authenticator
     */
    private $csrfAuth;

    /**
     * File path to store temporary current login session
     */
    private $tmpFile;

    /**
     * Flag to indicate create file session
     */
    private $tmpFileCreate;

    /**
     * During initialize, create objects.
     * @example <code>
     * $elvis = new ElvisAPI($url, $username, $password, $tmpFile);
     * </code>
     * @param string $url Elvis URL
     * @param string $username Elvis login username
     * @param string $password Elvis login password
     * @param string $tmpFile File path to store temporary current login session
     */
    function __construct($url, $username, $password, $tmpFile = 'elviscache.idv') {
        $addSlash = false;
        if (!empty($url)) {
            if ($url[strlen($url) - 1] != '/') {
                $url .= '/';
            }
        }
        $this->url = $url;
        $this->username = $username;
        $this->password = $password;
        $this->csrf = '';
        $this->csrfAuth = '';
        $this->sessionID = '';
        $this->tmpFile = $tmpFile;
        $this->tmpFileCreate = false;
    }

    /**
     * postFile Function to upload file to Elvis.
     * @param string $url Elvis URL
     * @param array $metadata Metadata of file to upload
     * @param array $data File to upload
     * @return array Response from Elvis
     */
    private function postFile($url, $metadata, $data) {
        if (!empty($this->sessionID)) {
            $url .= ';jsessionid=' . $this->sessionID;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if (!empty($this->csrf)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: multipart/form-data',
                'Cookie: authToken=' . $this->csrfAuth,
                'X-CSRF-TOKEN: ' . $this->csrf
            ));
        }
        if (function_exists('curl_file_create')) {
            //above PHP 5.3
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $metadata['Filedata'] = new CurlFile($data['filepath'], finfo_file($finfo, $data['filepath']), $data['filename']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $metadata);
        } else {
            //below PHP 5.3
            $metadata['Filedata']  = '@' . $data['filepath'];
            curl_setopt($ch, CURLOPT_POSTFIELDS, $metadata);
        }
        return json_decode(curl_exec($ch), true);
    }

    /**
     * fetch Function to POST/GET at Elvis.
     * @param string $url Elvis URL
     * @param array $data Data to post
     * @param boolean $post true = POST, false = GET
     * @return array Response from Elvis
     */
    private function fetch($url, $data, $post = true) {
        $server_output = array();
        if (!empty($this->sessionID)) {
            $url .= ';jsessionid=' . $this->sessionID;
        }
        $ch = curl_init();
        if (!empty($this->csrf) && !empty($this->csrfAuth)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Cookie: authToken=' . $this->csrfAuth,
                'X-CSRF-TOKEN: ' . $this->csrf
            ));
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        if ($post) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } else {
            if (is_array($data) && count($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        $exec = curl_exec($ch);
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $exec, $matches);
        $cookies = array();
        foreach($matches[1] as $item) {
            parse_str($item, $cookie);
            $cookies = array_merge($cookies, $cookie);
        }
        $lines = explode("\n", $exec);
        $server_output = json_decode(end($lines), true);
        $server_output['cookie'] = $cookies;
        return $server_output;
    }

    /**
     * download Function to Download asset to local folder from Elvis.
     * @param string $url Elvis asset originalUrl
     * @param string $path Local folder path
     * @return boolean true = download success, false = download fail
     */
    private function download($url, $path) {
        $server_output = array();
        if (!empty($this->sessionID)) {
            $url .= ';jsessionid=' . $this->sessionID;
        }
        $ch = curl_init();
        if (!empty($this->csrf)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Cookie: authToken=' . $this->csrfAuth,
                'X-CSRF-TOKEN: ' . $this->csrf
            ));
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $exec = curl_exec($ch);
        file_put_contents($path, $exec);
        if (file_exists($path)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * saveSession Function to save current session temporarily.
     * @param array $server_output Login response from Elvis
     * @return void
     */
    private function saveSession($server_output) {
        $tmp = explode('/', $this->tmpFile);
        array_pop($tmp);
        if (count($tmp)) {
            $folder = implode('/', $tmp);
            if (!is_dir($folder)) {
                mkdir($folder, 0755, true);
            }
        }
        if (!file_exists($this->tmpFile)) {
            $json = json_encode($server_output);
            if (file_put_contents($this->tmpFile, $json)) {
                $this->tmpFileCreate = true;
            }
        }
    }

    /**
     * validateWebHooks Function to validate Elvis WebHook.
     * @example <code>
     * $header = getallheaders();
     * $data = file_get_contents("php://input");
     * if ($elvis->validateWebHooks($header['X-Hook-Signature'], $secret, $data)) {
     *     //webhook validation success
     * }
     * </code>
     * @param string $hashString X-Hook-Signature
     * @param string $elvisSecretToken Secret token generated by Webhook
     * @param string $data Raw POST data
     * @return boolean true = valid, false = not valid
     */
    public function validateWebHooks($hashString, $elvisSecretToken, $data) {
        $hash = hash_hmac('sha256', $data, $elvisSecretToken);
        return hash_equals($hash, $hashString);
    }

    /**
     * login Function to log in to Elvis.
     * @example <code>
     * if ($elvis->login()) {
     *     //log in is success
     * }
     * </code>
     * @return boolean true = successfully logged in, false = failed to log in
     */
    public function login() {
        if (file_exists($this->tmpFile)) {
            $server_output = json_decode(file_get_contents($this->tmpFile), true);
        } else {
            $loginURL = $this->url . 'services/login';
            $data = array(
                'username' => $this->username,
                'password' => $this->password
            );
            $server_output = $this->fetch($loginURL, $data);
            if (isset($server_output['loginSuccess'])) {
                $this->saveSession($server_output);
            }
        }
        if (isset($server_output['loginSuccess'])) {
            if (!empty($server_output['sessionId'])) {
                $this->sessionID = $server_output['sessionId'];
                return true;
            } else if (!empty($server_output['csrfToken'])) {
                $this->csrf = $server_output['csrfToken'];
                if (!empty($server_output['cookie']['authToken'])) {
                    $this->csrfAuth = $server_output['cookie']['authToken'];
                }
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * upload Function to upload asset to Elvis.
     * @example <code>
     * $images = array(
     *     'upload/abc.png',
     *     'upload/def.png'
     * );
     * $result = array();
     * foreach ($images as $img) {
     *     $tmp = explode('/', $img);
     *     $filename = end($tmp);
     *     $metadata = array(
     *         'assetPath' => ElvisUploadFolder . $filename
     *     );
     *     $result[] = $elvis->upload($filename, $metadata, $img);
     * }
     * </code>
     * @param string $filename Name of file to be uploaded
     * @param array $metadata Metadata of the file that match Elvis metadata field names
     * @param string $imgPath Location of file in local folder
     * @return array Response from Elvis
     */
    public function upload($filename, $metadata, $imgPath) {
        $uploadURL = $this->url . 'services/create';
        $data = array(
            'filename' => $filename, //filename.jpg
            'filepath' => $imgPath //upload/filename.jpg
        );
        return $this->postFile($uploadURL, $metadata, $data);
    }

    /**
     * update Function to update Elvis asset.
     * @example <code>
     * foreach ($searchResults as $r) {
     *     $metadata = array(
     *         'id' => $r['id'],
     *         'metadata' => json_encode(array(
     *             'category' => $r['metadata']['category'] . ' Demo',
     *         )),
     *         'status' => '',
     *     );
     *     $result[] = $elvis->update($metadata);
     * }
     * </code>
     * @param array $metadata Metadata to update Elvis asset
     * @return array Response from Elvis
     */
    public function update($metadata) {
        $updateURL = $this->url . 'services/update';
        return $this->fetch($updateURL, $metadata);
    }

    /**
     * search Function to search asset on Elvis.
     * @example <code>
     * $query = 'folderPath: /User/Demo';
     * $searchResult = $elvis->search($query, 0, 50);
     * </code>
     * @param string $q Query for file to be searched
     * @param array $start Starting number of result to be taken
     * @param string $num Ending number of result to be taken
     * @return array Response from Elvis
     */
    public function search($q, $start = 0, $num = 50) {
        $searchURL = $this->url . 'services/search';
        $data = array(
            'q' => $q,
            'start' => $start,
            'num' => $num
        );
        return $this->fetch($searchURL, $data);
    }

    /**
     * search Function to search asset on Elvis.
     * @example <code>
     * $id = '4DhDDez6qvRB3wdkAmZtd8';
     * $searchResult = $elvis->searchAssetID($id);
     * </code>
     * @param string $id Query for asset to be search by Elvis id
     * @return array Response from Elvis
     */
    public function searchAssetID($id) {
        $url = $this->url . 'services/search';
        $data = array(
            'q' => 'id:' . $id
        );
        $result = $this->fetch($url, $data);
        return $result;
    }

    /**
     * move Function to move asset from an Elvis folder to another Elvis folder.
     * @example <code>
     * foreach ($searchResults as $r) {
     *     $newPath = '/Demo/NewFolder/' . $r['metadata']['filename'];
     *     if (isset($r['metadata']['assetPath'])) {
     *         $result[] = $elvis->move($r['metadata']['assetPath'], $newPath);
     *     }
     * }
     * </code>
     * @param string $originalPath Get files from this folder
     * @param string $movePath Move files to this folder
     * @return array Response from Elvis
     */
    public function move($originalPath, $movePath) {
        $moveURL = $this->url . 'services/move';
        $data = array(
            'source' => $originalPath,
            'target' => $movePath
        );
        return $this->fetch($moveURL, $data);
    }

    /**
     * removeByID Function to remove asset from an Elvis based on ID.
     * @example <code>
     * foreach ($searchResult['hits'] as $s) {
     *     $result[] = $elvis->removeByID($s['id']);
     * }
     * </code>
     * @param string $id ID of Elvis asset
     * @return array Response from Elvis
     */
    public function removeByID($id) {
        $removeURL = $this->url . 'services/remove';
        $data = array(
            'ids' => $id
        );
        return $this->fetch($removeURL, $data);
    }

    /**
     * removeByFolder Function to delete entire folder including Elvis assets inside based on folder path.
     * @example <code>
     * $deleteFolder = '/Demo/DeleteThisFolder/';
     * $result = $elvis->removeByFolder($deleteFolder);
     * </code>
     * @param string $folderPath Folder path of Elvis asset
     * @return array Response from Elvis
     */
    public function removeByFolder($folderPath) {
        $removeURL = $this->url . 'services/remove';
        $data = array(
            'folderPath' => $folderPath
        );
        return $this->fetch($removeURL, $data);
    }

    /**
     * createCollection Function to create a collection.
     * @example <code>
     * foreach ($data as $d) {
     *     $assetPath = '/Demo/' . $d . '.collection';
     *     $folderPath = '/Demo/';
     *     $assetType = 'collection';
     *     $name = $d;
     *     $result[] = $elvis->createCollection($assetPath, $folderPath, $assetType, $name);
     * }
     * </code>
     * @param string $assetPath Asset path of Elvis asset
     * @param string $folderPath Folder path of Elvis asset
     * @param string $assetType Type of Elvis asset
     * @param string $name Name of Elvis asset
     * @return array Response from Elvis
     */
    public function createCollection($assetPath, $folderPath, $assetType, $name) {
        $createURL = $this->url . 'services/create';
        $data = array(
            'assetPath' => $assetPath,
            'folderPath' => $folderPath,
            'assetType' => $assetType,
            'name' => $name
        );
        return $this->fetch($createURL, $data, false);
    }

    /**
     * createRelation Function to create relation between assets
     * @param  string $relationType [Related|References|Contains|Duplicate|variation|Instance|Uses]
     * @param  string $id1 Asset Elvis ID
     * @param  string $id2 Asset Elvis ID
     * @return array Response from Elvis
     */
    public function createRelation($relationType, $id1, $id2) {
        $createURL = $this->url . 'services/createRelation';
        $data = array(
            'relationType' => $relationType,
            'target1Id' => $id1,
            'target2Id' => $id2
        );
        return $this->fetch($createURL, $data);
    }

    /**
     * downloadFile Function to Download asset to local folder from Elvis.
     * @example <code>
     * foreach ($data as $d) {
     *     $url = $searchResult['hits'][0]['originalUrl'];
     *     $path = 'Demo/' . $searchResult['hits'][0]['metadata']['filename'];
     *     $elvis->downloadFile($url, $path);
     * }
     * </code>
     * @param string $url Elvis asset originalUrl
     * @param string $path Local folder path
     * @return boolean Download status
     */
    public function downloadFile($url, $path) {
        return $this->download($url, $path);
    }

    /**
     * collectionRelation Create Relation between collection
     * @param string $id1 Elvis Collection ID
     * @param string $id2 Elvis Collection ID
     * @return array Response from Elvis
     */
    public function collectionRelation($id1, $id2) {
        $url = $this->url . 'services/createRelation';
        $data = array(
            'relationType' => 'contains',
            'target1Id' => $id1,
            'target2Id' => $id2
        );
        $result = $this->fetch($url, $data);
        return $result;
    }

    /**
     * createFolder Create folder in ELvis
     * @param string $path Elvis folder path
     * @return array Response from Elvis
     */
    public function createFolder($path) {
        $url = $this->url . 'services/createFolder';
        $data = array(
            'path' => $path
        );
        $result = $this->fetch($url, $data);
        return $result;
    }

    /**
     * createAuthkey Create Sharelink in ELvis
     * @example <code>
     * $share = array(
     *  'subject' => "name of the Authkey",
     *  'validUntil' => date('Y-m-d', strtotime('+1 year')),
     *  'requestUpload' => 'true',
     *  'importFolderPath' => $folderPath
     * );
     * $result = $elvis->createAuthkey($share);
     * </code>
     * @param string $data Sharelink information
     * @return array Response from Elvis
     */
    public function createAuthkey($data) {
        $url = $this->url . 'services/createAuthKey';
        $result = $this->fetch($url, $data);
        return $result;
    }

    /**
     * searchAssetIDAuth function is to search Elvis without login into the system
     * @param string $id Elvis asset id
     * @return array Response from Elvis
     */
    public function searchAssetIDAuth($id) {
        $url = $this->url . 'services/search?q=id:' . $id . '&authcred=' . base64_encode($this->user . ':' . $this->pass);
        $data = file_get_contents($url);
        return json_decode($data, true);
    }

    /**
     * logout Function to delete entire folder including Elvis assets inside based on folder path.
     * @example <code>
     * $logout = $elvis->logout();
     * </code>
     * @return array Response from Elvis
     */
    public function logout() {
        if ($this->tmpFileCreate) {
            $logoutURL = $this->url . 'services/logout';
            if (file_exists($this->tmpFile)) {
                unlink($this->tmpFile);
            }
            return $this->fetch($logoutURL, array());
        } else {
            return array(
                'status' => false,
                'Not logged in in this session'
            );
        }
    }
}

?>
