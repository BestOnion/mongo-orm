<?php

declare(strict_types=1);

namespace fairwic\MongoOrm\redis;

use fairwic\MongoOrm\MongoModel;
use Hyperf\Context\ApplicationContext;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Redis\Redis;

trait MongoRedisCache
{
    protected bool $useCache = true;
    protected string $redis_prefix = 'default';
    // 缓存过期时间3h
    protected int $expire = 3600 * 3;


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
        //开启管道
        $this->getRedis()->multi(\Redis::PIPELINE);
        foreach ($keys as $key) {
            $this->getRedis()->hGetAll($key);
        }
        //执行
        $arr = $this->getRedis()->exec();
        if ($arr && $arr[0]) {
            if (!$fields) {
                foreach ($arr as $hmvalues) {
                    if ($hmvalues) {
                        $redis_res[] = $this->changeObj($hmvalues);
                    }
                }
            } else {
                $resArr = array_map(fn($item) => array_intersect_key($item, array_flip($fields)), $arr);
                foreach ($resArr as $hmvalues) {
                    if ($hmvalues) {
                        $redis_res [] = $this->changeObj($hmvalues);
                    }
                }
            }
        }
        //是否从redis取得所有数据
        if (isset($redis_res) && count($redis_res) == count($ids)) {
            return $redis_res;
        } else {
            $arr = $this->whereIn('id', $ids)->get();
            $redisArr = [];
            $db_result = [];
            if ($arr->toArray()) {
                //开启管道
                $this->getRedis()->multi(\Redis::PIPELINE);
                /** @var MongoModel $item */
                foreach ($arr as $item) {
                    $key = $this->getRedisKey($item['id']);
                    $redisArr[] = $key;
                    $this->getRedis()->hMSet($key, $item->toArray());
                    if (!$fields) {
                        $db_result[] = $item;
                    } else {
                        $array = array_intersect_key($item->toArray(), array_flip($fields));
                        $db_result[] = $this->patchAttribute($array);
                    }
                }
                // 使用管道为每个键设置过期时间
                foreach ($redisArr as $value) {
                    $this->getRedis()->expire($value, $this->expire);
                }
                // 执行管道中的所有命令
                $this->getRedis()->exec();
            }
            return $db_result;
        }
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
                $obj = $this->changeObj($arr);
                return $obj;
            } else {
                $arr = array_intersect_key($arr, array_flip($fields));
                $obj = $this->changeObj($arr);
                return $obj;
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
