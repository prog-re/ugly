<?php
include_once "../ugly.php";

$api = new Ugly();

$api->get(["echo"],function($echo){
    return $echo;
});

$api->get(["data"],function($data){
    return ["id" => 0,"item" => "ITM"];
});

$api->get(["req","?opt"],function($req,$opt="unused"){
    return ["req" => $req,"opt" => $opt];
});

$api->get(["protected"],function($_){
    return ["id" => 0,"item" => "ITM"];
})->withAuth();

$api->get([],function(){
    return "GET ok";
});

$api->post([],function(){
    return "POST ok";
});

$api->put([],function(){
    return "PUT ok";
});

$api->delete([],function(){
    return "DELETE ok";
});

$api->execute();

?>