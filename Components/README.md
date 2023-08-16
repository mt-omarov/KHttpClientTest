# Library components
All the main library functions are located in this directory.
## `CurlHttpClient` module structure
Let's break down the structure of the call first with respect to the constructor, then with respect to the two main methods: request, getStatusCode.
### Constructor
```PHP
$client = new CurlHttpClient(null);
```
The function call will be as follows: 
1. **Options constructor class call**: \
*Allows to store many query parameters of different types, avoiding the problem of strict typing.*
3. **Prepare request function of `ExtractedHttpClient` class**: \
*Validates all options and parameters of the request, making it possible to send it further to the server.*
3. **Object of state machine `CurlClientState` creation**: \
*This class is a link between the curlhandles and it's multiCurlhandles, and it also stores important information.*
    1. **`CurlMultiHandle` object creation**: \
    *It is an auxiliary class created to wrap over the raw resource type. It also has syntax inside to avoid data type conflicts between KPHP and PHP.*
    2. **`DnsCache` oject creation**: \
    *Needed to store hosts and remote requests.*

### Request method
```PHP
$url = 'https://<someExampleUrl>';
$response = $client->request('GET', $url);
```
1. **Validation of transmitted options via `prepareRequest()` method.**
2. **Preparing other request parameters using the `ExtractedHttpClient` functionality.**
3. **Creating a request object of `CurlResponse` class object**: \
*This class object stores all the information of the request. 
It stores the relation between the request and its multihandler. \
Creating a query object means adding the query to the multihandler queue, 
setting all the options with `curl_setopt()` and, among other things, creating a temporary thread for debugging*

### `getStatusCode` method
```PHP
$response = $client->request('GET', $url);
var_dump($statusCode = $response->getStatusCode());
```
This method allows you to get a request status code. \
Its call has a rather complex construction and requires the use of a generator function. 
KPHP doesn't yet support generators, so it uses recursion. The `StreamIterator` class is used for this.
It turns out the structure is as follows:
1. **StreamIterator object creation.**
2. **Using StreamIterator functionality**: uses methods to retrieve query information in conjunction with curl_multi_exec().  
