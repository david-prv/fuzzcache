<?php
require_once 'vendor/autoload.php';
error_reporting(E_ERROR | E_PARSE);
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;

class EchoWrapperVisitor extends PhpParser\NodeVisitorAbstract
{
    public function leaveNode(Node $node)
    {
        if ($node instanceof Stmt\Echo_ ) {
            // Create an array of arguments for the wrapper function call
            $arguments = [];
            foreach ($node->exprs as $arg) {
                $arguments[] = new Node\Arg($arg);
            }

            // Create a function call node for the wrapper function
            $wrapperFunctionCall = new Node\Expr\FuncCall(new Node\Name('echo_wrapper'), $arguments);

            // Replace the echo statement with the wrapper function call
            return new Stmt\Expression($wrapperFunctionCall);
        }
        if ($node instanceof Node\Expr\Print_) {
            // Create an array of arguments for the wrapper function call
            $arguments = [new Node\Arg($node->expr)];

            // Create a function call node for the wrapper function
            $wrapperFunctionCall = new Node\Expr\FuncCall(new Node\Name('print_wrapper'), $arguments);

            // Replace the print expression with the wrapper function call
            return $wrapperFunctionCall;
        }
        

        return $node;
    }
}

$code = '<?php $result = mysqli_query($conn, $query);';


function parseAndModifyCode($path)
{
    $code = file_get_contents($path);
    $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);

    try {
        $stmts = $parser->parse($code);

        // Create the node visitor
        $nodeVisitor = new EchoWrapperVisitor();

        // Traverse the AST with the node visitor
        $traverser = new PhpParser\NodeTraverser();
        $traverser->addVisitor($nodeVisitor);
        $stmts = $traverser->traverse($stmts);

        // Print the modified code
        $modifiedCode = (new PrettyPrinter\Standard)->prettyPrintFile($stmts);
        #echo $modifiedCode;
        copy($path, $path.".bak");
        file_put_contents($path, $modifiedCode);
    } catch (Error $error) {
        echo 'Parse Error: ', $error->getMessage();
    }
}

function processFileOrDirectory($path)
{
    if (is_file($path)) {
        // Process a single file
        parseAndModifyCode($path);
    } elseif (is_dir($path)) {
        // Process all PHP files in a directory
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        $phpFiles = new RegexIterator($files, '/\.php$/');

        foreach ($phpFiles as $file) {
            if ($file->isFile()) {
                #$fileCode = file_get_contents($file->getPathname());
                parseAndModifyCode($file->getPathname());
            }
        }
    } else {
        echo 'Invalid path: ', $path;
    }
}
// Example usage
$pathToProcess = $argv[1];
processFileOrDirectory($pathToProcess);