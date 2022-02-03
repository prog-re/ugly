<?php
include_once "../ugly.php";

$api = new Ugly();
$api->withAuth();


$api->get(["admin_only"],function($_){
    return ["id" => 0,"item" => "ITM"];
})->WithRole('admin');

$api->get(["reader_only"],function($_){
    return ["id" => 0,"item" => "ITM"];
})->withScope('reader');

$api->get([],function(){
    return ["id" => 0,"item" => "ITM"];
});
$api->execute();

?>