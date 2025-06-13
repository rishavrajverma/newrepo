<?php

define('FM_HOST', '172.16.8.104');
define('FM_DATABASE', 'OrderSysV3.fmp12');
define('FM_USERNAME', 'admin');
define('FM_PASSWORD', 'admin');

class FileMakerConnection {
    private static $instance = null;
    private $host, $database, $username, $password, $token, $baseUrl;

    private function __construct() {
        $this->host = FM_HOST;
        $this->database = FM_DATABASE;
        $this->username = FM_USERNAME;
        $this->password = FM_PASSWORD;
        $this->baseUrl = "https://{$this->host}/fmi/data/vLatest/databases/{$this->database}";
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        if (empty($this->token)) {
            $this->authenticate();
        }
        return $this;
    }

    private function authenticate() {
        $url = $this->baseUrl . "/sessions";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }

        curl_close($ch);
        $data = json_decode($response, true);

        if ($httpCode !== 200 || !isset($data['response']['token'])) {
            throw new Exception('FileMaker authentication failed');
        }

        $this->token = $data['response']['token'];
    }

    public function getAuthHeaders() {
        return [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ];
    }

    public function getToken() {
        
        return $this->token;
    }

    public function makeApiRequest($method, $endpoint, $data = null) {
        $url = $this->baseUrl . $endpoint;

        try {
            return $this->makeRequest($method, $url, $data, $this->getAuthHeaders());
        } catch (Exception $e) {
            // Retry once if token invalid
            if (strpos($e->getMessage(), '952') !== false || strpos($e->getMessage(), 'Invalid FileMaker Data API token') !== false) {
                $this->token = null;
                $this->authenticate();
                return $this->makeRequest($method, $url, $data, $this->getAuthHeaders());
            }
            throw $e;
        }
    }

    private function makeRequest($method, $url, $data = null, $headers = []) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($data && in_array($method, ['POST', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }

        curl_close($ch);
        $result = json_decode($response, true);

        if (isset($result['messages'][0]['code']) && $result['messages'][0]['code'] !== '0') {
            throw new Exception('FileMaker Error (' . $result['messages'][0]['code'] . '): ' . $result['messages'][0]['message']);
        }

        return $result;
    }

    public function close() {
        if (!empty($this->token)) {
            try {
                $url = $this->baseUrl . "/sessions/{$this->token}";
                $this->makeRequest("DELETE", $url, null, $this->getAuthHeaders());
            } catch (Exception $e) {
                // Ignore logout errors
            }
            $this->token = null;
        }
    }

    public function __destruct() {
        $this->close();
    }
}

function getFileMakerConnection() {
    return FileMakerConnection::getInstance()->getConnection();
}
