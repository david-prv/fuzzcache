<?php 
function echo_wrapper(...$args) {
    // Process or use the arguments as needed
    foreach ($args as $arg) {
        echo $arg;
    }
}

function print_wrapper($arg) {
    // Process or use the arguments as needed
    print($arg);
}