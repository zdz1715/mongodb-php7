<?php
namespace MongodbPhp7;

use MongoDB\BSON\ObjectId;
use MongoDB\Driver\Command;
use MongoDB\Driver\Cursor;
use MongoDB\Driver\Manager;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\WriteConcern;
use MongoDB\Driver\WriteResult;

use MongodbPhp7\exception\QueryException;
use MongodbPhp7\Connection;

class Query
{

    const FIELD_SHOW = 1;
    const FIELD_HIDDEN = 0;



    /**
     * @var Connection
     */
    protected $connection;

    /**
     * 默认查询参数
     * @var array
     */
    protected $defaultOptions = [
        // 查找和更新此主键会转化成对应的形式，如：查找 ：_id = 5d71c5415c998d3dc4006832, 更新: _id 会处理成objectID类型
        'pk_convert_string' => false
    ];

    /**
     * 当前查询参数
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
     * 设置当前的查询参数
     * @param $option  string $option 参数名
     * @param $value  mixed  $value  参数值
     * @return $this
     */
    protected function setOption($option, $value)
    {
        $this->options[$option] = $value;
        return $this;
    }

    /**
     * 去除查询参数
     * @param bool $option  参数名 true 表示去除所有参数
     * @return $this
     */
    public function removeOption($option = true)
    {
        if (true === $option) {
            $this->options = $this->defaultOptions;
        } elseif (is_string($option) && isset($this->options[$option])) {
            unset($this->options[$option]);
        }

        return $this;
    }


    /**
     * 获取当前的查询参数
     * @param string $name    参数名
     * @param array $default  不存在返回的默认值
     * @return array|mixed
     */
    public function getOptions($name = '', $default = [])
    {
        if ('' === $name) {
            return $this->options;
        }
        return isset($this->options[$name]) ? $this->options[$name] : $default;
    }

    /**
     * where
     * @param $where
     * @return $this
     */
    public function where($where) {
        return $this->setOption('where', $this->parseWhere($where));
    }

    /**
     * @param $limit
     * @return $this
     */
    public function limit($limit) {
        return $this->setOption('limit', $limit);
    }


    /**
     * @param $bool
     * @return Query
     */
    public function pcs($bool) {
        return $this->setOption('pk_convert_string', $bool);
    }


    /**
     * @param $sort
     * @return $this
     */
    public function sort($sort) {
        return $this->setOption('sort', $this->parseSort($sort));
    }


    /**
     * @param $upsert
     * @return $this
     */
    public function upsert($upsert) {
        return $this->setOption('upsert', $upsert);
    }

    /**
     * 指定显示字段
     * @param $field
     * @return $this
     */
    public function field($field) {
        return $this->setOption('field', $this->parseField($field));
    }

    /**
     * 分组
     * @param $group
     * @return $this
     */
    public function group($group) {
        return $this->setOption('group', $group);

    }


    /**
     * @param $field
     * @return array
     */
    protected function parseField($field) {
        if (empty($field)) {
            return [];
        }

        $result = [
            $this->getConnectionConfig('pk')   => self::FIELD_HIDDEN
        ];

        if (is_string($field)) {
            $field = explode(',', $field);
        }

        if (is_array($field)) {
            foreach ($field as $k => $f) {
                if (is_array($f)) {
                    $result[$k] = $f;
                } else {
                    $result[$f] = self::FIELD_SHOW;
                }
            }
        }
        return $result;
    }


    /**
     * 处理inset数据
     * @param $data
     * @return array
     */
    protected function parseData($data) {
        if (empty($data)) {
            return [];
        }

        $result = [];
        foreach ($data as $key => $val) {
            $item = $this->parseKey($key);
            $result[$item] = $this->parseValue($val, $key);

        }
        return $result;
    }

    /**
     * @param $data
     * @param bool $getLastInsID
     * @return int|mixed|null
     * @throws QueryException
     */
    public function insert($data, $getLastInsID = false) {
        $data = $this->parseData($data);

        if (empty($data)) {
            throw new QueryException('no data insert');
        }


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
     * 处理更新字段
     * @param $key
     * @return string
     */
    protected function parseKey($key) {
        return trim($key);
    }

    /**
     * value分析
     * @access protected
     * @param mixed     $value
     * @param string    $field
     * @return string
     */
    protected function parseValue($value, $field = '')
    {
        $pk = $this->getConnectionConfig('pk');
        if ($pk == $field && is_string($value)) {
            try {
                return new ObjectID($value);
            } catch (\InvalidArgumentException $e) {
                return new ObjectID();
            }
        }
        return $value;
    }

    /**
     * @param $setData
     * @return mixed
     */
    protected function parseSet($setData) {
        if (empty($setData)) {
            return $setData;
        }
        $result = [];
        foreach ($setData as $key => $val) {
            $item = $this->parseKey($key);
            if (is_array($val) && isset($val[0]) && is_string($val[0]) && 0 === strpos($val[0], '$')) {
                $result[$val[0]][$item] = $this->parseValue($val[1] ?? null, $item);
            } else {
                $result['$set'][$item] = $this->parseValue($val, $item);
            }
        }
        return $result;
    }

    /**
     * @param $field
     * @param $value
     * @return int|null
     * @throws QueryException
     */
    public function setField($field, $value) {
        return $this->update([
            $field  => $value
        ]);
    }

    /**
     * @param $field
     * @param int $step
     * @return int|null
     * @throws QueryException
     */
    public function setInc($field, $step = 1) {
        return $this->update([
            $field => ['$inc', $step]
        ]);
    }

    /**
     * @param $field
     * @param int $step
     * @return int|null
     * @throws QueryException
     */
    public function setDec($field, $step = 1) {
        return $this->update([
            $field => ['$inc', -$step]
        ]);
    }


    /**
     *  更新数据
     * @param $document
     * @return int|null
     * @throws QueryException
     */
    public function update($document) {
        $where = $this->getOptions('where');
        if (empty($where)) {
            throw new QueryException('miss update condition');
        }

        $document = $this->parseSet($document);

        if (empty($document)) {
            throw new QueryException('no data update');
        }

        $limit = $this->getOptions('limit');

        $multi = $limit == 1 ? false : true;

        $bulk = new BulkWrite;
        $updateSetting = [
            'multi'     => $multi,
            'upsert'    => $this->getOptions('upsert', false)
        ];
        $options = [
            'where'     => $where,
            'up_set'    => $updateSetting
        ];
        $bulk->update($where, $document, $updateSetting);

        $this->log('update', $document, $options);

        $writeResult = $this->connection->execute($this->connection->getCollection(), $bulk);
        return $writeResult->getMatchedCount();
    }


    /**
     * 删除
     * @return int|null
     * @throws QueryException
     */
    public function delete() {
        $where = $this->getOptions('where');
        if (empty($where)) {
            throw new QueryException('miss delete condition');
        }

        $limit = $this->getOptions('limit');

        $limit = $limit == 1 ? true : false;

        $bulk = new BulkWrite;
        $delSetting = [
            'limit'     => $limit
        ];

        $bulk->delete($where, $delSetting);
        $writeResult = $this->connection->execute($this->connection->getCollection(), $bulk);

        $delOption = [
            'justOne'   => $limit
        ];
        $this->log('remove', $where, $delOption);
        return $writeResult->getDeletedCount();
    }

    /**
     * @param $sort
     * @return mixed
     */
    protected function parseSort($sort) {
        return $sort;
    }


    /**
     * @param $where
     * @return array
     */
    protected function parseWhere($where) {
        if (empty($where)) {
            return [];
        }
        $result = [];
        foreach ($where as $key => $val) {
            $item = $this->parseKey($key);
            $result[$item] = $this->parseValue($val, $item);
        }
        return $result;
    }


    /**
     * 解析管道操作
     * @return array
     * @throws \MongoDB\Driver\Exception\Exception
     */
    protected function parseAggregate() {
        list($database, $collection) = $this->connection->getCollection(true);
        $pipeline = [];
        $project = $this->getOptions('field');
        $sort = $this->getOptions('sort');
        $where = $this->getOptions('where');
        $limit = $this->getOptions('limit', 0);
        $group = $this->getOptions('group');
        $skip = $this->getOptions('skip', 0);
        $count = $this->getOptions('count', false);

        if (!empty($where)) {
            $pipeline[]['$match'] = $where;
        }

        if (!empty($group)) {
            $pipeline[]['$group'] = $group;
        }

        if (!empty($sort)) {
            $pipeline[]['$sort'] = $sort;
        }

        if ($skip > 0) {
            $pipeline[]['$skip'] = $skip;
        }


        if ($limit > 0) {
            $pipeline[]['$limit'] = $limit;
        }

        if (!empty($project)) {
            $pipeline[]['$project'] = $project;
        }

        if ($count) {
            if ($this->connection->getVersion() >= '3.4') {
                $pipeline[]['$count'] = $count;
            } else {
                $pipeline[]['$group'] = [
                    '_id'   => null,
                    $count  => [
                        '$sum'  => 1
                    ]
                ];
            }
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
     * 查找一条
     * @return array|mixed
     * @throws QueryException
     * @throws \MongoDB\Driver\Exception\Exception
     */
    public function find() {
        $this->limit(1);
        return $this->select()[0] ?? [];
    }

    /**
     * @param $page
     * @return mixed
     * @throws QueryException
     * @throws \MongoDB\Driver\Exception\Exception
     */
    public function page($page) {
        $page = $page < 1 ? 1 : $page;
        if ($this->getOptions('limit', 0) <= 0) {
            $this->limit($this->getConnectionConfig('rows_limit'));
        }
        $this->setOption('skip', ($page - 1) * $this->options['limit']);
        return $this->select();
    }

    /**
     * 查找一个值
     * @param $field
     * @param string $default
     * @return array|bool|mixed
     * @throws QueryException
     * @throws \MongoDB\Driver\Exception\Exception
     */
    public function value($field, $default = '') {
        if (!is_string($field)) {
            return false;
        }
        $this->field($field);
        $find = $this->find();
        return $find[$field] ?? [];
    }

    /**
     * @return array|bool|mixed
     * @throws QueryException
     * @throws \MongoDB\Driver\Exception\Exception
     */
    public function count() {
        $this->setOption('count', '__cols__');
        $count = $this->column('__cols__')[0];
        return $count;
    }

    /**
     * @param $field
     * @param string $default
     * @return array|bool|mixed
     * @throws QueryException
     * @throws \MongoDB\Driver\Exception\Exception
     */
    public function column($field) {
        $this->field($field);
        $select = $this->select();
        if (is_string($field) && strpos($field, ',')) {
            $field = explode(',', $field);
        }
        $field = is_array($field) ? $field : [$field];
        $result = [];
        $count = count($field);
        if ($count > 1) {
            $result = array_column($select, null, $field[0]);
        } else {
            $result = array_column($select, $field[0]);
        }
        return $result;
    }

    /**
     * @return mixed
     * @throws QueryException
     * @throws \MongoDB\Driver\Exception\Exception
     */
    public function select() {
        $aggregate = $this->parseAggregate();
        $originCommand = $aggregate['query'] ?? [];
        $database = $aggregate['database'] ?? '';
        if ($database == '') {
            throw new QueryException('Undefined db database');
        }
        $command = new Command($originCommand);

        $cursor = $this->connection->command($command, $database);

        $this->log('aggregate', $originCommand);

        return $this->connection->getResult($cursor);

    }


    /**
     * @param $dataSet
     * @return int|null
     * @throws QueryException
     */
    public function insertAll($dataSet) {
        if (empty($dataSet)) {
            throw new QueryException('no data insert');
        }

        $bulk = new BulkWrite;
        $this->insertId = [];

        foreach ($dataSet as $data) {
            // 分析并处理数据
            $data = $this->parseData($data);
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
        if ($this->getOptions('pk_convert_string')) {
            $id = $this->pkConvertString($id);
        }
        return $id;
    }



    /**
     * @param $id
     * @return array|string
     */
    public function pkConvertString($id) {
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
            $this->connection->log($type, $data, $options);
        }
    }
}
