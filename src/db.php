<?php


use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Command;
use MongoDB\Driver\Manager;
use MongoDB\Driver\WriteConcern;
use MongoDB\Driver\Query;

class db
{
    const FIELD_SHOW = 1;
    const FIELD_HIDDEN = 0;

    const SORT_ASC = 1;
    const SORT_DESC = -1;

    /**
     * @var MongoDB\Driver\Manager
     */
    private static $manager;

    protected $host;
    protected $port;
    protected $managerOption;
    protected $database = '';

    protected $collection;
    /**
     * @var MongoDB\Driver\WriteResult
     */
    protected $writeResult;
    /**
     * @var \MongoDB\Driver\Cursor
     */
    protected $cursor;

    protected $pk = '_id';

    protected $lastSql;
    /**
     * 当前查询参数
     * @var array
     */
    protected $options = [];

    /**
     * db constructor.
     * @param $host
     * @param int $port
     * @param array $option
     * @param string $database
     */
    public function __construct($host, $database, $port = 27017, $option = [])
    {
        $this->host = $host;
        $this->port = $port;
        $this->managerOption = $option;

        if ($database != '') {
            $this->setDataBase($database);
        }

        $this->connection();
    }

    /**
     * @param $database
     */
    public function setDataBase($database) {
        $this->database = $database;
    }


    /**
     * @param $collection
     * @return $this
     */
    public function collection($collection) {
        // 防止依赖注入保留查询参数
        $this->removeOption(true);

        $this->collection = $this->parseCollectionName($collection);
        return $this;
    }

    /**
     * @param $where
     * @return $this
     */
    public function where($where) {
        $this->options['where'] = $where;
        return $this;
    }

    /**
     * todo 暂时没找到实现方法
     * @return mixed
     */
    private function getLastSql() {
        return $this->lastSql;
    }

    /**
     * @param $project
     * @return $this
     */
    public function project($project) {
        $this->options['project'] = [
            $this->pk   => self::FIELD_HIDDEN
        ];
        if (is_string($project)) {
            $project = explode(',', $project);
        }
        if (is_array($project)) {
            foreach ($project as $k => $p) {
                if (is_array($p)) {
                    $this->options['project'][$k] = $p;
                } else {
                    $this->options['project'][$p] = self::FIELD_SHOW;
                }
            }
        }
        return $this;
    }

    /**
     * @param $sort
     * @return $this
     */
    public function sort($sort) {
        $this->options['sort'] = $sort;
        return $this;
    }

    /**
     * @return Manager
     */
    private function connection() {
        if (empty($this->host)) {
            throw new InvalidArgumentException('Undefined db host');
        }

        self::$manager = new Manager("mongodb://{$this->host}:{$this->port}", $this->managerOption);
    }


    /**
     * 解析文档名 如果有数据库，则不加前缀数据库名，无则加上数据库
     * @param $database
     * @param $collection
     * @return string
     */
    private function parseCollectionName($collection) {
        $split = '.';
        if (strpos($collection, $split) === false) {
            return $this->database. $split .$collection;
        }
        return $collection;
    }




    /**
     * 插入一条数据
     * @param $document
     * @return bool
     */
    public function insert($document) {
        $bulk = $this->createBulkObject();
        $bulk->insert($document);
        $result = $this->executeBulkWrite($bulk);
        return $result;
    }





    /**
     * 批量插入
     * @param $document
     * @return bool
     */
    public function insertAll($document) {
        $bulk = $this->createBulkObject();

        foreach ($document as $row) {
            $bulk->insert($row);
        }
        $result = $this->executeBulkWrite($bulk);
        return $result;
    }





    /**
     *  更新数据
     * @param $document
     * @param bool $multi    是否更新多条  默认true
     * @param bool $upsert  不存在数据则创建，默认false
     * @param array $where
     * @return bool
     */
    public function update($document, $multi = true, $upsert = false, $where = []) {
        $bulk = $this->createBulkObject();
        $optionsWhere = $this->options['where'] ?? [];
        $where = array_merge($optionsWhere, $where);
        $bulk->update($where, $document, [
            'multi'     => $multi,
            'upsert'    => $upsert
        ]);
        $result = $this->executeBulkWrite($bulk);
        return $result;
    }


    /**
     * 批量更新
     * @param $document
     * @return bool
     */
    public function updateAll($document) {
        $bulk = $this->createBulkObject();
        foreach ($document as $row) {
            $bulk->update(...$row);
        }
        $result = $this->executeBulkWrite($bulk);
        return $result;
    }

    /**
     * 删除
     * @param $where
     * @param bool $limit
     * @return bool
     */
    public function delete($where, $limit = false) {
        $bulk = $this->createBulkObject();
        $bulk->delete($where, [
            'limit' => $limit
        ]);
        $result = $this->executeBulkWrite($bulk);
        return $result;
    }


    /**
     * 去除查询参数
     * @access public
     * @param  string|bool $option 参数名 true 表示去除所有参数
     * @return $this
     */
    public function removeOption($option = true)
    {
        if (true === $option) {
            $this->options = [];
        } elseif (is_string($option) && isset($this->options[$option])) {
            unset($this->options[$option]);
        }

        return $this;
    }

    /**
     * 解析管道操作
     * @return array
     */
    public function parseAggregate() {
        list($database, $collection) = explode('.', $this->collection);
        $pipeline = [];
        $project = $this->options['project'] ?? [];
        $sort = $this->options['sort'] ?? [];
        $where = $this->options['where'] ?? [];

        if (!empty($where)) {
            $pipeline[]['$match'] = $where;
        }

        if (!empty($project)) {
            $pipeline[]['$project'] = $project;
        }

        if (!empty($sort)) {
            $pipeline[]['$sort'] = $sort;
        }



        $query = [
            'aggregate' => $collection,
            'pipeline'  => $pipeline,
            'cursor'    => new \stdClass()
        ];
        return [
            'query' => $query,
            'database' => $database
        ];
    }





    /**
     * @return mixed
     */
    public function select() {
       return $this->executeCommand($this->parseAggregate());
    }

    public function setInc($field, $num = 1) {

    }

    public function setDec($field, $num = 1) {

    }


    /**
     * @param $query
     * @param array $option
     * @return mixed
     */
    private function executeCommand($query, $option = []) {
        $command = $query['query'] ?? [];
        $database = $query['database'] ?? '';
        if ($database == '') {
            throw new InvalidArgumentException('Undefined db database');
        }
        $command = new Command($command);
        try {
            $this->cursor = self::$manager->executeCommand($database, $command, $option);
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            throw new \MongoDB\Driver\Exception\LogicException($e->getMessage());
        }
        return json_decode(json_encode($this->cursor->toArray()), true);
    }

    /**
     * @param $bulk
     * @param string $w
     * @param int $timeout
     * @return bool
     */
    private function executeBulkWrite($bulk, $w = WriteConcern::MAJORITY, $timeout = 1000) {
        if ($this->collection == '') {
            throw new InvalidArgumentException('Undefined db collection');
        }

        $writeConcern = new WriteConcern($w, $timeout);
        var_dump($bulk);
        $this->writeResult = self::$manager->executeBulkWrite($this->collection, $bulk, $writeConcern);
        return $this->writeResult->isAcknowledged();
    }

    /**
     * @return BulkWrite
     */
    private function createBulkObject() {
        return new BulkWrite();
    }


    /**
     * @param $filter
     * @param $option
     * @return Query
     */
    private function createQueryObject($filter, $option) {
        return new Query($filter, $option);
    }

    public function getManager() {
        return self::$manager;
    }

    public function getCursor() {
        return $this->cursor;
    }

    /**
     * @return \MongoDB\Driver\WriteResult
     */
    public function getWriteResult() {
        return $this->writeResult;
    }

    public function __destruct() {}
}




