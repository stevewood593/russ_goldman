
Phlint - PHP Linter, Code Analyzer and Tester
=============================================


Introduction
------------

Phlint is a tool with an aim to help maintain quality of php code by analyzing code and pointing out potential code
issues. It focuses on how the code works rather than how the code looks. Phlint is designed from the start to do
deep semantic analysis rather than doing only shallow or stylistic analysis.


Why Phlint?
-----------

As there are already many code analysis tools the question that arises a lot is "Why yet another one?".
The reason is because there are not so many analyzers like Phlint for PHP, more info is available on
[Why Phlint](/documentation/why-phlint.md) documentation page.


Basic usage
-----------

Phlint can be downloaded from [the download section](https://gitlab.com/phlint/phlint-compiled/tree/master) and used
as a command line utility:

    # To download run:
    wget https://gitlab.com/phlint/phlint-compiled/raw/master/phlint-0.3.2.phar -O phlint.phar

    # To invoke run:
    php phlint.phar /path/to/project

Alternatively it can be included in the project through composer:

    # To install run:
    composer require phlint/phlint

    # To invoke run:
    ./vendor/bin/phlint


Documentation
-------------

For a full documentation visit [Phlint documentation page](/documentation/index.md).
For a contribution guidelines visit [Contributing guidelines page](/contributing.md).


License
-------

Phlint is licensed under the [MIT license](/license.txt).
