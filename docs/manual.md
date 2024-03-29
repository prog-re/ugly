# Ugly REST API PHP Framework Manual

## API organization

Unlike most other API frameworks Ugly does not have any central routing setup. Instead it relies on the web server to do our routing for us. This means that each of your RESTful "objects" that you wish your API to cover will have it's own PHP file.
Example file structure:
```
api/
⸠-> pet.php
⸠-> petowner.php
⸠-> petstore.php
⸠-> petstores/
    ⸠-> customer.php
    ⸠-> inventory.php
```

## General usage

Each api file must create an Ugly object:
``` PHP
<?php
include_once "ugly.php";

$api = new Ugly();

?>
```

The object does nothing by itself, but configuration function calls can be made to it. Each call returns the original object allowing the configuration to be done in the fluent style.

The very last call must be `execute();` otherwise the Ugly object is not activated.

```PHP
<?php
include_once "ugly.php";

$api = new Ugly();

$api->get([],function($_){
        return "Hello World!";
    })
    ->execute();
?>

```

## Routing

The most basic configuration for the api is the routing. This is done with the four methods: `get`, `post`, `put` and  `delete`. These each map to the four HTTP methods GET, POST, PUT and DELETE. Apart for representing different HTTP methods they all work the same. The examples will mostly use the `get` method.

All of these expect a list of strings as the first parameter and an anonymous function as the second parameter. The number of strings in the list must match the number of parameters to the anonymous function!

The list of strings represents the HTTP parameters that the api can handle.

Example file `customer.php`
```PHP
<?php
include_once "ugly.php";

$api = new Ugly();

$api->get(['id'],function($customerId){
        return [
            'id' => $customerId,
            'name' => 'Mr Example',
            'balance' => 1234.56 
        ];
    })
    ->get(['all'],function($_){
        return [
            ['id'=>'cid_12'],
            ['id'=>'cid_13'],
            ['id'=>'cid_14']
        ];
    })
    ->execute();
?>
```

Calls to this api will yield the following:
For `GET customer.php?id=cid_12`:
```JSON
{
   "id":"cid_12",
   "name":"Mr Example",
   "balance":1234.56 
}
```

For `GET customer.php?all`:
```JSON
[
    { "id":"cid_12"},
    { "id":"cid_13"},
    { "id":"cid_14"},
]
```

Multiple parameters  can be used:
```PHP
<?php
include_once "ugly.php";

$api = new Ugly();

$api->get(['greeting','name'],function($greeting,$name){
        return $greeting . " ". $name;
    })
    ->execute();
?>
```

The order (and number) of the routing strings in the list must match the parameters in the function. The *names* does not need to match but not doing so might be a little confusing:

```PHP
<?php
include_once "ugly.php";

$api = new Ugly();

// Works but makes no sense
$api->get(['apple'],function($orange){
        return $orange;
    })
    ->execute();
?>
```

Ugly will try to match the parameters to a routing configuration in the order that they are configured. The matching is greedy so an empty string list will match all parameters:

```PHP
<?php
include_once "ugly.php";

$api = new Ugly();
// This ordering is broken - 'fruit' will never match
$api->get([],function(){
        return "Always match!";
    })
    ->get(["fruit"],function($fruit){
        return "Never match!";
    })
    ->execute();
?>
```

It's therefore important to order the routing configurations so that a less specific routing does not shadow a more specific routing.

```PHP
<?php
include_once "ugly.php";

$api = new Ugly();
// This ordering is ok - more specific before less specific
$api->get(['fruit','vegetable'],function($fruit,$vegetable){
        return "Matches on 'fruit' AND 'vegetable'";
    })
    ->get(["fruit"],function($fruit){
        return "Matches when only 'fruit' is provided";
    })
    ->execute();
?>
```

### Optional parameters

If a parameter to a route is optional the string must begin with `?` and the corresponding function parameter must have a default value

```PHP
<?php
include_once "ugly.php";

$api = new Ugly();
$api->get(['fruit','?vegetable'],function($fruit,$vegetable=null){
        return "Matches on 'fruit' alone AND when 'fruit' and 'vegetable' is provided";
    })
    ->execute();
?>
```

The greedy nature of the matcher means that routes with optional parameters must be carefully ordered so they don't shadow other routes.

### Request bodies

Ugly has no special handling of request bodies. This means that request bodies provided as form-formatted data will be available in the global $_POST variable as usual. If JSON data is provided it can be obtained by using `json_decode(file_get_contents('php://input'))` 

# Basic Auth

Ugly has built in Auth. To provide `signup` and `login` endpoints, create a `auth.php` file (the name is not important). 
Create an `UglyAuth` object and configure it:

```PHP
<?php
include_once "../ugly.php";

$auth = new UglyAuth();
$auth->WithSelfSignup()
     ->WithBasicLogin()
     ->GetUserWith(function($username){
        // Must return an Ugly User object
      })
      ->StoreUserWith(function($userObj){
        // Must store an Ugly User object
      }) 
     ->execute();
?>
```

The User object to be stored and retrieved has the following structure:
```PHP
class User{
  public $name; //string, must be unique 
  public $hash; //string, the users BCrypt password hash
  public $role; //optional string, the users role in the system. Example 'admin'
  public $scopes; //optional space separated string, the users scopes in the system. Example 'reader writer'
}
```
During development Ugly provides file system development storage. This should only be used when running the development server.

```PHP
<?php
include_once "../ugly.php";

$auth = new UglyAuth();

$auth
    ->WithSelfSignup()
    ->WithBasicLogin()
    ->UseDevelopmentStorage()
    ->execute();
?>
```

If the API only has a single user (or maybe needs a bootstrap user), this can be set up like this:

```PHP
<?php
include_once "../ugly.php";

$auth = new UglyAuth();

$auth
    ->SingleUser('Username','password')
    ->WithBasicLogin()
    ->execute();
?>
```



*Please note that the example above is the only example in this section to work stand-alone. All other examples needs user storage and retrieving configuration to work but it has been left out to make the examples shorter.*

## Signup

If the auth endpoint is configured in the file `auth.php` a POST call to 
`auth.php?signup` will add a user to the system. Username and password must be provided as an Authorization header in the `Basic` style:
```
Authorization: Basic dXNlcm5hbWU6cGFzc3dvcmQ
``` 
The string of gibberish after Basic is the username and password formatted as `username:password` and then Base64 encoded

### Self-assigned Roles

It is sometimes convenient to let the users decide which role they should have. For example, an marketplace app may have users that want to act as either `Seller` or `Buyer`. Ugly can then be configured like this:

```PHP
<?php
include_once "../ugly.php";

$auth = new UglyAuth();

$auth
    ->WithSelfSignup()
    ->WithBasicLogin()
    ->AddRole("Buyer")
    ->AddRole("Seller")
    ->execute();
?>
```

On sign-up the user can then use the optional parameter `role`: 

```
POST auth.php?signup&role=Seller
```

You may of course have additional roles that you don't want your users to self-assign. 
### Token lifetime

The default token lifetime is 30 minutes but can be configured like this:

```PHP
<?php
include_once "../ugly.php";

$auth = new UglyAuth();

$auth
    ->WithSelfSignup()
    ->WithBasicLogin()
    ->WithTokenlifetimeMinutes(15)
    ->execute();
?>
```
## Login

When the user is signed up, it can log in with a call GET call to `auth.php?login`

Username and password is again provided in the `Basic` style. The response for a valid user will be a JSON object like:
```JSON
{
    "name":"Username",
    "id":"asd34q23w",
    "role":"",
    "scopes":"",
    "expires":1644150883,
    "token":"D1GkFn8M5y1UNli7C2PuuQ.XdEv1CVMOXW43SvO8EGk1n_24s6zAIdJ.fcikQtM_bMVEREqoXY97ZWglHTU8h_PwjPW9JQ8u6hjtxxXbbbO5KCutJ7l58AHK5mgum9oQtK4K1-k1GzfH3pYcQfHTb8Hvz3j7RUVHGaowhh-mFv5DK21yGHhcp-tMx88oZMyzJzOZNpX19OzUv75vfgE8TZcC9oCldbLM72DLx4nV2itiYO0"
}
```
The `token` value can be provided to subsequent calls to other API endpoints as a Authorization header in the `Bearer` style:
```
Authorization: Bearer D1GkFn8M5y1UNli7C2PuuQ.XdEv1CVMOXW43SvO8EGk1n_24s6zAIdJ.fcikQtM_bMVEREqoXY97ZWglHTU8h_PwjPW9JQ8u6hjtxxXbbbO5KCutJ7l58AHK5mgum9oQtK4K1-k1GzfH3pYcQfHTb8Hvz3j7RUVHGaowhh-mFv5DK21yGHhcp-tMx88oZMyzJzOZNpX19OzUv75vfgE8TZcC9oCldbLM72DLx4nV2itiYO0
```

## Protected API:s

In the most basic cases an API does not need any other protection than knowing that it is a signed up user who is calling. In that case you can protect a specific route like this:

```PHP
<?php
include_once "../ugly.php";

$api = new Ugly();
$api->get(["protected"],function($_){
    return "I only answer when my friends call!";
})->withAuth()
  ->execute();

?>
```

Very often you'd need to know *who* is actually calling an API. This information is available through the `AuthContext` object:

```PHP
<?php
include_once "../ugly.php";

$api = new Ugly();
$api->get(["saymyname"],function($_){
    $context = AuthContext::getAuthContext();
    return $context->user->name;
})->withAuth()
  ->execute();;

?>
```

If all routes in the API are protected, configure the auth *before* any routes. Then this configuration will be the default for all routes.

```PHP
<?php
include_once "../ugly.php";

$api = new Ugly();
$api->withAuth();
    ->get(["first"],function($_){
        return "I'm protected!";
    })
    ->get(["second"],function($_){
        return "Me too!";
    })
    ->execute();
?>
```

### Roles 

If only specific roles can access an endpoint it can be configured like this.

```PHP
<?php
include_once "../ugly.php";

$api = new Ugly();
$api->get(["teachers_lounge"],function($_){
    return "I'm giving my students a surprise test today!";
})->withRole("teacher")
  ->execute();

?>
```

If multiple roles can access an endpoint, just add more roles:

```PHP
<?php
include_once "../ugly.php";

$api = new Ugly();
$api->get(["common_area"],function($_){
    return "I'm in the common area";
})->withRole("teacher")
  ->withRole("student")
  ->execute();

?>
```

### Scopes

Scopes allows you to add more fine grained security for your API but you will also need to administer the scopes yourself. Apart from the ability to specify a required scope for an endpoint there is no built in support for adding or removing scopes to a user. Unlike roles, an endpoint can only have a single scope associated with it. 

```PHP
<?php
include_once "../ugly.php";

$api = new Ugly();
$api
    ->get([],function(){
        return ["all","the","things"];
    })->withScope("reader")
  
    ->post([],function(){
        // add to the things to read
    })->withScope("writer")
    
    ->execute();
?>
```

Scopes and roles can be combined.