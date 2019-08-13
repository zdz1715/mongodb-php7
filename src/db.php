<?php


use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Command;
use MongoDB\Driver\Manager;
use MongoDB\Driver\WriteConcern;
use MongoDB\Driver\Query;

class db
{
    protected $fieldShow = 1;
    protected $fieldHidden = 0;

    /**
     * @var MongoDB\Driver\Manager
     */
    private static $manager;

    protected $host;
    protected $port;
    protected $option;
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

    protected $where;
    protected $field;

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
        $this->option = $option;

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
        $this->collection = $this->parseCollectionName($collection);
        return $this;
    }

    /**
     * @param $where
     * @return $this
     */
    public function where($where) {
        $this->where = $where;
        return $this;
    }

    /**
     * @param $field
     * @return $this
     */
    public function field($field) {
        $this->field = [
            '_id'   => $this->fieldHidden
        ];
        if (is_string($field)) {
            $field = explode(',', $field);
        }
        if (is_array($field)) {
            foreach ($field as $f) {
                $this->field[$f] = $this->fieldShow;
            }
        }
        return $this;
    }

    /**
     * @return Manager
     */
    private function connection() {
        if (empty($this->host)) {
            throw new InvalidArgumentException('Undefined db host');
        }

        self::$manager = new Manager("mongodb://{$this->host}:{$this->port}", $this->option);
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
        $where = array_merge($this->where, $where);
        $bulk->update($this->where, $document, [
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



    public function parseAggregate() {
        list($database, $collection) = explode('.', $this->collection);
        $aggregate = [
            'aggregate' => $collection
        ];
        $pipeline = [];
        if ($this->field) {
            $pipeline[]['$project'] = $this->field;
        }
        $aggregate['pipeline'] = $pipeline;
        $aggregate['cursor'] = new \stdClass();
        return [
            'aggregate' => $aggregate,
            'database' => $database
        ];
    }


    /**
     * @return \MongoDB\Driver\Cursor
     * @throws \MongoDB\Driver\Exception\Exception
     */
    public function select() {
       return $this->executeCommand($this->parseAggregate());
    }


    /**
     * @param $command
     * @param array $option
     * @return \MongoDB\Driver\Cursor
     * @throws \MongoDB\Driver\Exception\Exception
     */
    private function executeCommand($query, $option = []) {
        $command = $query['aggregate'] ?? [];
        $database = $query['database'] ?? '';
        if ($database == '') {
            throw new InvalidArgumentException('Undefined db database');
        }
        $command = new Command($command);
        $this->cursor = self::$manager->executeCommand($database, $command, $option);
        return json_decode(json_encode($this->cursor->toArray()), true);
    }


    private function executeBulkWrite($bulk, $w = WriteConcern::MAJORITY, $timeout = 1000) {
        if ($this->collection == '') {
            throw new InvalidArgumentException('Undefined db collection');
        }

        $writeConcern = new WriteConcern($w, $timeout);
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
}




