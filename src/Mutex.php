<?php

namespace realbattletoad\yii2\redis;

use Yii;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\di\Instance;


/**
 * @see https://github.com/yiisoft/yii2-redis/blob/master/src/Mutex.php
 */
class Mutex extends \yii\mutex\Mutex
{
    /**
     * @var int the number of seconds in which the lock will be auto released.
     */
    public $expire = 30;
    /**
     * @var string a string prefixed to every cache key so that it is unique. If not set,
     * it will use a prefix generated from [[Application::id]]. You may set this property to be an empty string
     * if you don't want to use key prefix. It is recommended that you explicitly set this property to some
     * static value if the cached data needs to be shared among multiple applications.
     */
    public $keyPrefix;
    /**
     * @var Connection|string|array the Redis [[Connection]] object or the application component ID of the Redis [[Connection]].
     * This can also be an array that is used to create a redis [[Connection]] instance in case you do not want do configure
     * redis connection as an application component.
     * After the Mutex object is created, if you want to change this property, you should only assign it
     * with a Redis [[Connection]] object.
     */
    public $redis = 'redis';

    /**
     * @var array Redis lock values. Used to be safe that only a lock owner can release it.
     */
    private $_lockValues = [];

    /**
     * Initializes the redis Mutex component.
     * This method will initialize the [[redis]] property to make sure it refers to a valid redis connection.
     * @throws InvalidConfigException if [[redis]] is invalid.
     */
    public function init()
    {
        parent::init();
        $this->redis = Instance::ensure($this->redis, Connection::className());
        if ($this->keyPrefix === null) {
            $this->keyPrefix = substr(md5(Yii::$app->id), 0, 5);
        }
    }

    /**
     * Acquires a lock by name.
     * @param string $name of the lock to be acquired. Must be unique.
     * @param int $timeout time (in seconds) to wait for lock to be released. Defaults to `0` meaning that method will return
     * false immediately in case lock was already acquired.
     * @return bool lock acquiring result.
     * @throws Exception
     */
    protected function acquireLock($name, $timeout = 0): bool
    {
        $key = $this->calculateKey($name);
        $value = Yii::$app->security->generateRandomString(20);
        $waitTime = 0;
        while (!$this->redis->set($key, $value, ['NX', 'PX' => (int)($this->expire * 1000)])) {
            $waitTime++;
            if ($waitTime > $timeout) {
                return false;
            }
            sleep(1);
        }
        $this->_lockValues[$name] = $value;
        return true;
    }

    /**
     * Releases acquired lock. This method will return `false` in case the lock was not found or Redis command failed.
     * @param string $name of the lock to be released. This lock must already exist.
     * @return bool lock release result: `false` in case named lock was not found or Redis command failed.
     */
    protected function releaseLock($name): bool
    {
        static $releaseLuaScript = <<<LUA
if redis.call("GET",KEYS[1])==ARGV[1] then
    return redis.call("DEL",KEYS[1])
else
    return 0
end
LUA;
        if (!isset($this->_lockValues[$name]) || !$this->redis->eval(
                $releaseLuaScript,
                1,
                $this->calculateKey($name),
                $this->_lockValues[$name]
            )) {
            return false;
        } else {
            unset($this->_lockValues[$name]);
            return true;
        }
    }

    /**
     * Generates a unique key used for storing the mutex in Redis.
     * @param string $name mutex name.
     * @return string a safe cache key associated with the mutex name.
     */
    protected function calculateKey(string $name): string
    {
        return $this->keyPrefix . md5(json_encode([__CLASS__, $name]));
    }
}
