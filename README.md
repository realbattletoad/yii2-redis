# yii2-redis
Simple integration of [phpredis](https://github.com/phpredis/phpredis).

## Installation
The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist trorg/yii2-redis:"1.*"
```

or add

```json
"trorg/yii2-redis": "1.*"
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
            'class' => 'trorg\yii2\redis\Connection',
            'servers' => [
                'server1:6379',
            ],
        ],
    ]
];
```

Redis cluster or Array distribution
```php
return [
    //....
    'components' => [
        'redis' => [
            'class' => 'trorg\yii2\redis\Connection',
            'servers' => [
                'server1:6379',
                'server2:6379',
                'server3:6379',
            ],
            'cluster' => 'redis', // or 'array'
        ],
    ]
];
```

