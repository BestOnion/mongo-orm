<?php

namespace Hyperf\HyperfMongoOrm\Elasticsearch;
use Hyperf\HyperfMongoOrm\Elasticsearch\Es;

interface EsInstanceInterface
{
    public function getEsInstance(): Es;
    public function getEsFields(): array;
}