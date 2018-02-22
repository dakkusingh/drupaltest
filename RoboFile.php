<?php

use Robo\Tasks;

class RoboFile extends Tasks {

  /**
   * This hook will fire for all commands in this command file.
   *
   * @hook init
   */
  public function initialize() {
    $this->drupalRoot = __DIR__;
    $this->bin = $this->drupalRoot . '/vendor/bin';
    $this->phpcs = $this->bin . '/phpcs';
    $this->phpunit = $this->bin . '/phpunit';
  }

  /**
   * Run PHPCS.
   *
   * @return \Robo\Result
   */
  public function codesniffdrupalfiles() {
    // Get an array of files.
    exec('git diff --name-only --cached --diff-filter=ACM', $files);

    $extensions = [
      'php',
      'module',
      'inc',
      'install',
      'test',
      'profile',
      'theme',
      'css',
      'info',
//      'txt',
//      'md',
      'yml'
    ];

    // Step over files.
    foreach ($files as $file) {
      // Get extension of the affected file.
      $ext = pathinfo($file, PATHINFO_EXTENSION);

      // Only check file if its affected.
      if (in_array($ext, $extensions)) {
        $this->phpcsdrupal($file, 'Drupal');
        $this->phpcsdrupal($file, 'DrupalPractice');
      }
    }

  }

  public function phpcsdrupal($path, $standard = 'Drupal') {
    $extensions = 'php,module,inc,install,test,profile,theme,css,info,txt,md';

    // Check for Drupal standards.
    $result = $this->taskExec("{$this->phpcs} --standard={$standard} --extensions={$extensions} {$path}")
      ->run()
      ->stopOnFail();

    return $result;
  }

  public function buildbranch($branch_name) {
    // Git Pull.
    #$this->pullbranch($branch_name);

    // Composer update the project.
    #$this->composerupdate();

    // Cleanup GIT.
    $this->cleanup_git();

    // Do the commit steps.
    $this->commitbranch();

    if ($branch_name == 'master') {
      $this->pushbranchwithtag($branch_name);
    }
    else {
      $this->pushbranch($branch_name);
    }
  }

  public function pullbranch($branch_name) {
    // git pull origin master-build.
    $build_branch_name = $this->buildbranchname($branch_name);
    return $this->taskGitStack()
      ->pull('origin ' . $build_branch_name)
      ->run()
      ->stopOnFail();
  }

  private function cleanup_git() {
    // see https://github.com/acquia/blt/blob/8.x/src/Robo/Commands/Deploy/DeployCommand.php#L416
    // find 'vendor' -type d | grep '\.git' | xargs rm -rfv
    // find 'web' -type d | grep '\.git' | xargs rm -rfv
    exec("find 'vendor' -type d | grep '\.git' | xargs rm -rfv");
    exec("find 'vendor' -type d | grep '\.git' | xargs rm -rfv");
  }

  private function buildbranchname($branch_name = 'master') {
    $build_branch_name = $branch_name . '-build';
    return $build_branch_name;
  }

  private function commitbranch() {
    // git remote -v
    // git add -f --ignore-errors *
    // git diff --quiet && git diff --staged --quiet || git commit -am 'Built by Bitbucket Pipelines'
    return $this->taskExecStack()
      ->exec('git remote -v')
      ->exec('git add -f --ignore-errors *')
      ->exec("git diff --quiet && git diff --staged --quiet || git commit -am 'Built by Bitbucket Pipelines'")
      ->run()
      ->stopOnFail();
  }

  private function pushbranchwithtag($branch_name) {
    $build_branch_name = $this->buildbranchname($branch_name);

    // git tag `date "+V.%y.%m.%d-%H%M%S"`
    // git push -v --tags origin master:master-build --force
    return $this->taskExecStack()
      ->exec('git tag `date "+V.%Y.%m.%d-%H%M%S"`')
      ->exec("git push -v --tags origin {$branch_name}:{$build_branch_name} --force")
      ->run()
      ->stopOnFail();
  }

  private function pushbranch($branch_name) {
    $build_branch_name = $this->buildbranchname($branch_name);

    // git push -v origin develop:develop-build --force
    return $this->taskExecStack()
      ->exec("git push -v origin {$branch_name}:{$build_branch_name} --force")
      ->run()
      ->stopOnFail();
  }

  public function composerupdate() {
    // composer update --verbose --prefer-dist
    $this->taskComposerUpdate()
      ->preferDist()
      ->option('--verbose')
      ->run()
      ->stopOnFail();
  }

  public function phpunittests() {
    $phpunit_command_string = $this->phpunit . ' -c web/core web/core/tests/Drupal/Tests/Core/Password/PasswordHashingTest.php';
    $task = $this->taskExecStack()->exec($phpunit_command_string);
    $result = $task->run();
    return $result;
  }

}
