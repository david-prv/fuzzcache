<?php
// Define a namespace
namespace MyNamespace;

// Define a variable within the namespace
$myVariable = "Hello from MyNamespace!";
function f(){
    print "eee\n";
}
// Access the variable within the same namespace
echo $myVariable; // Output: Hello from MyNamespace!

// Access the variable from outside the namespace using the fully qualified name
 \MyNamespace\f(); // Output: Hello from MyNamespace!

// Alternatively, you can import the namespace and access the variable directly
use MyNamespace;

echo $myVariable; // Output: Hello from MyNamespace!
