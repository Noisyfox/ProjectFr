#!/usr/bin/env python
#coding: GBK

'''
需要严重修改的地方：
    店铺的简介
    菜肴的名称、价格、简介
拒绝乱码，请从使用记事本修改此文件开始
'''

from poster.encode import multipart_encode
from poster.streaminghttp import register_openers
import hashlib, urllib2, json, random, unittest, os

register_openers()

def sha1(t):
    if isinstance(t, str):
        return hashlib.sha1(t).hexdigest()
    if isinstance(t, unicode):
        return sha1(t.encode('utf8'))
    if isinstance(t, file):
        return sha1(t.read())

class config:
    url = r'http://192.168.56.101/Fr/rest.php'
    
    images = map(lambda x: 'img/'+x, os.listdir('img'))
    users = [
        #用户编号 用户名    密码          性别 用户类型 头像              学校    地区  店铺编号
        (1,       'nuaa_1', sha1('nuaa'), 0,   1,       'img/s1.jpg',     'NUAA', 'NJ', 1),
        (2,       'nuaa_2', sha1('nuaa'), 0,   1,       'img/s2.jpg',     'NUAA', 'NJ', 2),
        (3,       'nuaa_3', sha1('nuaa'), 0,   1,       'img/s3.jpg',     'NUAA', 'NJ', 3),
        (4,       'nuaa_4', sha1('nuaa'), 0,   1,       'img/s4.jpg',     'NUAA', 'NJ', 4),
        (5,       'nuaa_5', sha1('nuaa'), 0,   1,       'img/s5.jpg',     'NUAA', 'NJ', 5),
        (6,       'nuaa_6', sha1('nuaa'), 0,   1,       'img/s6.jpg',     'NUAA', 'NJ', 6),
        (7,       'nuaa_7', sha1('nuaa'), 0,   1,       'img/s7.jpg',     'NUAA', 'NJ', 7),
        (8,       'nuaa_8', sha1('nuaa'), 0,   1,       'img/s8.jpg',     'NUAA', 'NJ', 8),
        (9,       'nuaa_9', sha1('nuaa'), 0,   1,       'img/s9.jpg',     'NUAA', 'NJ', 9),
        (10,      'nuaa_0', sha1('nuaa'), 0,   1,       'img/s0.jpg',     'NUAA', 'NJ', 10),
        (11,      'nuaa',   sha1('nuaa'), 0,   0,       'img/NFLogo.jpg', 'NUAA', 'NJ', 0),
    ]
    
    shops = [
        #用户编号，店铺编号，店铺名称，地址，简介，照片，电话
        (1,  1,  '小四川', '江宁南航后街', 'intro', 'img/s1.jpg', ''),
        (2,  2,  '香湘乡', '江宁区胜太路', 'intro', 'img/s2.jpg', '025-52127417'),
        (3,  3,  '永和大王', '江宁区胜太西路190号华润苏果1楼', 'intro', 'img/s3.jpg', '025-52075616'),
        (4,  4,  '苏客中式餐饮', '江宁区将军大道9号苏果超市1楼', 'intro', 'img/s4.jpg', '025-52126377'),
        (5,  5,  '宝岛战斗鸡排', '江宁区南航托乐嘉商业街129号', 'intro', 'img/s5.jpg', '15850784297'),
        (6,  6,  '高岗里小马牛肉面', '江宁胜太路166号', 'intro', 'img/s6.jpg', '025-52113718'),
        (7,  7,  'sweet欧式奶茶屋', '江宁胜太路胜太大市场内', 'intro', 'img/s7.jpg', '13062584700'),
        (8,  8,  '绝味鸭脖', '江宁区胜太西路168号百家湖超市内', 'intro', 'img/s8.jpg', ''),
        (9,  9,  '胖哥麻辣香锅店', '江宁将军大道9号托乐嘉138号商业铺', 'intro', 'img/s9.jpg', '18751963622'),
        (10,10,  '曹家土菜馆', '江宁区胜太路38-1号', 'intro', 'img/s0.jpg', '025-52123977')
    ]
    
    foods = [
        #店主用户编号，店铺编号，菜肴编号，菜肴名称，价格，简介，           照片，         特色菜
        ( 1,           1,        1,       's1f1',   1.0,   'FoodIntro_s1f1','img/s1f1.jpg',1),
        ( 1,           1,        2,       's1f2',   1.1,   'FoodIntro_s1f2','img/s1f2.jpg',0),
        ( 1,           1,        3,       's1f3',   1.3,   'FoodIntro_s1f3','img/s1f3.jpg',1),
        ( 1,           1,        4,       's1f4',   2.1,   'FoodIntro_s1f4','img/s1f4.jpg',0),
        ( 1,           1,        5,       's1f5',   2.2,   'FoodIntro_s1f5','img/s1f5.jpg',0),
        ( 1,           1,        6,       's1f6',   1.0,   'FoodIntro_s1f6','img/s1f6.jpg',1),
        ( 1,           1,        7,       's1f7',   1.1,   'FoodIntro_s1f7','img/s1f7.jpg',0),
        ( 2,           2,        8,       's2f1',   1.3,   'FoodIntro_s2f1','img/s2f1.jpg',1),
        ( 2,           2,        9,       's2f2',   2.1,   'FoodIntro_s2f2','img/s2f2.jpg',0),
        ( 2,           2,       10,       's2f3',   2.2,   'FoodIntro_s2f3','img/s2f3.jpg',0),
        ( 2,           2,       11,       's2f4',   1.0,   'FoodIntro_s2f4','img/s2f4.jpg',1),
        ( 3,           3,       12,       's3f1',   1.1,   'FoodIntro_s3f1','img/s3f1.jpg',0),
        ( 3,           3,       13,       's3f2',   1.3,   'FoodIntro_s3f2','img/s3f2.jpg',1),
        ( 3,           3,       14,       's3f3',   2.1,   'FoodIntro_s3f3','img/s3f3.jpg',0),
        ( 3,           3,       15,       's3f4',   2.2,   'FoodIntro_s3f4','img/s3f4.jpg',0),
        ( 3,           3,       16,       's3f5',   1.0,   'FoodIntro_s3f5','img/s3f5.jpg',1),
        ( 4,           4,       17,       's4f1',   1.1,   'FoodIntro_s4f1','img/s4f1.jpg',0),
        ( 4,           4,       18,       's4f2',   1.3,   'FoodIntro_s4f2','img/s4f2.jpg',1),
        ( 4,           4,       19,       's4f3',   2.1,   'FoodIntro_s4f3','img/s4f3.jpg',0),
        ( 4,           4,       20,       's4f4',   2.2,   'FoodIntro_s4f4','img/s4f4.jpg',0),
        ( 5,           5,       21,       's5f1',   1.0,   'FoodIntro_s5f1','img/s5f1.jpg',1),
        ( 5,           5,       22,       's5f2',   1.1,   'FoodIntro_s5f2','img/s5f2.jpg',0),
        ( 6,           6,       23,       's6f1',   1.3,   'FoodIntro_s6f1','img/s6f1.jpg',1),
        ( 6,           6,       24,       's6f2',   2.1,   'FoodIntro_s6f2','img/s6f2.jpg',0),
        ( 7,           7,       25,       's7f1',   2.2,   'FoodIntro_s7f1','img/s7f1.jpg',0),
        ( 7,           7,       26,       's7f2',   1.0,   'FoodIntro_s7f2','img/s7f2.jpg',1),
        ( 8,           8,       27,       's8f1',   1.1,   'FoodIntro_s8f1','img/s8f1.jpg',0),
        ( 8,           8,       28,       's8f2',   1.3,   'FoodIntro_s8f2','img/s8f2.jpg',1),
        ( 8,           8,       29,       's8f3',   2.1,   'FoodIntro_s8f3','img/s8f3.jpg',0),
        ( 9,           9,       30,       's9f1',   2.2,   'FoodIntro_s9f1','img/s9f1.jpg',0),
        (10,          10,       31,       's0f1',   1.0,   'FoodIntro_s0f1','img/s0f1.jpg',1),
        (10,          10,       32,       's0f2',   1.1,   'FoodIntro_s0f2','img/s0f2.jpg',0),
        (10,          10,       33,       's0f3',   1.3,   'FoodIntro_s0f3','img/s0f3.jpg',1)
    ]
    
    bookmark_f = [
        #书签编号，用户编号，菜肴编号
        (1,        1,         2),
        (2,        1,         4),
        (3,        1,         5),
        (4,        1,         7),
        (5,        1,        15),
        (6,        1,        23)
    ]
    
    bookmark_s = [
        #书签编号，用户编号，店铺编号
        (1,        1,        2),
        (2,        1,        4),
        (3,        1,        7)
    ]
    
    price_modify = [
        #sid, fid, price, delta, time
        (1, 1, 5, 1, '2013-09-01 08:00:00'),
        (1, 2, 10, -2, '2013-09-01 08:00:00'),
        (2, 10, 7, -1, '2013-08-30 23:00:00'),
        (2, 11, 5, 3, '2013-08-30 23:00:00')
    ]
    
    @staticmethod
    def UserSession(name):
        return sha1(name)
    
    @staticmethod
    def RandStr(length = None):
        if not length:
            length = random.randint(5, 25)
        s = ''
        for i in xrange(length):
            s += chr(random.randint(33,126))
        return s
    
    @classmethod
    def InitDb(self, uploadimage = False):
        t = urllib2.urlopen(self.url, 'method=database.reset').read()
        if t != '{"result":1}':
            raise Exeption('Error: Reset database')
            
        if uploadimage:
            #files = {}
            #names = []
            #for i in self.images:
            #    n = hashlib.sha1(i).hexdigest()
            #    files[n] = open(i, 'rb')
            #    names.append(n)
            #files['images'] = ','.join(names)
            #files['method'] = 'database.imageupload'
            #d, h = multipart_encode(files)
            #req = urllib2.Request(self.url, d, h)
            #r = urllib2.urlopen(req)
            #t = r.read()
            #o = json.loads(t)
            #if o['result'] != 1:
            #    print 'Return', t
            #    raise Exception('Error: Uploading images')
            for i in self.images:
                n = sha1(i)
                param = {}
                param[n] = open(i, 'rb')
                param['images'] = n
                param['method'] = 'database.imageupload'
                d, h = multipart_encode(param)
                req = urllib2.Request(self.url, d, h)
                r = urllib2.urlopen(req)
                t = r.read()
                print t
                o = json.loads(t)
                if o['result'] != 1:
                    print 'Return', t
                    raise Exception('Error: Uploading images')                
        
        s = 'BEGIN;\n'
        for uid, name, pwd, sex, ty, avatar, sch, reg, sid in self.users:
            s += "INSERT INTO `user` (uid,name,password,sex,type,avatar,school,region) VALUES (%d,'%s','%s',%d,%d,'%s','%s','%s');\n"%(
                uid,name,pwd,sex,ty,sha1(open(avatar,'rb')),sch,reg)
            s += "INSERT INTO `session` (sid,uid,expire,type) VALUES ('%s',%d,DATE_ADD(NOW(),INTERVAL 30 DAY),%d);\n"%(
                self.UserSession(name),uid,ty);
        s += "INSERT INTO `session` (sid,uid,expire,type) VALUES ('test',1,DATE_SUB(NOW(), INTERVAL 1 HOUR), 0);\n"
        for uid, sid, name, addr, intro, photo, phone in self.shops:
            s += "INSERT INTO `shop` (sid,uid,name,address,introduction,photo,phonenum,time,last_offer) VALUES (%d,%d,'%s','%s','%s','%s','%s', 0, 0);\n"%(
                sid, uid, name, addr, intro, sha1(open(photo,'rb')),phone)
        for uid,sid,fid,name,price,intro,photo,spec in self.foods:
            s += "INSERT INTO `food` (fid,sid,name,introduction,price,price_delta,photo,special) values (%d,%d,'%s','%s',%.2f,0,'%s',%d)\n"%(
                fid,sid,name,intro,price,sha1(open(photo,'rb')),spec)
        for id, uid, fid in self.bookmark_f:
            s += "INSERT INTO `bookmark_food` (id,uid,fid) values (%d,%d,%d);\n"%(id,uid,fid)
        for id, uid, sid in self.bookmark_s:
            s += "INSERT INTO `bookmark_shop` (id,uid,sid) values (%d,%d,%d);\n"%(id,uid,sid)
        for sid, fid, price, delta, t in self.price_modify:
            s += "UPDATE `food` SET price=%.2f,price_delta=%.2f where fid=%d;\n"%(price,delta,fid)
            s += "UPDATE `shop` SET time='%s' WHERE sid=%d;\n"%(t,sid)
            if delta<0:
                s += "UPDATE `shop` SET last_offer='%s' WHERE sid=%d;\n"%(t,sid)
        s += 'COMMIT;'
        #print s
        
        s = s.decode('GBK').encode('utf8')
        
        d, h = multipart_encode({'method': 'database.execute', 'sql':s})
        req = urllib2.Request(self.url, d, h)
        r = urllib2.urlopen(req)
        t = r.read()
        print t
        o = json.loads(t)
        if o['result'] != 1:
            raise Exception('Error: Adding records')
        
    @classmethod
    def call(self, param):
        param['DEBUG'] = 0
        d, h = multipart_encode(param)
        req = urllib2.Request(config.url, d, h)
        r = urllib2.urlopen(req).read()
        print r
        print 
        return json.loads(r)
    
if __name__ == '__main__':
    config.InitDb(False)
    