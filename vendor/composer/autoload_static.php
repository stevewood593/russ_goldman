<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInite4accb902f6fc45d156b7e092153e2cb
{
    public static $files = array (
        '49e724578049088f005a147470938820' => __DIR__ . '/..' . '/luka8088/phops/code/luka8088/phops/convert.php',
        '5dec29b499e7338aa45d38ebeef00658' => __DIR__ . '/..' . '/luka8088/phops/code/luka8088/phops/metaContext.php',
        'c7ad04898608e747c9948e36c9c07455' => __DIR__ . '/..' . '/luka8088/phops/code/luka8088/phops/scopeExit.php',
        'e69d29455f02dfb48138dc5bfb3df1f1' => __DIR__ . '/..' . '/luka8088/phops/code/luka8088/phops/strict.php',
        'd4ddbeb93bc4421700426f2a6aafcb5c' => __DIR__ . '/..' . '/luka8088/phops/code/luka8088/phops/traits.php',
    );

    public static $prefixLengthsPsr4 = array (
        'S' => 
        array (
            'Symfony\\Component\\Process\\' => 26,
        ),
        'P' => 
        array (
            'PhpParser\\' => 10,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Symfony\\Component\\Process\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/process',
        ),
        'PhpParser\\' => 
        array (
            0 => __DIR__ . '/..' . '/nikic/php-parser/lib/PhpParser',
        ),
    );

    public static $fallbackDirsPsr4 = array (
        0 => __DIR__ . '/..' . '/phlint/phif/code',
        1 => __DIR__ . '/..' . '/luka8088/attribute/code',
        2 => __DIR__ . '/..' . '/luka8088/extension-interface/code',
        3 => __DIR__ . '/..' . '/phlint/phlint/code',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInite4accb902f6fc45d156b7e092153e2cb::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInite4accb902f6fc45d156b7e092153e2cb::$prefixDirsPsr4;
            $loader->fallbackDirsPsr4 = ComposerStaticInite4accb902f6fc45d156b7e092153e2cb::$fallbackDirsPsr4;

        }, null, ClassLoader::class);
    }
}
