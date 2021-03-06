#!/usr/bin/env python
#coding: UTF8

from poster.encode import multipart_encode
from poster.streaminghttp import register_openers
import hashlib, urllib2, json, random, unittest

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
    hashpattern = r'[0-9a-f]{20,20}'
    
    images = ['img/a.jpg', 'img/b.jpg', 'img/c.jpg', 'img/d.jpg', 'img/_a.jpg', 'img/_b.jpg', 'img/_c.jpg', 'img/_d.jpg']
    
    users = [
        #uid,name , pwd       ,sex,ty,avatar     , sch , reg ,sid
        (1, 'aaaa', sha1('aaaa'), 1, 1, 'img/a.jpg', 'UA', 'JS', 1),
        (2, 'bbbb', sha1('bbbb'), 2, 1, 'img/b.jpg', 'UA', 'JS', 2),
        (3, 'cccc', sha1('cccc'), 1, 1, 'img/c.jpg', 'UA', 'SH', 3),
        (4, 'dddd', sha1('dddd'), 2, 1, 'img/d.jpg', 'UB', 'SH', 4),
        (5, 'eeee', sha1('eeee'), 1, 0, 'img/a.jpg', 'UA', 'JS', 0),
        (6, 'ffff', sha1('ffff'), 0, 0, 'img/b.jpg', 'UA', 'SH', 0),
        (7, 'gggg', sha1('gggg'), 1, 0, 'img/c.jpg', 'UB', 'JS', 0),
        (8, 'hhhh', sha1('hhhh'), 2, 0, 'img/d.jpg', 'UB', 'SH', 0)
    ]
    
    shops = [
        #uid,sid,name,  addr,       intro,       photo,        phone
        (1,  1,  'www', 'www road', 'www intro', 'img/_a.jpg', '111'),
        (2,  2,  'xxx', 'xxx road', 'xxx intro', 'img/_b.jpg', '222'),
        (3,  3,  'yyy', 'yyy road', 'yyy intro', 'img/_c.jpg', '333'),
        (4,  4,  'zzz', 'zzz road', 'zzz intro', 'img/_d.jpg', '444')
    ]
    
    foods = [
        #uid,sid,fid,name, price,intro,  photo,      spec
        (1,  1,  1, 'af-a',1.0,  'afi-a','img/a.jpg',1),
        (1,  1,  2, 'af-b',1.1,  'afi-b','img/b.jpg',0),
        (1,  1,  3, 'af-c',1.3,  'afi-c','img/c.jpg',1),
        (2,  2,  4, 'bf-a',2.1,  'bfi-a','img/a.jpg',0),
        (2,  2,  5, 'bf-b',2.2,  'bfi-b','img/b.jpg',0),
        (3,  3,  6, 'cf-a',3.1,  'cfi-a','img/a.jpg',0),
        (3,  3,  7, 'cf-b',3.2,  'cfi-b','img/b.jpg',1)
    ]
    
    bookmark_f = [
        #id,u, f (id)
        (1, 1, 1),
        (2, 1, 2),
        (3, 1, 3),
        (4, 1, 4),
        (5, 1, 7),
        (6, 5, 1),
        (7, 5, 2),
        (8, 5, 6),
        (9, 5, 7),
        (10,5, 5),
        (11,3, 6),
        (12,4, 2),
        (13,4, 5)
    ]
    
    bookmark_s = [
        #id,u, s (id)
        (1, 1, 2),
        (2, 1, 4),
        (3, 3, 2),
        (4, 4, 1),
        (5, 4, 2),
        (6, 4, 3),
        (7, 4, 4)
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
            files = {}
            names = []
            for i in self.images:
                n = hashlib.sha1(i).hexdigest()
                files[n] = open(i, 'rb')
                names.append(n)
            files['images'] = ','.join(names)
            files['method'] = 'database.imageupload'
            d, h = multipart_encode(files)
            req = urllib2.Request(self.url, d, h)
            r = urllib2.urlopen(req)
            t = r.read()
            o = json.loads(t)
            if o['result'] != 1:
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
        s += 'COMMIT;'
        #print s
        
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
    config.InitDb(True)
    