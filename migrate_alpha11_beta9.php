<?php
/**
 * @file
 *
 * Migrates content from amazeelabs.com from alpha11 to beta9.
 *
 * Alpha9 was released April 2014, beta9 was released March 2015.
 *
 * Instead of migrating configuration, the site was rebuilt manually
 * and content was migrated using this script.
 *
 * The data is taken from "old" database and copied to "default" database.
 * Before run, ensure that $databases['old']['default'] is set in the
 * settings.php.
 *
 * The migration uses "merge" strategy. It means that it could be run several
 * times. Already migrated data will be updated, new data will be added. Deleted
 * data will be partially deleted.
 *
 * To run migration:
drush8 scr migrate_alpha11_beta9.php
 *
 * Example on how to update files (run from Drupal root):
rsync -e ssh  -akzv --exclude=/files --exclude=/css --exclude=/js --exclude=/php --exclude=/styles --exclude=/config_* --exclude=/file --stats --progress -d -v user@server:/drupalpath/sites/default/files/ sites/default/files/
 *
 * It's recommended to remove all the directories specified in the --exclude
 * parameters (no matter before or after rsync).
 */
use Drupal\Core\Language\LanguageInterface;
AlD8MigrateBeta9::run();
return;

class AlD8MigrateBeta9 {
  public static function run() {
    ini_set('max_execution_time', 60*60);
    ini_set('memory_limit', '2G');
    self::migrateTaxonomy();
    self::migrateNode();
    self::migrateBlockContent();
    self::migrateUsers();
    self::migrateComments();
    self::migrateFields();
    self::migrateFiles();
    self::migratePathAliases();
    self::migrateLocaleStrings();
    drupal_flush_all_caches();
  }
  public static function migrateTaxonomy() {
    echo "Migrating taxonomy data...\n";
    // taxonomy_index => taxonomy_index
    db_set_active('old');
    $query = db_select('taxonomy_index', 'ti')
      ->fields('ti');
    $query->leftJoin('node_field_data', 'nfd', 'nfd.nid = ti.nid');
    $query->condition('nfd.default_langcode', 1); // only 1 row will be joined
    $query->addField('nfd', 'status');
    $rows = $query
      ->execute()
      ->fetchAll();
    db_set_active();
    foreach ($rows as $row) {
      $keys = [
        'nid' => $row->nid,
        'tid' => $row->tid,
      ];
      $fields = $keys + [
          'status' => $row->status,
          'sticky' => $row->sticky,
          'created' => $row->created,
        ];
      db_merge('taxonomy_index')
        ->keys($keys)
        ->fields($fields)
        ->execute();
    }
    self::cleanupTable('taxonomy_term_data', 'tid', 'taxonomy_index', 'tid');
    self::cleanupTable('node', 'nid', 'taxonomy_index', 'nid');
    // taxonomy_term_data => taxonomy_term_data, taxonomy_term_field_data
    db_set_active('old');
    $rows = db_select('taxonomy_term_data', 'ttd')
      ->fields('ttd')
      ->execute()
      ->fetchAll();
    db_set_active();
    foreach ($rows as $row) {
      $keys = [
        'tid' => $row->tid,
      ];
      $fields = $keys + [
          'vid' => $row->vid,
          'uuid' => $row->uuid,
          'langcode' => $row->langcode,
        ];
      db_merge('taxonomy_term_data')
        ->keys($keys)
        ->fields($fields)
        ->execute();
      $keys = [
        'tid' => $row->tid,
        'langcode' => $row->langcode,
      ];
      $fields = $keys + [
          'vid' => $row->vid,
          'name' => $row->name,
          'description__value' => $row->description__value,
          'description__format' => $row->description__format,
          'weight' => $row->weight,
          'changed' => $row->changed,
          'default_langcode' => 1,
        ];
      db_merge('taxonomy_term_field_data')
        ->keys($keys)
        ->fields($fields)
        ->execute();
    }
    self::cleanupTable('taxonomy_term_data', 'tid', 'taxonomy_term_data', 'tid');
    self::cleanupTable('taxonomy_term_data', 'tid', 'taxonomy_term_field_data', 'tid');
    // taxonomy_term_hierarchy => taxonomy_term_hierarchy
    db_set_active('old');
    $rows = db_select('taxonomy_term_hierarchy', 'tth')
      ->fields('tth')
      ->execute()
      ->fetchAll();
    db_set_active();
    foreach ($rows as $row) {
      $keys = [
        'tid' => $row->tid,
        'parent' => $row->parent,
      ];
      db_merge('taxonomy_term_hierarchy')
        ->keys($keys)
        ->fields($keys)
        ->execute();
    }
    self::cleanupTable('taxonomy_term_data', 'tid', 'taxonomy_term_hierarchy', 'tid');
    echo "Migrated taxonomy data.\n";
  }
  public static function migrateNode() {
    echo "Migrating node data...\n";
    // node => node
    db_set_active('old');
    $query = db_select('node', 'n')
      ->fields('n');
    $query->leftJoin('node_field_data', 'nfd', 'nfd.nid = n.nid');
    $query->condition('nfd.default_langcode', 1); // join one row
    $query->addField('nfd', 'langcode');
    $rows = $query->execute()->fetchAll();
    db_set_active();
    foreach ($rows as $row) {
      $keys = [
        'nid' => $row->nid,
      ];
      $fields = $keys + [
          'uuid' => $row->uuid,
          'vid' => $row->vid,
          'type' => $row->type,
          'langcode' => $row->langcode,
        ];
      db_merge('node')
        ->keys($keys)
        ->fields($fields)
        ->execute();
    }
    self::cleanupTable('node', 'nid', 'node', 'nid');
    // node_revision => node_revision
    db_set_active('old');
    $rows = db_select('node_revision', 'nr')
      ->fields('nr')
      ->execute()
      ->fetchAll();
    db_set_active();
    foreach ($rows as $row) {
      $keys = [
        'vid' => $row->vid,
      ];
      $fields = $keys + [
          'nid' => $row->nid,
          'revision_uid' => $row->revision_uid,
          'revision_log' => $row->log,
          'revision_timestamp' => $row->revision_timestamp,
          'langcode' => $row->langcode,
        ];
      db_merge('node_revision')
        ->keys($keys)
        ->fields($fields)
        ->execute();
    }
    self::cleanupTable('node', 'nid', 'node_revision', 'nid');
    // Ignore node_access table.
    // node_field_data => node_field_data
    db_set_active('old');
    $rows = db_select('node_field_data', 'nfd')
      ->fields('nfd')
      ->execute()
      ->fetchAll();
    db_set_active();
    foreach ($rows as $row) {
      $keys = [
        'nid' => $row->nid,
        'langcode' => $row->langcode,
      ];
      $fields = $keys + [
          'vid' => $row->vid,
          'type' => $row->type,
          'default_langcode' => $row->default_langcode,
          'title' => $row->title,
          'uid' => $row->uid,
          'status' => $row->status,
          'created' => $row->created,
          'changed' => $row->changed,
          'promote' => $row->promote,
          'sticky' => $row->sticky,
          // These two were not exist. No data available for them.
          'content_translation_source' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
          'content_translation_outdated' => 0,
        ];
      db_merge('node_field_data')
        ->keys($keys)
        ->fields($fields)
        ->execute();
    }
    self::cleanupTable('node', 'nid', 'node_field_data', 'nid');
    // node_field_revision => node_field_revision
    db_set_active('old');
    $rows = db_select('node_field_revision', 'nfr')
      ->fields('nfr')
      ->execute()
      ->fetchAll();
    db_set_active();
    foreach ($rows as $row) {
      $keys = [
        'vid' => $row->vid,
        'langcode' => $row->langcode,
      ];
      $fields = $keys + [
          'nid' => $row->nid,
          'default_langcode' => $row->default_langcode,
          'title' => $row->title,
          'uid' => $row->uid,
          'status' => $row->status,
          'created' => $row->created,
          'changed' => $row->changed,
          'promote' => $row->promote,
          'sticky' => $row->sticky,
          // These two were not exist. No data available for them.
          'content_translation_source' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
          'content_translation_outdated' => 0,
        ];
      db_merge('node_field_revision')
        ->keys($keys)
        ->fields($fields)
        ->execute();
    }
    self::cleanupTable('node', 'nid', 'node_field_revision', 'nid');
    echo "Migrated node data.\n";
  }
  public static function migrateFields() {
    echo "Migrating fields...\n";
    // Prepare mapping.
    $base = [
      'comment' => 'comment',
      'custom_block' => 'block_content',
      'custom_block_revision' => 'block_content_revision',
      'node' => 'node',
      'node_revision' => 'node_revision',
      'user' => 'user',
    ];
    db_set_active('old');
    $tables = array_flip(db_query('show tables')->fetchCol());
    foreach ($tables as $table_name => &$data) {
      list($base_name, $field_name) = explode('__', $table_name);
      $data = FALSE;
      foreach ($base as $base_old => $base_new) {
        if (strpos($table_name, $base_old . '__') === 0) {
          $data = [
            'from' => $table_name,
            'to' => $base[$base_name] . '__' . $field_name,
            'mapping' => db_query("SHOW COLUMNS FROM $table_name")->fetchCol()
          ];
          // Special handling for some fields.
          /* @see Drupal\Core\Entity\Sql\DefaultTableMapping::generateFieldTableName() */
          if ($data['to'] == 'block_content_revision__field_bullet_points_title') {
            $data['to'] = 'block_content_r__803e0697d7';
          }
          elseif ($data['to'] == 'block_content_revision__field_call_to_action_link') {
            $data['to'] = 'block_content_r__bfd235434a';
          }
          break;
        }
      }
    }
    unset($columns);
    $mapping = array_values(array_filter($tables));
    db_set_active();
    foreach ($mapping as &$data) {
      list(, $field_name) = explode('__', $data['from']);
      $table_name = $data['to'];
      $new_columns = db_query("SHOW COLUMNS FROM $table_name")->fetchCol();
      if ($data['mapping'] == $new_columns) {
        $data['mapping'] = array_combine($data['mapping'], $data['mapping']);
      }
      else {
        $removed = array_diff($data['mapping'], $new_columns);
        $added = array_diff($new_columns, $data['mapping']);
        if (isset($removed[7]) && $removed[7] == $field_name . '_format') {
          // Text format is removed for some fields.
          unset($data['mapping'][7]);
          $data['mapping'] = array_combine($data['mapping'], $data['mapping']);
        }
        elseif (isset($removed[7]) && $removed[7] == $field_name . '_revision_id') {
          // Entity reference does not need revision now.
          unset($data['mapping'][7]);
          $data['mapping'] = array_combine($data['mapping'], $data['mapping']);
        }
        elseif (isset($added[6]) && $added[6] == $field_name . '_uri') {
          // Link _url was renamed to _uri, also _route_name and
          // _route_parameters were removed.
          unset($data['mapping'][8], $data['mapping'][9]);
          $data['mapping'] = array_combine($data['mapping'], $data['mapping']);
          $data['mapping'][$field_name . '_url'] = $field_name . '_uri';
        }
        else {
          echo "ERROR: Unknown changes in {$data['from']} table. Data has NOT been migrated.";
        }
      }
    }
    unset($data);
    $mapping = array_filter($mapping);
    // Migrate data.
    foreach ($mapping as $data) {
      list(, $field_name) = explode('__', $data['from']);
      $url_field = $field_name . '_url';
      db_set_active('old');
      $rows = db_select($data['from'], 't')
        ->fields('t')
        ->execute()
        ->fetchAll();
      db_set_active();
      foreach ($rows as $row) {
        $keys = [
          'entity_id' => $row->entity_id,
          'deleted' => $row->deleted,
          'delta' => $row->delta,
          'langcode' => $row->langcode,
        ];
        if (strpos($data['to'], '_revision__') !== FALSE || strpos($data['to'], '_r__') !== FALSE) {
          $keys['revision_id'] = $row->revision_id;
        }
        $fields = [];
        foreach ($data['mapping'] as $from => $to) {
          if ($from == $url_field) {
            // URLs now should have "internal:" prefix.
            $row->{$from} = 'internal:/' . $row->{$from};
          }
          $fields[$to] = $row->{$from};
        }
        db_merge($data['to'])
          ->keys($keys)
          ->fields($fields)
          ->execute();
      }
      self::cleanupTable($data['from'], 'entity_id', $data['to'], 'entity_id');
    }
    echo "Migrated fields.\n";
  }
  public static function migrateFiles() {
    echo "Migrating files data...\n";
    // file_managed
    db_set_active('old');
    $rows = db_select('file_managed', 'fm')
      ->fields('fm')
      ->execute()
      ->fetchAll();
    db_set_active();
    foreach ($rows as $row) {
      $keys = [
        'fid' => $row->fid,
      ];
      $fields = $keys + (array) $row;
      db_merge('file_managed')
        ->keys($keys)
        ->fields($fields)
        ->execute();
    }
    self::cleanupTable('file_managed', 'fid', 'file_managed', 'fid');
    // file_usage
    db_set_active('old');
    $rows = db_select('file_usage', 'fu')
      ->fields('fu')
      ->execute()
      ->fetchAll();
    db_set_active();
    foreach ($rows as $row) {
      $keys = [
        'fid' => $row->fid,
        'type' => $row->type,
        'id' => $row->id,
        'module' => $row->module,
      ];
      $fields = $keys + (array) $row;
      db_merge('file_usage')
        ->keys($keys)
        ->fields($fields)
        ->execute();
    }
    self::cleanupTable('file_managed', 'fid', 'file_usage', 'fid');
    echo "Migrated files.\n";
  }
  /**
   * WARNING: block translations are not properly handled because we don't have
   * them on AL website.
   */
  public static function migrateBlockContent() {
    echo "Migrating block content...\n";
    // custom_block => block_content, block_content_field_data
    db_set_active('old');
    $rows = db_select('custom_block', 'cb')
      ->fields('cb')
      ->execute()
      ->fetchAll();
    db_set_active();
    foreach ($rows as $row) {
      $keys = [
        'id' => $row->id,
      ];
      $fields = $keys + [
          'revision_id' => $row->revision_id,
          'type' => $row->type,
          'uuid' => $row->uuid,
          'langcode' => $row->langcode,
        ];
      db_merge('block_content')
        ->keys($keys)
        ->fields($fields)
        ->execute();
      $keys = [
        'id' => $row->id,
        'langcode' => $row->langcode,
      ];
      $fields = $keys + [
          'revision_id' => $row->revision_id,
          'type' => $row->type,
          'info' => $row->info,
          'changed' => $row->changed,
          'default_langcode' => 1,
          'content_translation_source' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
          'content_translation_outdated' => 0,
          'content_translation_uid' => NULL,
          'content_translation_status' => 1,
          'content_translation_created' => $row->changed,
        ];
      db_merge('block_content_field_data')
        ->keys($keys)
        ->fields($fields)
        ->execute();
    }
    self::cleanupTable('custom_block', 'id', 'block_content', 'id');
    self::cleanupTable('custom_block', 'id', 'block_content_field_data', 'id');
    // custom_block_revision => block_content_revision, block_content_field_revision
    db_set_active('old');
    $query = db_select('custom_block_revision', 'cbr')
      ->fields('cbr');
    $query->innerJoin('custom_block', 'cb', 'cb.revision_id = cbr.revision_id');
    $query->addField('cb', 'langcode');
    $rows = $query->execute()->fetchAll();
    db_set_active();
    foreach ($rows as $row) {
      $keys = [
        'revision_id' => $row->revision_id,
      ];
      $fields = $keys + [
          'id' => $row->id,
          'langcode' => $row->langcode,
          'revision_log' => $row->log,
        ];
      db_merge('block_content_revision')
        ->keys($keys)
        ->fields($fields)
        ->execute();
      $keys = [
        'id' => $row->id,
        'langcode' => $row->langcode,
      ];
      $fields = $keys + [
          'revision_id' => $row->revision_id,
          'info' => $row->info,
          'changed' => $row->changed,
          'default_langcode' => 1,
          'content_translation_source' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
          'content_translation_outdated' => 0,
          'content_translation_uid' => NULL,
          'content_translation_status' => 1,
          'content_translation_created' => $row->changed,
        ];
      db_merge('block_content_field_revision')
        ->keys($keys)
        ->fields($fields)
        ->execute();
    }
    self::cleanupTable('custom_block', 'id', 'block_content_revision', 'id');
    self::cleanupTable('custom_block', 'id', 'block_content_field_revision', 'id');
    echo "Migrated block content.\n";
  }
  public static function migrateUsers() {
    echo "Migrating users...\n";
    // users => users, users_field_data
    db_set_active('old');
    $rows = db_select('users', 'u')
      ->condition('u.uid', [0, 1], 'NOT IN')
      ->fields('u')
      ->execute()
      ->fetchAll();
    db_set_active();
    foreach ($rows as $row) {
      $keys = [
        'uid' => $row->uid,
      ];
      $fields = $keys + [
          'uuid' => $row->uuid,
          'langcode' => $row->langcode,
        ];
      db_merge('users')
        ->keys($keys)
        ->fields($fields)
        ->execute();
      $keys = [
        'uid' => $row->uid,
        'langcode' => $row->langcode,
      ];
      $fields = $keys + [
          'preferred_langcode' => $row->preferred_langcode,
          'preferred_admin_langcode' => $row->preferred_admin_langcode,
          'name' => $row->name,
          'pass' => $row->pass,
          'mail' => $row->mail,
          'timezone' => $row->timezone,
          'status' => $row->status,
          'created' => $row->created,
          'changed' => $row->created,
          'access' => $row->access,
          'login' => $row->login,
          'init' => $row->init,
          'default_langcode' => 1,
          'content_translation_source' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
          'content_translation_outdated' => 0,
          'content_translation_uid' => NULL,
          'content_translation_status' => 1,
          'content_translation_created' => $row->created,
        ];
      db_merge('users_field_data')
        ->keys($keys)
        ->fields($fields)
        ->execute();
    }
    self::cleanupTable('users', 'uid', 'users', 'uid');
    self::cleanupTable('users', 'uid', 'users_field_data', 'uid');
    // users_data => users_data
    db_set_active('old');
    $rows = db_select('users_data', 'ud')
      ->fields('ud')
      ->execute()
      ->fetchAll();
    db_set_active();
    foreach ($rows as $row) {
      $keys = [
        'uid' => $row->uid,
        'module' => $row->module,
        'name' => $row->name,
      ];
      $fields = $keys + (array) $row;
      db_merge('users_data')
        ->keys($keys)
        ->fields($fields)
        ->execute();
    }
    self::cleanupTable('users', 'uid', 'users_data', 'uid');
    // users_roles => user__roles
    db_set_active('old');
    $query = db_select('users_roles', 'ur')
      ->fields('ur');
    $query->innerJoin('users', 'u', 'u.uid = ur.uid');
    $query->addField('u', 'langcode');
    $rows = $query->execute()->fetchAll();
    db_set_active();
    foreach ($rows as $row) {
      $keys = [
        'entity_id' => $row->uid,
        'delta' => 0,
        'deleted' => 0,
        'langcode' => $row->langcode,
      ];
      $fields = $keys + [
          'bundle' => 'user',
          'revision_id' => $row->uid,
          'roles_target_id' => $row->rid,
        ];
      db_merge('user__roles')
        ->keys($keys)
        ->fields($fields)
        ->execute();
    }
    self::cleanupTable('users', 'uid', 'user__roles', 'entity_id');
    echo "Migrated users.\n";
  }
  public static function cleanupTable($source_table, $source_column, $target_table, $target_column) {
    db_set_active('old');
    $ids = db_select($source_table, 't')
      ->fields('t', [$source_column])
      ->execute()
      ->fetchCol();
    db_set_active();
    if (!empty($ids)) {
      db_delete($target_table)
        ->condition($target_column, $ids, 'NOT IN')
        ->execute();
    }
  }
  public static function migrateComments() {
    echo "Migrating comments...\n";
    // comment => comment, comment_field_data
    db_set_active('old');
    $rows = db_select('comment', 'c')
      ->fields('c')
      ->execute()
      ->fetchAll();
    db_set_active();
    foreach ($rows as $row) {
      $keys = [
        'cid' => $row->cid,
      ];
      $fields = $keys + [
          'comment_type' => 'comment',
          'uuid' => $row->uuid,
          'langcode' => $row->langcode,
        ];
      db_merge('comment')
        ->keys($keys)
        ->fields($fields)
        ->execute();
      $keys = [
        'cid' => $row->cid,
        'langcode' => $row->langcode,
      ];
      $fields = $keys + [
          'comment_type' => 'comment',
          'pid' => $row->pid,
          'entity_id' => $row->entity_id,
          'subject' => $row->subject,
          'uid' => $row->uid,
          'name' => $row->name,
          'mail' => $row->mail,
          'homepage' => $row->homepage,
          'hostname' => $row->hostname,
          'created' => $row->created,
          'changed' => $row->changed,
          'status' => $row->status,
          'thread' => $row->thread,
          'entity_type' => $row->entity_type,
          'field_name' => 'comment',
          'default_langcode' => 1,
        ];
      db_merge('comment_field_data')
        ->keys($keys)
        ->fields($fields)
        ->execute();
    }
    self::cleanupTable('comment', 'cid', 'comment', 'cid');
    self::cleanupTable('comment', 'cid', 'comment_field_data', 'cid');
    // comment_entity_statistics => comment_entity_statistics
    db_set_active('old');
    $rows = db_select('comment_entity_statistics', 'ces')
      ->fields('ces')
      ->execute()
      ->fetchAll();
    db_set_active();
    foreach ($rows as $row) {
      $keys = [
        'entity_id' => $row->entity_id,
        'entity_type' => $row->entity_type,
        'field_name' => 'comment',
      ];
      $fields = $keys + [
          'cid' => $row->cid,
          'last_comment_timestamp' => $row->last_comment_timestamp,
          'last_comment_name' => $row->last_comment_name,
          'last_comment_uid' => $row->last_comment_uid,
          'comment_count' => $row->comment_count,
        ];
      db_merge('comment_entity_statistics')
        ->keys($keys)
        ->fields($fields)
        ->execute();
    }
    self::cleanupTable('comment_entity_statistics', 'entity_id', 'comment_entity_statistics', 'entity_id');
    echo "Migrated comments.\n";
  }
  public static function migratePathAliases() {
    echo "Migrating path aliases...\n";
    db_set_active('old');
    $rows = db_select('url_alias', 'ua')
      ->fields('ua')
      ->execute()
      ->fetchAll();
    db_set_active();
    foreach ($rows as $row) {
      $keys = [
        'pid' => $row->pid,
      ];
      $fields = $keys + [
          'source' => $row->source,
          'alias' => $row->alias,
          'langcode' => $row->langcode,
        ];
      db_merge('url_alias')
        ->keys($keys)
        ->fields($fields)
        ->execute();
    }
    self::cleanupTable('url_alias', 'pid', 'url_alias', 'pid');
    echo "Migrated path aliases.\n";
  }
  public static function migrateLocaleStrings() {
    echo "Migrating locale strings...\n";
    // locales_source => locales_source
    db_set_active('old');
    $rows = db_select('locales_source', 'ls')
      ->fields('ls')
      ->execute()
      ->fetchAll();
    db_set_active();
    db_truncate('locales_source')->execute();
    db_query('ALTER TABLE locales_source AUTO_INCREMENT=1');
    $query = db_insert('locales_source')
      ->fields(['lid', 'source', 'context', 'version']);
    foreach ($rows as $row) {
      $query->values([$row->lid, $row->source, $row->context, '8.0.0-dev']);
    }
    $query->execute();
    // locales_source => locales_source
    db_set_active('old');
    $rows = db_select('locales_target', 'lt')
      ->fields('lt')
      ->execute()
      ->fetchAll();
    db_set_active();
    db_truncate('locales_target')->execute();
    $query = db_insert('locales_target')
      ->fields(['lid', 'translation', 'language', 'customized']);
    foreach ($rows as $row) {
      $query->values([$row->lid, $row->translation, $row->language, $row->customized]);
    }
    $query->execute();
    echo "Migrated locale strings.\n";
  }
}