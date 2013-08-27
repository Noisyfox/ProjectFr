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
        
        function InternalError($stmt = null) {
            if ($stmt && $stmt->error) throw new FrException(500, $stmt->error);
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
            $this->conn->query(<<<sql
                CREATE TABLE IF NOT EXISTS `user` (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name CHAR(50),
                    password CHAR(40),
                    sex INT,
                    type INT,
                    avatar CHAR(40),
                    school VARCHAR(200),
                    region VARCHAR(200),
                    UNIQUE KEY `name`(`name`)
                ) DEFAULT CHARSET=UTF8;
sql
                );
            $this->conn->query(<<<sql
                CREATE TABLE IF NOT EXISTS `session` (
                    sid CHAR(40) NOT NULL,
                    uid INT,
                    expire DATETIME,
                    type INT,
                    CONSTRAINT FOREIGN KEY(uid) REFERENCES `user`(id)
                );
sql
                );
            $this->conn->query(<<<sql
                CREATE TABLE IF NOT EXISTS `shop`(
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    uid INT, name VARCHAR(200),
                    address VARCHAR(200),
                    introduction TEXT,
                    photo CHAR(40),
                    phonenum VARCHAR(50),
                    CONSTRAINT FOREIGN KEY(uid) REFERENCES `user`(id)
                ) DEFAULT CHARSET=UTF8;
sql
                );
            $this->conn->query(<<<sql
                CREATE TABLE IF NOT EXISTS `food` (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    shopid INT,
                    name VARCHAR(50),
                    introduction TEXT,
                    price DECIMAL(5,2),
                    photo CHAR(40),
                    special BOOL,
                    CONSTRAINT FOREIGN KEY(shopid) REFERENCES `shop`(id)
                ) DEFAULT CHARSET=UTF8;
sql
                );
            $this->conn->query(<<<sql
                CREATE TABLE IF NOT EXISTS `shopmark` (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    shopid INT,
                    uid INT,
                    mark INT,
                    CONSTRAINT FOREIGN KEY(shopid) REFERENCES `shop`(id),
                    CONSTRAINT FOREIGN KEY(uid) REFERENCES `user`(id)
                );
sql
                );
            $this->conn->query(<<<sql
                CREATE TABLE IF NOT EXISTS `foodcmt` (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    uid INT,
                    fid INT,
                    liked BOOL,
                    disliked BOOL,
                    comment TEXT,
                    time DATETIME,
                    CONSTRAINT FOREIGN KEY(uid) REFERENCES `user`(id),
                    CONSTRAINT FOREIGN KEY(fid) references `food`(id) ON DELETE CASCADE
                ) DEFAULT CHARSET=UTF8;
sql
            );
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
            if (!$r) throw new FrException(-1, 'Session Invalid');
            if ($owner_required && $type != 1) throw new FrException(-1, 'Guest cannot operate shops');
        }
        
        function SessionReset($uid) {
            $stmt = $this->conn->prepare('DELETE FROM session WHERE uid=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('i', $uid);
            if (!$stmt->execute()) $this->InternalError();
        }
        
        function ShopOwnerCheck($shopid, $uid) {
            $stmt = $this->conn->prepare('SELECT id FROM `shop` WHERE id=? AND uid=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('ii', $uid, $shopid);
            if (!$stmt->execute()) $this->InternalError();
            if (!$stmt->fetch()) throw new FrException(-1, 'Not shopowner');
            $stmt->close();
        }
        
        function MethodTest() {
            $stmt = $this->conn->prepare('SELECT id FROM foodcmt');
            $stmt->execute();
            $stmt->bind_result($id);
            $resp = array();
            while ($stmt->fetch()) {
                $resp[] = $id;
            }
            return array('result'=>$resp);
        
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
                if (!$stmt->execute()) $this->InternalError();
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
                if (!$stmt->execute()) $this->InternalError();
                $stmt->bind_result($uid, $type);
                if (!$stmt->fetch()) throw new FrException(-1, 'Login fail 1');
                $stmt->close();
                
                // Update session
                $random = 'SESSION'.$uid.time().mt_rand().mt_rand().mt_rand();
                $newses = hash('sha1', $random);
                $stmt = $this->conn->prepare('INSERT INTO session (uid,type,sid,expire) VALUES (?,?,?,DATE_ADD(NOW(), INTERVAL 30 DAY));');
                if (!$stmt) $this->InternalError();
                $stmt->bind_param('iis', $uid, $type, $newses);
                if (!$stmt->execute()) $this->InternalError();
                $stmt->close();
                }
            else if (isset($_REQUEST['uid']) && isset($_REQUEST['session'])) {
                $uid = (int)$_REQUEST['uid'];
                $newses = $_REQUEST['session'];
                $stmt = $this->conn->prepare('SELECT uid FROM session WHERE uid=? AND sid=? AND expire>NOW()');
                if (!$stmt) $this->InternalError();
                $stmt->bind_param('is', $uid, $_REQUEST['session']);
                if (!$stmt->execute()) $this->InternalError();
                if (!$stmt->fetch()) throw new FrException(-1, 'Login fail 2');
                $stmt->close();
                
                // Refresh session
                $stmt = $this->conn->prepare('UPDATE session SET expire=DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE uid=? AND sid=?');
                if (!$stmt) $this->InternalError();
                $stmt->bind_param('is', $uid, $newses);
                if (!$stmt->execute()) $this->InternalError();
            }
            else throw new FrException(1, 'Wrong calling parameters');
            
            if ($session_only) return $newses;

            // Return user info
            $stmt = $this->conn->prepare('SELECT user.name,sex,type,avatar,school,region,shop.id FROM `user` LEFT JOIN shop ON shop.uid=user.id WHERE user.id=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('i', $uid);
            if (!$stmt->execute()) $this->InternalError();
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
            if (!$stmt->execute()) $this->InternalError();
            $stmt->bind_result($name,$password, $sex, $avatar, $school, $region);
            if (!$stmt->fetch()) throw new FrException(5, 'No such user');
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
            if (!$stmt->execute()) $this->InternalError();
            
            if ($new_password) {
                $this->SessionReset($id);
                $_REQUEST['name'] = $name;
                $session = $this->MethodUserLogin(true);
            }
            else $session = $_REQUEST['session'];
            return array('result'=>1, 'session'=>$session);
        }
        
        function MethodShopCreate() {
            $this->SessionCheck($_REQUEST, true);
            $uid = (int)$this->GetParam('uid', $_REQUEST);
            $name = $this->GetParam('name', $_REQUEST);
            $address = $this->GetParam('address', $_REQUEST);
            $introduction = $this->GetParam('introduction', $_REQUEST);
            $photohash = $this->ImageSave($this->GetUploadfile('photo'));
            $phonenum = $this->GetParam('phonenum', $_REQUEST);
            
            $stmt = $this->conn->prepare('INSERT INTO `shop` (uid,name,address,introduction,photo,phonenum) VALUES (?,?,?,?,?,?);');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('issssss', $uid, $name, $address, $introduction, $photohash, $phonenum);
            if (!$stmt->execute()) $this->InternalError();
            $stmt->close();
            
            return array('result'=>1, 'id'=>$this->conn->insert_id);
        }
        
        function MethodShopModify() {
            $this->SessionCheck($_REQUEST, true);
            $id = (int)$this->GetParam('id', $_REQUEST);
            $stmt = $this->conn->prepare('SELECT uid,name,address,introduction,photo,phonenum FROM `shop` WHERE id=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('i', $id);
            if (!$stmt->execute()) $this->InternalError();
            $stmt->bind_result($uid, $name, $address, $introduction, $photohash, $phonenum);
            if (!$stmt->fetch()) throw new FrException(5, 'No such shop');
            $stmt->close();
            
            $name = $this->GetParam('name', $_REQUEST, false, $name);
            $address = $this->GetParam('address', $_REQUEST, false, $address);
            $introduction = $this->GetParam('introduction', $_REQUEST, false, $introduction);
            $phonenum = $this->GetParam('phonenum', $_REQUEST, false, $phonenum);
            $photo_tmp = $this->GetUploadfile('photo', false);
            if ($photo_tmp) $photohash = $this->ImageSave($photo_tmp);
            
            $stmt = $this->conn->prepare('UPDATE shop SET name=?,address=?,introduction=?,photo=?,phonenum=? WHERE id=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('sssssi', $name, $address, $introduction, $photohash, $phonenum, $id);
            if (!$stmt->execute()) $this->InternalError();
            $stmt->close();
            return array('result'=>1);
        }
        
        function MethodShopDelete() {
            $this->SessionCheck($_REQUEST, true);
            $id = (int)$this->GetParam('id', $_REQUEST);
            $uid = (int)$this->GetParam('uid', $_REQUEST);
            $stmt = $this->conn->prepare('DELETE FROM `shop` WHERE id=? AND uid=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('ii', $id, $uid);
            if (!$stmt->execute()) $this->InternalError();
            $stmt->close();
            return array('result'=>1);
        }
        
        function MethodFoodCreate() {
            $this->SessionCheck($_REQUEST, true);
            $uid = (int)$this->GetParam('uid', $_REQUEST);
            $shopid = (int)$this->GetParam('id', $_REQUEST);
            $this->ShopOwnerCheck($shopid, $uid);
            $name = $this->GetParam('name', $_REQUEST);
            $introduction = $this->GetParam('introduction', $_REQUEST);
            $price = (float)$this->GetParam('price', $_REQUEST);
            $photohash = $this->ImageSave($this->GetUploadfile('photo'));
            $special = (int)$this->GetParam('special', $_REQUEST);
            
            $stmt = $this->conn->prepare('INSERT INTO `food` (shopid,name,introduction,price,photo,special) VALUES (?,?,?,?,?,?);');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('issdsi', $shopid, $name, $introduction, $price, $photohash, $special);
            if (!$stmt->execute()) $this->InternalError();
            $stmt->close();
            
            $fid = $this->conn->insert_id;
            return array('result'=>1, 'id'=>$fid);
        }
        
        function MethodFoodModify() {
            $this->SessionCheck($_REQUEST, true);
            $uid = (int)$this->GetParam('uid', $_REQUEST);
            $fid = (int)$this->GetParam('id', $_REQUEST);
            $stmt = $this->conn->prepare('SELECT shopid,name,introduction,price,photo,special FROM `food` WHERE id=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('i', $fid);
            if (!$stmt->execute()) $this->InternalError();
            $stmt->bind_result($shopid, $name, $introduction, $price, $photohash, $special);
            if (!$stmt->fetch()) throw new FrException(5, 'No such food');
            $stmt->close();
            
            $this->ShopOwnerCheck($shopid, $uid);
            $name = $this->GetParam('name', $_REQUEST, false, $name);
            $introduction = $this->GetParam('introduction', $_REQUEST, false, $introduction);
            $price_t = $this->GetParam('price', $_REQUEST, false);
            if ($price_t) $price = (float)$price_t;
            $special_t = $this->GetParam('special', $_REQUEST, false);
            if ($special_t != false) $special = (int)$special_t;
            $photo_t = $this->GetUploadfile('photo', false);
            if ($photo_t) $photohash = $this->ImageSave($photo_t);
            
            $stmt = $this->conn->prepare('UPDATE `food` SET shopid=?,name=?,introduction=?,price=?,photo=?,special=? WHERE id=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('issdsii', $shopid, $name, $introduction, $price, $photohash, $special, $fid);
            if (!$stmt->execute()) $this->InternalError();
            return array('result'=>1);
        }
        
        function MethodFoodComment() {
            $this->SessionCheck($_REQUEST);
            $fid = (int)$this->GetParam('id', $_REQUEST);
            $uid = (int)$this->GetParam('uid', $_REQUEST);
            $like = (int)$this->GetParam('like', $_REQUEST);
            $comment = $this->GetParam('comment', $_REQUEST);
            $x = strlen($comment);
            if ($like != 1 && $like != 0) throw new FrException(1, 'Illegal `like` parameter');
            $liked = null;
            $disliked = null;
            if ($like == 1)
                $liked = true;
            else
                $disliked = true;
            if (strlen($comment) == 0) $comment = null;
            
            $stmt = $this->conn->prepare('INSERT INTO `foodcmt` (uid,fid,liked,disliked,comment,time) VALUES (?,?,?,?,?,NOW());');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('iiiis', $uid, $fid, $liked, $disliked, $comment);
            if (!$stmt->execute()) {
                // Foreign key constraint fails(no such food)
                if ($stmt->errno == 1452)
                    throw new FrException(5, 'No such food');
                else
                    $this->InternalError();
            }
            $stmt->close();
            
            $stmt = $this->conn->prepare('SELECT COUNT(liked), COUNT(disliked), COUNT(comment) FROM `foodcmt` WHERE fid=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('i', $fid);
            if (!$stmt->execute()) $this->InternalError();
            $stmt->bind_result($like_n, $dislike_n, $comment_n);
            if (!$stmt->fetch()) $this->InternalError();
            $stmt->close();
            
            return array('result'=>1, 'likes'=>$like_n, 'dislikes'=>$dislike_n, 'comments'=>$comment_n);
        }
        
        function MethodFoodViewcomment() {
            $fid = $this->GetParam('id', $_REQUEST);
            $stmt = $this->conn->prepare('SELECT uid,user.name,user.avatar,liked,disliked,comment,UNIX_TIMESTAMP(time) AS time FROM `foodcmt` JOIN `user` ON user.id=foodcmt.uid WHERE foodcmt.fid=? ORDER BY time DESC');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('i', $fid);
            if (!$stmt->execute()) $this->InternalError($stmt);
            $stmt->bind_result($uid, $name, $avatar, $liked, $disliked, $comment, $time);
            $comments = array();
            while ($stmt->fetch()) {
                if (!($liked ^ $disliked)) continue;
                if ($liked)
                    $like = true;
                else
                    $like = false;
                $comments[] = array('user'=>array('id'=>$uid, 'name'=>$name, 'avatar'=>$avatar), 'like'=>$like, 'comment'=>$comment, 'time'=>$time);
            }
            $stmt->close();
            return array('result'=>1, 'comments'=>$comments);
        }
        
        function MethodShopMark() {
            $this->SessionCheck($_REQUEST);
            $shopid = (int)$this->GetParam('id', $_REQUEST);
            $uid = (int)$this->GetParam('uid', $_REQUEST);
            $mark = (int)$this->GetParam('mark', $_REQUEST);
            if ($mark < 0 || $mark > 10) throw new FrException(-1, 'Mark out of range');
            
            $stmt = $this->conn->prepare('DELETE FROM `shopmark` WHERE shopid=? AND uid=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('ii', $shopid, $uid);
            if (!$stmt->execute()) $this->InternalError();
            $stmt->close();
            
            $stmt = $this->conn->prepare('INSERT INTO `shopmark` (shopid,uid,mark) VALUES (?,?,?);');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('iii', $shopid, $uid, $mark);
            if (!$stmt->execute()) {
                // Foreign key constraint fails(no such shop)
                if ($stmt->errno == 1452)
                    throw new FrException(5, 'No such shop');
                else
                    $this->InternalError();
            }
            $stmt->close();
            
            $stmt = $this->conn->prepare('SELECT AVG(mark) FROM `shopmark` WHERE shopid=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('i', $shopid);
            if (!$stmt->execute()) $this->InternalError();
            $stmt->bind_result($average);
            if (!$stmt->fetch()) $this->InternalError();
            $stmt->close();
            
            return array('result'=>1, 'newmark'=>$average);
        }
        
        function MethodShopDetail() {
            $this->SessionCheck($_REQUEST);
            $uid = $this->GetParam('uid', $_REQUEST);
            $shopid = $this->GetParam('id', $_REQUEST);
            
            // Fetch general information
            $stmt = $this->conn->prepare('SELECT name,address,introduction,photo,phonenum,shopavg.mark,shopavg.popularity FROM `shop` LEFT JOIN (SELECT shopid,COUNT(*) AS popularity, AVG(mark) AS mark FROM `shopmark` GROUP BY shopid) shopavg ON shopavg.shopid=shop.id WHERE shop.id=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('i', $shopid);
            if (!$stmt->execute()) $this->InternalError();
            $stmt->bind_result($name, $address, $introduction, $photo, $phonenum, $mark, $popularity);
            if (!$stmt->fetch()) throw new FrException(5, 'No such shop');
            $stmt->close();
            
            // Fetch current user's mark
            $stmt = $this->conn->prepare('SELECT mark FROM `shopmark` WHERE shopid=? AND uid=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('ii', $shopid, $uid);
            if (!$stmt->execute()) $this->InternalError();
            $stmt->bind_result($user_mark);
            if (!$stmt->fetch()) $user_mark = -1;
            $stmt->close();
            
            
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
                case 'shop.detail':
                    return $this->MethodShopDetail();
                case 'shop.mark':
                    return $this->MethodShopMark();
                case 'food.create':
                    return $this->MethodFoodCreate();
                case 'food.modify':
                    return $this->MethodFoodModify();
                case 'food.comment':
                    return $this->MethodFoodComment();
                case 'food.viewcomment':
                    return $this->MethodFoodViewcomment();
                default:
                    throw new FrException(0x002, 'Parameter `method` is not valid');
            }
        }
        
        function HandleRequest() {
            $debug = 1;
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