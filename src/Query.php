<?php
namespace MongodbPhp7;

use MongoDB\BSON\ObjectId;
use MongoDB\Driver\Manager;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\WriteConcern;
use MongoDB\Driver\WriteResult;

use MongodbPhp7\exception\QueryException;
use MongodbPhp7\Connection;

require_once 'exception/QueryException.php';
class Query
{
    /**
     * @var Connection
     */
    protected $connection;


    /**
     * 查询参数
     * @var array
     */
    protected $options = [];

    protected $insertId;

    /**
     * Query constructor.
     * @param $connection
     */
    public function __construct($connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return \MongodbPhp7\Connection
     */
    public function getConnection() {
        return $this->connection;
    }




    /**
     * @param $data
     * @param $getLastInsID
     * @return int|null
     */
    public function insert($data, $getLastInsID = false) {

        $bulk = new BulkWrite;
        if ($insertId = $bulk->insert($data)) {
            $this->insertId = $insertId;
        }

        $writeResult = $this->connection->execute($this->connection->getCollection(), $bulk);

        $this->log('insert', $data);

        if ($getLastInsID) {
            return $this->getLastInsID();
        }
        return $writeResult->getInsertedCount();
    }

    /**
     * @param $dataSet
     * @return int|null
     */
    public function insertAll($dataSet) {
        $bulk = new BulkWrite;
        $this->insertId = [];

        foreach ($dataSet as $data) {
            if ($insertId = $bulk->insert($data)) {
                $this->insertId[] = $insertId;
            }
        }
        $writeResult = $this->connection->execute($this->connection->getCollection(), $bulk);

        $this->log('insert', $dataSet);
        return $writeResult->getInsertedCount();
    }

    /**
     * 获取最后写入的ID 如果是insertAll方法的话 返回所有写入的ID
     * @access public
     * @return mixed
     */
    public function getLastInsID()
    {
        $id = $this->insertId;
        if (is_array($id)) {
            array_walk($id, function (&$item, $key) {
                if ($item instanceof ObjectID) {
                    $item = $item->__toString();
                }
            });
        } elseif ($id instanceof ObjectID) {
            $id = $id->__toString();
        }
        return $id;
    }

    /**
     * @param string $config
     * @return array|mixed
     */
    protected function getConnectionConfig($config = '') {
        return $this->connection->getConfig($config);
    }

    /**
     * 记录debug日志
     * @param $type
     * @param $data
     * @param array $options
     */
    protected function log($type, $data, $options = [])
    {
        if ($this->getConnectionConfig('debug')) {
            $this->connection->log($type, $data);
        }
    }
}