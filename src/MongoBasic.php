<?php

namespace fairwic\MongoOrm;

use App\Controller\Statistics\Statistics;
use App\Utils\Log;
use Exception;
use fairwic\MongoOrm\Elasticsearch\EsInstanceInterface;
use fairwic\MongoOrm\Elasticsearch\EsTrait;
use fairwic\MongoOrm\redis\MongoRedisCache;
use Hyperf\Context\ApplicationContext;
use Hyperf\GoTask\MongoClient\Collection;
use Hyperf\GoTask\MongoClient\MongoClient;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class MongoBasic extends DocumentArr implements \JsonSerializable
{
    use EsTrait;
    use MongoRedisCache;

    /** @var bool 是否自动维护时间戳,默认true */
    public bool $auto_timestamps = true;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = 'updated_at';

    protected ?string $dateFormat = 'U';


    //    private QueryBuilder $queryBuilder;
    //
    //    public function __construct()
    //    {
    //        $this->queryBuilder = new QueryBuilder();
    //    }
    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        if ($this->relations) {
            //合并属性和关联属性
            foreach ($this->relations as $key => $item) {
                //如果key是驼峰的，转为下划线
                if (preg_match('/[A-Z]/', $key)) {
                    $key = strtolower(preg_replace('/[A-Z]/', '_\\0', $key));
                }
                $this->attributes[$key] = $item;
            }
        }
        return $this->attributes;
    }

    /**
     * 增加关联属性
     * @param array $relation
     * @return void
     */
    public function addRelation(array $relation): void
    {
        $this->relations = array_merge($this->relations, $relation);
    }

    protected string $primaryKey = 'id';
    protected string $database_name;
    protected string $document_name;
    protected bool $isSoftDelete = false;
    private array $columns = [];
    private array $option = [];
    private array $filter = [];
    private array $collections;
    private array $pipleline = [];
    private array $opter_map = [
        '=' => '$eq',
        '!=' => '$ne',
        '<>' => '$ne',
        '>' => '$gt',
        '<' => '$lt',
        '>=' => '$gte',
        '<=' => '$lte',
        'like' => '$regex',
        'in' => '$in',
        'exists' => '$exists',
    ];

    /**
     * @return Collection
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */

    public function getCollection(): Collection
    {
        return ApplicationContext::getContainer()->get(MongoClient::class)->database($this->database_name)->collection($this->document_name);
    }

    /**
     * @return array|object
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getArr(): array|object
    {
        if ($this->isSoftDelete) {
            $this->filter['deleted_at'] = null;
        }
        foreach ($this->columns as $value) {
            $this->option['projection'][$value] = 1;
        }
        if ($this->pipleline) {
            if ($this->isSoftDelete) {
                $this->pipleline['deleted_at'] = null;
            }
            $result = $this->getCollection()->aggregate($this->pipleline, $this->option);
        } else {
            if ($this->isSoftDelete) {
                $this->where('deleted_at', null);
            }
            $result = $this->getCollection()->find($this->filter, $this->option);
            Log::info('mongo-get查询返回结果', [$result, $this->filter, $this->option]);
        }


        return $result;
    }


    /**
     * @param array $fields
     * @return $this
     */
    public function select(array $fields = []): static
    {
        $this->columns = $fields;
        return $this;
    }

    /**
     * @return $this
     */
    public function skip(int $skip = 0): static
    {
        $this->option['skip'] = min($skip, 1000);
        return $this;
    }

    /**
     * @return $this
     */
    public function limit(int $limit = 10): static
    {
        $this->option['limit'] = min($limit, 1000);
        return $this;
    }

    /**
     * @param string $field
     * @param string $asc
     * @return $this
     */
    public function orderBy(string $field, string $asc): static
    {
        if (strtolower($asc) == 'asc') {
            $this->option['sort'][$field] = 1;
        } else {
            $this->option['sort'][$field] = -1;
        }
        return $this;
    }

    /**
     *
     * array|Closure|string
     * Returns:
     * Builder
     * @param string|array $field
     * @param string|int|null $operator
     * @param null $value
     * @return MongoBasic
     */
    public function where(string|array $field, string|int|null $operator = null, $value = null): static
    {
        $where = [];
        if (gettype($field) == 'string') {
            if (in_array($operator, array_keys($this->opter_map))) {
                //如果第二个参数是操作符
                if ($field == $this->primaryKey) {
                    $field = '_id';
                    $value = parse_object_id($value);
                }
                $v = [$this->opter_map[$operator] => $value];
            } else {
                if ($field == $this->primaryKey) {
                    $field = '_id';
                    $operator = parse_object_id($operator);
                }
                //第二个参数是值
                $v = $operator;
            }
            //第二个参数是值
            $where[$field] = $v;
        } else if (gettype($field) == 'array') {
            foreach ($field as $key => $operator) {
                if ($key == $this->primaryKey) {
                    $where['_id'] = parse_object_id($operator);
                } else {
                    $where[$key] = $operator;
                }
            }
        }
        $this->filter = $this->mergeFilter($this->filter, $where);
        return $this;
    }


    /**
     * @param string $field
     * @param array $value
     * @return MongoBasic
     */
    public function whereIn(string $field, array $value): static
    {
        if ($field == $this->primaryKey) {
            $field = '_id';
            $value = array_map(function ($item) {
                return parse_object_id($item);
            }, $value);
        }
        $array = [$field => ['$in' => $value]];
        $this->filter = array_merge($this->filter, $array);
        return $this;
    }

    /**
     * @param string|array $field
     * @param array $array
     * @return $this
     */
    public function whereBetween(string|array $field, array $array): static
    {
        $where[$field] = ['$gte' => $array[0], '$lte' => $array[1]];
        $this->filter = array_merge($this->filter, $where);
        return $this;
    }

    /**
     * @param array $data
     * @return int
     * @throws \RedisException
     */
    public function update(array $data): int
    {
        if ($this->isSoftDelete) {
            $this->filter['deleted_at'] = null;
        }
        if (!$this->filter) {
            return 0;
        }
        if ($this->auto_timestamps) {
            $data['updated_at'] = time();
        }
        //判断是否要有redis缓存
        if (isset($this->useCache) && $this->useCache) {
            $infos = $this->get();
        }
        $result = $this->getCollection()->updateMany($this->filter, ['$set' => $data]);
        $count = $result->getModifiedCount();
        if ($count) {
            if (isset($infos)) {
                $argv = [];
                foreach ($infos as $info) {
                    $argv[] = (string)$info['id'];
                }
                if (isset($this->useCache) && $this->useCache) {
                    $this->batchDeleteRedisKey($argv);
                }
                if ($this instanceof EsInstanceInterface) {
                    $this->searchableMany($argv);
                }
            }

        }
        return $count;
    }

    /**
     * 同步单挑数据到es
     * @return int
     * @throws Exception
     */
    public function searchableOne(): int
    {
        $item = $this->first();
        // 检查对象中是否存在 'sampleMethod' 方法
        if (method_exists($this, 'toSearchableArray')) {
            $data = $item->toSearchableArray();
        } else {
            $data = $item->getAtrributes();
        }
        $result = $this->searchable_one($item->id, $data);

        return $result;
    }

    /**
     * 同步单挑数据到es
     * @return int
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function searchable(): int
    {
        $data = $this->get();
        $count = 0;
        /** @var self $item */
        foreach ($data as $item) {
            // 检查对象中是否存在 'sampleMethod' 方法
            if (method_exists($this, 'toSearchableArray')) {
                $data = $item->toSearchableArray();
            } else {
                $data = $item->getAtrributes();
            }
            $result = $this->searchable_one($item->id, $data);
            if ($result) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * @param bool $forceDelete
     * @return int
     * @throws Exception
     */
    public function delete(bool $forceDelete = false): int
    {
        if (!$forceDelete && $this->isSoftDelete) {
            if (!$this->filter) {
                throw  new \Exception('mongo filter is not empty in update');
            }
            $data['deleted_at'] = time();
            //判断是否要有redis缓存
            if (isset($this->useCache) && $this->useCache) {
                $infos = $this->get();
            }
            $count = $this->updateMany($this->filter, $data);
            if ($count) {
                if (isset($infos)) {
                    $argv = [];
                    foreach ($infos as $info) {
                        $argv[] = (string)$info['id'];
                    }
                    if (isset($this->useCache) && $this->useCache) {
                        $this->batchDeleteRedisKey($argv);
                    }
                    if ($this instanceof EsInstanceInterface) {
                        //删除es中的数据
                        $this->delete_es($argv);
                    }
                }
                return $count;
            }
        } else {
            $result = $this->getCollection()->deleteMany($this->filter);
            return $result->getDeletedCount();
        }
        return 1;
    }

    public function updateMany(array $filter, array $data): int
    {
        $result = $this->getCollection()->updateMany($filter, ['$set' => $data]);
        return $result->getModifiedCount();
    }

    /**
     * @param string $id
     * @param array $data
     * @return int
     * @throws \Exception
     */
    public function saveWithEs(string $id, array $data): int
    {
        $count = $this->where('id', $id)->update($data);
        if ($count > 0) {
            $this->where('id', $id)->searchable();
        }
        return $count;
    }

    public function updateManyWithEs(array $filter, array $data): int
    {
        $count = $this->updateMany($filter, $data);
        if ($count > 0) {
            $this->searchableMany($filter['_id']);
        }
        return $count;
    }

    /**
     * @param string $id
     * @param array $fields 查询字段
     * @return array
     */
    public function findById(string $id, array $fields = [])
    {
        foreach ($fields as $value) {
            $options['projection'][$value] = 1;
        }
        $this->filter['_id'] = parse_object_id($id);
        if ($this->isSoftDelete) {
            $this->filter['deleted_at'] = null;
        }
        $this->option['limit'] = 1;
        $result = $this->getCollection()->find($this->filter, $this->option);
        Log::info('mongo-findbByid查询返回结果', [$result, $this->filter, $this->option]);
        if ($result) {
            $result = $result[0];
            //返回一个Document对象
            $result = $this->changeObj($result);
        }
        return $result;
    }

    /**
     * @param string $field
     * @return mixed|null
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function value(string $field): mixed
    {
        $this->select([$field]);
        $this->option['limit'] = 1;
        $result = $this->getCollection()->find($this->filter, $this->option);
        if ($result) {
            $result = $result[0];
        }
        return $result ? $result[$field] : null;
    }

    /**
     * @return DocumentArr|array|object
     * @throws Exception
     */
    public function first()
    {
        if ($this->isSoftDelete) {
            $this->filter['deleted_at'] = null;
        }
        //finOne如何结果不存在直接抛出异常了
        //$result = $this->col->findOne($this->filter, $this->option);
        $this->option['limit'] = 1;
        $result = $this->getCollection()->find($this->filter, $this->option);
        Log::info('mongo-first查询返回结果', [$result, $this->filter, $this->option]);
        if ($result) {
            //取第一个
            $result = $result[0];
            //返回一个Document对象
            $result = $this->changeObj($result);
        }
        return $result ?: null;
    }

    /**
     * @param array $data
     * @return DocumentArr
     */

    public function changeObj(array $data): DocumentArr
    {
        $model = $this->getDocument($data);
        return $model;
    }

    /**
     * @param array $items
     * @return MongoCollection
     */
    public function toObject(array $items): MongoCollection
    {
        $array = [];
        $collection = new MongoCollection();
        foreach ($items as $data) {
            $doc = $this->getDocument($data);
            $array[] = $doc;
        }
        $collection->items = $array;
        return $collection;
    }

    /**
     * @param $key
     * @return array
     */
    public function pluck($key): array
    {
        $this->columns = [$key];
        $result = $this->get();
        $array = [];
        foreach ($result as $value) {
            $array[] = $value[$key];
        }
        return array_unique($array);
    }

    /**
     * @param $field
     * @return MongoBasic
     */
    public function groupBy($field): static
    {
        // 构建聚合管道
        $pipeline = [];
        if ($this->filter) {
            $pipeline[] = ['$match' => $this->filter];
        }
        $pipeline[] = [
            '$group' => [
                '_id' => '$' . $field, // 分组的键
                'document' => ['$first' => '$$ROOT'] // 使用 $first 累加器获取分组中的第一个文档
            ]
        ];
        $pipeline[] = [
            '$replaceRoot' => ['newRoot' => '$document'] // 将 document 字段提升为根文档
        ];
        $this->pipleline = $pipeline;
        return $this;
    }

    /**
     * @param int $num
     * @param callable $callable
     * @return array
     */
    public function chunk(int $num, callable $callable): array
    {
        return $this->collections;
    }

    /**
     * @return MongoCollection
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function get()
    {
        $result = $this->getArr();
        return $this->toObject($result);
    }

    /**
     * 两个数组合并
     * @param array $data1
     * @param array|null $data2
     * @param $withKey
     * @return array
     */
    public function hasOne(mixed $data1, mixed $data2, $withKey)
    {
        $data1[$withKey] = $data2;
        return $data1;
    }

    /**
     * 两个数组合并
     * @param array|MongoCollection $data1
     * @param array|MongoCollection $data2
     * @param string $withKey
     * @param string $filed1
     * @param string $filed2
     * @return array|MongoCollection
     */
    public function with(array|MongoCollection $data1, mixed $data2, string $withKey, string $filed1, string $filed2): array|MongoCollection
    {
        /**
         * @var  $key
         * @var array|MongoBasic $value
         */
        foreach ($data1 as $key => $value) {
            $value->addRelation([$withKey => null]);
            foreach ($data2 as $v) {
                if ($value[$filed1] == $v[$filed2]) {
                    $value->addRelation([$withKey => $v]);
                }
            }
            $data1[$key] = $value;
        }
        return $data1;
    }

    /**
     * 两个数组合并
     * @param array|MongoCollection $data1
     * @param array|MongoCollection $data2
     * @param string $withKey
     * @param string $filed1
     * @param string $filed2
     * @return array|MongoCollection
     */
    public function withMany(array|MongoCollection $data1, array|MongoCollection $data2, string $withKey, string $filed1, string $filed2): array|MongoCollection
    {
        /**
         * @var  $key
         * @var array|MongoBasic $value
         */
        foreach ($data1 as $key => $value) {
            //            $value->addRelation([$withKey => []]);
            $items = [];
            foreach ($data2 as $v) {
                if ($value[$filed1] == $v[$filed2]) {
                    $items[] = $v;
                }
            }
            $value->addRelation([$withKey => $items]);
            $data1[$key] = $value;
        }
        return $data1;
    }

    /**
     * @return int
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function count(): int
    {
        if ($this->isSoftDelete) {
            $this->filter['deleted_at'] = null;
        }
        foreach ($this->columns as $value) {
            $this->option['projection'][$value] = 1;
        }
        return $this->getCollection()->countDocuments($this->filter, $this->option);
    }


    /**
     * @param $filter
     * @param $where
     * @return mixed|void
     */
    public function mergeFilter($filter, $where)
    {
        $array = $filter;
        foreach ($where as $key => $value) {
            if (array_key_exists($key, $array)) {
                //如果是数组则合并
                if (gettype($value) == 'array' && gettype($array[$key]) == 'array') {
                    $array[$key] = array_merge($array[$key], $value);
                } else {
                    //如果不都是数组，则后面值覆盖掉前面
                    $array[$key] = $value;
                }
            } else {
                // 新键直接添加
                $array[$key] = $value;
            }
        }
        return $array;
    }

    /**
     * @param mixed $data
     * @return MongoBasic
     */
    public function getDocument(mixed $data): MongoBasic
    {
        $doc = new static();
        $arr = [];
        foreach ($data as $key => $value) {
            //处理主键
            if ($key == '_id') {
                $tempkey = $this->primaryKey;
                $arr[$tempkey] = (string)$value;
            } else {
                $arr[$key] = $value;
                //处理返回时间格式
                if ($key == self::CREATED_AT || $key == self::UPDATED_AT) {
                    if ($this->dateFormat == 'U') {
                        try {
                            if (gettype($value) == 'integer') {
                                $arr[$key] = date('Y-m-d H:i:s', $value);
                            }
                        } catch (\Throwable $e) {
                        }
                    }
                }
            }
        }
        $doc->attributes = $arr;
        return $doc;
    }

    /**
     * @param array $data
     * @return string
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function save(array $data = []): string
    {
        if (!$data) {
            $data = $this->attributes;
        }
        if ($this->auto_timestamps) {
            $data['created_at'] = time();
            $data['updated_at'] = time();
        }
        $insertId = $this->getCollection()->insertOne($data)->getInsertedId();

        if ($insertId && $this instanceof EsInstanceInterface) {
            //如果使用了es则同步到es
            $this->where('id', $insertId->__toString())->searchable();
        }
        return $insertId->__toString();
    }
}