<?php

namespace Gini\Gapper\Auth;

class Gateway 
{
    private static $_RPC;
    public static function getRPC()
    {
        if (!self::$_RPC) {
            $conf = \Gini\Config::get('app.rpc');
            $gateway = $conf["gateway"];
            $gatewayURL = $gateway['url'];
            $clientId = $gateway['client_id'];
            $clientSecret = $gateway['client_secret'];

            $cacheKey = "gateway/client.$clientId/session-token";
            $token = self::cache($cacheKey);
            $rpc = \Gini\IoC::construct('\Gini\RPC', $gatewayURL);

            if ($token) {
                $rpc->setHeader([ 'X-Gini-Session' => $token ]);
            } else {
                $token = $rpc->Gateway->authorize($clientId, $clientSecret);
                if ($token) {
                    self::cache($cacheKey, $token);
                }
            }

            self::$_RPC = $rpc;
        }

        return self::$_RPC;
    }

    // alias for backwards compatibility
    public static function getGatewayRPC() {
        return self::getRPC();
    }

    public static function getCampuses()
    {
        $cacheKey = "gateway/location/campuses";
        $data = self::cache($cacheKey);
        if (empty($data)) {
            $data = (array)self::getGatewayRPC()->Gateway->Location->getCampuses();
            self::cache($cacheKey, $data);
        }
        return $data;
    }

    public static function getBuildings($campus)
    {
        $md5 = md5(J($campus));
        $cacheKey = "gateway/location/campus.$md5/buildings";
        $data = self::cache($cacheKey);
        if (empty($data)) {
            $data = (array)self::getGatewayRPC()->Gateway->Location->getBuildings($campus);
            self::cache($cacheKey, $data);
        }
        return $data;
    }

    public static function getRooms($building) 
    {
        $md5 = md5(J($building));
        $cacheKey = "gateway/location/building.$md5/rooms";
        $data = self::cache($cacheKey);
        if (empty($data)) {
            $data = (array)self::getGatewayRPC()->Gateway->Location->getRooms($building);
            self::cache($cacheKey, $data);
        }
        return $data;
    }



    public static function getUserInfo($username)
    {
        $cacheKey = "gateway/people/user.$username/info";
        $data = self::cache($cacheKey);
        if (empty($data)) {
            $data = (array)self::getGatewayRPC()->Gateway->People->getUser($username);
            self::cache($cacheKey, $data);
        }
        return $data;
    }

    public static function getUsers(array $criteria)
    {
        $key = md5(J($criteria));
        $cacheKey = "gateway/people/users.$key/info";
        $data = self::cache($cacheKey);
        if (empty($data)) {
            $data = self::getGatewayRPC()->Gateway->People->getUsers($criteria);
            $data = empty($data) ? [] : $data;
            self::cache($cacheKey, $data);
        }
        return $data;
    }

    public static function getSchools()
    {
        $cacheKey = "gateway/organization/schools";
        $data = self::cache($cacheKey);
        if (empty($data)) {
            $data = (array)self::getGatewayRPC()->Gateway->Organization->getSchools();
            self::cache($cacheKey, $data);
        }
        return $data;
    }

    public static function getDepartments($school)
    {
        $cacheKey = "gateway/organization/school.$school/departments";
        $data = self::cache($cacheKey);
        if (empty($data)) {
            $criteria = ['school'=>$school];
            $data = (array)self::getGatewayRPC()->Gateway->Organization->getDepartments($criteria);
            self::cache($cacheKey, $data);
        }
        return $data;
    }

    private static function cache($key, $value=null)
    {
        $cacher = \Gini\Cache::of("gapper-auth-gateway");
        if (is_null($value)) {
            return $cacher->get($key);
        }
        $config = \Gini\Config::get('cache.default');
        $timeout = @$config['timeout'];
        $timeout = is_numeric($timeout) ? $timeout : 500;
        $cacher->set($key, $value, $timeout);
    }
}
