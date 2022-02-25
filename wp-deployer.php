<?php
use function Deployer\{
  after,
  askConfirmation,
  cd,
  desc,
  download,
  get,
  has,
  inventory,
  invoke,
  parse,
  task,
  run,
  runLocally,
  set,
  upload,
  within,
  writeln
};

use Symfony\Component\Yaml\Yaml;

class WP_Deployer {
  function __construct($config_file = null) {
    require_once('recipe/common.php');

    if (!$config_file) $config_file = 'config.yml';

    set('forwardAgent', true);

    set('allow_anonymous_stats', false);
    set('default_stage', 'staging');
    set('git_tty', true);
    set('ssh_multiplexing', true);

    set('url', 'http://localhost');
    set('wp_content_dir', 'wp-content/content');

    set('deploy_path', '/var/www/public_html');
    set('public_path', '/var/www/public');
    set('db_backups_path', 'db_backups');
    set('templates_path', 'templates');
    set('tmp_path', 'tmp');

    set('extra_files', array(
      'auth.json',
      '{{wp_content_dir}}/themes/{{theme_name}}/*.css',
      '{{wp_content_dir}}/themes/{{theme_name}}/*.map',
      '{{wp_content_dir}}/themes/{{theme_name}}/js/*.js',
      '{{wp_content_dir}}/themes/{{theme_name}}/js/*.map'
    ));

    set('clear_paths', array(
      '.git',
      'templates',
      '.gitignore',
      '.lando.yml',
      'auth.json',
      'composer.json',
      'composer.lock',
      'config.yml',
      'deploy.php',
      'readme.md'
    ));

    set('shared_files', array(
      '.htaccess',
      'robots.txt',
      'wp-config.php'
    ));

    set('shared_dirs', array(
      'content/uploads'
    ));

    set('writable_dirs', array(
      '{{wp_content_dir}}/uploads'
    ));

    inventory($config_file);

    set('shared_path', '{{deploy_path}}/shared');

    if (!get('wp_site_url')) set('wp_site_url', '{{url}}');

    desc('Deploy the website');
    task('deploy', array(
      'deploy:info',
      'deploy:prepare',
      'deploy:lock',
      'deploy:release',
      'deploy:update_code',
      'deploy:extra_files',
      'deploy:shared',
      'deploy:writable',
      'deploy:vendors',
      'deploy:clear_paths',
      'deploy:symlink',
      'deploy:symlink:public',
      'deploy:unlock',
      'cleanup',
      'success'
    ));

    desc('Uploads extra_files');
    task('deploy:extra_files', function () {
      $included_files = get('extra_files');

      if (!$included_files || empty($included_files)) return;

      $included_dirs = array();
      $cmd = implode(' ', array(
        'rsync',
        '-azP',
        '-e "ssh -A"',
        '.',
        '{{user}}@{{hostname}}:{{release_path}}',
        '--verbose',
        '--delete'
      ));

      foreach($included_files as $file_path) {
        $tree = explode('/', ltrim($file_path, '/'));
        $tree_count = count($tree);

        foreach($tree as $i => $file) {
          $path = implode('/', array_slice($tree, 0, ($i + 1)));

          if ($i !== ($tree_count - 1)) {
            $path .= '/';

            if (in_array($path, $included_dirs)) {
              continue;
            } else {
              $included_dirs[] = $path;
            }
          }

          $cmd .= " --include=\"$path\"";
        }
      }

      $cmd .= ' --exclude="*"';

      runLocally($cmd);
    });

    desc('Symlinks the public directory to the current release');
    task('deploy:symlink:public', function () {
      run('ln -sfn {{release_path}} {{public_path}}');
    });

    desc('Converts the local database from utf8mb4_unicode_520_ci encoding to utf8mb4_unicode_ci');
    task('db:fix', function () {
      $db_file = '{{db_backups_path}}/' . date('ymdHis') . '.sql';

      runLocally('mkdir -p db_backups');
      runLocally("./vendor/bin/wp db export $db_file --add-drop-table", [ 'timeout' => null ]);
      runLocally("LC_ALL=C sed -i'-before-fix.sql' 's/utf8mb4_unicode_520_ci/utf8mb4_unicode_ci/g' $db_file", [ 'timeout' => null ]);
      runLocally("./vendor/bin/wp db import $db_file", [ 'timeout' => null ]);
    });

    desc('Pulls the database');
    task('db:pull', function () {
      $db_file = '{{db_backups_path}}/' . date('ymdHis') . '.sql';
      $local_url = $this->load_yml('config.yml', '.default')['url'];

      run('mkdir -p {{shared_path}}/db_backups');
      cd('{{release_path}}');
      runLocally("./vendor/bin/wp db export {{shared_path}}/$db_file --add-drop-table --ssh={{user}}@{{hostname}}:{{release_path}}");
      runLocally('mkdir -p db_backups');
      download("{{shared_path}}/$db_file", $db_file);
      runLocally("./vendor/bin/wp db import $db_file");
      runLocally("./vendor/bin/wp search-replace {{url}} $local_url");
      runLocally("rm $db_file");
      run("rm {{shared_path}}/$db_file");
    })->once();

    desc('Pushes the database');
    task('db:push', function () {
      $db_file = '{{db_backups_path}}/' . date('ymdHis') . '.sql';
      $stage = get('stage');
      $local_url = $this->load_yml('config.yml', '.default')['url'];

      if (!(askConfirmation("The database on $stage will be replaced with the one on local! Continue?"))) return;

      runLocally('mkdir -p db_backups');
      runLocally("./vendor/bin/wp db export $db_file --add-drop-table");
      run('mkdir -p {{shared_path}}/db_backups');
      upload($db_file, "{{shared_path}}/$db_file");
      cd('{{release_path}}');
      runLocally("./vendor/bin/wp db import {{shared_path}}/$db_file --ssh={{user}}@{{hostname}}:{{release_path}}");
      runLocally("./vendor/bin/wp search-replace $local_url {{url}} --ssh={{user}}@{{hostname}}:{{release_path}}");
      run("rm {{shared_path}}/$db_file");
      runLocally("rm $db_file");
    })->once();

    desc('Gets the site initially all set up for local development');
    task('setup:init', function () {
      invoke('setup:local');
      invoke('uploads:pull');
      invoke('db:pull');
    });

    desc('Generates the wp-config.php file for local development environment');
    task('setup:local', function () {
      $templates = $this->get_templates_list();
      $config = $this->load_yml('config.yml', '.default');
      $pass = $this->generate_random_string();
      $data = array(
        'database'            => $config['database'],
        'deploy_path'         => array_key_exists('deploy_path', $config) ? $config['deploy_path'] : '/var/www/public_html',
        'env'                 => 'local',
        'secret_keys'         => $this->generate_secrets(),
        'url'                 => $config['url'],
        'wp_config_constants' => array_key_exists('wp_config_constants', $config) ? $config['wp_config_constants'] : array(),
        'wp_content_dir'      => array_key_exists('wp_content_dir', $config) ? $config['wp_content_dir'] : 'wp-content/content',
        'wp_site_url'         => array_key_exists('wp_site_url', $config) ? $config['wp_site_url'] : $config['url']
      );

      foreach($templates as $template) {
        $contents = $this->render_template($template, $data, 'local');
        $file_name = substr($template, 0, -5);

        file_put_contents($file_name, $contents);
      }

      runLocally("./vendor/bin/wp core install --url='{$config['url']}' --title='{$config['application']}' --admin_user='{$config['wp_user']}' --admin_password='$pass' --admin_email='{$config['wp_email']}'");
      
      writeln('');
      writeln('----------------------------------');
      writeln("| Username: {$config['wp_user']}");
      writeln("| Password: $pass");
      writeln('----------------------------------');
      writeln('');
    });

    desc('Generates the wp-config.php file for staging or production');
    task('setup:remote', function () {
      if (!(askConfirmation('This command should only be ran on a new website! Continue?'))) return;

      $templates = $this->get_templates_list();
      $pass = $this->generate_random_string();
      $stage = get('stage');
      $data = array(
        'database'            => get('database'),
        'deploy_path'         => get('deploy_path'),
        'env'                 => $stage,
        'secret_keys'         => $this->generate_secrets(),
        'url'                 => get('url'),
        'wp_config_constants' => has('wp_config_constants') ? get('wp_config_constants') : array(),
        'wp_content_dir'      => get('wp_content_dir'),
        'wp_site_url'         => get('wp_site_url')
      );

      runLocally('mkdir -p {{tmp_path}}');

      foreach($templates as $template) {
        $contents = $this->render_template($template, $data);
        $file_name = substr($template, 0, -5);
        $tmp_name = parse("{{tmp_path}}/$file_name.$stage");

        file_put_contents($tmp_name, $contents);

        upload($tmp_name, "{{shared_path}}/$file_name");
        runLocally("rm $tmp_name");
      }

      invoke('deploy:shared');

      cd('{{release_path}}');
      runLocally("./vendor/bin/wp core install --url='{{url}}' --title='{{application}}' --admin_user='{{wp_user}}' --admin_password='$pass' --admin_email='{{wp_email}}' --ssh={{user}}@{{hostname}}:{{release_path}}");
    });

    desc('Pulls the uploads');
    task('uploads:pull', function () {
      download('{{deploy_path}}/shared/{{wp_content_dir}}/uploads/', '{{wp_content_dir}}/uploads');
    });

    desc('Pushes the uploads');
    task('uploads:push', function () {
      upload('{{wp_content_dir}}/uploads/', '{{deploy_path}}/shared/{{wp_content_dir}}/uploads');
    });

    after('deploy:failed', 'deploy:unlock');
  }

  private function generate_random_string($length = 12) {
    return bin2hex(openssl_random_pseudo_bytes($length / 2));
  }

  private function generate_secrets() {
    return file_get_contents('https://api.wordpress.org/secret-key/1.1/salt');
  }

  private function get_templates_list() {
    $templates = array(
      '.htaccess.twig',
      'robots.txt.twig',
      'wp-config.php.twig'
    );

    // @TODO: Allow for custom templates to be defined

    return $templates;
  }

  private function load_yml($file, $property = null) {
    $contents = Yaml::parseFile($file);

    if ($property) {
      if (array_key_exists($property, $contents)) {
        $contents = $contents[$property];
      } else {
        return false;
      }
    }

    array_walk_recursive($contents, function (&$value) {
      if (is_string($value)) $value = parse($value);
    });

    return $contents;
  }

  private function render_template($file, $options = array(), $stage = null) {
    if (!$stage) $stage = get('stage');

    $dir = get('templates_path');
    $default_dir = dirname(__FILE__) . '/templates';
    $stage_file = substr($file, 0, -4) . $stage . '.twig';

    if (file_exists($dir)) {
      if (file_exists($dir . '/' . $stage_file)) {
        $file = $stage_file;
      } elseif (!file_exists($dir . '/' . $file)) {
        $dir = $default_dir;
      }
    } else {
      $dir = $default_dir;

      if (file_exists($dir . '/' . $stage_file)) {
        $file = $stage_file;
      }
    }

    $loader = new \Twig\Loader\FilesystemLoader($dir);
    $twig = new \Twig\Environment($loader);
    return $twig->render($file, $options);
  }
}
?>