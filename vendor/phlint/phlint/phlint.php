<?php

ini_set('memory_limit', -1);

$cwd = getcwd();

foreach ([__dir__ . '/../../autoload.php', __dir__ . '/vendor/autoload.php'] as $autoloadFile)
  if (is_file($autoloadFile))
    require $autoloadFile;

chdir($cwd);

\phlint\Internal::disableXDebug();

if (in_array('xdebug', array_map(function ($name) { return strtolower($name); }, get_loaded_extensions(true))))
  echo "\x1b[33m" .
    "Warning: XDebug is currently enabled. Running phlint with XDebug has significant performance implications." .
    "\x1b[0m\n";

$phlint = new \Phlint();

$phlint->addOutput(fopen('php://stdout', 'w'));

$path = count($argv) > 1 ? $argv[1] : '.';

$codePath = $path;
while ($codePath && $codePath != dirname($codePath)) {
    if (is_file($codePath . '/phlint.configuration.php')) {
        break;
    }
    if (is_file($codePath . '/composer.json')) {
        break;
    }
    $codePath = dirname($codePath);
}

if (is_file($codePath . '/phlint.configuration.php')) {

  if (true) {
  $configurator = require($codePath . '/phlint.configuration.php');
  $configurator($phlint);
  }

  if (count($argv) > 1 && realpath($argv[1]) != realpath($codePath)) {
    $phlint->code = [];
    $phlint->addPath($path);
  }

} else {

  if (true)
  if (is_file($codePath . '/composer.json'))
    $phlint[] = new \phlint\autoload\Composer($codePath . '/composer.json');

  if (is_file($path))
    $phlint->addPath($path);
  else
    foreach (glob($path . '/*') as $subPath) {
      if (basename($subPath) == 'vendor')
        continue;
      $phlint->addPath($subPath);
    }

}

$phlint->analyze();
