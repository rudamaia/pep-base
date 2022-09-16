<?php
namespace Deployer;

require 'recipe/common.php';

// Values in (>>__CAPS__<<) needs to be changed. <<<<<<<<
// Config
set('repository', 'git@github.com:(>>__REPOSITORY_USER__<<)/(>>__REPOSITORY_NAME__<<).git');

// Hosts
host('(>>__PROJECT_DOMAIN.com__<<)')
// Linux user with same apache 'www-data' group.
  ->set('remote_user', '(>>__DEPLOYER_USER__<<)')
  ->set('deploy_path', '/var/www/(>>__PROJECT_DIRECTORY__<<)');
// Release.
set('release', '{{release_or_current_path}}');
set('keep_releases', 1);
// Composer - PHP package manager.
task('composer', function () {
  set('composer_action', 'install');
  set('composer_options', '--no-progress --no-interaction --no-dev --optimize-autoloader');

  // Returns Composer binary path in found. Otherwise try to install latest
  // composer version to `.dep/composer.phar`. To use specific composer version
  // download desired phar and place it at `.dep/composer.phar`.
  set('bin/composer', function () {
    if (test('[ -f {{deploy_path}}/.dep/composer.phar ]')) {
      return '{{bin/php}} {{deploy_path}}/.dep/composer.phar';
    }

    if (commandExist('composer')) {
      return '{{bin/php}} ' . which('composer');
    }

    warning("Composer binary wasn't found. Installing latest composer to \"{{deploy_path}}/.dep/composer.phar\".");
    run("cd {{deploy_path}} && curl -sS https://getcomposer.org/installer | {{bin/php}}");
    run('mv {{deploy_path}}/composer.phar {{deploy_path}}/.dep/composer.phar');
    return '{{bin/php}} {{deploy_path}}/.dep/composer.phar';

  });
  if (!commandExist('unzip')) {
    warning('To speed up composer installation setup "unzip" command with PHP zip extension.');
  }
  desc('Installs vendors');
  run('cd {{release_or_current_path}} && {{bin/composer}} {{composer_action}} {{composer_options}} 2>&1');
  run('cd {{release}} && {{bin/composer}} {{composer_action}} {{composer_options}} 2>&1');

});
// Runs Drush (Drupal CLI) to update db, import configs, clear cache.
set('drush', '{{deploy_path}}/current/vendor/bin/drush');
task('drush:update', function () {
  run('rm -rf {{release}}/files/js {{release}}/files/php {{release}}/files/css ');
  run('chown -R deployer:www-data {{release_or_current_path}}');
  run('cd {{release}}');
  run('{{drush}} cr');
  run('{{drush}} updb -y');
  run('{{drush}} cim -y');
  run('{{drush}} cr');
});

// Tasks - Runs tasks in order including common tasks and custom ones described before.
task('deploy', [
  'deploy:prepare',
  'composer',
  'deploy:publish',
  'drush:update',
]);

//Set drupal site. Change if you use different site
set('drupal_site', 'default');

//Drupal 8 shared dirs
set('shared_dirs', [
  'sites/{{drupal_site}}/files',
]);

//Drupal 8 shared files - Shared files are setup outside the repository folder.
set('shared_files', [
  'docroot/sites/{{drupal_site}}/settings.local.php',
  'docroot/sites/{{drupal_site}}/services.yml',
  'docroot/.htaccess',
]);

//Drupal 8 Writable dirs
set('writable_dirs', [
  'docroot/sites/{{drupal_site}}/files',
]);

// Hooks

after('deploy:failed', 'deploy:unlock');
