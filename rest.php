<?php
    include_once('config.php');
    
    class FrException extends Exception {
        function __construct($code, $message) {
            parent::__construct($message, $code);
        }
        
        function JsonMessage() {
            $r = $this->getCode();
            $result = $r;
            if ($r >= 0) {
                $result = 0;
            }
            return array(
                'result'=>$result,
                'error_code'=>$this->getCode(),
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
            if ($stmt && $stmt->error) 
                throw new FrException(500, $stmt->error);
            if ($this->conn && $this->conn->error) 
                throw new FrException(500, $this->conn->error);
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
        
        function DatabaseReset() {
            if (!$this->conn->query('DROP TABLE `bookmark_food`;')) $this->InternalError();
            if (!$this->conn->query('DROP TABLE `bookmark_shop`;')) $this->InternalError();
            if (!$this->conn->query('DROP TABLE `foodcmt`;')) $this->InternalError();
            if (!$this->conn->query('DROP TABLE `shopmark`;')) $this->InternalError();
            if (!$this->conn->query('DROP TABLE `food`;')) $this->InternalError();
            if (!$this->conn->query('DROP TABLE `shop`;')) $this->InternalError();
            if (!$this->conn->query('DROP TABLE `session`;')) $this->InternalError();
            if (!$this->conn->query('DROP TABLE `user`;')) $this->InternalError();
            return array('result'=>1);
        }
        
        function DatabaseExecute() {
            $sqls = explode("\n", $this->GetParam('sql', $_REQUEST));
            foreach($sqls as &$sql) {
                if (!$this->conn->query($sql)) $this->InternalError();
            }
            if (!$this->conn->query($sql)) $this->InternalError();
            return array('result'=>1);
        }
        
        function DatabaseUploadImages() {
            $images = explode(',',$this->GetParam('images', $_REQUEST));
            foreach($images as &$img) {
                $this->ImageSave($this->GetUploadfile($img));
            }
            return array('result'=>1);
        }
        
        function InitTable() {
            // $this->conn->query('CREATE TABLE IF NOT EXISTS `image` (`uid` CHAR(40) NOT NULL, `img` LONGBLOB, UNIQUE KEY `uid` (`uid`)) COLLATE utf8_general_ci;');
            if (!$this->conn->query('
                CREATE TABLE IF NOT EXISTS `user` (
                    uid INT AUTO_INCREMENT PRIMARY KEY,
                    name CHAR(50),
                    password CHAR(40),
                    sex INT,
                    type INT,
                    avatar CHAR(40),
                    school VARCHAR(200),
                    region VARCHAR(200),
                    UNIQUE KEY `name`(`name`)
                ) DEFAULT CHARSET=UTF8;')) $this->InternalError();
            if (!$this->conn->query('
                CREATE TABLE IF NOT EXISTS `session` (
                    sid CHAR(40) NOT NULL,
                    uid INT,
                    expire DATETIME,
                    type INT,
                    CONSTRAINT FOREIGN KEY(uid) REFERENCES `user`(uid)
                );')) $this->InternalError();
            if (!$this->conn->query('
                CREATE TABLE IF NOT EXISTS `shop`(
                    sid INT PRIMARY KEY AUTO_INCREMENT,
                    uid INT, name VARCHAR(200),
                    address VARCHAR(200),
                    introduction TEXT,
                    photo CHAR(40),
                    phonenum VARCHAR(50),
                    time DATETIME,
                    last_offer DATETIME,
                    CONSTRAINT FOREIGN KEY(uid) REFERENCES `user`(uid)
                ) DEFAULT CHARSET=UTF8;')) $this->InternalError();
            if (!$this->conn->query('
                CREATE TABLE IF NOT EXISTS `food` (
                    fid INT PRIMARY KEY AUTO_INCREMENT,
                    sid INT,
                    name VARCHAR(50),
                    introduction TEXT,
                    price DECIMAL(5,2),
                    price_delta DECIMAL(5,2),
                    photo CHAR(40),
                    special BOOL,
                    CONSTRAINT FOREIGN KEY(sid) REFERENCES `shop`(sid)
                ) DEFAULT CHARSET=UTF8;')) $this->InternalError();
            if (!$this->conn->query('
                CREATE TABLE IF NOT EXISTS `shopmark` (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    sid INT,
                    uid INT,
                    mark INT,
                    CONSTRAINT FOREIGN KEY(sid) REFERENCES `shop`(sid),
                    CONSTRAINT FOREIGN KEY(uid) REFERENCES `user`(uid)
                );')) $this->InternalError();
            if (!$this->conn->query('
                CREATE TABLE IF NOT EXISTS `foodcmt` (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    uid INT,
                    fid INT,
                    liked BOOL,
                    disliked BOOL,
                    comment TEXT,
                    time DATETIME,
                    CONSTRAINT FOREIGN KEY(uid) REFERENCES `user`(uid),
                    CONSTRAINT FOREIGN KEY(fid) references `food`(fid) ON DELETE CASCADE
                ) DEFAULT CHARSET=UTF8;')) $this->InternalError();
            if (!$this->conn->query('
                CREATE TABLE IF NOT EXISTS bookmark_shop (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    uid INT,
                    sid INT,
                    CONSTRAINT FOREIGN KEY(uid) REFERENCES `user`(uid),
                    CONSTRAINT FOREIGN KEY(sid) REFERENCES `shop`(sid)
                );')) $this->InternalError();
            if (!$this->conn->query('
                CREATE TABLE IF NOT EXISTS bookmark_food (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    uid INT,
                    fid INT,
                    CONSTRAINT FOREIGN KEY(uid) REFERENCES `user`(uid),
                    CONSTRAINT FOREIGN KEY(fid) REFERENCES `food`(fid) ON DELETE CASCADE
                );')) $this->InternalError();
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
            if (!preg_match($pattern, $id)) 
                throw new FrException(6, 'Illegal id');
            $contents = @file_get_contents(IMAGE_PATH.'/'.$id);
            return array('response_type'=>'image','data'=>$contents);
        }
        
        function SessionCheck(&$data, $owner_required = false) {
            $id = (int)$this->GetParam('uid', $data);
            $session = $this->GetParam('session', $data);
            $stmt = $this->conn->prepare('
                SELECT type FROM session WHERE uid=? AND sid=? AND expire>NOW()');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('is', $id, $session);
            $r = $stmt->execute();
            if (!$r) $this->InternalError();
            $stmt->bind_result($type);
            $r = $stmt->fetch();
            $stmt->close();
            if (!$r) throw new FrException(21, 'Session Invalid');
            if ($owner_required && $type != 1) 
                throw new FrException(22, 'Guest cannot operate shops');
        }
        
        function SessionReset($uid) {
            $stmt = $this->conn->prepare('DELETE FROM session WHERE uid=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('i', $uid);
            if (!$stmt->execute()) $this->InternalError();
        }
        
        function ShopOwnerCheck($sid, $uid) {
            $stmt = $this->conn->prepare('SELECT sid FROM `shop` WHERE sid=? AND uid=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('ii', $sid, $uid);
            if (!$stmt->execute()) $this->InternalError();
            if (!$stmt->fetch()) throw new FrException(22, 'Not shopowner');
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
            return array(
                'result'=>1, 
                'name'=>$_FILES['img']['name'], 
                'size'=>$_FILES['img']['size'], 
                'sha1'=>$hash
            );
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
            
            if (strlen($name) < 4) throw new FrException(1, 'Name too short');
            
            $stmt = $this->conn->prepare('
                INSERT INTO `user` 
                    (name, password, sex, type, avatar, school, region)
                VALUES (?, ?, ?, ?, ?, ?, ?);');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param(
                'ssiisss', 
                $name, $password, $sex, $type, $avatar_hash, $school, $region
            );
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
                $stmt = $this->conn->prepare('
                    INSERT INTO `shop` 
                        (uid, name, address, introduction, photo, phonenum, time)
                    VALUES (?, ?, ?, ?, ?, ?, NOW());');
                if (!$stmt) $this->InternalError();
                $stmt->bind_param(
                    'isssss', 
                    $uid, $blank, $blank, $blank, $blank, $blank);
                if (!$stmt->execute()) $this->InternalError();
                $stmt->close();
            }
            return array(
                'result'=>1, 
                'uid'=>$uid, 
                'session'=>$this->MethodUserLogin(true)
            );
        }
        
        function MethodUserLogin($session_only = false) {
            // Authenticate
            $uid = 0;
            if (isset($_REQUEST['name']) && isset($_REQUEST['password'])) {
                $stmt = $this->conn->prepare('
                    SELECT uid, type FROM `user` WHERE name=? AND password=?');
                if (!$stmt) $this->InternalError();
                $stmt->bind_param('ss', $_REQUEST['name'], $_REQUEST['password']);
                if (!$stmt->execute()) $this->InternalError();
                $stmt->bind_result($uid, $type);
                if (!$stmt->fetch()) 
                    throw new FrException(-1, 'Login fail 1');
                $stmt->close();
                
                // Update session
                $random = 'SESSION'.$uid.time().mt_rand().mt_rand().mt_rand();
                $newses = hash('sha1', $random);
                $stmt = $this->conn->prepare('
                    INSERT INTO session 
                    (uid, type, sid, expire) VALUES 
                    (?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY));');
                if (!$stmt) $this->InternalError();
                $stmt->bind_param('iis', $uid, $type, $newses);
                if (!$stmt->execute()) $this->InternalError();
                $stmt->close();
                }
            else if (isset($_REQUEST['uid']) && isset($_REQUEST['session'])) {
                $uid = (int)$_REQUEST['uid'];
                $newses = $_REQUEST['session'];
                $stmt = $this->conn->prepare('
                    SELECT uid FROM session 
                    WHERE uid=? AND sid=? AND expire>NOW()');
                if (!$stmt) $this->InternalError();
                $stmt->bind_param('is', $uid, $_REQUEST['session']);
                if (!$stmt->execute()) $this->InternalError();
                if (!$stmt->fetch()) 
                        throw new FrException(-1, 'Login fail 2');
                $stmt->close();
                
                // Refresh session
                $stmt = $this->conn->prepare('
                    UPDATE session 
                    SET expire=DATE_ADD(NOW(), INTERVAL 30 DAY)
                    WHERE uid=? AND sid=?;');
                if (!$stmt) $this->InternalError();
                $stmt->bind_param('is', $uid, $newses);
                if (!$stmt->execute()) $this->InternalError();
            }
            else throw new FrException(1, 'Wrong calling parameters');
            
            if ($session_only) return $newses;

            // Return user info
            $stmt = $this->conn->prepare('
                SELECT user.name, sex, type, avatar, school, region, shop.sid 
                FROM `user` 
                LEFT JOIN shop ON shop.uid=user.uid 
                WHERE user.uid=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('i', $uid);
            if (!$stmt->execute()) $this->InternalError();
            $stmt->bind_result($name,$sex,$type,$avatar,$school,$region,$sid);
            if (!$stmt->fetch())
                throw new FrException(5, 'No such user');
            $stmt->close();
            $result = array(
                'result'=>1, 
                'uid'=>$uid,
                'name'=>$name,
                'session'=>$newses, 
                'sex'=>$sex, 
                'type'=>$type, 
                'avatar'=>$avatar, 
                'school'=>$school, 
                'region'=>$region
            );
            if ($result['type'] == 1) $result['sid'] = $sid;
            return $result;
        }
        
        function MethodUserModify() {
            // Get original profile
            $this->SessionCheck($_REQUEST);
            $uid = (int)$this->GetParam('uid', $_REQUEST);
            $stmt = $this->conn->prepare('
                SELECT name, password, sex, avatar, school, region 
                FROM `user` 
                WHERE uid=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('i', $uid);
            if (!$stmt->execute()) $this->InternalError();
            $stmt->bind_result($name, $password, $sex, $avatar, $school, $region);
            if (!$stmt->fetch()) 
                throw new FrException(5, 'No such user');
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
            
            $stmt = $this->conn->prepare('
                UPDATE `user` 
                SET password=?, sex=?, avatar=?, school=?, region=? 
                WHERE uid=?;');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param(
                'sisssi', 
                $password, $sex, $avatar, $school, $region, $uid
            );
            if (!$stmt->execute()) $this->InternalError();
            
            if ($new_password) {
                $this->SessionReset($uid);
                $_REQUEST['name'] = $name;
                $session = $this->MethodUserLogin(true);
            }
            else $session = $_REQUEST['session'];
            return array('result'=>1, 'session'=>$session);
        }
        
        function MethodShopCreate() {
            throw new FrException(1, 'Method disabled');
        
            $this->SessionCheck($_REQUEST, true);
            $uid = (int)$this->GetParam('uid', $_REQUEST);
            $name = $this->GetParam('name', $_REQUEST);
            $address = $this->GetParam('address', $_REQUEST);
            $introduction = $this->GetParam('introduction', $_REQUEST);
            $photohash = $this->ImageSave($this->GetUploadfile('photo'));
            $phonenum = $this->GetParam('phonenum', $_REQUEST);
            
            $stmt = $this->conn->prepare('
                INSERT INTO `shop` 
                    (uid, name, address, introduction, photo, phonenum, time, last_offer)
                VALUES (?, ?, ?, ?, ?, ?, 0, 0);');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param(
                'issssss', 
                $uid, $name, $address, $introduction, $photohash, $phonenum
            );
            if (!$stmt->execute()) $this->InternalError();
            $stmt->close();
            
            return array('result'=>1, 'sid'=>$this->conn->insert_id);
        }
        
        function MethodShopModify() {
            $this->SessionCheck($_REQUEST, true);
            $sid = (int)$this->GetParam('sid', $_REQUEST);
            $uid = (int)$this->GetParam('uid', $_REQUEST);
            $this->ShopOwnerCheck($sid, $uid);
            
            $stmt = $this->conn->prepare('
                SELECT uid, name, address, introduction, photo, phonenum 
                FROM `shop` 
                WHERE sid=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('i', $sid);
            if (!$stmt->execute()) $this->InternalError();
            $stmt->bind_result(
                $uid, $name, $address, $introduction, $photohash, $phonenum
            );
            if (!$stmt->fetch()) 
                throw new FrException(5, 'No such shop');
            $stmt->close();
            
            $name = $this->GetParam('name', $_REQUEST, false, $name);
            $address = $this->GetParam('address', $_REQUEST, false, $address);
            $introduction = $this->GetParam('introduction', $_REQUEST, false, $introduction);
            $phonenum = $this->GetParam('phonenum', $_REQUEST, false, $phonenum);
            $photo_tmp = $this->GetUploadfile('photo', false);
            if ($photo_tmp) $photohash = $this->ImageSave($photo_tmp);
            
            $stmt = $this->conn->prepare('
                UPDATE shop 
                SET name=?, address=?, introduction=?, photo=?, phonenum=?, time=NOW() 
                WHERE sid=?;');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param(
                'sssssi', 
                $name, $address, $introduction, $photohash, $phonenum, $sid
            );
            if (!$stmt->execute()) $this->InternalError();
            $stmt->close();
            return array('result'=>1);
        }
        
        function MethodShopDelete() {
            throw new FrException(1, 'Method disabled');
        
            $this->SessionCheck($_REQUEST, true);
            $sid = (int)$this->GetParam('id', $_REQUEST);
            $uid = (int)$this->GetParam('uid', $_REQUEST);
            $this->ShopOwnerCheck($sid, $uid);
            
            $stmt = $this->conn->prepare('
                DELETE FROM `shop` 
                WHERE sid=? AND uid=?;');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('ii', $sid, $uid);
            if (!$stmt->execute()) $this->InternalError();
            $stmt->close();
            return array('result'=>1);
        }
        
        function MethodShopMark() {
            $this->SessionCheck($_REQUEST);
            $sid = (int)$this->GetParam('sid', $_REQUEST);
            $uid = (int)$this->GetParam('uid', $_REQUEST);
            $mark = (int)$this->GetParam('mark', $_REQUEST);
            if ($mark < 0 || $mark > 10) throw new FrException(-1, 'Mark out of range');
            
            $stmt = $this->conn->prepare('
                DELETE FROM `shopmark` 
                WHERE sid=? AND uid=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('ii', $sid, $uid);
            if (!$stmt->execute()) $this->InternalError();
            $stmt->close();
            
            $stmt = $this->conn->prepare('
                INSERT INTO `shopmark` (sid, uid, mark) VALUES (?, ?, ?);');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('iii', $sid, $uid, $mark);
            if (!$stmt->execute()) {
                // Foreign key constraint fails(no such shop)
                if ($stmt->errno == 1452)
                    throw new FrException(5, 'No such shop');
                else
                    $this->InternalError();
            }
            $stmt->close();
            
            $stmt = $this->conn->prepare('
                SELECT AVG(mark) FROM `shopmark` WHERE sid=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('i', $sid);
            if (!$stmt->execute()) $this->InternalError();
            $stmt->bind_result($average);
            if (!$stmt->fetch()) $this->InternalError();
            $stmt->close();
            
            return array('result'=>1, 'newmark'=>$average);
        }
        
        function MethodShopList() {
            $this->SessionCheck($_REQUEST);
            $uid = $this->GetParam('uid', $_REQUEST);
            $region = $this->GetParam('region', $_REQUEST, false, '');
            $school = $this->GetParam('school', $_REQUEST, false, '');
            $order = $this->GetParam('order', $_REQUEST, false, 'newest');
            if ($order == 'newest') {
                $orderkey = 'shop.time';
            } else if ($order == 'activity') {
                $orderkey = 'shop.last_offer';
            } else if ($order == 'rank') {
                $orderkey = 'sorting_mark';
            } else throw new FrException(1, 'Unknown sorting');
            echo "orderkey: $orderkey\n";
            
            $region = '%'.$region.'%';
            $school = '%'.$school.'%';
            
            $stmt = $this->conn->prepare('
                SELECT shop.sid, shop.name, photo,
                    IFNULL(shopscore.avgmark, -1) AS mark,
                    IFNULL(shopscore.popularity, 0) AS popularity,
                    NOT ISNULL(bookmark.id) AS bookmarked,
                    user.region, user.school,
                    IFNULL(shopscore.avgmark, 5) as sorting_mark
                FROM shop
                LEFT JOIN (
                    SELECT sid, AVG(mark) AS avgmark, COUNT(*) AS popularity
                    FROM shopmark GROUP BY sid
                    ) shopscore ON shopscore.sid=shop.sid
                LEFT JOIN (
                    SELECT id, sid FROM bookmark_shop WHERE uid=?
                    ) bookmark ON bookmark.sid=shop.sid
                JOIN user ON user.uid=shop.uid
                WHERE user.region LIKE ? AND user.school LIKE ?
                ORDER BY '.$orderkey.' DESC');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('iss', $uid, $region, $school);
            if (!$stmt->execute()) $this->InternalError($stmt);
            
            $stmt->bind_result($sid, $name, $photo, $mark, $popularity, $bookmarked, $region, $school, $sorting_mark);
            $result = array();
            while ($stmt->fetch()) {
                $result[] = array(
                    'sid'=>$sid,
                    'name'=>$name,
                    'bookmarked'=>(bool)$bookmarked,
                    'photo'=>$photo,
                    'mark'=>(float)$mark
                );
            }
            return array('result'=>1, 'shops'=>$result);
        }
        
        function MethodShopDetail() {
            $this->SessionCheck($_REQUEST);
            $uid = (int)$this->GetParam('uid', $_REQUEST);
            $sid = (int)$this->GetParam('sid', $_REQUEST);
            
            // Fetch general information
            $stmt = $this->conn->prepare('
                SELECT name, address, introduction, photo, phonenum, 
                    IFNULL(shopavg.mark, 0), IFNULL(shopavg.popularity, 0) 
                FROM `shop` 
                LEFT JOIN (
                    SELECT sid, COUNT(*) AS popularity, AVG(mark) AS mark 
                    FROM `shopmark` 
                    WHERE sid=? 
                    GROUP BY sid
                ) shopavg ON shopavg.sid=shop.sid 
                WHERE shop.sid=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('ii', $sid, $sid);
            if (!$stmt->execute()) $this->InternalError($stmt);
            $stmt->bind_result(
                $shop_name, $address, $shop_introduction, $shop_photo, 
                $phonenum, $mark, $popularity
            );
            if (!$stmt->fetch())
                throw new FrException(5, 'No such shop');
            $stmt->close();
            
            // Fetch current user's mark
            $stmt = $this->conn->prepare('
                SELECT mark FROM `shopmark` WHERE sid=? AND uid=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('ii', $sid, $uid);
            if (!$stmt->execute()) $this->InternalError($stmt);
            $stmt->bind_result($user_mark);
            if (!$stmt->fetch()) $user_mark = -1;
            $stmt->close();
            
            // Fetch foods
            $stmt = $this->conn->prepare('
                SELECT food.fid,food.sid,name,introduction,price,price_delta,special,photo,
                    IFNULL(food_stat.likes,0),IFNULL(food_stat.dislikes,0),IFNULL(food_stat.comments,0), 
                    NOT ISNULL(bookmark.fid) AS bookmarked 
                FROM `food` 
                LEFT JOIN (
                    SELECT fid, COUNT(liked) AS likes, 
                        COUNT(disliked) AS dislikes, COUNT(comment) AS comments 
                    FROM `foodcmt` 
                    GROUP BY fid
                ) food_stat ON food_stat.fid=food.fid 
                LEFT JOIN (SELECT fid FROM `bookmark_food` WHERE uid=?) bookmark 
                ON bookmark.fid=food.fid 
                WHERE food.sid=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('ii', $uid, $sid);
            if (!$stmt->execute()) $this->InternalError($stmt);
            $result = array();
            $stmt->bind_result(
                $fid, $sid, $name, $intro_f, $price, $price_delta, $special, $photo, 
                $likes, $dislikes, $comments, $bookmarked);
            while ($stmt->fetch()) {
                $result[] = array(
                    'fid'=>$fid,
                    'sid'=>$sid,
                    'name'=>$name,
                    'introduction'=>$intro_f,
                    'price'=>(float)$price,
                    'price_delta'=>(float)$price_delta,
                    'special'=>(bool)$special, 
                    'photo'=>$photo, 
                    'likes'=>$likes, 
                    'dislikes'=>$dislikes, 
                    'comments'=>$comments, 
                    'bookmarked'=>(bool)$bookmarked
                );
            }
            
            return array(
                'result'=>1, 
                'name'=>$shop_name, 
                'address'=>$address, 
                'introduction'=>$shop_introduction, 
                'photo'=>$shop_photo, 
                'phonenum'=>$phonenum, 
                'mark'=>(float)$mark, 
                'popularity'=>$popularity, 
                'user_mark'=>$user_mark, 
                'foods'=>$result
            );
        }
        
        function MethodFoodCreate() {
            $this->SessionCheck($_REQUEST, true);
            $uid = (int)$this->GetParam('uid', $_REQUEST);
            $sid = (int)$this->GetParam('sid', $_REQUEST);
            $this->ShopOwnerCheck($sid, $uid);
            $name = $this->GetParam('name', $_REQUEST);
            $introduction = $this->GetParam('introduction', $_REQUEST);
            $price = (float)$this->GetParam('price', $_REQUEST);
            $photohash = $this->ImageSave($this->GetUploadfile('photo'));
            $special = (int)$this->GetParam('special', $_REQUEST);
            
            $stmt = $this->conn->prepare('
                INSERT INTO `food` 
                    (sid, name, introduction, price, price_delta, photo, special) 
                VALUES (?, ?, ?, ?, 0, ?, ?);');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param(
                'issdsi', 
                $sid, $name, $introduction, $price, $photohash, $special
            );
            if (!$stmt->execute()) $this->InternalError();
            $stmt->close();
            
            $fid = $this->conn->insert_id;
            return array('result'=>1, 'fid'=>$fid);
        }
        
        function MethodFoodModify() {
            $this->SessionCheck($_REQUEST, true);
            $uid = (int)$this->GetParam('uid', $_REQUEST);
            $fid = (int)$this->GetParam('fid', $_REQUEST);
            $stmt = $this->conn->prepare('
                SELECT sid, name, introduction, price, photo, special 
                FROM `food` 
                WHERE fid=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('i', $fid);
            if (!$stmt->execute()) $this->InternalError();
            $stmt->bind_result(
                $sid, $name, $introduction, $price, $photohash, $special
            );
            if (!$stmt->fetch()) 
                throw new FrException(5, 'No such food');
            $stmt->close();
            
            $this->ShopOwnerCheck($sid, $uid);
            
            $name = $this->GetParam('name', $_REQUEST, false, $name);
            $introduction = $this->GetParam('introduction', $_REQUEST, false, $introduction);
            
            $old_price = $price;
            $price_t = $this->GetParam('price', $_REQUEST, false);
            if ($price_t) $price = (float)$price_t;
            $price_delta = $price - $old_price;
            
            $special_t = $this->GetParam('special', $_REQUEST, false);
            if ($special_t != false) $special = (int)$special_t;
            $photo_t = $this->GetUploadfile('photo', false);
            if ($photo_t) $photohash = $this->ImageSave($photo_t);
            
            var_dump($name);
            
            $stmt = $this->conn->prepare('
                UPDATE `food` 
                SET name=?, introduction=?, price=?, price_delta=?, photo=?, special=? 
                WHERE fid=?;');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param(
                'ssddsii', 
                $name, $introduction, $price, $price_delta, $photohash, $special, $fid
            );
            if (!$stmt->execute()) $this->InternalError();
            $stmt->close();
            
            if ($price_delta < 0) {
                // New price offer found
                $stmt = $this->conn->prepare('UPDATE `shop` SET last_offer=NOW() WHERE sid=?');
                if (!$stmt) $this->InternalError();
                $stmt->bind_param('i', $sid);
                if (!$stmt->execute()) $this->InternalError();
                $stmt->close();
            }
            
            return array('result'=>1);
        }
        
        function MethodFoodDelete() {
            $this->SessionCheck($_REQUEST);
            $uid = (int)$this->GetParam('uid', $_REQUEST);
            $sid = (int)$this->GetParam('sid', $_REQUEST);
            $this->ShopOwnerCheck($sid, $uid);
            $ids = explode(',', $this->GetParam('fids', $_REQUEST));
            
            $result = array();
            if (!$this->conn->query('START TRANSACTION;')) $this->InternalError();
            foreach ($ids as $id) {
                $r = true;
                $fid = (int)$id;
                if (!$this->conn->query('
                    DELETE FROM `food` WHERE fid='.$fid.' AND sid='.$sid.';')
                    || $this->conn->affected_rows == 0) {
                    $r = false;
                }
                $result[$fid] = $r;
            }
            if (!$this->conn->query('COMMIT;')) $this->InternalError();
            return array('result'=>1, 'results'=>$result);
        }
        
        function MethodFoodDetail() {
            $this->SessionCheck($_REQUEST);
            $uid = (int)$this->GetParam('uid', $_REQUEST);
            $fid = (int)$this->GetParam('fid', $_REQUEST);
            
            $stmt = $this->conn->prepare('
                SELECT food.fid,food.sid,name,introduction,price,price_delta,special,photo,
                    IFNULL(food_stat.likes,0),IFNULL(food_stat.dislikes,0),IFNULL(food_stat.comments,0), 
                    NOT ISNULL(bookmark.fid) AS bookmarked 
                FROM `food` 
                LEFT JOIN (
                    SELECT fid, COUNT(liked) AS likes, 
                        COUNT(disliked) AS dislikes, COUNT(comment) AS comments 
                    FROM `foodcmt` 
                    GROUP BY fid
                ) food_stat ON food_stat.fid=food.fid 
                LEFT JOIN (SELECT fid FROM `bookmark_food` WHERE uid=?) bookmark 
                ON bookmark.fid=food.fid 
                WHERE food.fid=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('ii', $uid, $fid);
            if (!$stmt->execute()) $this->InternalError();
            $stmt->bind_result(
                $fid, $sid, $name, $introduction, $price, $price_delta, $special, $photo, 
                $likes, $dislikes, $comments, $bookmarked);
            if (!$stmt->fetch()) {
                // No record found
                return array('result'=>0);
            }
            $stmt->close();
            return array(
                'result'=>1,
                'fid'=>$fid,
                'sid'=>$sid,
                'name'=>$name,
                'introduction'=>$introduction,
                'price'=>(float)$price,
                'price_delta'=>(float)$price_delta,
                'special'=>(bool)$special, 
                'photo'=>$photo, 
                'likes'=>$likes, 
                'dislikes'=>$dislikes, 
                'comments'=>$comments, 
                'bookmarked'=>(bool)$bookmarked
            );
        }
        
        function MethodFoodComment() {
            $this->SessionCheck($_REQUEST);
            $fid = (int)$this->GetParam('fid', $_REQUEST);
            $uid = (int)$this->GetParam('uid', $_REQUEST);
            $like = (int)$this->GetParam('like', $_REQUEST);
            $comment = $this->GetParam('comment', $_REQUEST);
            if ($like != 1 && $like != 0) 
                throw new FrException(1, 'Illegal `like` parameter');
            $liked = null;
            $disliked = null;
            if ($like == 1)
                $liked = true;
            else
                $disliked = true;
            if (strlen($comment) == 0) $comment = null;
            
            $stmt = $this->conn->prepare('
                INSERT INTO `foodcmt` 
                    (uid, fid, liked, disliked, comment, time) 
                VALUES (?, ?, ?, ?, ?, NOW());');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param(
                'iiiis', 
                $uid, $fid, $liked, $disliked, $comment);
            if (!$stmt->execute()) {
                // Foreign key constraint fails(no such food)
                if ($stmt->errno == 1452)
                    throw new FrException(5, 'No such food');
                else
                    $this->InternalError();
            }
            $stmt->close();
            
            $stmt = $this->conn->prepare('
                SELECT COUNT(liked), COUNT(disliked), COUNT(comment) 
                FROM `foodcmt` 
                WHERE fid=?');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('i', $fid);
            if (!$stmt->execute()) $this->InternalError();
            $stmt->bind_result($like_n, $dislike_n, $comment_n);
            if (!$stmt->fetch()) $this->InternalError();
            $stmt->close();
            
            return array(
                'result'=>1, 
                'likes'=>$like_n, 
                'dislikes'=>$dislike_n, 
                'comments'=>$comment_n
            );
        }
        
        function MethodFoodViewcomment() {
            $fid = $this->GetParam('fid', $_REQUEST);
            $stmt = $this->conn->prepare('
                SELECT user.uid, user.name, user.avatar, 
                    liked, disliked, comment, UNIX_TIMESTAMP(time) AS time 
                FROM `foodcmt` 
                JOIN `user` ON user.uid=foodcmt.uid 
                WHERE foodcmt.fid=? 
                ORDER BY time DESC');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('i', $fid);
            if (!$stmt->execute()) $this->InternalError($stmt);
            $stmt->bind_result(
                $uid, $name, $avatar, $liked, $disliked, $comment, $time
            );
            $comments = array();
            while ($stmt->fetch()) {
                if (!($liked ^ $disliked)) continue;
                if ($liked)
                    $like = true;
                else
                    $like = false;
                $comments[] = array(
                    'user'=>array('uid'=>$uid, 'name'=>$name, 'avatar'=>$avatar), 
                    'like'=>$like, 
                    'comment'=>$comment, 
                    'time'=>$time
                );
            }
            $stmt->close();
            return array('result'=>1, 'comments'=>$comments);
        }
        
        function MethodBookmarkList() {
            $this->SessionCheck($_REQUEST);
            $uid = (int)$this->GetParam('uid', $_REQUEST);
            $type = $this->GetParam('type', $_REQUEST);
            if ($type == 'food') {
                $stmt = $this->conn->prepare('
                    SELECT bookmark.id, bookmark.fid, food.name, food.price, food.price_delta, 
                        food.special, food.photo, food.sid, shop.name
                    FROM `bookmark_food` AS bookmark
                    JOIN `food` ON food.fid=bookmark.fid
                    JOIN `shop` ON shop.sid=food.sid
                    WHERE bookmark.uid = ?');
                if (!$stmt) $this->InternalError();
                $stmt->bind_param('i', $uid);
                if (!$stmt->execute()) $this->InternalError($stmt);
                $stmt->bind_result($id, $fid, $f_name, $price, $price_delta, $special, $photo, $sid, $s_name);
                $result = array();
                while ($stmt->fetch()) {
                    $result[] = array(
                        'id'=>$id,
                        'fid'=>$fid,
                        'name'=>$f_name,
                        'price'=>(float)$price,
                        'price_delta'=>(float)$price_delta,
                        'special'=>(bool)$special,
                        'photo'=>$photo,
                        'sid'=>$sid,
                        'shopname'=>$s_name
                    );
                }
                return array('result'=>1, 'bookmarks'=>$result);
            } else if ($type == 'shop') {
                $stmt = $this->conn->prepare('
                    SELECT bookmark.id, bookmark.sid, shop.photo, shop.name
                    FROM `bookmark_shop` AS bookmark
                    JOIN `shop` ON shop.sid=bookmark.sid
                    WHERE bookmark.uid=?');
                if (!$stmt) $this->InternalError();
                $stmt->bind_param('i', $uid);
                if (!$stmt->execute()) $this->InternalError($stmt);
                $stmt->bind_result($id, $sid, $photo, $name);
                $result = array();
                while ($stmt->fetch()) {
                    $result[] = array(
                        'id'=>$id,
                        'sid'=>$sid,
                        'photo'=>$photo,
                        'name'=>$name
                    );
                }
                return array('result'=>1, 'bookmarks'=>$result);
            } else throw new FrException(1, 'Illegal bookmark type');
        }
        
        function MethodBookmarkAdd() {
            $this->SessionCheck($_REQUEST);
            $uid = (int)$this->GetParam('uid', $_REQUEST);
            $id = (int)$this->GetParam('id', $_REQUEST);
            $type = $this->GetParam('type', $_REQUEST);
            if ($type != 'food' && $type != 'shop') 
                throw new FrException(5, 'Unknown bookmark type');
            
            $stmt = $this->conn->prepare('INSERT INTO `bookmark_'.$type.'` ('.$type[0].'id, uid) VALUES (?,?)');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('ii', $id, $uid);
            if (!$stmt->execute()) {
                // Foreign key constraint fails(no such user/food/shop)
                if ($stmt->errno == 1452)
                    throw new FrException(5, 'No such user/food/shop');
                else
                    $this->InternalError();
            }
            $stmt->close();
            return array('result'=>1);
        }
        
        function MethodBookmarkDelete() {
            $this->SessionCheck($_REQUEST);
            $uid = (int)$this->GetParam('uid', $_REQUEST);
            $type = $this->GetParam('type', $_REQUEST);
            $id = (int)$this->GetParam('id', $_REQUEST);
            if ($type != 'food' && $type != 'shop')
                throw new FrException(1, 'Illegal type parameter');
            $stmt = $this->conn->prepare('DELETE FROM `bookmark_'.$type.'` WHERE id=? AND uid=?;');
            if (!$stmt) $this->InternalError();
            $stmt->bind_param('ii', $id, $uid);
            if (!$stmt->execute()) $this->InternalError($stmt);
            $r = $stmt->affected_rows;
            $stmt->close();
            return array('result'=>$r);
        }
        
        function MainHandler() {
            $this->Connect();
            $method = $this->GetParam('method', $_REQUEST);
            switch ($method) {
                case 'test':
                    return $this->MethodTest();
                case 'database.reset':
                    return $this->DatabaseReset();
                case 'database.execute':
                    return $this->DatabaseExecute();
                case 'database.imageupload':
                    return $this->DatabaseUploadImages();
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
                case 'shop.list':
                    return $this->MethodShopList();
                case 'shop.detail':
                    return $this->MethodShopDetail();
                case 'shop.mark':
                    return $this->MethodShopMark();
                case 'food.create':
                    return $this->MethodFoodCreate();
                case 'food.modify':
                    return $this->MethodFoodModify();
                case 'food.delete':
                    return $this->MethodFoodDelete();
                case 'food.detail':
                    return $this->MethodFoodDetail();
                case 'food.comment':
                    return $this->MethodFoodComment();
                case 'food.viewcomment':
                    return $this->MethodFoodViewcomment();
                case 'bookmark.add':
                    return $this->MethodBookmarkAdd();
                case 'bookmark.list':
                    return $this->MethodBookmarkList();
                case 'bookmark.delete':
                    return $this->MethodBookmarkDelete();
                default:
                    throw new FrException(0x002, 'Parameter `method` is not valid');
            }
        }
        
        function HandleRequest() {
            $debug = (int)$this->GetParam('DEBUG', $_REQUEST, false, '0');
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
