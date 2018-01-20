<?php

namespace phlint;

use \Exception;
use \Phlint;

class PHPUnitTest extends \PHPUnit_Framework_TestCase
{
    public function testPhlint()
    {
        ini_set('memory_limit', '1024M');
        ini_set('xdebug.max_nesting_level', '1024');

        $this->disableXDebug();

        if (in_array('xdebug', get_loaded_extensions())) {
            return;
        }

        $phlint = new Phlint();

        if (is_file($this->getCodePath() . '/phlint.configuration.php')) {
          $configurator = require($this->getCodePath() . '/phlint.configuration.php');
          $configurator($phlint);
        }

        /** Log in the same folder where `--log-junit` is being stored to. */
        if (preg_match('/(?is)--log-junit[ \t]+(.*?)(?=[ \t]|$)/', implode(' ', $GLOBALS['argv']), $match)) {
            $phlint->addOutput(fopen(dirname($match[1]) . '/phlint.log', 'w+'));
        }

        $testResult = $phlint->analyze();

        $this->assertEquals(0, count($testResult->getIssues()), $testResult->toString());
    }

    protected function getCodePath()
    {
        $codePath = __dir__;
        while ($codePath && $codePath != dirname($codePath)) {
            if (is_file($codePath . '/phlint.configuration.php')) {
                break;
            }
            if (is_file($codePath . '/composer.json')) {
                break;
            }
            $codePath = dirname($codePath);
        }
        return $codePath;
    }

    /**
     * Disable XDebug due to performance issues with it.
     * It seems that for data processing heavy code XDebug has
     * massive performance implications.
     */
    protected function disableXDebug()
    {
        if (!in_array('xdebug', get_loaded_extensions())) {
            return;
        }

        $method = debug_backtrace()[1]['function'];

        $process = new \Symfony\Component\Process\PhpProcess('<?php

            class PHPUnit_Framework_TestCase {
                function __call($name, $arguments)
                {
                    echo "\\$this->" . $name . "(" . implode(", ", array_map(function ($value) {
                        return var_export($value, true);
                    }, $arguments)) . ");\\n";
                }
            }

            $GLOBALS["argv"] = ' . var_export($GLOBALS['argv'], true) . ';

            require ' . var_export($this->getCodePath(), true) . ' . "/vendor/autoload.php";

            $test = new ' . __class__ . '();
            $test->' . $method . '();

        ');

        /**
         * Don't load the default ini file. This is the main reason why we are running
         * a sub-process in the first place - to disable XDebug.
         * It seems that XDebug can't be disabled during runtime, nor can an extension
         * defined in the php.ini be excluded from loading with parameters.
         * So far this seems to be the only way not to load XDebug.
         */
        $process->setCommandLine($process->getCommandLine() . ' -n');

        /**
         * For some reason these two extensions are not statically linked
         * on *nix systems so we need to load the explicitly.
         */
        if (PHP_SHLIB_SUFFIX == 'so') {
            $process->setCommandLine($process->getCommandLine() . ' -dextension=tokenizer.so -dextension=json.so');
        }

        $process->run();

        if (!$process->isSuccessful()) {
            throw new Exception('Error while running a php sub-process: ' . $process->getOutput());
        }

        eval($process->getOutput());
    }
}
