<?php

namespace fairwic\MongoOrm;

use ArrayIterator;
use Iterator;
use IteratorAggregate;
use Traversable;

class DocumentArr implements \ArrayAccess
{
    protected array $attributes = [];
    protected array $relations = [];

    public function getAtrributes()
    {
        return $this->attributes;
    }

    public function getRelations()
    {
        return $this->relations;
    }

    /**
     * 获取不存在的属性时候
     * @param string $name
     * @return mixed|void
     */
    public function __get(string $name)
    {
        //        var_dump(__FUNCTION__);
        // 检查属性是否存在
        if (array_key_exists($name, $this->attributes)) {
            return $this->attributes[$name];
        } else if ($this->relations) {
            foreach ($this->relations as $key => $item) {
                if ($key == $name) {
                    return $item;
                }
            }
        }
        return [];
    }

    /**
     * add property
     * @param string $name
     * @param $value
     * @return void
     */
    public function __set(string $name, $value)
    {
        if (!array_key_exists($name, $this->attributes)) {
            //新增key
            $this->attributes[$name] = $value;
        }else{
            //更新值
            $this->attributes[$name] = $value;
        }
    }

    public function __unset(string $name): void
    {
        // 检查属性是否存在
        if (array_key_exists($name, $this->attributes)) {
            unset($this->attributes[$name]);
        } else if ($this->relations) {
            foreach ($this->relations as $key => $item) {
                if ($key == $name) {
                    unset($this->relations[$key]);
                }
            }
        }
    }

    public function toArray()
    {
        return $this->attributes;
    }


    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->attributes[] = $value;
        } else {
            $this->attributes[$offset] = $value;
        }
    }

    public function offsetExists($offset): bool
    {
        if (isset($this->attributes[$offset])) {
            return true;
        } else {
            return isset($this->relations[$offset]);
        }
    }

    public function offsetUnset($offset): void
    {
        if (isset($this->attributes)) {
            unset($this->attributes[$offset]);
        } else {
            unset($this->relations[$offset]);
        }
    }

    public function offsetGet($offset): mixed
    {
        if (isset($this->attributes[$offset])) {
            return $this->attributes[$offset];
        } else if ($this->relations) {
            if (isset($this->relations[$offset])) {
                return $this->relations[$offset];
            }
        }
        return null;
    }


}