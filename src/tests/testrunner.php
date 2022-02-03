<?php

class TestRunner{

    private $host = null;
    private $tests = [];
    private $current = null;
    private $authToken = null;

    public function __construct($host)
    {
        $this->host = $host;
    }

    public function Test($what){
        $test = new Test($what);
        $this->Add($test);
        return $this;
    }

    private function WithMetod($m,$endpoint){
        $this->current->method = $m;
        $this->current->url = $this->host . $endpoint;
        return $this;
    }

    public function WithGet($endpoint){return $this->WithMetod('GET',$endpoint);}
    public function WithPost($endpoint){return $this->WithMetod('POST',$endpoint);}
    public function WithPut($endpoint){return $this->WithMetod('PUT',$endpoint);}
    public function WithDelete($endpoint){return $this->WithMetod('DELETE',$endpoint);}

    public function ExpectResponseCode($code){
        $this->current->expectedResponseCode = $code;
        return $this;
    }

    public function SendMetod($data){
        $this->current->data = $data;
        return $this;
    }

    public function UsingCredentials($username,$password){
        $this->current->credentials = [$username,$password];
        return $this;
    }

    public function UsingToken(){
        $this->current->useToken = true;
        return $this;
    }

    public function ExpectData($data){
        $this->current->expectedData = json_encode($data);
        return $this;
    }

    public function Assert($assertFun){
        $this->current->assert = $assertFun;
        return $this;
    }

    public function Setup($setupFun){
        $this->current->setup = $setupFun;
        return $this;
    }

    public function SetToken(){
        $this->current->setToken = true;
        return $this;
    }

    public function Run(){
        startDevServer($this->host);
        $tests = array_reverse($this->tests);
        $results = [];
        $result = [-1,null];
        while(count($tests)){
            $test = array_pop($tests);
            if($test->useToken){
                $test->credentials = [$this->authToken];
            }
            if($test->setup){
                $setup = $test->setup;
                $setup();
            }
            $result = curl($test->method,$test->url,$test->data,$test->credentials);
            if($test->expectedResponseCode && $test->expectedResponseCode != $result[0]){
                $test->errors[] = "Expected response code " . $test->expectedResponseCode . ", got " . $result[0];
            }
            if($test->expectedData && $test->expectedData != $result[1]){
                $test->errors[] = "Expected data " . $test->expectedData . ", got " . $result[1];
            }
            if($test->assert){
                $assertFun = $test->assert;
                $assertRes = $assertFun($result[1]);
                if(!$assertRes){
                    $test->errors[] = "Custom assert failed for result: " . $result[1];
                }
            }
            if($test->setToken){
                $dataObj = json_decode($result[1]);
                $this->authToken = $dataObj->token; 
            }
            print($test->desc);
            if($test->errors){
                print(": ERROR\n");
            }else{
                print(": OK\n");
            }
            $results[] = $test;
        }
        stopDevServer();
        $errors = array_filter($results,function ($t){return !!$t->errors;});
        if(count($errors) == 0){
            print("\nAll tests pass.\n");
        }else{
            print("\nSummary:\n");
            foreach($errors as $error){
                print($error->desc . ": " . implode(", ", $error->errors) . "\n");
            }
        }
    }

    private function Add($test){
        $this->tests[] = $test;
        $this->current = $test;
    }
}

class Test{
    public $desc;
    public $method;
    public $url;
    public $expectedResponseCode = null;
    public $expectedData = null;
    public $errors = [];
    public $data = null;
    public $credentials = null;
    public $assert = null;
    public $setup = null;
    public $setToken = false;
    public $useToken = false;

    public function __construct($what)
    {
        $this->desc = $what;
    }
}

function curl($method,$url,$data = null,$credentials = null){
    $curl = curl_init(); 
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl,CURLOPT_CUSTOMREQUEST,$method);
    if(($method == 'POST' || $method == 'PUT') && $data != null)
    {
        curl_setopt($curl,CURLOPT_POSTFIELDS,$data);
    }
    if(@count($credentials) == 2){
        curl_setopt($curl,CURLOPT_USERNAME,$credentials[0]);
        curl_setopt($curl,CURLOPT_PASSWORD,$credentials[1]);
    }
    if(@count($credentials) == 1){
        $authorization = "Authorization: Bearer ".$credentials[0]; 
        curl_setopt($curl, CURLOPT_HTTPHEADER, array($authorization));
    }
    $tuData = curl_exec($curl); 
    $responseCode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    return [$responseCode,$tuData];
}


$serverout = [];

function startDevServer($host){
    global $serverout;    
    @unlink('pidfile');
    exec('php -q -S '.$host.' >> /dev/null & echo $! > pidfile',$serverout);
    sleep(1);
}

function stopDevServer(){
    $file = fopen('pidfile','r');
    $pid = fread($file,256);
    fclose($file);
    $pid = trim($pid);
    exec('kill '.$pid);
    unlink('pidfile');
}


?>