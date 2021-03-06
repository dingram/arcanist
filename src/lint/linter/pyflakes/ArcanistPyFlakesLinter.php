<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Uses "PyFlakes" to detect various errors in Python code.
 *
 * @group linter
 */
final class ArcanistPyFlakesLinter extends ArcanistLinter {

  public function willLintPaths(array $paths) {
    return;
  }

  public function getLinterName() {
    return 'PyFlakes';
  }

  public function getLintSeverityMap() {
    return array();
  }

  public function getLintNameMap() {
    return array(
    );
  }

  public function getPyFlakesOptions() {
    return null;
  }

  public function lintPath($path) {
    $working_copy = $this->getEngine()->getWorkingCopy();
    $pyflakes_path = $working_copy->getConfig('lint.pyflakes.path');
    $pyflakes_prefix = $working_copy->getConfig('lint.pyflakes.prefix');

    // Default to just finding pyflakes in the users path
    $pyflakes_bin = 'pyflakes';
    $python_path = '';

    // If a pyflakes path was specified, then just use that as the
    // pyflakes binary and assume that the libraries will be imported
    // correctly.
    //
    // If no pyflakes path was specified and a pyflakes prefix was
    // specified, then use the binary from this prefix and add it to
    // the PYTHONPATH environment variable so that the libs are imported
    // correctly.  This is useful when pyflakes is installed into a
    // non-default location.
    if ($pyflakes_path !== null) {
      $pyflakes_bin = $pyflakes_path;
    } else if ($pyflakes_prefix !== null) {
      $pyflakes_bin = $pyflakes_prefix.'/bin/pyflakes';
      $python_path = $pyflakes_prefix.'/lib/python2.6/site-packages:';
    }

    $options = $this->getPyFlakesOptions();

    $f = new ExecFuture(
          "/usr/bin/env PYTHONPATH=%s\$PYTHONPATH ".
            "{$pyflakes_bin} {$options}", $python_path);
    $f->write($this->getData($path));

    try {
      list($stdout, $_) = $f->resolvex();
    } catch (CommandException $e) {
      // PyFlakes will return an exit code of 1 if warnings/errors
      // are found but print nothing to stderr in this case.  Therefore,
      // if we see any output on stderr or a return code other than 1 or 0,
      // pyflakes failed.
      if ($e->getError() !== 1 || $e->getStderr() !== '') {
        throw $e;
      } else {
        $stdout = $e->getStdout();
      }
    }

    $lines = explode("\n", $stdout);
    $messages = array();
    foreach ($lines as $line) {
      $matches = null;
      if (!preg_match('/^(.*?):(\d+): (.*)$/', $line, $matches)) {
        continue;
      }
      foreach ($matches as $key => $match) {
        $matches[$key] = trim($match);
      }
      $message = new ArcanistLintMessage();
      $message->setPath($path);
      $message->setLine($matches[2]);
      $message->setCode($this->getLinterName());
      $message->setDescription($matches[3]);
      $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
      $this->addLintMessage($message);
    }
  }

}
