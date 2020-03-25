<?php
use \MongodbPhp7\Connection;
require_once '../src/Connection.php';
require_once '../src/Query.php';

// 配置文件- 没有填写的则按默认值来
$config = [
    // 服务器地址
    'host'              => '127.0.0.1',
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
    // 主键字段
    'pk'                => '_id',
    // 主键处理(全局) 查找和更新此主键会转化成对应的形式，如：查找 ：_id = 5d71c5415c998d3dc4006832, 更新: _id 会处理成objectID类型
    'pk_convert_string' => true,
    // 数据库表前缀
    'prefix'            => '',
    // 开启debug 支持: 记录最后一条指令
    'debug'             => true,
    // 默认分页一页数量
    'rows_limit'        => 10
];

//  静态方法 或 实例化对象
$db = Connection::instance($config);
//$db = new Connection($config);

// 修改数据库
//$db->setConfig('database', 'test1');
// $db->collection('test2.first')... 只作用于当前操作

$collection = 'collection_2';

// 新增链式操作,只针对当前语句 pcs(boolean)  默认 false
// true:查找和更新主键会转化成对应的形式，如：查找 ：_id = 5d71c5415c998d3dc4006832, 更新: _id 会处理成objectID类型

echo '-------------------------------- 插入一条数据 ------------------------------------' . PHP_EOL;
// 默认返回插入成功的条数, $getLastInsID 为 true, 返回主键
$insert = $db->collection($collection)->pcs(false)->insert([
    'name'  => '小明',
    'age'   => 15
], false);
echo '插入了'. $insert .'条'. PHP_EOL;
echo '插入的主键：'. PHP_EOL;
print_r($db->getLastInsertID());
echo '执行的语句：'. $db->getLastSql() . PHP_EOL; // 开启debug才会记录

echo '------------------------------- 插入多条数据 -------------------------------------' . PHP_EOL;

// 返回插入成功的条数
$insertAll = $db->collection($collection)->insertAll([
    [
        'name'  => '静香',
        'age'   => 10
    ],
    [
        'name'  => '大熊',
        'age'   => 18
    ],
    [
        'name'  => '大熊',
        'age'   => 19
    ],
    [
        'name'  => '小明',
        'age'   => 12
    ]
]);

echo '插入了'. $insertAll .'条'. PHP_EOL;
echo '插入的主键数组：'. PHP_EOL;
print_r($db->getLastInsertID());
echo '执行的语句：'. $db->getLastSql() . PHP_EOL; // 开启debug才会记录


//echo '------------------------------ 更新数据 -----------------------------------' . PHP_EOL;
// where条件没做处理，请参考文档的运算符 https://docs.mongodb.com/manual/reference/operator/query/
// update 更新运算符参考 https://docs.mongodb.com/manual/reference/operator/update/
// upsert 不存在更新数据则插入。true 插入，false 不插入，默认不插入
$update = $db->collection($collection)
    ->where(['_id'  => '5d71ee675c998d22b0004b92'])
    ->upsert(false)
    ->limit(1)  //  1 是只更新一条，其他数字都是更新多条，默认更新多条
    ->update([
        'name'  => '测试',
        'age'   => [
            '$inc'  => 1
        ]
    ]);
echo '修改了'. $update .'条'. PHP_EOL;
echo '执行的语句：'. $db->getLastSql() . PHP_EOL; // 开启debug才会记录

// 修改一个字段
$update = $db->collection($collection)
    ->where(['name'  => '小明'])
    ->setField('gender', 1);

// 自增
$update = $db->collection($collection)
    ->where(['name'  => '小明'])
    ->setInc('gender', 1);

// 自减
$update = $db->collection($collection)
    ->where(['name'  => '小明'])
    ->setDec('gender', 1);

echo '修改了'. $update .'条'. PHP_EOL;
echo '执行的语句：'. $db->getLastSql() . PHP_EOL; // 开启debug才会记录



//echo '------------------------------ 删除数据 -----------------------------------' . PHP_EOL;

$delete = $db->collection($collection)->where([
    'name'  => '大熊'
])->limit(1)->delete();

echo '删除了'. $delete .'条'. PHP_EOL;
echo '执行的语句：'. $db->getLastSql() . PHP_EOL; // 开启debug才会记录

//echo '------------------------------ 查找数据 -----------------------------------' . PHP_EOL;
$field = '_id,name,age,set';
//
// 计算两个字段的和 参考 https://docs.mongodb.com/manual/reference/operator/aggregation/project/
$field = [
    'name',
    'age',
    'set',
    'ageSet'  => [
        '$add'  => ['$age', '$set']
    ]
];
// group ：_id 是强制要求的，要想显示，field中必须要有这个字段, 参考 https://docs.mongodb.com/manual/reference/operator/aggregation/group/
$select = $db->collection($collection)
    ->field($field)
    ->where([])
    ->sort(['age'   => $db::SORT_ASC])
    ->group(['_id' => '$name', 'age' => ['$sum' => '$age']])
    ->limit(3)
    ->select();
// 只查找一条
$select = $db->collection($collection)->find();
// 查找单个值
$select = $db->collection($collection)->sort(['age' => $db::SORT_DESC])->value('age');
// 查找一列
$select = $db->collection($collection)->sort(['age' => $db::SORT_DESC])->column('name,age');
// 查找条数
$select = $db->collection($collection)->where(['name' => '小明'])->count();
// 查找随机几条
$select = $db->collection($collection)->where(['name' => '小明'])->sample(10);

// 分页
$select = $db->collection($collection)->where(['name' => '小明'])->sort(['age' => $db::SORT_DESC])->page(1);

print_r($select);
echo PHP_EOL;
echo '执行的语句：'. $db->getLastSql() . PHP_EOL; // 开启debug才会记录
