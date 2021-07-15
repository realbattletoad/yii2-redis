# yii2-redis
Fork of [trorg/yii2-redis](https://github.com/trorg/yii2-redis)

Simple integration of [phpredis](https://github.com/phpredis/phpredis).

## Installation
The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist realbattletoad/yii2-redis:"1.*"
```

or add

```json
"realbattletoad/yii2-redis": "1.*"
```

to the require section of your composer.json.


## Configuration

To use this extension, you have to configure the Connection class in your application configuration:

Simple, with one server
```php
return [
    //....
    'components' => [
        'redis' => [
            'class' => 'realbattletoad\yii2\redis\Connection',
            'servers' => [
                'server1:6379',
            ],
        ],
    ]
];
```

Redis cluster or Array distribution or Sentinels
```php
return [
    //....
    'components' => [
        'redis' => [
            'class' => 'realbattletoad\yii2\redis\Connection',
            'servers' => [
                'server1:6379',
                'server2:6379',
                'server3:6379',
            ],
            'cluster' => 'redis', // or 'array' // or 'sentinels'
        ],
    ]
];
```

