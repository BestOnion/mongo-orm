<?php

namespace Hyperf\HyperfMongoOrm\mongo;

use Hyperf\HyperfMongoOrm\Elasticsearch\EsTrait;
use Hyperf\Di\Annotation\Inject;
use PhpParser\Builder\Trait_;

trait  SoftDelete
{

    public function delete()
    {
        var_dump('tarit delete');
    }

}