<?php

require __dir__ . '/vendor/autoload.php';

function runUnitTests () {

  set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
  });

  $result = (object) [];

  $timestamp = microtime(true);
  $files = [];

  foreach ([__dir__ . '/code', __dir__ . '/test'] as $scanPath)
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scanPath)) as $file)
      if (in_array($file->getExtension(), ['php'])) {
        $fileContent = file_get_contents($file);
        if (preg_match('/(?si)function\s*unittest/', $fileContent) == 0)
          continue;
        $files[] = $file;
      }

  usort($files, function ($lhs, $rhs) {
    return filemtime($rhs) - filemtime($lhs);
  });

  $includeFile = function ($file) {
    ob_start();
    include_once (string) $file;
    ob_end_clean();
  };

  foreach ($files as $file) {
    if (in_array(realpath($file), get_included_files()))
      continue;
    $includeFile($file);
  }

  $tests = [];

  $functions = get_defined_functions();
  foreach (array_merge($functions['internal'], $functions['user']) as $function)
    if (substr(strtolower($function), 0, 8) == 'unittest')
      $tests[] = $function;

  foreach (get_declared_classes() as $class) {
    foreach (get_class_methods($class) as $method) {
      $reflector = new ReflectionMethod($class, $method);
      if ($reflector->getDeclaringClass()->getName() != $class)
        continue;
      if (substr(strtolower($method), 0, 8) == 'unittest' && !in_array("$class::$method", [
        'unitTest_webBrowser::unitTestElement',
        'unitTest_element::unitTestElement',
        'unitTest_webContext::unitTestElement',
        'unitTest_webBrowser::unitTestElements',
        'unitTest_element::unitTestElements',
        'unitTest_webContext::unitTestElements',
      ]))
        $tests[] = [$class, $method];
    }
  }

  $result->tests = (object) [
    'count' => count($tests),
    'all' => [],
    'successes' => [],
    'failures' => [],
    'errors' => [],
  ];

  usort($tests, function ($lhs, $rhs) {
    $lhsReflection = is_string($lhs) ? new ReflectionFunction($lhs) : new ReflectionMethod($lhs[0], $lhs[1]);
    $rhsReflection = is_string($rhs) ? new ReflectionFunction($rhs) : new ReflectionMethod($rhs[0], $rhs[1]);
    return filemtime($rhsReflection->getFileName()) - filemtime($lhsReflection->getFileName());
  });

  echo "Running " . count($tests) . " tests:\n";

  foreach ($tests as $key => $test) {
    $id = (is_string($test) ? $test : $test[0] . '::' . $test[1]) . '()';
    $result->tests->all[$id] = (object) ['status' => 'unknown'];
  }

  foreach ($tests as $key => $test) {
    $id = (is_string($test) ? $test : $test[0] . '::' . $test[1]) . '()';
    echo '  ' . $id;
    try {
      call_user_func($test);
      echo ": success";
      $result->tests->all[$id]->status = 'success';
      $result->tests->successes[] = $id;
    } catch (\Throwable $e) {
      echo ": failure - " . $e;
      $result->tests->all[$id]->status = 'failure';
      $result->tests->failures[] = $id;
    }
    echo "\n";
  }

  echo "\nResults: \n";
  echo "  Total:   " . $result->tests->count . "\n";
  echo "  Success: " . count($result->tests->successes) . "\n";
  echo "  Failure: " . count($result->tests->failures) . "\n";
  echo "  Errors:  " . count($result->tests->errors) . "\n";

  if (count($result->tests->successes) != $result->tests->count)
    echo "\nTests completed with failures, errors or skipped tests !\n\n";
  else
    echo "\nEverything completed successfully !\n\n";

  echo "Done\n";

}

runUnitTests();
