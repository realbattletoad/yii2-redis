<?php

namespace realbattletoad\yii2\redis;

use Redis;
use RedisArray;
use RedisCluster;
use RedisClusterException;
use yii\base\Component;
use yii\helpers\ArrayHelper;

class Connection extends Component
{
    /**
     * @var array list of hostname:port
     */
    public $servers = [];
    /**
     * @var int default database. RedisCluster has only 1 database with index 0.
     * @see https://redis.io/topics/cluster-spec
     */
    public $database = 0;
    /**
     * @var int timeout
     */
    public $timeout = 2;
    /**
     * @var int read timeout
     */
    public $readTimeout = 2;
    /**
     * @var bool reuse connection, affects only when cluster is 'none' or 'redis'
     */
    public $persistent = false;
    /**
     * @var string|null password to authenticate in redis
     */
    public $password = null;
    /**
     * @var string clustering mode: 'redis' or 'array'
     * @see https://github.com/phpredis/phpredis/blob/develop/cluster.markdown#readme
     * @see https://github.com/phpredis/phpredis/blob/develop/arrays.markdown#readme
     */
    public $cluster = 'none';
    /**
     * @var array cluster options, vary for each mode (RedisCluster, RedisArray)
     */
    public $clusterOptions = [];

    /**
     * @var RedisCluster|RedisArray|Redis redis client
     */
    private $_client;

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();
        switch ($this->cluster) {
            case 'redis':
                try {
                    $this->_client = new RedisCluster(
                        null,
                        $this->servers,
                        (float)$this->timeout,
                        (float)$this->readTimeout,
                        (bool)$this->persistent,
                        $this->password
                    );
                } catch (RedisClusterException $e) {
                }
                break;
            case 'array':
                $options = [
                    'connect_timeout' => $this->timeout,
                    'read_timeout' => $this->readTimeout,
                    'pconnect' => $this->persistent,
                    'auth' => $this->password,
                ];
                $options = ArrayHelper::merge($options, $this->clusterOptions);

                $this->_client = new RedisArray($this->servers, $options);
                if ($this->database > 0) {
                    $multi = $this->_client->multi();
                    foreach ($this->servers as $server) {
                        $multi->rawcommand(explode(":", $server), 'SELECT', $this->database);
                    }
                    $multi->exec();
                }
                break;
            default:
                $this->_client = new Redis();
                list($host, $port) = explode(":", $this->servers[0]);
                $fn = $this->persistent ? 'pconnect' : 'connect';
                $this->_client->{$fn}($host, (int)$port);
                if ($this->database > 0) {
                    $this->_client->select($this->database);
                }
        }
    }

    /**
     * @return RedisCluster|RedisArray|Redis
     */
    public function getClient()
    {
        return $this->_client;
    }

    /**
     * {@inheritdoc}
     */
    public function __call($name, $params)
    {
        return $this->_client->{$name}(...$params);
    }

    /**
     *  Flush database regardless connection type
     *
     * @param bool $async
     * @return bool
     */
    public function flushdb(bool $async = false): bool
    {
        if ($this->_isCluster()) {
            return $this->_client->flushdb($async);
        }

        foreach ($this->_clients->_masters() as $host) {
            $this->_client->flushdb($host, $async);
        }

        return true;
    }

    /**
     * Check if connection is RedisCluster
     * @return bool
     */
    private function _isCluster(): bool
    {
        return $this->_client instanceof RedisCluster;
    }

    /**
     * Check if connection is RedisArray
     * @return bool
     */
    private function _isArray(): bool
    {
        return $this->_client instanceof RedisArray;
    }

    /**
     * Check if connection is Redis (standalone server)
     * @return bool
     */
    private function _isSingle(): bool
    {
        return !($this->_isCluster() || $this->_isArray());
    }
}
