<?php

declare(strict_types=1);

namespace fairwic\MongoOrm\redis;

use Hyperf\Context\ApplicationContext;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Redis\Redis;

trait MongoRedisCache
{
    protected bool $useCache = true;
    protected string $redis_prefix = 'default';
    // 缓存过期时间3h
    protected int $expire = 3600 * 3;

    public function __construct()
    {
    }

    public function getRedis()
    {
        $container = ApplicationContext::getContainer();
        return $container->get(Redis::class);
    }

    /**
     * @return array|false|object|Redis
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    protected function findManyFromCache(array $ids, $fields = [])
    {
        $keys = array_map(fn($id) => $this->getRedisKey($id), $ids);
        $arr = $this->getRedis()->mGet($keys);
        if ($arr && $arr[0]) {
            //var_dump('get from redis');
            if (!$fields) {
                return array_map(fn($item) => json_decode($item, true), $arr);
            } else {
                return array_map(fn($item) => array_intersect_key(json_decode($item, true), array_flip($fields)), $arr);
            }
        }

        //开启管道
        $this->getRedis()->multi(\Redis::PIPELINE);
        //        var_dump('get from db');
        $arr = $this->whereIn('id', $ids)->get();
        $redisArr = [];
        $result = [];
        foreach ($arr as &$item) {
            $key = $this->getRedisKey($item['id']);
            $redisArr[$key] = json_encode($item);
            if (!$fields) {
                $result[] = $item;
            } else {
                $result[] = array_intersect_key($item, array_flip($fields));
            }
        }
        $this->getRedis()->mset($redisArr);
        // 使用管道为每个键设置过期时间
        foreach ($redisArr as $key => $value) {
            $this->getRedis()->expire($key, $this->expire);
        }
        // 执行管道中的所有命令
        $this->getRedis()->exec();
        return $result;
    }

    public function patchAttribute(array $data)
    {
        $class = new (static::class);

        foreach ($data as $key => $value) {
            $class->$key = $value;
        }
        return $class;
    }

    /**
     * @return array|false|object|Redis
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public function findOneFromCache(string $id, $fields = [])
    {
        $keys = $this->getRedisKey($id);
        $arr = $this->getRedis()->hGetAll($keys);
        //$arr=array('a'=>'b','c'=>'d');
        if ($arr) {
            if (!$fields) {
                //转换成对象
                $obj = $this->patchAttribute($arr);
                return $obj;
            } else {
                return array_intersect_key($arr, array_flip($fields));
            }
        } else {
            //var_dump('get from db');
            //开启管道
            $arr = $this->findById($id);
            if ($arr) {
                $key = $this->getRedisKey($id);
                if ($fields) {
                    $arr = array_intersect_key($arr, array_flip($fields));
                }
                foreach ($arr->toArray() as $has_key => $value) {
                    $this->getRedis()->hset($key, $has_key, $value);
                }
            }
            return $arr;
        }

    }

    /**
     * @param string $id
     * @return string
     */
    protected function getRedisKey(string $id): string
    {
        // mc:$prefix:m:$model:$pk:$id
        return 'mc:' . $this->redis_prefix . ':m:' . $this->document_name . ':id:' . $id;
    }

    /**
     * @param $key
     * @return false|int|\Redis
     * @throws \RedisException
     */
    protected function redis_delete($key)
    {
        return $this->getRedis()->del($this->getRedisKey($key));
    }

    /**
     * @param array $k
     * @return false|int|\Redis
     * @throws \RedisException
     */
    protected function batchDeleteRedisKey(array $k): bool|int|\Redis
    {
        $keys = [];
        foreach ($k as $val) {
            $keys[] = $this->getRedisKey($val);
        }
        if ($keys) {
            return $this->getRedis()->del($keys);
        }
        return 0;
    }
}
