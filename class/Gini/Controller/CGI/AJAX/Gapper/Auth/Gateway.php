<?php

namespace Gini\Controller\CGI\AJAX\Gapper\Auth;

class Gateway extends \Gini\Controller\CGI
{
    use \Gini\Module\Gapper\Client\RPCTrait;
    use \Gini\Module\Gapper\Client\CGITrait;
    use \Gini\Module\Gapper\Client\LoggerTrait;

    protected static $identitySource = 'ntu';

    protected function getConfig()
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

        // 以一卡通号获取gapper用户信息
        try {
            $info = self::getRPC()->Gapper->User->getUserByIdentity(self::$identitySource, $username);
        } catch (\Exception $e) {
        }

        if ($info['id']) {
            // 用户已经存在，正常登录
            $result = \Gini\Gapper\Client::loginByUserName($info['username']);
            if ($result) {
                return $this->showJSON(true);
            }

            return $this->showJSON(T('Login failed! Please try again.'));
        }

    }

    /**
        * @brief 获取登录表单
        *
        * @return 
     */
    public function actionGetForm()
    {
        $config = $this->getConfig();
        return $this->showHTML('gapper/auth/gateway/login', [
            'icon'=> $config->icon,
            'type'=> $config->name
        ]);
    }
}
