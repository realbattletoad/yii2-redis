<?php

namespace realbattletoad\yii2\redis;

use Closure;
use yii\base\InvalidConfigException;
use yii\caching\ArrayCache;
use yii\di\Instance;

class Cache extends \yii\caching\Cache
{
    /**
     * @var Connection|string|array the Redis [[Connection]] object or the application component ID of the Redis [[Connection]].
     */
    public $redis = 'redis';
    /**
     * @var ArrayCache top level cache, usefull to prevent
     * repeating queries within application lifecycle
     */
    public $arrayCache;
    /**
     * @var bool allow to automatically get/set from top level cache
     */
    public $autoArrayCache = false;

    /**
     * {@inheritdoc}
     * @throws InvalidConfigException
     */
    public function init()
    {
        parent::init();

        $this->redis = Instance::ensure($this->redis, Connection::class);
    }

    /**
     * Array cache.
     * Cache data into ArrayCache, which live only 1 application cycle.
     * Useful to prevent repeating queries to the same keys within
     * application's lifecycle.
     *
     * Example:
     *
     * ```php
     * <?php
     *
     * $key = 'user:1:profile'
     * $data = \Yii::$app->cache->arcache($key, function () use ($key {
     *     return \Yii::$app->cache->get($key);
     * });
     * ```
     *
     * @param string|array $key cache key or keys used in redis command
     * @param closure $fn function which do redis call and
     * @return mixed
     * @throws InvalidConfigException
     */
    public function arcache($key, Closure $fn)
    {
        if ($this->arrayCache && $this->arrayCache instanceof ArrayCache) {
            return $this->arrayCache->getOrSet(
                $key,
                function () use ($fn) {
                    return $fn();
                }
            );
        }
        throw new InvalidConfigException('To use this methid you should configure "arrayCache".');
    }

    /**
     * {@inheritdoc}
     */
    public function exists($key): bool
    {
        return (bool)$this->redis->exists($this->buildKey($key));
    }

    /**
     * @inheritdoc
     */
    protected function getValue($key)
    {
        return $this->redis->get($key);
    }

    /**
     * @inheritdoc
     */
    protected function getValues($keys): array
    {
        $result = [];
        $i = 0;
        $response = $this->redis->mget($keys);
        foreach ($keys as $key) {
            $result[$key] = $response[$i++];
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    protected function setValue($key, $value, $expire): bool
    {
        if ($expire == 0) {
            return (bool)$this->redis->set($key, $value);
        } else {
            $expire = (int)($expire * 1000);
            return (bool)$this->redis->pSetEx($key, $expire, $value);
        }
    }

    /**
     * @inheritdoc
     */
    protected function setValues($data, $expire)
    {
        $result = $this->redis->mset($data);
        if ($expire > 0) {
            //$expire = (int) $expire * 1000;
            $multi = $this->redis->multi();
            foreach ($data as $k => $v) {
                $multi->pexpire($k, $v);
            }
            $multi->exec();
        }
        return $result;
    }

    /**
     * @inheritdoc
     */
    protected function addValue($key, $value, $expire): bool
    {
        if ($expire == 0) {
            return (bool)$this->redis->setNx($key, $value);
        } else {
            $expire = (int)($expire * 1000);
            return (bool)$this->redis->set($key, $value, ['nx', 'px' => $expire]);
        }
    }

    /**
     * @inheritdoc
     */
    protected function deleteValue($key): bool
    {
        return (bool)$this->redis->del($key);
    }

    /**
     * @inheritdoc
     */
    protected function flushValues(): bool
    {
        return $this->redis->flushdb();
    }

    /**
     * Array cache decorator
     * @param string $key cache key used in redis command
     * @param closure $fn function which do redis call and
     * @return Closure
     *
     * Example:
     * ```php
     * $fn = function($redis, $key) {
     *     return $this->redis->get($key);
     * };
     * $this->tlcache($key, $fn);
     * ```
     * @throws InvalidConfigException
     */
    private function tlcache(string $key, Closure $fn): Closure
    {
        if ($this->autoArrayCache && Instance::ensure($this->arrayCache, ArrayCache::class)) {
            return $this->arcache($key, $fn);
        }
        return $fn;
    }
}