# mongodb-php7
因为内置的mongodb类流程有些繁琐，封装个简单的工具类来简化流程

[例子](https://github.com/zdz1715/mongodb-php7/blob/master/example/simple.php)

## 安装
```shell script
composer require zdz/mongodb-php7 
```
## 实例化
```php
<?php
use \MongodbPhp7\Connection;
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
// 使用静态方法
$db = Connection::instance($config);

//$db = new Connection($config);
```
## 修改配置
```php
$db->setConfig('database', 'test1');
```
## 选择集合
```php
$collection = $db->collection('first'); // 配置下数据库的first集合
$collection = $db->collection('test2.first'); // 临时选择test2数据库的first集合
```
## 插入
#### insert($array, $getLastInsertID)
参数：
* $array 插入数组
* $getLastInsertID 布尔值，是否返回插入的主键,默认falsefalse，返回插入条数
```php
$count = $collection->insert($insertData, false);
```
#### insertAll($dataSet)
参数：
* $dataSet 插入的二维数组
```php
$insertCount = $collection->insertAll([
    [
        'name'  => '静香',
        'age'   => 10
    ],
    [
        'name'  => '大熊',
        'age'   => 18
    ]
]);
```
#### getLastInsertID()
返回主键，若是插入多条，则会返回主键数组


## 更新
#### where($array)
参数：
* $array 条件数组，[参考文档](https://docs.mongodb.com/manual/reference/operator/query/)

**若配置pk_convert_string为true，以主键为条件时**
```php
$collection->where(['_id' => '5d71ee675c998d22b0004b92']);
// 多个则需要自己转化，暂没有处理
$collection->where([
    '_id' => [
        '$in' => [
            $db->stringConvertPk('5d71ee675c998d22b0004b92'),
            $db->stringConvertPk('5d71ee675c998d22b0004b92')                
        ]
    ]
]);
```
#### upsert($bool)
参数：
* $bool: 布尔值，默认false，当更新数据不存在时无动作，true 则插入一条

### limit($num)
参数：
* $num: 更新或删除的条数，默认多条

#### update($array)
参数：
* $array 更新数组，[参考文档](https://docs.mongodb.com/manual/reference/operator/update/)

```php
$collection->where($where)
    ->upsert(true)
    ->update([
        'name'  => '测试',
        'age'   => [ // 年龄加1
            '$inc'  => 1
        ]
    ]);
```
#### setField($field, $value)
参数：
* $field 修改的字段
* $value 值

```php
$collection->where($where)->setField($field, $value);
```
#### setInc($field, $num)、setDec($field, $num)
参数：
* $field 修改的字段
* $num 增加或减少的值，默认1
```php
$collection->where($where)->setInc($field, $num);
```
## 删除
#### delete()
```php
$collection->where($where)->limit(1)->delete();
```
## 查找
#### field($field)
参数：
* $field 数组或字符串，非必须，有group时可以不要 [参考文档](https://docs.mongodb.com/manual/reference/operator/aggregation/project/)
```php
$field = '_id,name,age';
// 计算字段和
$field = [
    'name',
    'age2',
    'age2',
    'ageSet'  => [
        '$add'  => ['$age1', '$age2']
    ]
];
```
#### sort($sort)
参数：
* $sort 1 升序，-1 降序， 提供两个常量$db::SORT_ASC，$db::SORT_DESC，也可以自己写数字
```php
$collection->where($where)
    ->sort(['age'   => $db::SORT_ASC]);
```
#### group($array)
参数：
* $array 必须有`_id`这个字段 [参考文档](https://docs.mongodb.com/manual/reference/operator/aggregation/group/)

```php
// 以name分组，计算年龄和
$collection->where($where)
    ->sort(['age'   => $db::SORT_ASC])
    ->limit(10)
    ->group(['_id' => '$name', 'age' => ['$sum' => '$age']])
```
#### find()
查找一条
```php
$collection->where($where)->find();
```
#### value(查找某个字段单个值)
查找某个字段单个值

参数：
* $field 单个字段

```php
$collection->where($where)->find($field);
```
#### column($field)
查找一列

参数：
* $field 单个字段或多个字段，多个字段`name,age`会以`name`为下标

```php
$collection->where($where)->column($field);
```
### count()
查找条数
```php
$collection->where($where)->count();
```
### ($num)
参数：
* $num 随机查找的条数
```php
$collection->where($where)->sample($num);
```
### page($page)
参数：
* $page 页码，和limit()配合可以更改每页的数量

```php
$collection->where($where)
//    ->group($group)
//    ->sort($sort)
//    ->limit($limit)
    ->page($page);
```
