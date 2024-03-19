<?php

namespace fairwic\MongoOrm;
class QueryBuilder
{
    protected array $query = [];
    protected array $filters = [];
    protected array $options = [];

    public function select(array $fields): self
    {
        $this->options['projection'] = array_fill_keys($fields, 1);
        return $this;
    }

    public function where($field, $operator, $value): self
    {
        $this->filters[] = compact('field', 'operator', 'value');
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->options['limit'] = $limit;
        return $this;
    }

    public function skip(int $skip): self
    {
        $this->options['skip'] = $skip;
        return $this;
    }

    public function sort($field, $direction): self
    {
        $this->options['sort'] = [$field => $direction];
        return $this;
    }

    public function getQuery(): array
    {
        return $this->buildQuery();
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    protected function buildQuery(): array
    {
        if (empty($this->filters)) {
            return [];
        }

        $query = ['$and' => []];
        foreach ($this->filters as $filter) {
            $query['$and'][] = $this->buildFilter($filter);
        }

        return $query;
    }

    protected function buildFilter(array $filter): array
    {
        $field = $filter['field'];
        $operator = $filter['operator'];
        $value = $filter['value'];

        if (is_array($value)) {
            $condition = ['$' . $operator => ['$in' => $value]];
        } else {
            $condition = ['$' . $operator => $value];
        }

        return [$field => $condition];
    }
}