<?php
require_once 'vendor/autoload.php';
error_reporting(E_ERROR | E_PARSE);
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;

class ModifyWhileLoopVisitor extends PhpParser\NodeVisitorAbstract
{
    private $replacementFunctionName = 'PHPSHMCache\sqlWrapperFunc';

    public function enterNode(Node $node)
    {
        #echo get_class($node) . "\n";
        if ($node instanceof Stmt\While_) {
            #var_dump($node);
            // Check for a while loop with mysqli_fetch_assoc
            if ($this->isMysqliFetchAssocLoop($node)) {
                // Modify the while loop as needed
                // For example, change it to a foreach loop
                #echo "is while loop???\n";
                #var_dump($node->cond);
                $foreachLoop = new Node\Stmt\Foreach_($node->cond->expr,
                    $node->cond->var         
                );

                return $foreachLoop;
            }
        }

        if ($node instanceof Node\Expr\FuncCall && $this->isMysqliQueryCall($node)) {
            // Replace the mysqli_query call with PHPSHMCache\sqlWrapperFunc
            $newFuncCall = new Node\Expr\FuncCall(
                new Node\Name($this->replacementFunctionName), 
                [
                    new Node\Arg(new Node\Scalar\String_($node->name->toString())),
                    new Node\Expr\Array_(
                        $this->getArgumentsForReplacement($node)
                    )
                ]
            );

            return $newFuncCall;
        }
        
        
        return $node;
    }
    private function getArgumentsForReplacement(Node\Expr $expr)
    {
        // Return the arguments for the replacement function call
        $arguments = [];
        if ($expr instanceof Node\Expr\FuncCall) {
            foreach ($expr->args as $arg) {
                $arguments[] = new Node\Arg($arg->value);
            }
        }

        return $arguments;
    }
    private function isMysqliQueryCall(Node\Expr\FuncCall $node)
    {
        return $node->name instanceof Node\Name && in_array($node->name->toString(), ['mysqli_connect', 'mysqli_query', "mysqli_close", "mysqli_error", "mysqli_connect_error", "mysqli_fetch_assoc", 'mysqli_num_rows', "mysqli_fetch_array", "mysqli_fetch_row", "mysqli_fetch_all"]);
    }

    private function isMysqliFetchAssocLoop(Node\Stmt\While_ $whileNode)
    {
        // Check if it's a while loop with mysqli_fetch_assoc
        #var_dump($whileNode);
        return (
            $whileNode->cond instanceof Node\Expr\Assign &&
            $whileNode->cond->expr instanceof Node\Expr\FuncCall &&
            $this->isMysqliFetchAssocCall($whileNode->cond->expr)
        );
    }

    private function isMysqliFetchAssocCall(Node\Expr\FuncCall $funcCall)
    {
        // Check if it's a mysqli_fetch_assoc() function call
        #echo $funcCall->name->toString() . "\n";
        return (
            $funcCall->name instanceof Node\Name &&
            in_array($funcCall->name->toString(), array('mysqli_fetch_assoc', 'mysqli_fetch_row', 'mysqli_fetch_array'))
        );
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
        $nodeVisitor = new ModifyWhileLoopVisitor();

        // Traverse the AST with the node visitor
        $traverser = new PhpParser\NodeTraverser();
        $traverser->addVisitor($nodeVisitor);
        $stmts = $traverser->traverse($stmts);

        // Print the modified code
        $modifiedCode = (new PrettyPrinter\Standard)->prettyPrintFile($stmts);
        #echo $modifiedCode;
        #copy($path, $path.".bak");
        echo rtrim($path, ".php") . "-fuzzcache.php";
        file_put_contents(rtrim($path, ".php") . "-fuzzcache.php", $modifiedCode);
    } catch (Error $error) {
        echo 'Parse Error: ', $error->getMessage();
    }
}

function processFileOrDirectory($path)
{
    if (is_file($path)) {
        // Process a single file
        #$fileCode = file_get_contents($path);
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

$pathToProcess = $argv[1];
processFileOrDirectory($pathToProcess);
