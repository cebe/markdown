<?php
/*
 * Copyright (c) 2014 Carsten Brandt <mail@cebe.cc>
 * 
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 * 
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * @author Carsten Brandt <mail@cebe.cc>
 */

require(__DIR__ . '/../Parser.php');
require(__DIR__ . '/../Markdown.php');


$markdown = '';
$markdown = file_get_contents(__DIR__ . '/markdown-data/specs.md');
//$markdown = file_get_contents(__DIR__ . '/github-data/github-sample.md');


//ini_set('xhprof.output_dir', __DIR__ . '/xhprof');

// http://de3.php.net/manual/en/xhprof.examples.php
xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY);

for ($n = 0; $n < 100; $n++) {
	$pd = new \cebe\markdown\Markdown();
	$pd->parse($markdown);
}

$xhprof_data = xhprof_disable();

$XHPROF_ROOT = __DIR__ . '/../vendor/facebook/xhprof/';
include_once $XHPROF_ROOT . '/xhprof_lib/utils/xhprof_lib.php';
include_once $XHPROF_ROOT . '/xhprof_lib/utils/xhprof_runs.php';

$xhprof_runs = new XHProfRuns_Default();
$run_id = $xhprof_runs->save_run($xhprof_data, "xhprof_testing");

echo "http://localhost/xhprof/xhprof_html/index.php?run={$run_id}&source=xhprof_testing\n";
