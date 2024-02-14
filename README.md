# Ugly REST API PHP Framework

Ugly is a single file REST API for PHP including authentication/authorization that is usable without server URL re-writes or from the dev server without a router script. All routing is done through query parameters. Hence the name.

## Example

File name: example.php
```PHP
<?php
include_once "ugly.php";

$api = new Ugly();

$api->get(["greetings"],function($name){
        return "Hello " . $name;
    })

    ->get(["salutations"],function($name){
        return "May your loins be full of fruit great " . $name;
    })
    ->withAuth()

    ->get([],function($_){
        return "Hello World!";
    })
    
    ->execute();
?>

```
A HTTP GET request to `example.php` will get the response `"Hello World!"`.

A HTTP GET request to `example.php?greetings=Stibbons` will get the response `"Hello Stibbons"`.

A HTTP GET request to `example.php?salutations=Stibbons` will get the response `"May your loins be full of fruit great Stibbons"` provided that the user is first signed in.

## Features

* Support for GET, POST, PUT and DELETE requests
* Built-in Authentication and role-based and/or scope-based Authorization
* Support for routing on optional parameters
* JSON responses by default
* Ugly routes 

## How to use

Please refer to [the manual](./docs/manual.md) or check the files [src/tests/api.php](./src/tests/api.php), [src/tests/auth.php](./src/tests/auth.php), [src/tests/protectedapi.php](./src/tests/protectedapi.php) for some examples. Keep in mind that those are there for test purposes though.

## Ugly is too Ugly?

If Ugly is too ugly for you, check out these Single File API frameworks:

* https://github.com/aaviator42/zaap
* https://github.com/jcarlosroldan/oink
