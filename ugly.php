<?php

/***********************
 * Main UglyAPI class
 ***********************/

class Ugly{

    private $get_methods  = [];
    private $post_methods  = [];
    private $put_methods  = [];
    private $delete_methods  = []; 
    private $default_authenticated = false;
    private $default_roles = [];
    private $default_scope = null;
    private $latest_method = null;

    public function get($parameters,$handler){
      $this->add_method('GET',$handler,$parameters);
      return $this;  
    }    

    public function post($parameters,$handler){
      $this->add_method('POST',$handler,$parameters);
      return $this;  
    }

    public function put($parameters,$handler){
      $this->add_method('PUT',$handler,$parameters);
      return $this;  
    }

    public function delete($parameters,$handler){
      $this->add_method('DELETE',$handler,$parameters);
      return $this;  
    }

    public function withAuth(){
      if($this->latest_method == null){
        $this->default_authenticated = true;
      }else{
        $this->latest_method->requiresAuth = true;
      }
      return $this;
    }

    public function withRole($role){
      if($this->latest_method == null){
        $this->default_authenticated = true;
        $this->default_roles[] = $role;
      }else{
        $this->latest_method->requiresAuth = true;
        $this->latest_method->requiresRoles[] = $role;
      }
      return $this;
    }

    public function withScope($scope){
      if($this->latest_method == null){
        $this->default_authenticated = true;
        $this->default_scope = $scope;
      }else{
        $this->latest_method->requiresAuth = true;
        $this->latest_method->requiresScope = $scope;
      }
      return $this;
    }

    public function execute(){
      $method = $_SERVER['REQUEST_METHOD'];
      $parameters = $_GET;
      $handler_methods = null;
      $handler_index = -1;
      switch ($method) {
        case 'GET':
          $handler_index = $this->search_handler($this->get_methods,$parameters);
          $handler_methods = $this->get_methods;
          break;
        case 'POST':
          $handler_index = $this->search_handler($this->post_methods,$parameters);
          $handler_methods = $this->post_methods;          
          break;
        case 'PUT':
          $handler_index = $this->search_handler($this->put_methods,$parameters);
          $handler_methods = $this->put_methods;  
          break;
        case 'DELETE':
          $handler_index = $this->search_handler($this->delete_methods,$parameters);
          $handler_methods = $this->delete_methods;
          break;
        default:
        http_response_code(404);  
          break;
      }
      if($handler_index != -1){
        $method = $handler_methods[$handler_index];
        $handler = $method->handler;
        if($method->requiresAuth){
          session_start();
          $authContext = AuthContext::getAuthContext();
          if(!$authContext->user){
            notAuthenticated();   
          }
          if($method->requiresRoles && array_search($authContext->user->role,$method->requiresRoles,true) === false){
            notAuthorized();
          }
          if($method->requiresScope && array_search($method->requiresScope,explode(" ",$authContext->user->scopes),true) === false){
            notAuthorized();
          }
        }
        $ordered_args = $this->order_arguments($parameters,$handler_methods[$handler_index]->parameters);
        $result = $handler(...$ordered_args);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result);
        exit();
      }else{
        http_response_code(404);
      }
    }

    private function add_method($verb,$handler,$parameters){
      $method = new Method(
        $handler,
        $parameters,
        $this->default_authenticated,
        $this->default_roles,
        $this->default_scope);
      $this->latest_method = $method;
      switch ($verb) {
        case 'GET':
          $this->get_methods[] = $method;
          break;
        case 'POST':
          $this->post_methods[] = $method;          
          break;
        case 'PUT':
          $this->put_methods[] = $method;  
          break;
        case 'DELETE':
          $this->delete_methods[] = $method;
          break;
      }
    }

    private function order_arguments($arguments,$parameters){
      $ordered_args = [];
      $parameters = array_reverse($parameters);
      while(count($arguments) > 0 && count($parameters) > 0){
        $param = array_pop($parameters);
        if($param[0] == '?'){
          $param = substr($param,1-strlen($param));
        }
        $ordered_args[] = $arguments[$param];
        unset($arguments[$param]);
      }
      return $ordered_args;
    }

    private function search_handler($methods,$parameters){
      foreach($methods as $midx => $method){
        $handler_params = $method->parameters;
        $request_params = array_keys($parameters);
        foreach($request_params as $request_param){
          if (($idx = array_search($request_param, $handler_params)) !== false) {
            unset($handler_params[$idx]);
          }
          if (($idx = array_search('?'.$request_param, $handler_params)) !== false) {
            unset($handler_params[$idx]);
          }
        }
        foreach($handler_params as $idx => $handler_param){
          if ($handler_param[0] == '?') {
            unset($handler_params[$idx]);
          }
        }
        if(count($handler_params) == 0){
          return $midx;
        }
      }
      return -1;
    }
}

/***********************
 * Auth
 ***********************/

class UglyAuth {

  private $ugly;
  private $storeUserFunc;
  private $getUserFunc;
  private $tokenExpiresInMinutes = 30;
  private $allowedRoles = [];

  public function __construct()
  {
      $this->ugly = new Ugly();

      $this->ugly
          ->post(["signup","?role"],function($signup,$role=""){
              $username = $_SERVER["PHP_AUTH_USER"];
              $getUserFunc = $this->getUserFunc;
              $existingUser = $getUserFunc($username);
              if($existingUser->hash){
                http_response_code(409);
                return null;
              }
              if($role != "" && !array_key_exists($role,$this->allowedRoles)){
                http_response_code(400);
                return null;
              }
              $hash = password_hash($_SERVER["PHP_AUTH_PW"], PASSWORD_BCRYPT);
              $id = base64_url_encode(random_bytes(16));
              $user = new User(['name'=>$username,'hash'=>$hash,'role'=>$role, 'id'=>$id]);
              $storeUserFunc = $this->storeUserFunc;
              $storeUserFunc($user);
          })
          ->get(["login"],function($login){
              $username = $_SERVER["PHP_AUTH_USER"];
              $password = $_SERVER["PHP_AUTH_PW"];
              $getUserFunc = $this->getUserFunc;
              $user = $getUserFunc($username);
              if(password_verify($password,$user->hash)){
                  session_start();
                  $userArray = $user->toArray();
                  $userArray['expires'] =  time() + (int)($this->tokenExpiresInMinutes * 60);
                  $token = dataToToken($userArray,$this->tokenExpiresInMinutes*2);
                  $userArray['token'] =  $token;
                  $_SESSION = $userArray;
                  return $userArray;
              } else {
                  http_response_code(404);
              }
              return false;
          });
  }

  public function AddRole($role){
      $this->allowedRoles[$role] = true;
      return $this;
  }

  public function WithScopes($scopes){
      return $this;
  }

  public function GetUserWith($getUserFunc){
      $this->getUserFunc = $getUserFunc;
      return $this;
  }

  public function StoreUserWith($storeUserFunc){
      $this->storeUserFunc = $storeUserFunc;
      return $this;
  }

  public function UseDevelopmentStorage(){
      $this
          ->GetUserWith(function($username){
              $file = fopen("./devusers/".$username,'r');
              $json = fread($file,10000);
              fclose($file);
              $userdata = json_decode($json,true);
              return new User($userdata);
          })
          ->StoreUserWith(function($user){
              $userdata = json_encode($user);
              $file = fopen("./devusers/".$user->name,'w');
              fwrite($file,$userdata);
              fclose($file);
          });
      return $this;
  }

  public function WithTokenlifetimeMinutes($tokenLifetimeMinutes){
      $this->tokenExpiresInMinutes = $tokenLifetimeMinutes;
      return $this;
  }

  public function execute(){
      $this->ugly->execute();
  }
}

class AuthSecrets{

  private $db;
  private $minutes_to_expire;

  public function __construct($minutes_to_expire = 30)
  {
      $this->db = new SQLite3(dirname(__FILE__) .'/auth.sqlite3');
      $this->db->exec(' create table if not exists secrets (key number,secret text, expires number);');
      $this->minutes_to_expire = $minutes_to_expire;
  }

  public function getCurrentSecret(){
      $query = $this->db->query(
          ' select [key],[secret],expires'
        . ' from secrets '
        . ' order by expires desc');
      $secrets = [];
      while ($secretdata = $query->fetchArray()) {
          $secrets[] = new Secret($secretdata);
      }
      if(count($secrets) > 2){
        $this->removeOldest();
      }
      if(count($secrets) > 0 && $secrets[0]->expires > time()){
          return $secrets[0];
      } 
      $secret = $this->generate();
      $this->insertSecret($secret);
      return $secret;
  }
 
  public function getSecretForKey($key){
    $query = $this->db->prepare(
         ' select secret,expires from secrets'
        .' where key = :key');
    if(!$query){
      return false;
    }
    $query->bindValue(':key', $key, SQLITE3_TEXT);
    $result = $query->execute();
    $secret = $result->fetchArray();
    return new Secret($secret);
  }

  private function generate(){
      $secret = new Secret();
      $secret->expires = time() + (int)(60 * $this->minutes_to_expire);
      $secret->key = base64_url_encode(random_bytes(16));
      $secret->secret = base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
      return $secret;
  }

  private function insertSecret($secret){
      $query = $this->db->prepare(
           ' insert into secrets'
          .' (key,secret,expires)'
          .' values ('
          .' :key,'
          .' :secret,'
          .' :expires'
          .' )');
      foreach((array) $secret as $name => $value){
        $query->bindValue(':'.$name,$value);
      }
      $query->execute();
  }

  private function removeOldest(){
      $this->db->exec(
          ' delete from secrets'
         .' where key <> ('
         .'  select key from secrets order by expires desc limit 2'
         .' )');
  }
}

class AuthContext{
  private static $instance;
  public $user = null;

  private function __construct()
  {
    $token = null;
    if(@$_SESSION['token']){
      $token = $_SESSION['token'];
    }else if($_SERVER['HTTP_AUTHORIZATION']) {
      $token = explode(" ",$_SERVER['HTTP_AUTHORIZATION'])[1];
    }
    if($token){
      $data = tokenToData($token);
      if($data){
        $this->user = new User((array)$data);
      }
    } 
  }

  public static function getAuthContext(){
    if (self::$instance == null)
    {
      self::$instance = new AuthContext();
    }
    return self::$instance;
  }
}

//Auth functions

function dataToToken($data,$minutes_to_expire){
  $authSecrets = new AuthSecrets($minutes_to_expire);
  $secret = $authSecrets->getCurrentSecret();
  $nonce = random_bytes(
      SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
  );
  $tokenData = [];
  $tokenData[] = $secret->key;
  $tokenData[] = base64_url_encode($nonce);
  $tokenData[] = base64_url_encode(
      sodium_crypto_secretbox(
          json_encode($data),
          $nonce,
          base64_decode($secret->secret)
      )
  );
  return implode('.',$tokenData);
}

function tokenToData($token){
  $tokenParts = explode(".",$token);
  $key = $tokenParts[0];
  $nonce = base64_url_decode($tokenParts[1]);
  $data = base64_url_decode($tokenParts[2]);
  $authSecrets = new AuthSecrets();
  $secret = $authSecrets->getSecretForKey($key);
  if($secret->expires > time()){
    $decrypted = sodium_crypto_secretbox_open($data,$nonce,base64_decode($secret->secret));
    if($decrypted){
      $decoded = json_decode($decrypted);
      if($decoded->expires > time()){
        return $decoded;
      }
    }
  }
  return false;
}

function base64_url_encode($data){
  $encoded = base64_encode($data);
  $encoded = str_replace('+','-',$encoded);
  $encoded = str_replace('/','_',$encoded);
  $encoded = str_replace('=','',$encoded);
  return $encoded;
} 

function base64_url_decode($encoded){
  $encoded = str_replace('-','+',$encoded);
  $encoded = str_replace('_','/',$encoded);
  return base64_decode($encoded,true);
}

function notAuthenticated(){
  http_response_code(401);
  exit();
}

function notAuthorized(){
  http_response_code(403);
  exit();
}

// Debug functions

function debug($data){
  $jsondata = json_encode($data);
  $file = fopen("./debug.txt",'a');
  fwrite($file,$jsondata);
  fwrite($file,"\n");
  fclose($file);
}

/***********************
 * Data classes
 ***********************/

class User{
  public $name;
  public $hash;
  public $role;
  public $scopes;

  public function __construct($data)
  {
      $this->name = $data['name'];
      @$this->id = $data['id']; 
      @$this->hash = $data['hash'];
      @$this->role = $data['role'];
      @$this->scopes = $data['scopes'];
      @$this->extra = $data['extra'];
  }

  public function toArray(){
    return ['name'=>$this->name,'id'=>$this->id,'role'=>$this->role,'scopes'=>$this->scopes,'extra'=>$this->extra];
  }
}

class Secret{
  public $key;
  public $secret;
  public $expires;

  public function __construct($data = [])
  {
      @$this->key = $data['key'];
      @$this->secret = $data['secret'];
      @$this->expires = $data['expires'];
  }
}

class Method{
  public $handler;
  public $parameters;
  public $requiresAuth;
  public $requiresRoles;
  public $requiresScope;

  public function __construct(
    $handler,
    $parameters,
    $requiresAuth = false,
    $requiresRoles = [],
    $requiresScope = null
  )
  {
    $this->handler = $handler;
    $this->parameters = $parameters;
    $this->requiresAuth = $requiresAuth;
    $this->requiresRoles = $requiresRoles;
    $this->requiresScope = $requiresScope;
  }
}

?>