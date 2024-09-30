# FuzzCache
FuzzCache is a software-based data cache mechanism that complements and optimizes dynamic web application fuzzing. It is based on a key observation that data fetch is often repeated, redundant, yet expensive during web application fuzzing. FuzzCache thus stores the data into software-based in-memory caches, eliminating the need for repeated and expensive operations. More technical details can be found in the paper.

```tex
@inproceedings{fuzzcache,
    title       = {FuzzCache: Optimizing Web Application Fuzzing Through Software-Based Data Cache},
    author      = {Li, Penghui and Zhang, Mingxue},
    booktitle   = {Proceedings of 31st ACM Conference on Computer and Communications Security (CCS)},
    month       = oct,
    year        = 2024
}
```

In this repository, we provide the tool for profiling the execution dynamics of server-side web applications, and also a library for performing cross-request data cache.

## Profiling
We used XHProf to profile the function-level execution dynamics of server-side web applications. At profiling time, XHProf records the execution statistic per request in a file. After profiling, its web interface reads the files and sorts it in a user-friendly form.

Install XHProf following the standard procedures listed [here](https://github.com/longxinH/xhprof/tree/master#installation), and then leverage a web scanner or fuzzer at your own preference to profile the web application. [Black-Widow](https://github.com/SecuringWeb/BlackWidow) is a good choice. To enable XHprof on the server side, one should first set up the environments/configurations at the beginning of serving requests. We provide an example at `utils/xhprof_enable.php`. The user should find an appropriate place to include the script so that it is always executed before processing requests. One can also try use preload functionality of PHP to realize this goal.

Using a browser to visit the web interface of XHProf, e.g., http://localhost/xhprof/xhprof_html/index.php (assuming you have installed xhprof_html under the document root of Apache), scroll down to the bottom, and the collected data can be viewed there.

## Cache library
The cache library mainly uses the [shmop module](https://www.php.net/manual/en/book.shmop.php) of PHP. The current version acts as a library that can invoke the shmop functions to interact with cross-request cache. This requires the users to invoke the corresponding functions where they need the data cache. However, we are planing to implement in a more systematic way at the PHP interpreter level. This aims to achieve an adaptive data cache when the PHP interpreter observes any frequent data read.
A list of helpful functions in this version:
- getSHMKey ($path, $pj = "b"): to get a share memory key used to index cache data.
- write($shmKey, $data, int $seconds = 0): serialize the data and write to the cache,
- read($shmKey): read the data from cache
- clean($shmKey): clean data cache

TODO: adaptive data cache in interpreter

### Use
We provide a simple example to demonstrate the performance of our cache mechanism. For simplicity, we use a command line PHP code snippet.

### Demo
1. Install database systems and create a user `test`.
	```txt
	$servername = "localhost";
	$username = "test";
	$password = "123456";
	```

2. Import basic databse table data:
    ```sh
    $ cd utils/
    $ mysql -u test -p < example/db.sql
    # prompt to type password, 123456
    # the data is successfully imported for testing.
    ```
3. We provide a simple case in `utils/example/example.php`. From this simple example, a significant time difference is observed.
	```sh
	# setup the database as specified above
	$ cd utils/
    $ php Main1.php example/example.php
	$ php example/example.php
	# This would output the time without FuzzCache
    Using time (10000 rounds)
    1.791738986969
    
    $ php example/example-fuzzcache.php
	# This would output the time with FuzzCache
    Using time (10000 rounds)
    0.30018901824951
	```
	Note that the actual time would differ in different machines.

