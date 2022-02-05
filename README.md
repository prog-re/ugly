# Ugly REST API PHP Framework

Ugly is a single file REST API for PHP including authentication/authorization that is usable without server URL re-writes or from the dev server without a routerscript. All routing is done through query parameters. Hence the name.

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
* Support for routing on optional parameters
* Built-in Authentication and role-based and/or scope-based Authorization
* Ugly routes 