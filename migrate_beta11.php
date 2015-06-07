<?php
/**
 * @file
 *
 * @todo Which version / date are we migrating from?
 *
 * (On the moment of writing this, the old installation is "tags/2015-05-06(2)",
 * the new installation is beta11.)
 *
 * The data is taken from "old" database and copied to "default" database.
 * Before run, ensure that $databases['old']['default'] is set in the
 * settings.php.
 *
 * WARNING: All tables that are affected by the script are truncated at the
 * beginning. Be sure to not lost the data. Use "drush sql-dump" before run.
 *
 * Run from drupal root:
 * drush scr modules/beta11_migration/migrate.php
 */
Beta11Migration::run();
return;
class Beta11Migration {
  public static function run() {
    if (PHP_SAPI !== 'cli') {
      return;
    }
    ini_set('max_execution_time', 60*60);
    ini_set('memory_limit', '2G');
    // Get list of common tables.
    // Check if the "old" database connection is properly defined during the
    // first switch.
    if (db_set_active('old') === NULL) {
      print "ERROR: the 'old' database connection is not defined!\n";
      return;
    }
    $tables_old = db_query('SHOW TABLES')->fetchCol();
    db_set_active();
    $tables_new = db_query('SHOW TABLES')->fetchCol();
    $tables_common = array_intersect($tables_old, $tables_new);
    // This is what we want to migrate. The array values are table names or
    // table name prefixes.
    $base_tables_to_migrate = [
      'block',
      'comment',
      'file',
      'node',
      'taxonomy',
      'url',
      'user',
      'users',
    ];
    $tables_to_migrate = [];
    foreach ($tables_common as $table_name) {
      foreach ($base_tables_to_migrate as $base_table_name) {
        if ($table_name == $base_table_name || strpos($table_name, $base_table_name . '_') === 0) {
          $tables_to_migrate[] = $table_name;
          break;
        }
      }
    }
    // Migrate common data.
    foreach ($tables_to_migrate as $table) {
      // Get column info.
      db_set_active('old');
      $columns_old = db_query('SHOW COLUMNS FROM ' . $table)->fetchCol();
      db_set_active();
      $columns_new = db_query('SHOW COLUMNS FROM ' . $table)->fetchCol();
      $exists_only_in_old_db = array_diff($columns_old, $columns_new);
      $exists_only_in_new_db = array_diff($columns_new, $columns_old);
      // Get rows.
      db_set_active('old');
      $rows = db_select($table, 't')
        ->fields('t')
        ->execute()
        ->fetchAll(PDO::FETCH_ASSOC);
      // Prepare table.
      db_set_active();
      db_truncate($table)->execute();
      $table_status = db_query("SHOW TABLE STATUS LIKE '$table'")->fetch();
      if (isset($table_status->Auto_increment)) {
        db_query("ALTER TABLE `$table` AUTO_INCREMENT = 1");
      }
      // Copy rows.
      foreach ($rows as $row) {
        // Modify row data if required.
        // CASE: some columns were removed in new scheme.
        if (
          !empty($exists_only_in_old_db)
          && empty($exists_only_in_new_db)
        ) {
          foreach ($exists_only_in_old_db as $column) {
            unset($row[$column]);
          }
        }
        // CASE: langcode column added.
        elseif (
          empty($exists_only_in_old_db)
          && count($exists_only_in_new_db) == 1
          && reset($exists_only_in_new_db) == 'langcode'
        ) {
          $row['langcode'] = 'en';
        }
        // CASE: link field scheme is changed.
        elseif (preg_match('/^.*__field_link$/', $table)) {
          unset($row['field_link_route_name']);
          unset($row['field_link_route_parameters']);
          $row['field_link_uri'] = 'internal:/' . $row['field_link_url'];
          unset($row['field_link_url']);
        }
        // CASE: status column added to taxonomy_index table.
        elseif ($table == 'taxonomy_index') {
          $langcode = db_select('node', 'n')
            ->fields('n', ['langcode'])
            ->condition('n.nid', $row['nid'])
            ->execute()
            ->fetchField();
          $row['status'] = db_select('node_field_data', 'nfd')
            ->fields('nfd', ['status'])
            ->condition('nfd.nid', $row['nid'])
            ->condition('nfd.langcode', $langcode)
            ->execute()
            ->fetchField();
        }
        // Insert.
        db_insert($table)
          ->fields($row)
          ->execute();
      }
      echo "Migrated $table table.\n";
    }
    // Migrate user roles.
    db_set_active('old');
    $rows = db_select('users_roles', 'ur')
      ->fields('ur')
      ->execute()
      ->fetchAll(PDO::FETCH_ASSOC);
    db_set_active();
    db_truncate('user__roles')->execute();
    foreach ($rows as $row) {
      $row = [
        'bundle' => 'user',
        'deleted' => 0,
        'entity_id' => $row['uid'],
        'revision_id' => $row['uid'],
        'langcode' => 'en',
        'delta' => 0,
        'roles_target_id' => $row['rid'],
      ];
      db_insert('user__roles')
        ->fields($row)
        ->execute();
    }
    echo "Migrated user roles.\n";
    echo "Rebuilding caches...\n";
    drupal_flush_all_caches();
    echo "DONE!\n";
  }
  public static function debug($variable, $name = NULL) {
    print "\n" . ($name ? $name . ' >> ' : '') . \Drupal\Component\Utility\Variable::export($variable) . "\n";
  }
}