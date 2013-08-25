<?php
    // include_once('/adodb/adodb.inc.php');
    include_once('config.php');
    
    class FrException extends Exception {
        function __construct($code, $message) {
            parent::__construct($message, $code);
        }
        
        function JsonMessage() {
            return array('error_code'=>$this->getCode(),
                         'error_message'=>$this->getMessage()
                         );
        }
    }
    
    class FrApp {
        protected $conn;
        
        function __construct() {
            $this->Connect();
        }
        
        function InternalError() {
            throw new FrException(500, 'Internal Error');
        }
        
        function Connect() {
            //$conn = @ADONewConnection('mysql://'.MYSQL_USER.':'.MYSQL_PWD.'@'.MYSQL_HOST.'/'.MYSQL_DB.'?persist');
            $this->conn = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PWD, MYSQL_DB);
            $conn = $this->conn;
            if (!$conn) {
                $this->InternalError();
            }
            $conn->query('SET NAMES UTF8');
            $this->InitTable();
        }
        
        function InitTable() {
            $this->conn->query('CREATE TABLE IF NOT EXISTS `image` (`uid` CHAR(40) NOT NULL, `img` LONGBLOB, UNIQUE KEY `uid` (`uid`)) COLLATE utf8_general_ci;');
        }
        
        function GetParam($key, &$data, $force = true, $default = null) {
            if (!isset($data[$key])) {
                if ($force) {
                    throw new TyException(0x001, 'Required parameter `'.$key.'` is not present');
                }
                return $default;
            }
            return $data[$key];
        }
        
        function MethodTest() {
            $name = $_FILES['img']['tmp_name'];
            $handle = fopen($name, 'rb');
            $contents = fread($handle, filesize($name));
            $hash = hash_file('sha1', $name);
            
            $stmt = $this->conn->prepare('INSERT INTO image (uid, img) VALUES (?,?)');
            $null = NULL;
            $stmt->bind_param('sb', $hash, $null);
            $stmt->send_long_data(1, file_get_contents($name));
            $stmt->execute();
            
            $hash = hash_file('sha1', $name);
            return array('result'=>1, 'name'=>$_FILES['img']['name'], 'size'=>$_FILES['img']['size'], 'sha1'=>$hash);
        }
        
        function MainHandler() {
            try {
                $this->Connect();
                $method = $this->GetParam('method', $_POST);
                switch ($method) {
                    case 'test':
                        return $this->MethodTest();
                    default:
                        throw new FrException(0x002, 'Parameter `method` is not valid');
                }
            }
            catch (FrException $e) {
                return $e->JsonMessage();
            }
        }
        
        function HandleRequest() {
            $result = $this->MainHandler();
            if (!isset($result['type'])) {
                // Plaintext result
                header('Content-Type: text/plain; charset=utf8');
                echo json_encode($result);
            }
            else if ($result['type'] == 'html') {
                header('Content-Type: text/html; charset=utf8');
                echo $result['data'];
            }
            else {
                // Image Output
                header('Content-Type: image/jpeg');
                echo $result['data'];
            }
        }
    }
    
    $m = new FrApp();
    $m->HandleRequest();
?>