<?php
namespace MongodbPhp7;

use MongoDB\BSON\ObjectId;
use MongoDB\Driver\Command;
use MongoDB\Driver\Manager;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\WriteConcern;
use MongoDB\Driver\WriteResult;
use MongoDB\Driver\Cursor;

use MongodbPhp7\exception\MongoException;
use MongodbPhp7\Query;

class Connection
{
    const SORT_ASC = 1;
    const SORT_DESC = -1;


    // 实例对象
    protected static $instance = [];


    /**
     * 集合全称
     * @var
     */
    protected $namespace;

    /**
     * 执行的sql语句
     * @var string
     */
    protected $queryStr = '';

    /**
     * @var Query;
     */
    protected $query;


    /**
     * 连接
     * @var array
     */
    protected $link = [];

    /**
     * @var Manager
     */
    protected $manager;

    /**
     * @var WriteResult
     */
    protected $writeResult;

    /**
     * @var Cursor
     */
    protected $cursor;

    // 查询参数
    protected $options = [];

    /**
     * @var WriteConcern|null $writeConcern
     */
    protected $writeConcern = null;


    /**
     * @var ReadPreference|null $readPreference
     */
    protected $readPreference = null;

    /**
     * 影响的行数
     * @var
     */
    public $numRows;

    /**
     * 连接配置
     * @var array
     */
    protected $config = [
        // 服务器地址
        'host'              => '',
        // 端口
        'port'              => 27017,
        // 数据库名
        'database'          => 'test',
        // 用户名
        'username'          => '',
        // 密码
        'password'          => '',
        // 连接dsn
        'dsn'               => '',
        // MongoDB\Driver\Manager option参数
        'option'            => [],
        // 主键字段，会处理成objectID类型
        'pk'                => '_id',
        // 数据库表前缀
        'prefix'            => '',
        // 查询类型
        'type_map'          => ['root' => 'array', 'document' => 'array'],
        // 开启debug 支持: 记录最后一条指令
        'debug'             => true,
        // 默认分页一页数量
        'rows_limit'        => 10
    ];


    /**
     * @param array $config
     * @param bool $force
     * @return Connection
     */
    public static function instance($config = [], $force = false)
    {
        $name = md5(serialize($config));
        if ($force === true || !isset(self::$instance[$name])) {
            self::$instance[$name] = new static($config);
        }

        return self::$instance[$name];
    }



    public function __destruct() {
        $this->close();
    }

    /**
     * 释放连接
     */
    public function close() {
        $this->manager = null;
        $this->writeResult = null;
        $this->cursor = null;
    }





    /**
     * Connection constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->initConfig($config);
        $this->connect();

        $this->query = new Query($this);
    }

    /**
     * @param $w
     * @param int $timeout
     * @param bool $journal
     */
    public function setWriteConcern($w, $timeout = 1000, $journal = false) {
        $this->writeConcern = new WriteConcern($w, $timeout, $journal);
    }


    /**
     * @param $mode
     * @param array $tagSets
     */
    public function setReadPreference($mode, array $tagSets = []) {
        $this->readPreference = new ReadPreference($mode, $tagSets);
    }

    /**
     * @param $config
     * @return array
     */
    public function initConfig($config = []) {
        if (!empty($config)) {
            $this->config = array_merge($this->config, $config);
        }
        return $this->config;
    }

    /**
     * 获取数据库配置
     * @param string $config
     * @return array|mixed
     */
    public function getConfig($config = '') {
        return $config ? $this->config[$config] : $this->config;
    }

    /**
     * 设置数据库配置
     * @param $config
     * @param $value
     */
    public function setConfig($config, $value) {
        $this->config[$config] = $value;
    }



    /**
     * 生成连接对象
     * @param array $config
     * @param int $linkNum
     * @return Manager
     */
    public function connect(array $config = [], $linkNum = 0) {
        $config = $this->initConfig($config);

        if (empty($config['dsn'])) {
            $username = $config['username'] ? $config['username'] : '';
            $password = $config['password'] ? ':'. $config['password'] .'@' : '';
            $host = $config['host'];
            $port = $config['port'] ? ':'. $config['port'] : '';
            $config['dsn'] = "mongodb://{$username}{$password}{$host}{$port}";
        }

        $this->manager = new Manager($config['dsn'], $config['option']);

        return $this->manager;
    }

    /**
     * @return Manager
     */
    public function getManager() {
        return $this->manager;
    }


    /**
     * @return WriteResult
     */
    public function getWriteResult() {
        return $this->writeResult;
    }


    /**
     * @return Cursor
     */
    public function getCursor() {
        return $this->cursor;
    }

    /**
     * @param $collection
     * @return \MongodbPhp7\Query
     */
    public function collection($collection) {
        if (strpos($collection, '.') === false) {
            $this->namespace =  $this->config['database']. '.' . $this->config['prefix'] . $collection;
        } else {
            $this->namespace = $collection;
        }
        // 删除残留的options
        $this->query->removeOption();
        return $this->query;
    }



    /**
     * @param bool $explode
     * @return array
     */
    public function getCollection($explode = false) {
        if ($explode) {
            return explode('.', $this->namespace);
        }
        return $this->namespace;
    }





    /**
     * @return mixed
     */
    public function getLastInsertID() {
        $this->query->getLastInsID();
        return $this->query->getLastInsID();
    }

    /**
     * 执行写操作
     * @param $namespace
     * @param BulkWrite $bulk
     * @param WriteConcern|null $writeConcern
     * @return WriteResult
     */
    public function execute($namespace, BulkWrite $bulk, WriteConcern $writeConcern = null) {
        if (is_null($writeConcern)) {
            $writeConcern = $this->writeConcern;
        }
        $this->writeResult = $this->manager->executeBulkWrite($namespace, $bulk, $writeConcern);
        $this->numRows = $this->writeResult->getMatchedCount();

        return $this->writeResult;
    }

    /**
     * 执行命令
     * @param Command $command
     * @param string $dbName
     * @param ReadPreference|null $readPreference
     * @return Cursor
     * @throws \MongoDB\Driver\Exception\Exception
     */
    public function command(Command $command, $dbName = '', ReadPreference $readPreference = null) {
        if (is_null($readPreference)) {
            $readPreference = $this->readPreference;
        }

        $this->cursor = $this->manager->executeCommand($dbName, $command, $readPreference);

        return $this->cursor;
    }


    /**
     * @param string $field
     * @return mixed
     * @throws \MongoDB\Driver\Exception\Exception
     */
    public function getBuildInfo($field = '') {
        $command = new Command([
            'buildInfo'  => true
        ]);
        $info =  $this->command($command, $this->getConfig('database'));
        $result = $this->getResult($info)[0];
        return $field ? $result[$field] : $result;
    }

    /**
     * @param Cursor $cursor
     * @return array
     */
    public function getResult(Cursor $cursor) {
        $cursor->setTypeMap($this->getConfig('type_map'));
        $result =  $cursor->toArray();
        return $result;
    }

    /**
     * @return mixed
     * @throws \MongoDB\Driver\Exception\Exception
     */
    public function getVersion() {
        return $this->getBuildInfo('version');
    }

    /**
     * @param $type
     * @param $data
     * @param array $options
     */
    public function log($type, $data, $options =[]) {
        if (!$this->config['debug']) {
            return;
        }

        switch (strtolower($type)) {
            case 'aggregate':
                $this->queryStr = 'runCommand(' . ($data ? json_encode($data) : '') . ');';
                break;
            case 'find':
                $this->queryStr = $type . '(' . ($data ? json_encode($data) : '') . ')';

                if (isset($options['sort'])) {
                    $this->queryStr .= '.sort(' . json_encode($options['sort']) . ')';
                }

                if (isset($options['skip'])) {
                    $this->queryStr .= '.skip(' . $options['skip'] . ')';
                }

                if (isset($options['limit'])) {
                    $this->queryStr .= '.limit(' . $options['limit'] . ')';
                }

                $this->queryStr .= ';';
                break;
            case 'insert':
            case 'remove':
                $where = json_encode($data ?? []);
                $delOption = json_encode($options);
                $this->queryStr = $type . '(' . implode(',', [$where, $delOption]) . ');';
                break;
            case 'update':
                $where = json_encode($options['where'] ?? []);
                $update = json_encode($data);
                $upSet = json_encode($options['up_set'] ?? []);
                $this->queryStr = $type . '(' . implode(',', [$where, $update, $upSet]) . ');';
                break;
            case 'cmd':
                $this->queryStr = $data . '(' . json_encode($options) . ');';
                break;
        }
        $this->queryStr = 'db'. strstr($this->namespace, '.') . '.' . $this->queryStr;
        $this->options = $options;
    }

    /**
     * 获取最近执行的指令
     * @access public
     * @return string
     */
    public function getLastSql()
    {
        return $this->queryStr;
    }
}
