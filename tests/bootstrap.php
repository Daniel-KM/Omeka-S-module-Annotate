<?php declare(strict_types=1);

require dirname(__DIR__, 2) . '/Common/tests/Bootstrap.php';

\CommonTest\Bootstrap::bootstrap(
    ['Common', 'CustomVocab', 'Annotate'],
    'AnnotateTest',
    __DIR__ . '/AnnotateTest'
);
