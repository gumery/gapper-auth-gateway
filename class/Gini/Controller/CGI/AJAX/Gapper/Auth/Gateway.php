<?php

namespace Gini\Controller\CGI\AJAX\Gapper\Auth;

class Gateway extends \Gini\Controller\CGI
{
    use \Gini\Module\Gapper\Client\CGITrait;
    use \Gini\Module\Gapper\Client\LoggerTrait;

    protected function _config()
    {
        $infos = (array)\Gini\Config::get('gapper.auth');
        return (object)$infos['gateway'];
    }

    protected function verify($username, $password)
    {
        try {
            $config = (array) \Gini\Config::get('app.rpc');
            $config = $config['gateway'];
            $api = $config['url'];
            $rpc = \Gini\IoC::construct('\Gini\RPC', $api);
            return !!$rpc->Gateway->Auth->verify($username, $password);
        }
        catch (\Exception $e) {
        }
        return false;
    }

    /**
        * @brief 执行登录逻辑
        *
        * @return
     */
    public function actionLogin()
    {
        // 如果用户已经登录
        if ($this->isLogin()) {
            return $this->showJSON(true);
        }

        $form = $this->form('post');
        $username = trim($form['username']);
        $password = $form['password'];

        if (!$username || !$password) {
            return $this->showJSON('请填写用户名和密码');
        }

        // 验证用户一卡通和密码是否匹配
        if (!$this->verify($username, $password)) {
            return $this->showJSON('卡号密码不匹配');
        }

        // 记录当前登录的一卡通号
        $_SESSION['gapper-auth-gateway.username'] = $username;

        $config = $this->_config();
        // 以一卡通号获取gapper用户信息
        try {
            $rpc = \Gini\Gapper\Client::getRPC();
            $info = $rpc->Gapper->User->getUserByIdentity($config->source, $username);
        } catch (\Exception $e) {
            return $this->showJSON(T('网络异常，请重试'));
        }

        if (null===$info || false===$info) {
            return $this->showJSON(T('获取用户登录信息失败，请重试'));
        }

        // 一卡通号没有对应的gapper用户，需要激活，进入group401进行用户和组的激活
        if (empty($info)) {
            $appInfo = \Gini\Gapper\Client::getInfo();
            if (strtolower($appInfo['type'])=='user') {
                return \Gini\CGI::request('ajax/gapper/step/user401', $this->env)->execute();
            }
            return \Gini\CGI::request('ajax/gapper/step/group401', $this->env)->execute();
        }

        // 用户已经存在，正常登录
        $result = \Gini\Gapper\Client::loginByUserName($info['username']);
        if ($result) {
            return $this->showJSON(true);
        }

        return $this->showJSON(T('获取系统应用信息失败，请重试'));

    }

    /**
        * @brief 获取登录表单
        *
        * @return
     */
    public function actionGetForm()
    {
        $config = $this->_config();
        if (!$config->login['url']) {
            return $this->showHTML('gapper/auth/gateway/login', [
                'icon'=> $config->icon,
                'type'=> $config->name
            ]);
        }
        $redirectURL = $_SERVER['HTTP_REFERER'];
        if (!$redirectURL) {
            $ui = parse_url(\Gini\URI::url());
            $redirectURL = "{$ui['scheme']}://{$ui['host']}";
            if (!$ui['port'] || $ui['port']!='80') {
                $redirectURL = "{$redirectURL}:{$ui['port']}";
            }
        }

        $confs = \Gini\Config::get('gapper.rpc');
        $clientId = $confs['client_id'];
        // login_url: http://hxpglgw.njust.edu.cn/login
        $needRelogin = $config->login['relogin'];
        $redirectURL = \Gini\URI::url($config->login['url'], [
            'from'=> $clientId,
            'relogin'=> $needRelogin===false ? 1 : 0,
            'redirect'=> $redirectURL
        ]);

        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'redirect'=> $redirectURL,
            'message'=> $config->login['tip'] ?: T('去统一身份认证登录')
        ]);
    }

    public static function addMember()
    {
        $args = func_get_args();
        $action = array_shift($args);
        switch ($action) {
        case 'get-add-modal':
            return call_user_func_array([self, '_getAddModal'], $args);
            break;
        case 'search':
            return call_user_func_array([self, '_getSearchResults'], $args);
            break;
        case 'post-add':
            return call_user_func_array([self, '_postAdd'], $args);
            break;
        }
    }

    private static function _getAddModal($type, $groupID)
    {
        return \Gini\IoC::construct('\Gini\CGI\Response\HTML', V('gapper/auth/gateway/add-member/modal'));
    }

    private static function _getSearchResults($type, $keyword)
    {
        try {
            $infos = (array)\Gini\Config::get('gapper.auth');
            $gInfo = (object)$infos['gateway'];
            $identitySource = @$gInfo->source;
            $info = \Gini\Gapper\Client::getRPC()->gapper->user->getUserByIdentity($identitySource, $keyword);
        } catch (\Exception $e) {
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }

        if ($info && $info['id']) {
            try {
                $groups = \Gini\Gapper\Client::getRPC()->gapper->user->getGroups((int)$info['id']);
            } catch (\Exception $e) {
                return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
            }
            $current = \Gini\Gapper\Client::getGroupID();
            if (isset($groups[$current])) {
                return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
            }
            $data = [[
                'username'=> $keyword,
                'name'=> $info['name'],
                'initials'=> $info['initials'],
                'icon'=> $info['icon'],
                'ref_no'=> $keyword,
            ]];
        } else {
            $infos = (array)\Gini\Gapper\Auth\Gateway::getUsers([
                'keyword'=> $keyword
            ]);
            $data = [];
            foreach ($infos as $info) {
                $data[] = [
                    'username'=> $info['ref_no'],
                    'name'=> $info['name'],
                    'email'=> $info['email'],
                    'ref_no'=> $info['ref_no'],
                    'school'=> $info['school'],
                ];
            }
        }

        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', (string)V('gapper/auth/gateway/add-member/match', [
            'keyword'=> $keyword,
            'data'=> $data
        ]));
    }

    private static function _postAdd($type, $form)
    {
        try {
            $infos = (array)\Gini\Config::get('gapper.auth');
            $gInfo = (object)$infos['gateway'];
            $identitySource = @$gInfo->source;
            $info = \Gini\Gapper\Client::getRPC()->gapper->user->getUserByIdentity($identitySource, $form['username']);
        } catch (\Exception $e) {
            return self::_alert(T('操作失败，请您重试'));
        }

        $current = \Gini\Gapper\Client::getGroupID();

        if ($info && $info['id']) {
            try {
                $groups = \Gini\Gapper\Client::getRPC()->gapper->user->getGroups((int)$info['id']);
            } catch (\Exception $e) {
                return self::_alert(T('操作失败，请您重试'));
            }
            if (isset($groups[$current])) {
                return self::_success($info);
            }

            try {
                $bool = \Gini\Gapper\Client::getRPC()->gapper->group->addMember((int)$current, (int)$info['id']);
            } catch (\Exception $e) {
                return self::_alert(T('操作失败，请您重试'));
            }
            if ($bool) {
                return self::_success($info);
            }
            return self::_alert(T('操作失败，请您重试'));
        }

        // 如果没有提交email和name, 展示确认name和email的表单
        if (empty($form['name']) || empty($form['email'])) {
            $error = [];
            if (empty($form['name'])) {
                $error['name'] = T('请补充用户姓名');
            }
            if (empty($form['email'])) {
                $error['email'] = T('请填写Email');
            }
        }

        $pattern = '/^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/';
        if ($form['email'] && !preg_match($pattern, $form['email'])) {
            $error['email'] = T('请填写真实的Email');
        }

        if (!empty($error)) {
            return self::_showFillInfo([
                'username'=> $form['username'],
                'name'=> $form['name'],
                'email'=> $form['email'],
                'error'=> $error
            ]);
        }

        $email = $form['email'];
        $name = $form['name'];
        $username = $form['username'];
        try {
            $info = \Gini\Gapper\Client::getRPC()->gapper->user->getInfo($email);
        } catch (\Exception $e) {
            return self::_alert(T('操作失败，请您重试'));
        }

        if ($info['id']) {
            return self::_showFillInfo([
                'username'=> $username,
                'name'=> $name,
                'email'=> $email,
                'error'=> [
                    'email'=> T('Email已经被占用, 请换一个试试')
                ]
            ]);
        }

        try {
            $infos = (array)\Gini\Config::get('gapper.auth');
            $gInfo = (object)$infos['gateway'];
            $identitySource = @$gInfo->source;

            $uid = \Gini\Gapper\Client::getRPC()->gapper->user->registerUserWithIdentity([
                'username'=> $email,
                'password'=> \Gini\Util::randPassword(),
                'name'=> $name,
                'email'=> $email
            ], $identitySource, $username);
            if (!$uid) {
                throw new \Exception();
            }
            $bool = \Gini\Gapper\Client::getRPC()->gapper->group->addMember((int)$current, (int)$uid);
        } catch (\Exception $e) {
            return self::_alert(T('操作失败，请您重试'));
        }
        if (!$uid) return self::_alert(T('添加用户失败, 请重试!'));

        try {
        } catch (\Exception $e) {
            return self::_alert(T('操作失败，请您重试'));
        }
        if (!$bool) return self::_alert(T('用户添加失败, 请换一个Email试试!'));

        if ($bool) {
            $info = \Gini\Gapper\Client::getRPC()->gapper->user->getInfo((int)$uid);
            return self::_success($info);
        }

        return self::_alert(T('一卡通用户已经激活, 但是暂时无法将该用户加入当前组, 请联系网站管理员处理!'));
    }

    private static function _success(array $user=[])
    {
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'type'=> 'replace',
            'replace'=> $user,
            'message'=> (string)V('gapper/client/add-member/success')
        ]);
    }

    private static function _alert($message)
    {
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'type'=> 'alert',
            'message'=> $message
        ]);
    }

    private static function _showFillInfo($vars)
    {
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'type'=> 'replace',
            'message'=> (string)V('gapper/auth/gateway/add-member/fill-info', $vars)
        ]);
    }
}
