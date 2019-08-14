<?php
require_once '../src/Connection.php';

$db = \MongodbPhp7\Connection::instance([
    'host'  => '127.0.0.1'
]);

$res = $db->collection('ss')->insertAll([
    [
        'ss'    => 1
    ],
    [
        'ss'    => 2
    ],
    [
        'ss'    => 3
    ]
]);

var_dump($res);
var_dump($db->getLastSql());
var_dump($db->getLastInsertID());

// 实例化
//$db = new db('127.0.0.1', 'test', 27017, [
//    'username'  => '',
//    'password'  => ''
//]);
//$db = new db('127.0.0.1', 'test');

// 获取 \MongoDB\Driver\Manager 对象
//$db->getManager();
//
//// 获取 \MongoDB\Driver\WriteResult 对象
//$db->getWriteResult();
//
//// 获取 \MongoDB\Driver\Cursor 对象
//$db->getCursor();
//
// 临时改变数据库 $db->collection('test1.first_collection')
// 修改数据库 $db->setDataBase('test1');

//// 插入一条数据
//$inset = $db->collection('first_collection')->insert([
//    'id'            => 1,
//    'name'          => 'first',
//    'create_time'   => time()
//]);

//// 插入多条数据
//$inset = $db->collection('first_collection')->insertAll([
//    [
//        'id'            => 2,
//        'name'          => 'sss',
//        'create_time'   => time()
//    ],
//    [
//        'id'            => 3,
//        'name'          => 'sss',
//        'create_time'   => time()
//    ]
//]);
//
//// 查找插入几条
//var_dump($db->getWriteResult()->getInsertedCount());
//
// // 修改  注：若是替换文档，$multi 必须等于false
//$update = $db->collection('first_collection')->where(
//    ['id' => 4]
//)->update([
//    '$set'   => [
//        'name'   => 'four'
//    ]
//],false, true);

//// 批量修改
//$update = $db->collection('first_collection')->updateAll([
//    [
//        [ // where
//            'id'    => 1
//        ],
//        [ // document
//            '$set'  => [
//                'name'  => 'one'
//            ]
//        ],
//        [ // updateOption
//            'multi'     => false,
//            'upsert'    => false
//        ]
//    ],
//    [
//        [ // where
//            'id'    => 2
//        ],
//        [ // document
//            '$set'  => [
//                'name'  => 'two'
//            ]
//        ]
//    ]
//]);
//
//// 删除匹配的第一个
//$delete = $db->collection('first_collection')->delete([
//    '$or'   => [
//        [
//            'id'    => 1
//        ],
//        [
//            'id'    => 2
//        ]
//    ]
//], true);
//
//// 删除所有匹配的
//$delete = $db->collection('first_collection')->delete([
//    '$or'   => [
//        [
//            'id'    => 3
//        ],
//        [
//            'id'    => 4
//        ]
//    ]
//], false);

// 查找
//$select = $db->collection('first_collection')
//    ->project([
//        'id',
//        'name',
//        'id1'    => [ // id 字段 + 1
//            '$add' => ['$id', 1]
//        ]
//    ])
//    ->where([
//        'id'   => [
//            '$gt'   => 1
//        ]
//    ])
//    ->sort([
//        'id1'    => $db::SORT_DESC,
//        'id'     => $db::SORT_ASC
//    ])
//    ->select();
//print_r($select);
