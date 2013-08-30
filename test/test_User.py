#!/usr/bin/env python
#coding: UTF8

from mytest_config import *

class Test_0_Register(unittest.TestCase):
    def setUp(self):
        config.InitDb()
    
    def tearDown(self):
        pass
    
    def testNormalGuest(self):
        p = {
            'method': 'user.register',
            'name': 'unique'+config.RandStr(),
            'password': config.RandStr(),
            'sex': 1,
            'type': 0,
            'avatar': open('img/b.jpg', 'rb'),
            'school': 'b',
            'region': 'b'
        }
        o = config.call(p)
        self.assertEqual(o['result'], 1)
        self.assertIn('uid', o)
        self.assertIn('session', o)
    
    def testNormalOwner(self):
        p = {
            'method': 'user.register',
            'name': 'unique'+config.RandStr(),
            'password': config.RandStr(),
            'sex': 1,
            'type': 1,
            'avatar': open('img/b.jpg', 'rb'),
            'school': 'b',
            'region': 'b'
        }
        o = config.call(p)
        self.assertEqual(o['result'], 1)
        self.assertIn('uid', o)
        self.assertIn('session', o)
    
    def testDuplicate(self):
        uid,name,pwd,sex,ty,avatar,school,region,sid = random.choice(config.users)
        p = {
            'method': 'user.register',
            'name': name,
            'password': config.RandStr(),
            'sex': random.randint(0,2),
            'type': random.randint(0,1),
            'avatar': open('img/a.jpg', 'rb'),
            'school': config.RandStr(),
            'region': config.RandStr()
        }
        o = config.call(p)
        self.assertEqual(o['result'], -1)
    
class Test_1_Login(unittest.TestCase):
    def setUp(self):
        config.InitDb()
    
    def tearDown(self):
        pass
    
    def testNormalPwd(self):
        uid,name,pwd,sex,ty,avatar,sch,reg,sid = random.choice(config.users)
        p = {
            'method': 'user.login',
            'name': name,
            'password': pwd
        }
        print 'Login as %s'%(name)
        o = config.call(p)
        self.assertEqual(o['result'], 1)
        self.assertEqual(o['uid'], uid)
        self.assertEqual(o['sex'], sex)
        self.assertEqual(o['type'], ty)
        self.assertEqual(o['avatar'], sha1(open(avatar,'rb')))
        self.assertEqual(o['school'], sch)
        self.assertEqual(o['region'], reg)
        if (ty == 1):
            self.assertEqual(o['sid'], sid)
        self.assertIn('session', o)
    
    def testNormalSession(self):
        uid,name,pwd,sex,ty,avatar,sch,reg,sid = random.choice(config.users)
        p = {
            'method': 'user.login',
            'uid': uid,
            'session': config.UserSession(name)
        }
        print 'Login as %s'%(name)
        o = config.call(p)
        self.assertEqual(o['result'], 1)
        self.assertEqual(o['uid'], uid)
        self.assertEqual(o['sex'], sex)
        self.assertEqual(o['type'], ty)
        self.assertEqual(o['avatar'], sha1(open(avatar,'rb')))
        self.assertEqual(o['school'], sch)
        self.assertEqual(o['region'], reg)
        if (ty == 1):
            self.assertEqual(o['sid'], sid)
        self.assertIn('session', o)
    
    def testWrongPwd(self):
        p = {
            'method': 'user.login',
            'name': 'DoNotExists',
            'password': '=,='
        }
        print 'Nothing exists'
        o = config.call(p)
        self.assertEqual(o['result'], -1)
        
        p = {
            'method': 'user.login',
            'name': 'aaa',
            'password': '=,='
        }
        print 'Wrong password'
        o = config.call(p)
        self.assertEqual(o['result'], -1)
        
        p = {
            'method': 'user.login',
            'name': 'aaa',
            'password': '=,='
        }
        print 'Wrong password'
        o = config.call(p)
        self.assertEqual(o['result'], -1)
        
        p = {
            'method': 'user.login',
            'name': 'aaa',
            'password': 'bbb'
        }
        print 'Mismatch password'
        o = config.call(p)
        self.assertEqual(o['result'], -1)
    
    def testWrongSession(self):
        p = {
            'method': 'user.login',
            'uid': 222,
            'session': '222'
        }
        print 'Nothing exists'
        o = config.call(p)
        self.assertEqual(o['result'], -1)
        
        p = {
            'method': 'user.login',
            'uid': 1,
            'session': 'aaa'
        }
        print 'Wrong session'
        o = config.call(p)
        self.assertEqual(o['result'], -1)
        
        p = {
            'method': 'user.login',
            'uid': 1,
            'session': config.UserSession('ccc')
        }
        print 'Session mismatch'
        o = config.call(p)
        self.assertEqual(o['result'], -1)
        
class Test_2_Modify(unittest.TestCase):
    def setUp(self):
        config.InitDb()
    
    def tearDown(self):
        pass
    
    def testNormal_withoutpwd(self):
        uid, name, pwd, sex, ty, avatar, school, region, sid = random.choice(config.users)
        sex = random.randint(0, 2)
        school = config.RandStr()
        region = config.RandStr()
        avatar = random.choice(config.images)
        
        p = {
            'method': 'user.modify',
            'uid': uid,
            'session': config.UserSession(name),
            'password': '',
            'sex': sex,
            'avatar': open(avatar, 'rb'),
            'school': school,
            'region': region
        }
        o = config.call(p)
        self.assertEqual(o['result'], 1)
        self.assertIn('session', o)
        
        session = o['session']
        p = {
            'method': 'user.login',
            'uid': uid,
            'session': session
        }
        o = config.call(p)
        self.assertEqual(o['result'], 1)
        self.assertEqual(o['sex'], sex)
        self.assertEqual(o['type'], ty)
        self.assertEqual(o['avatar'], sha1(open(avatar, 'rb')))
        self.assertEqual(o['school'], school)
        self.assertEqual(o['region'], region)
    
    def testNormal_withpwd(self):
        uid, name, pwd, sex, ty, avatar, school, region, sid = random.choice(config.users)
        password = config.RandStr(20)
        sex = random.randint(0, 2)
        school = config.RandStr()
        region = config.RandStr()
        avatar = random.choice(config.images)
        
        p = {
            'method': 'user.modify',
            'uid': uid,
            'session': config.UserSession(name),
            'password': password,
            'sex': sex,
            'avatar': open(avatar, 'rb'),
            'school': school,
            'region': region
        }
        o = config.call(p)
        self.assertEqual(o['result'], 1)
        self.assertIn('session', o)
        self.assertNotEqual(o['session'], config.UserSession(name))
        
        newsession = o['session']
        p = {
            'method': 'user.login',
            'uid': uid,
            'session': config.UserSession(name)
        }
        o = config.call(p)
        self.assertEqual(o['result'], -1)
        
        p = {
            'method': 'user.login',
            'name': name,
            'password': pwd
        }
        o = config.call(p)
        self.assertEqual(o['result'], -1)
        
        p = {
            'method': 'user.login',
            'name': name,
            'password': password
        }
        o = config.call(p)
        self.assertEqual(o['result'], 1)
        
        p = {
            'method': 'user.login',
            'uid': uid,
            'session': newsession
        }
        o = config.call(p)
        self.assertEqual(o['result'], 1)
        self.assertEqual(o['sex'], sex)
        self.assertEqual(o['type'], ty)
        self.assertEqual(o['avatar'], sha1(open(avatar, 'rb')))
        self.assertEqual(o['school'], school)
        self.assertEqual(o['region'], region)
        
    def testInvalidSession_nothing(self):
        uid,name,pwd,sex,ty,avatar,sch,reg,sid = random.choice(config.users)
        p = {
            'method': 'user.modify',
            'uid': 999,
            'session': '=,=',
            'password': 'new shop'
        }
        o = config.call(p)
        self.assertEqual(o['result'], 0)
    
    def testInvalidSession_mismatch(self):
        uid,name,pwd,sex,ty,avatar,sch,reg,sid = random.choice(config.users)
        n_name = name
        while (n_name == name):
            _,n_name,_,_,_,_,_,_,_ = random.choice(config.users)
        session = config.UserSession(n_name)
        p = {
            'method': 'user.modify',
            'uid': uid,
            'session': session,
            'sex': 0
        }
        o = config.call(p)
        self.assertEqual(o['result'], 0)
    
    def testSessionExpire(self):
        p = {
            'method': 'user.modify',
            'uid': 1,
            'session': 'test',
            'sex': 0
        }
        o = config.call(p)
        self.assertEqual(o['result'], 0)
    

if __name__ == '__main__':
    unittest.main()
