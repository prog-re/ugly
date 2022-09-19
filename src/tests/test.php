<?php
include "testrunner.php";
$authToken = null;

$testRunner = new TestRunner("localhost:8080");
$testRunner
    ->Test("Simple GET, default route")
    ->WithGet("/api.php")
    ->ExpectResponseCode(200)
    ->ExpectData("GET ok")

    ->Test("Simple POST, default route")
    ->WithPost("/api.php")
    ->ExpectResponseCode(200)
    ->ExpectData("POST ok")

    ->Test("Simple PUT, default route")
    ->WithPut("/api.php")
    ->ExpectResponseCode(200)
    ->ExpectData("PUT ok")

    ->Test("Simple DELETE, default route")
    ->WithDelete("/api.php")
    ->ExpectResponseCode(200)
    ->ExpectData("DELETE ok")

    ->Test("Simple GET, route with argument")
    ->WithGet("/api.php?echo=hoho")
    ->ExpectResponseCode(200)
    ->ExpectData("hoho")

    ->Test("Simple GET, route with empty argument")
    ->WithGet("/api.php?data")
    ->ExpectResponseCode(200)
    ->ExpectData(["id" => 0,"item" => "ITM"])

    ->Test("Simple GET, route with optional argument unused")
    ->WithGet("/api.php?req=REQ")
    ->ExpectResponseCode(200)
    ->ExpectData(["req" => "REQ","opt" => "unused"])
    
    ->Test("Simple GET, route with optional argument used")
    ->WithGet("/api.php?req=REQ&opt=OPT")
    ->ExpectResponseCode(200)
    ->ExpectData(["req" => "REQ","opt" => "OPT"])

    ->Test("Protected GET (unauthorized)")
    ->WithGet("/api.php?protected")
    ->ExpectResponseCode(401)

    ->Test("Auth signup (short expiry)")
    ->WithPost("/shortauth.php?signup")
    ->UsingCredentials('TestUser','TestPassword')
    ->ExpectResponseCode(200)
    ->Setup(function(){
        @unlink('./devusers/TestUser');
    })

    ->Test("Auth login (short expiry)")
    ->WithGet("/shortauth.php?login")
    ->UsingCredentials('TestUser','TestPassword')
    ->ExpectResponseCode(200)
    ->SetToken()
    ->Assert(function($data){
        $dataObj = json_decode($data);
        return $dataObj->name == 'TestUser';
    })

    ->Test("Protected GET (token expired)")
    ->WithGet("/api.php?protected")
    ->UsingToken()
    ->ExpectResponseCode(401)
    ->Setup(function(){
        $future = time() + 1;
        while($future > time()){}
    })
    ->Assert(function($_){
        @unlink('../auth.sqlite3');
        return true;
    })

    ->Test("Auth login (user don't exist)")
    ->WithGet("/auth.php?login")
    ->Setup(function(){
        @unlink('./devusers/TestUser');
    })
    ->UsingCredentials('TestUser','TestPassword')
    ->ExpectResponseCode(404)    

    ->Test("Auth signup")
    ->WithPost("/auth.php?signup")
    ->UsingCredentials('TestUser','TestPassword')
    ->ExpectResponseCode(200)

    ->Test("Auth signup (user already exists)")
    ->WithPost("/auth.php?signup")
    ->UsingCredentials('TestUser','TestPassword')
    ->ExpectResponseCode(409)

    ->Test("Auth login (wrong password)")
    ->WithGet("/auth.php?login")
    ->UsingCredentials('TestUser','WrongPassword')
    ->ExpectResponseCode(404)

    ->Test("Auth login")
    ->WithGet("/auth.php?login")
    ->UsingCredentials('TestUser','TestPassword')
    ->ExpectResponseCode(200)
    ->SetToken()
    ->Assert(function($data){
        $dataObj = json_decode($data);
        return $dataObj->name == 'TestUser';
    })

    ->Test("Protected GET (authorized)")
    ->WithGet("/api.php?protected")
    ->UsingToken()
    ->ExpectResponseCode(200)

    ->Test("Read user data through AuthContext")
    ->WithGet("/api.php?username")
    ->UsingToken()
    ->Assert(function($name){
        return $name === '"TestUser"';
    })

    ->Test("GET protected by default (unauthorized)")
    ->WithGet("/protectedapi.php")
    ->ExpectResponseCode(401)

    ->Test("GET protected by default (authorized)")
    ->WithGet("/protectedapi.php")
    ->UsingToken()
    ->ExpectResponseCode(200)

    ->Test("GET protected (role 'admin' required, unauthorized)")
    ->WithGet("/protectedapi.php?admin_only")
    ->UsingToken()
    ->ExpectResponseCode(403)

    ->Test("Auth signup with role admin")
    ->WithPost("/auth.php?signup&role=admin")
    ->Setup(function(){
        @unlink('./devusers/TestAdmin');
    })
    ->UsingCredentials('TestAdmin','TestPassword')
    ->ExpectResponseCode(200)

    ->Test("Auth signup with role notallowed")
    ->WithPost("/auth.php?signup&role=notallowed")
    ->UsingCredentials('TestNotAllowed','TestPassword')
    ->ExpectResponseCode(400)

    ->Test("Auth login")
    ->WithGet("/auth.php?login")
    ->UsingCredentials('TestAdmin','TestPassword')
    ->ExpectResponseCode(200)
    ->SetToken()

    ->Test("GET protected (role 'admin' required, authorized)")
    ->WithGet("/protectedapi.php?admin_only")
    ->UsingToken()
    ->ExpectResponseCode(200)

    ->Test("GET protected (scope 'reader' required, unauthorized)")
    ->WithGet("/protectedapi.php?reader_only")
    ->UsingToken()
    ->ExpectResponseCode(403)

    ->Test("Auth login")
    ->WithGet("/auth.php?login")
    ->UsingCredentials('TestUser','TestPassword')
    ->Setup(function(){
        $file = fopen("./devusers/TestUser",'r+');
        $user = fread($file,10000);
        $user = str_replace('"scopes":null','"scopes":"writer reader squasher"',$user);
        fseek($file,0);
        fwrite($file,$user);
        fclose($file);
    })
    ->SetToken()

    ->Test("GET protected (scope 'reader' required, authorized)")
    ->WithGet("/protectedapi.php?reader_only")
    ->UsingToken()
    ->ExpectResponseCode(200);


$testRunner->Run();
?>