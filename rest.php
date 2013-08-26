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
            if ($this->conn && $this->conn->error) throw new FrException(500, $this->conn->error);
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
            // $this->conn->query('CREATE TABLE IF NOT EXISTS `image` (`uid` CHAR(40) NOT NULL, `img` LONGBLOB, UNIQUE KEY `uid` (`uid`)) COLLATE utf8_general_ci;');
            $this->conn->query('CREATE TABLE IF NOT EXISTS `user` (id INT AUTO_INCREMENT PRIMARY KEY, name CHAR(50), password CHAR(40), sex INT, type INT, avatar CHAR(40), school VARCHAR(200), region VARCHAR(200), UNIQUE KEY `name`(`name`)) DEFAULT CHARSET=UTF8;');
            $this->conn->query('CREATE TABLE IF NOT EXISTS `session` (sid CHAR(40) NOT NULL, uid INT, expire DATETIME, type INT)');
            $this->conn->query('CREATE TABLE IF NOT EXISTS `shop` (id INT PRIMARY KEY AUTO_INCREMENT, uid INT, name VARCHAR(200), address VARCHAR(200), introduction TEXT, photo CHAR(40), phonenum VARCHAR(50)) DEFAULT CHARSET=UTF8;');
        }
        
        function GetParam($key, &$data, $force = true, $default = false) {
            if (!isset($data[$key])) {
                if ($force) {
                    throw new FrException(0x001, 'Required parameter `'.$key.'` is not present');
                }
                return $default;
            }
            return $data[$key];
        }
        
        function GetUploadfile($key, $force = true, $default = false) {
            if (!isset($_FILES[$key]) || !$_FILES[$key]['tmp_name']) {
                if ($force) throw new FrException(1, 'Parameter `'.$key.'` missing');
                return $default;
            }
            return $_FILES[$key]['tmp_name'];
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
            
            $hash = @hash_file('sha1', $tmpfile);
            if ($hash == false) {
                throw new FrException(3, 'Cannot open temp file');
            }
            // $r = copy($tmpfile, IMAGE_PATH.'/'.$hash);
            $r = @move_uploaded_file($tmpfile, IMAGE_PATH.'/'.$hash);
            if (!$r) {
                throw new FrException(3, 'Cannot copy file');
            }
            return $hash;
        }
        
        function ImageGet() {
            $id = $this->GetParam('id', $_REQUEST);
            $pattern = '/[0-9a-fA-F]+/';
            if (!preg_match($pattern, $id)) throw new FrException(6, 'Illegal id');
            $contents = @file_get_contents(IMAGE_PATH.'/'.$id);
            return array('response_type'=>'image','data'=>$contents);
        }
        
        function SessionCheck(&$data, $owner_required = false) {
            $id = (int)$this->GetParam('uid', $data);
            $session = $this->GetParam('session', $data);
            $stmt = $this->conn->prepare('SELECT type FROM session WHERE uid=? AND sid=? AND expire>NOW()');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('is', $id, $session);
            $r = $stmt->execute();
            if (!$r) $this->InternalError();
            $stmt->bind_result($type);
            $r = $stmt->fetch();
            $stmt->close();
            if ($r == false) $this->InternalError();
            if ($r == NULL) throw new FrException(-1, 'Session Invalid');
            if ($owner_required && $type != 1) return false;
            return true;
        }
        
        function SessionReset($uid) {
            $stmt = $this->conn->prepare('DELETE FROM session WHERE uid=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('i', $uid);
            $r = $stmt->execute();
            if (!$r) $this->InternalError();
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
            
            $stmt = $this->conn->prepare('INSERT INTO `user` (name,password,sex,type,avatar,school,region) VALUES (?,?,?,?,?,?,?);');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('ssiisss', $name, $password, $sex, $type, $avatar_hash, $school, $region);
            $r = $stmt->execute();
            if (!$r) {
                if ($this->conn->errno == 1062) {
                    // Duplicated key(user name)
                    throw new FrException(-1, 'Duplicated user name');
                }
                else $this->InternalError();
            }
            $uid = $this->conn->insert_id;
            
            if ($type == 1) {
                // Reserve a shop
                $blank = '';
                $stmt = $this->conn->prepare('INSERT INTO `shop` (uid,name,address,introduction,photo,phonenum) VALUES (?,?,?,?,?,?);');
                if (!$stmt) $this->InternalError();
                $stmt->bind_param('isssss', $uid, $blank, $blank, $blank, $blank, $blank);
                $r = $stmt->execute();
                if (!$r) $this->InternalError();
                $stmt->close();
            }
            return array('result'=>1, 'id'=>$uid, 'session'=>$this->MethodUserLogin(true));
        }
        
        function MethodUserLogin($session_only = false) {
            // Authenticate
            $uid = 0;
            if (isset($_REQUEST['name']) && isset($_REQUEST['password'])) {
                $stmt = $this->conn->prepare('SELECT id, type FROM `user` WHERE name=? AND password=?');
                if (!$stmt) $this->InternalError();
                $stmt->bind_param('ss', $_REQUEST['name'], $_REQUEST['password']);
                $r = $stmt->execute();
                if (!$r) $this->InternalError();
                $stmt->bind_result($uid, $type);
                if (!$stmt->fetch()) throw new FrException(-1, 'Login fail 1');
                $stmt->close();
                
                // Update session
                $random = 'SESSION'.$uid.time().mt_rand().mt_rand().mt_rand();
                $newses = hash('sha1', $random);
                $stmt = $this->conn->prepare('INSERT INTO session (uid,type,sid,expire) VALUES (?,?,?,DATE_ADD(NOW(), INTERVAL 30 DAY));');
                if (!$stmt) $this->InternalError();
                $stmt->bind_param('iis', $uid, $type, $newses);
                $r = $stmt->execute();
                if (!$r) $this->InternalError();
                $stmt->close();
                }
            else if (isset($_REQUEST['uid']) && isset($_REQUEST['session'])) {
                $uid = (int)$_REQUEST['uid'];
                $newses = $_REQUEST['session'];
                $stmt = $this->conn->prepare('SELECT uid FROM session WHERE uid=? AND sid=? AND expire>NOW()');
                if (!$stmt) $this->InternalError();
                $stmt->bind_param('is', $uid, $_REQUEST['session']);
                $r = $stmt->execute();
                if (!$r) $this->InternalError();
                $r = $stmt->fetch();
                $stmt->close();
                if ($r == NULL) throw new FrException(-1, 'Login fail 2');
                if ($r == false) $this->InternalError();
                
                // Refresh session
                $stmt = $this->conn->prepare('UPDATE session SET expire=DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE uid=? AND sid=?');
                if (!$stmt) $this->InternalError();
                $stmt->bind_param('is', $uid, $newses);
                $r = $stmt->execute();
                if (!$r) $this->InternalError();
            }
            else throw new FrException(1, 'Wrong calling parameters');
            
            if ($session_only) return $newses;

            // Return user info
            $stmt = $this->conn->prepare('SELECT user.name,sex,type,avatar,school,region,shop.id FROM `user` LEFT JOIN shop ON shop.uid=user.id WHERE user.id=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('i', $uid);
            $r = $stmt->execute();
            if (!$r) $this->InternalError();
            $stmt->bind_result($name,$sex,$type,$avatar,$school,$region,$shopid);
            if (!$stmt->fetch()) throw new FrException(5, 'No such user');
            $stmt->close();
            $result = array('result'=>1, 'id'=>$uid, 'session'=>$newses, 'sex'=>$sex, 'type'=>$type, 'avatar'=>$avatar, 'school'=>$school, 'region'=>$region);
            if ($result['type'] == 1) $result['shopid'] = $shopid;
            return $result;
        }
        
        function MethodUserModify() {
            // Get original profile
            $this->SessionCheck($_REQUEST);
            $id = (int)$this->GetParam('uid', $_REQUEST);
            $stmt = $this->conn->prepare('SELECT name,password,sex,avatar,school,region FROM `user` WHERE id=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('i', $id);
            $r = $stmt->execute();
            if (!$r) $this->InternalError();
            $stmt->bind_result($name,$password, $sex, $avatar, $school, $region);
            $r = $stmt->fetch();
            if ($r == NULL) throw new FrException(5, 'No such user');
            if ($r == false) $this->InternalError();
            $stmt->close();
            
            // Update profile
            $new_password = $this->GetParam('password', $_REQUEST, false);
            if ($new_password) $password = $new_password;
            if (isset($_REQUEST['sex'])) $sex = (int)$this->GetParam('sex', $_REQUEST);
            $school = $this->GetParam('school', $_REQUEST, false, $school);
            $region = $this->GetParam('region', $_REQUEST, false, $region);
            if (isset($_FILES['avatar']) && $_FILES['avatar']['name']) {
                $avatar = $this->ImageSave($_FILES['avatar']['tmp_name']);
            }
            
            $stmt = $this->conn->prepare('UPDATE `user` SET password=?, sex=?, avatar=?, school=?, region=? WHERE id=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('sisssi', $password, $sex, $avatar, $school, $region, $id);
            $r = $stmt->execute();
            if (!$r) $this->InternalError();
            
            if ($new_password) {
                $this->SessionReset($id);
                $_REQUEST['name'] = $name;
                $session = $this->MethodUserLogin(true);
            }
            else $session = $_REQUEST['session'];
            return array('result'=>1, 'session'=>$session);
        }
        
        function MethodShopCreate() {
            if (!$this->SessionCheck($_REQUEST, true)) throw new FrException(-1, 'Guest cannot create shops');
            $uid = (int)$this->GetParam('uid', $_REQUEST);
            $name = $this->GetParam('name', $_REQUEST);
            $address = $this->GetParam('address', $_REQUEST);
            $introduction = $this->GetParam('introduction', $_REQUEST);
            $photohash = $this->ImageSave($this->GetUploadfile('photo'));
            $phonenum = $this->GetParam('phonenum', $_REQUEST);
            
            $stmt = $this->conn->prepare('INSERT INTO `shop` (uid,name,address,introduction,photo,phonenum) VALUES (?,?,?,?,?,?);');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('issssss', $uid, $name, $address, $introduction, $photohash, $phonenum);
            $r = $stmt->execute();
            if (!$r) $this->InternalError();
            $stmt->close();
            
            return array('result'=>1, 'id'=>$this->conn->insert_id);
        }
        
        function MethodShopModify() {
            if (!$this->SessionCheck($_REQUEST, true)) throw new FrException(-1, 'Guest cannot create shops');
            $id = (int)$this->GetParam('id', $_REQUEST);
            $stmt = $this->conn->prepare('SELECT uid,name,address,introduction,photo,phonenum FROM `shop` WHERE id=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('i', $id);
            $r = $stmt->execute();
            if (!$r) $this->InternalError();
            $stmt->bind_result($uid, $name, $address, $introduction, $photohash, $phonenum);
            $r = $stmt->fetch();
            $stmt->close();
            if ($r == false) $this->InternalError();
            if ($r == NULL) throw new FrException(5, 'No such shop');
            
            $name = $this->GetParam('name', $_REQUEST, false, $name);
            $address = $this->GetParam('address', $_REQUEST, false, $address);
            $introduction = $this->GetParam('introduction', $_REQUEST, false, $introduction);
            $phonenum = $this->GetParam('phonenum', $_REQUEST, false, $phonenum);
            $photo_tmp = $this->GetUploadfile('photo', false);
            if ($photo_tmp) $photohash = $this->ImageSave($photo_tmp);
            
            $stmt = $this->conn->prepare('UPDATE shop SET name=?,address=?,introduction=?,photo=?,phonenum=? WHERE id=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('sssssi', $name, $address, $introduction, $photohash, $phonenum, $id);
            $r = $stmt->execute();
            if (!$r) $this->InternalError();
            $stmt->close();
            return array('result'=>1);
        }
        
        function MethodShopDelete() {
            if (!$this->SessionCheck($_REQUEST, true)) throw new FrException(-1, 'Guest cannot create shops');
            $id = (int)$this->GetParam('id', $_REQUEST);
            $uid = (int)$this->GetParam('uid', $_REQUEST);
            $stmt = $this->conn->prepare('DELETE FROM `shop` WHERE id=? AND uid=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('ii', $id, $uid);
            $r = $stmt->execute();
            if (!$r) $this->InternalError();
            $stmt->close();
            return array('result'=>1);
        }
        
        function MainHandler() {
            $this->Connect();
            $method = $this->GetParam('method', $_POST);
            switch ($method) {
                case 'test':
                    return $this->MethodTest();
                case 'image':
                    return $this->ImageGet();
                case 'user.register':
                    return $this->MethodUserRegister();
                case 'user.login':
                    return $this->MethodUserLogin();
                case 'user.modify':
                    return $this->MethodUserModify();
                //case 'shop.create':
                //    return $this->MethodShopCreate();
                case 'shop.modify':
                    return $this->MethodShopModify();
                //case 'shop.delete':
                //    return $this->MethodShopDelete();
                default:
                    throw new FrException(0x002, 'Parameter `method` is not valid');
            }
        }
        
        function HandleRequest() {
            $debug = true;
            if ($debug) {
                $result = $this->MainHandler();
            } else {
                try {
                    $result = $this->MainHandler();
                }
                catch (FrException $e) {
                    $result = $e->JsonMessage();
                }
            }
            if (!isset($result['response_type'])) {
                // Json response
                header('Content-Type: text/plain; charset=utf8');
                echo json_encode($result);
            }
            else if ($result['response_type'] == 'html') {
                header('Content-Type: text/html; charset=utf8');
                echo $result['data'];
            }
            else {
                // Image response
                if ($result['data'] == false) {
                    header('HTTP/1.0 404 Not Found');
                    echo('Not found');
                }
                else {
                    header('Content-Type: image/jpeg');
                    echo $result['data'];
                }
            }
        }
    }
    
    $m = new FrApp();
    $m->HandleRequest();
?>