<?php

namespace fairwic\MongoOrm;

use ArrayIterator;
use IteratorAggregate;

class MongoCollection implements IteratorAggregate, \ArrayAccess, \JsonSerializable
{
    public array $items = [];

    public function __construct()
    {

    }

    public function items()
    {
        return $this->items;
    }

    public function getIterator(): ArrayIterator
    {
        // 返回一个 ArrayIterator，使得 foreach 能够遍历 $this->items 数组
        return new ArrayIterator($this->items);
    }

    // 添加其他有用的 Collection 方法，如添加、删除项等
    public function add($item): void
    {
        $this->items[] = $item;
    }

    public function all()
    {
        return $this->items;
    }


    public function __get(string $name)
    {
        // 检查属性是否存在
        if (array_key_exists($name, $this->items)) {
            return $this->items[$name];
        }
        // TODO: Implement __get() method.
    }

    public function __set(string $name, $value)
    {
        if (!array_key_exists($name, $this->items)) {
            $this->items[$name] = $value;
        }
    }

    public function toArray(): array
    {
        foreach ($this->items as $key => $value) {
            /** @var $value MongoModel */
            $this->items[$key] = $value->getAtrributes();
            if ($value->getRelations()){
                foreach ($value->getRelations() as $k => $v){
                    $this->items[$key][$k] = $v;
                }
            }
        }
        return $this->items;
    }

    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetExists($offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetUnset($offset): void
    {
        unset($this->items[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->items;
        // TODO: Implement jsonSerialize() method.
    }
}