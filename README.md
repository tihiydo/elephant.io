# Elephant.io

![Build Status](https://github.com/ElephantIO/elephant.io/actions/workflows/continuous-integration.yml/badge.svg)
[![Latest Stable Version](https://poser.pugx.org/elephantio/elephant.io/v/stable.svg)](https://packagist.org/packages/elephantio/elephant.io)
[![Total Downloads](https://poser.pugx.org/elephantio/elephant.io/downloads.svg)](https://packagist.org/packages/elephantio/elephant.io) 
[![License](https://poser.pugx.org/elephantio/elephant.io/license.svg)](https://packagist.org/packages/elephantio/elephant.io)

```
        ___     _,.--.,_         Elephant.io is a rough websocket client
      .-~   ~--"~-.   ._ "-.     written in PHP. Its goal is to ease the
     /      ./_    Y    "-. \    communications between your PHP Application and
    Y       :~     !         Y   a real-time server.
    lq p    |     /         .|
 _   \. .-, l    /          |j   Requires PHP 7.2 and openssl, licensed under
()\___) |/   \_/";          !    the MIT License.
 \._____.-~\  .  ~\.      ./
            Y_ Y_. "vr"~  T      Built-in Engines:
            (  (    |L    j      - Socket.io 4.x, 3.x, 2.x, 1.x
            [nn[nn..][nn..]      - Socket.io 0.x (courtesy of @kbu1564)
          ~~~~~~~~~~~~~~~~~~~
```

## Installation

We are suggesting you to use composer, with the following: `php composer.phar require elephantio/elephant.io`. For other ways, you can check the release page, or the git clone urls.

## Usage

To use Elephant.io to communicate with socket server is described as follows.

```php
<?php

use ElephantIO\Client;

$url = 'http://localhost:8080';
$client = new Client(Client::engine(Client::CLIENT_4X, $url));
$client->initialize();
$client->of('/');

// emit an event to the server
$data = ['username' => 'my-user'];
$client->emit('get-user-info', $data);

// wait an event to arrive
// beware when waiting for response from server, the script may be killed if
// PHP max_execution_time is reached
if ($packet = $client->wait('user-info')) {
    // an event has been received, the result will be a stdClass
    // data property contains the first argument
    // args property contains array of arguments, [$data, ...]
    $data = $packet->data;
    $args = $packet->args;
    // access data
    $email = $data['email'];
}
```

One can pass the options to the engine such as passing headers, providing additional
authentication token, or [stream context](https://www.php.net/manual/en/function.stream-context-create.php).

```php
<?php

// additional headers
$client = new Client(Client::engine(Client::CLIENT_4X, $url, [
    'headers' => ['Authorization' => 'Bearer MYTOKEN']
]));

// authentication
$client = new Client(Client::engine(Client::CLIENT_4X, $url, [
    'auth' => [
        'user' => 'user@example.com',
        'token' => 'my-secret-token',
    ]
]));

// stream context
$client = new Client(Client::engine(Client::CLIENT_4X, $url, [
    'context' => ['http' => [], 'ssl' => []]
]));
```

The socket connection by default will be using a persistent connection. If you prefer for some
reasons to disable it, just pass `['persistent' => false]` options when creating the client.

```php
<?php

$client = new Client(Client::engine(Client::CLIENT_4X, $url, [
    'persistent' => false
]));
```

## Documentation

The docs are not written yet, but you should check [the example directory](https://github.com/ElephantIO/elephant.io/tree/master/example)
to get a basic knowledge on how this library is meant to work.

## Special Thanks

Special thanks goes to Mark Karpeles who helped the project founder to understand the way websockets works.
