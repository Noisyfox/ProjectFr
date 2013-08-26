<?php
    include_once('config.php');
    
    class FrException extends Exception {
        function __construct($code, $message) {
            parent::__construct($message, $code);
        }
        
        function JsonMessage() {
            $r = $this->getCode();
            // if ($r < 0) {
            //     return array('result'=>$r);
            // }
            // else {
            //     return array('result'=>0);
            // }
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
            $this->conn->query('CREATE TABLE IF NOT EXISTS `user` (id INT AUTO_INCREMENT PRIMARY KEY, name CHAR(50), password CHAR(40), sex INT, avatar CHAR(40), school VARCHAR(200), region VARCHAR(200), UNIQUE KEY `name`(`name`)) DEFAULT CHARSET=UTF8;');
        }
        
        function GetParam($key, &$data, $force = true, $default = null) {
            if (!isset($data[$key])) {
                if ($force) {
                    throw new FrException(0x001, 'Required parameter `'.$key.'` is not present');
                }
                return $default;
            }
            return $data[$key];
        }
        
        function ImageSave($tmpfile) {
            if (!file_exists(IMAGE_PATH)) {
                if (!mkdir(IMAGE_PATH)) {
                    throw new FrException(3, 'Cannot mkdir');
                }
            }
            else if (!is_dir(IMAGE_PATH)) {
                throw new FrException(3, 'Cannot open image directory');
            }
            
            $hash = hash_file('sha1', $tmpfile);
            if ($hash == false) {
                throw new FrException(3, 'Cannot open temp file');
            }
            $r = copy($tmpfile, IMAGE_PATH.'/'.$hash);
            if (!$r) {
                throw new FrException(3, 'Cannot copy file');
            }
            return $hash;
        }
        
        function MethodTest() {
            $name = $_FILES['img']['tmp_name'];
            $handle = fopen($name, 'rb');
            $contents = fread($handle, filesize($name));
            $hash = hash_file('sha1', $name);
            
            $this->ImageSave($name);
            
            $stmt = $this->conn->prepare('INSERT INTO image (uid, img) VALUES (?,?)');
            $null = NULL;
            $stmt->bind_param('sb', $hash, $null);
            $stmt->send_long_data(1, file_get_contents($name));
            $stmt->execute();
            
            $hash = hash_file('sha1', $name);
            return array('result'=>1, 'name'=>$_FILES['img']['name'], 'size'=>$_FILES['img']['size'], 'sha1'=>$hash);
        }
        
        function MethodUserRegister() {
            $name = $this->GetParam('name', $_REQUEST);
            $password = $this->GetParam('password', $_REQUEST);
            $sex = (int)$this->GetParam('sex', $_REQUEST);
            $type = (int)$this->GetParam('type', $_REQUEST);
            $avatar_tmp = $_FILES['avatar']['tmp_name'];
            $school = $this->GetParam('school', $_REQUEST);
            $region = $this->GetParam('region', $_REQUEST);
            $avatar_hash = $this->ImageSave($avatar_tmp);
            
            // Check duplicated user name
            // $stmt = $this->conn->prepare('SELECT id FROM `user` WHERE name=?');
            // $stmt->bind_param('s', $name);
            // $stmt->execute();
            // $duplicated = $stmt->fetch();
            // $stmt->close();
            // if ($duplicated == true) {
            //     throw new FrException(-1, 'Duplicated user name');
            // }
            // if ($duplicated == false) {
            //     $this->InternalError();
            // }
            
            $stmt = $this->conn->prepare('INSERT INTO `user` (name,password,sex,avatar,school,region) VALUES (?,?,?,?,?,?);');
            if (!$stmt) throw new FrException(5, $this->conn->error);
            $stmt->bind_param('ssisss', $name, $password, $sex, $avatar_hash, $school, $region);
            $r = $stmt->execute();
            if (!$r) {
                if ($this->conn->errno == 1062) {
                    // Duplicated key(user name)
                    throw new FrException(-1, 'Duplicated user name');
                }
                else throw new FrException(5, 'DbError '.$this->conn->error);
            }
            return array('result'=>1);
        }
        
        function MainHandler() {
            try {
                $this->Connect();
                $method = $this->GetParam('method', $_POST);
                switch ($method) {
                    case 'test':
                        return $this->MethodTest();
                    case 'user.register':
                        return $this->MethodUserRegister();
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
                // Json result
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