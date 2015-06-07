<?php

/**
 * @file
 *
 * Updates the Drupal.com site from Alpha13 to Beta1.
 */

use Drupal\Core\DrupalKernel;
use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\Request;

$autoloader = require_once __DIR__ . '/core/vendor/autoload.php';

try {
  $request = Request::createFromGlobals();
  $kernel = DrupalKernel::createFromRequest($request, $autoloader, 'prod');
  $kernel->boot();

  // Blocks.
  update_blocks();

  // Batch table: no need to migrate.

  // Cache tags, a special table.
  update_cache_tags_table();

  // Comments: no need to migrate for us.

  // Files.
  update_files();

  // Menus will be manually recreated.

  // Node tables.
  update_node_table();

  // Node fields content.
  update_node_fields();

  update_sequences_table();

  // Shortcuts.
  update_shortcuts();

  // Taxonomy terms.
  update_taxonomy_tables();

  // Url aliases.
  update_url_aliases();

  // Users.
  update_users();

  // We do not migrate the watchdog at the moment.

  // Config
  update_config();

}
catch (Exception $e) {
  $message = 'If you have just changed code (for example deployed a new module or moved an existing one) read <a href="http://drupal.org/documentation/rebuild">http://drupal.org/documentation/rebuild</a>';
  if (Settings::get('rebuild_access', FALSE)) {
    $rebuild_path = $GLOBALS['base_url'] . '/rebuild.php';
    $message .= " or run the <a href=\"$rebuild_path\">rebuild script</a>";
  }

  // Set the response code manually. Otherwise, this response will default to a
  // 200.
  http_response_code(500);
  print $message;
  throw $e;
}

function update_blocks() {
  update_block_content_table();
  update_block_content__body_table();
  update_block_content_field_data_table();
  update_block_content_field_revision_table();
  update_block_content_revision_table();
  update_block_content_revision__body_table();
}

function update_block_content_table() {
  db_set_active('alpha');
  $custom_block = db_select('custom_block', 'cb')->fields('cb', array('id', 'revision_id', 'type', 'uuid'))->execute()->fetchAll();

  db_set_active();
  $query = db_insert('block_content')->fields(array('id', 'revision_id', 'type', 'uuid'));
  foreach ($custom_block as $entry) {
    $query->values(array($entry->id, $entry->revision_id, $entry->type, $entry->uuid));
  }
  $query->execute();
  echo "block_content table updated.\n";
}

function update_block_content__body_table() {
  db_set_active('alpha');
  $custom_block_body = db_select('custom_block__body', 'cbb')->fields('cbb')->execute()->fetchAll();

  db_set_active();
  $query = db_insert('block_content__body')->fields(array('bundle', 'deleted', 'entity_id', 'revision_id', 'langcode', 'delta', 'body_value', 'body_summary', 'body_format'));
  foreach ($custom_block_body as $entry) {
    $query->values(array($entry->bundle, $entry->deleted, $entry->entity_id, $entry->revision_id, $entry->langcode, $entry->delta, $entry->body_value, $entry->body_summary, $entry->body_format));
  }
  $query->execute();
  echo "block_content__body table updated.\n";
}

function update_block_content_field_data_table() {
  db_set_active('alpha');
  $custom_block = db_select('custom_block', 'cb')->fields('cb')->execute()->fetchAll();

  db_set_active();
  $query = db_insert('block_content_field_data')->fields(array('id', 'revision_id', 'type', 'langcode', 'info', 'changed', 'default_langcode'));
  foreach ($custom_block as $entry) {
    $query->values(array($entry->id, $entry->revision_id, $entry->type, $entry->langcode, $entry->info, $entry->changed, 1));
  }
  $query->execute();
  echo "block_content_field_data table updated.\n";
}


function update_block_content_field_revision_table() {
  db_set_active('alpha');
  $query = db_select('custom_block_revision', 'cbr')->fields('cbr', array('id', 'revision_id', 'info', 'changed'));
  $query->innerJoin('custom_block', 'cb', 'cb.id = cbr.id');
  $query->fields('cb', array('langcode'));
  $data = $query->execute()->fetchAll();

  db_set_active();
  $query = db_insert('block_content_field_revision')->fields(array('id', 'revision_id', 'langcode', 'info', 'changed', 'default_langcode'));
  foreach ($data as $entry) {
    $query->values(array($entry->id, $entry->revision_id, $entry->langcode, $entry->info, $entry->changed, 1));
  }
  $query->execute();

  echo "block_content_field_revision table updated.\n";
}

function update_block_content_revision_table() {
  db_set_active('alpha');
  $query = db_select('custom_block_revision', 'cbr')->fields('cbr', array('id', 'revision_id', 'log'));
  $query->innerJoin('custom_block', 'cb', 'cb.id = cbr.id');
  $query->fields('cb', array('langcode'));
  $data = $query->execute()->fetchAll();

  db_set_active();
  $query = db_insert('block_content_revision')->fields(array('id', 'revision_id', 'langcode', 'revision_log'));
  foreach ($data as $entry) {
    $query->values(array($entry->id, $entry->revision_id, $entry->langcode, $entry->log));
  }
  $query->execute();
  echo "block_content_revision table updated.\n";
}

function update_block_content_revision__body_table() {
  db_set_active('alpha');
  $data = db_select('custom_block_revision__body', 'cbrb')->fields('cbrb')->execute()->fetchAll();

  db_set_active();
  $query = db_insert('block_content_revision__body')->fields(array('bundle', 'deleted', 'entity_id', 'revision_id', 'langcode', 'delta', 'body_value', 'body_summary', 'body_format'));
  foreach ($data as $entry) {
    $query->values(array($entry->bundle, $entry->deleted, $entry->entity_id, $entry->revision_id, $entry->langcode, $entry->delta, $entry->body_value, $entry->body_summary, $entry->body_format));
  }
  $query->execute();
  echo "block_content_revision__body table updated.\n";
}

function update_cache_tags_table() {
  db_set_active('alpha');
  $data = db_select('cache_tags', 'cbrb')->fields('cbrb')->execute()->fetchAll();

  db_set_active();
  $query = db_insert('cachetags')->fields(array('tag', 'invalidations', 'deletions'));
  foreach ($data as $entry) {
    $query->values(array($entry->tag, $entry->invalidations, $entry->deletions));
  }
  $query->execute();
  echo "The cache_tags table has been updated\n";
}

function update_files() {
  db_set_active('alpha');
  $file_managed = db_select('file_managed', 'f')->fields('f')->execute()->fetchAll();
  $file_usage = db_select('file_usage', 'f')->fields('f')->execute()->fetchAll();

  db_set_active();
  $query = db_insert('file_managed')->fields(array('fid', 'uuid', 'langcode', 'uid', 'filename', 'uri', 'filemime', 'filesize', 'status', 'created', 'changed'));
  foreach ($file_managed as $file) {
    $query->values(array($file->fid, $file->uuid, $file->langcode, $file->uid, $file->filename, $file->uri, $file->filemime, $file->filesize, $file->status, $file->created, $file->changed));
  }
  $query->execute();

  $query = db_insert('file_usage')->fields(array('fid', 'module', 'type', 'id', 'count'));
  foreach ($file_usage as $file) {
    if ($file->type == 'custom_block') {
      $file->type = 'block_content';
    }
    if ($file->module == 'custom_block') {
      $file->module = 'block_content';
    }

    // This should actually never be the case, but we put this condition here
    // anyways.
    if ($file->type == 'menu_link') {
      $file->type = 'menu_link_content';
    }
    if ($file->module == 'menu_link') {
      $file->module = 'menu_link_content';
    }
    $query->values(array($file->fid, $file->module, $file->type, $file->id, $file->count));
  }
  $query->execute();
  echo "The file tables have been updated.\n";
}

function update_node_table() {
  db_set_active('alpha');
  $data = db_select('node', 'n')->fields('n')->execute()->fetchAll();
  $revision = db_select('node_revision', 'nr')->fields('nr')->execute()->fetchAll();
  // We do not need to migrate the node access records in our case.
  $node_field_data = db_select('node_field_data', 'nfd')->fields('nfd')->execute()->fetchAll();
  $node_field_revision = db_select('node_field_revision', 'nfr')->fields('nfr')->execute()->fetchAll();

  db_set_active();
  $query = db_insert('node')->fields(array('nid', 'vid', 'type', 'uuid'));
  foreach ($data as $entry) {
    $query->values(array($entry->nid, $entry->vid, $entry->type, $entry->uuid));
  }
  $query->execute();

  $query = db_insert('node_revision')->fields(array('nid', 'vid', 'langcode', 'revision_timestamp', 'revision_uid', 'revision_log'));
  foreach ($revision as $entry) {
    $query->values(array($entry->nid, $entry->vid, $entry->langcode, $entry->revision_timestamp, $entry->revision_uid, $entry->log));
  }
  $query->execute();

  $query = db_insert('node_field_data')->fields(array('nid', 'vid', 'type', 'langcode', 'title', 'uid', 'status', 'created', 'changed', 'promote', 'sticky', 'default_langcode'));
  foreach ($node_field_data as $entry) {
    $query->values(array($entry->nid, $entry->vid, $entry->type, $entry->langcode, $entry->title, $entry->uid, $entry->status, $entry->created, $entry->changed, $entry->promote, $entry->sticky, $entry->default_langcode));
  }
  $query->execute();

  $query = db_insert('node_field_revision')->fields(array('nid', 'vid', 'langcode', 'title', 'uid', 'status', 'created', 'changed', 'promote', 'sticky', 'default_langcode'));
  foreach ($node_field_revision as $entry) {
    $query->values(array($entry->nid, $entry->vid, $entry->langcode, $entry->title, $entry->uid, $entry->status, $entry->created, $entry->changed, $entry->promote, $entry->sticky, $entry->default_langcode));
  }
  $query->execute();
  echo "The node tables have been updated.\n";
}

function update_node_fields() {
  db_set_active('alpha');
  $result = db_query('SHOW TABLES')->fetchAllKeyed(0, 0);
  db_set_active();
  foreach ($result as $key => $table_name) {
    $table_type = 'field';
    if (strpos($table_name, 'node__') === 0) {
      $field_name = substr($table_name, 6);
      $table_comment = 'Data storage for node field ' . $field_name . '.';
    }
    elseif (strpos($table_name, 'node_revision__') === 0) {
      $field_name = substr($table_name, 15);
      $table_comment = 'Revision archive storage for node field ' . $field_name . '.';
      $table_type = 'revision';
    }
    else {
      continue;
    }
    // If the table does not exist, we create it first.
    if (!db_table_exists($table_name)) {
      db_set_active('alpha');
      $create_table = db_query('SHOW CREATE TABLE ' . $table_name)->fetchAllKeyed(0, 1);
      db_set_active();

      if ($table_type == 'field') {
        $create_table[$table_name] = str_replace('"revision_id" int(10) unsigned DEFAULT NULL', '"revision_id" int(10) unsigned NOT NULL', $create_table[$table_name]);
      }
      $create_table[$table_name] .= " ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='" . $table_comment . "';";
      db_query($create_table[$table_name]);
    }
    db_set_active('alpha');
    $data = db_select($table_name, 'table_data')->fields('table_data')->execute()->fetchAll();
    db_set_active();
    foreach ($data as $row) {
      $row = (array) $row;
      db_insert($table_name)->fields($row)->execute();
    }
  }
  echo "The node fields table have been updated.\n";
}

function update_sequences_table() {
  db_set_active('alpha');
  $result = db_select('sequences', 'sq')->fields('sq')->execute()->fetch();

  db_set_active();
  db_update('sequences')->fields(array('value' => $result->value));
  echo "The sequences table has been updated. (Please check if the value is correct, for some reason if does not work).\n";
}

function update_shortcuts() {
  db_set_active('alpha');
  $shortcut = db_select('shortcut', 'sh')->fields('sh')->execute()->fetchAllAssoc('id');
  $shortcut_field_data = db_select('shortcut_field_data', 'sh')->fields('sh')->execute()->fetchAllAssoc('id');
  $shortcut_set_users = db_select('shortcut_set_users', 'sh')->fields('sh')->execute()->fetchAll();

  db_set_active();
  db_truncate('shortcut')->execute();
  db_truncate('shortcut_field_data')->execute();
  db_truncate('shortcut_set_users')->execute();
  $query = db_insert('shortcut')->fields(array('id', 'shortcut_set', 'uuid', 'langcode'));
  foreach ($shortcut as $entry) {
    $query->values(array($entry->id, $entry->shortcut_set, $entry->uuid, $entry->langcode));
  }
  $query->execute();

  $query = db_insert('shortcut_field_data')->fields(array('id', 'shortcut_set', 'langcode', 'title', 'weight', 'route_name', 'route_parameters', 'default_langcode'));
  foreach ($shortcut_field_data as $entry) {
    $shortcut_entry = $shortcut[$entry->id];
    $route_name = _update_config_get_new_route_name(str_replace('custom_block.', 'block_content.', $shortcut[$entry->id]->route_name));
    $query->values(array($entry->id, $shortcut_entry->shortcut_set, $entry->langcode, $entry->title, $shortcut_entry->weight, $route_name, $shortcut_entry->route_parameters, $entry->default_langcode));
  }
  $query->execute();

  $query = db_insert('shortcut_set_users')->fields(array('uid', 'set_name',));
  foreach ($shortcut_set_users as $entry) {
    $query->values(array($entry->uid, $entry->set_name));
  }
  $query->execute();
  echo "The shortcut tables have been updated.\n";
}

function update_taxonomy_tables() {
  db_set_active('alpha');
  $taxonomy_index = db_select('taxonomy_index', 'ti')->fields('ti')->execute()->fetchAll();
  $taxonomy_term_data = db_select('taxonomy_term_data', 'ttd')->fields('ttd')->execute()->fetchAllAssoc('tid');
  $taxonomy_term_hierarcy = db_select('taxonomy_term_hierarchy', 'tth')->fields('tth')->execute()->fetchAll();

  db_set_active();
  $query = db_insert('taxonomy_index')->fields(array('nid', 'tid', 'sticky', 'created'));
  foreach ($taxonomy_index as $entry) {
    $query->values(array($entry->nid, $entry->tid, $entry->sticky, $entry->created));
  }
  $query->execute();

  // The term data is now split between term_data and term_field_data.
  $query = db_insert('taxonomy_term_data')->fields(array('tid', 'vid', 'uuid', 'langcode'));
  foreach ($taxonomy_term_data as $entry) {
    $query->values(array($entry->tid, $entry->vid, $entry->uuid, $entry->langcode));
  }
  $query->execute();

  $query = db_insert('taxonomy_term_field_data')->fields(array('tid', 'vid', 'langcode', 'name', 'description__value', 'description__format', 'weight', 'changed', 'default_langcode'));
  foreach ($taxonomy_term_data as $entry) {
    $query->values(array($entry->tid, $entry->vid, $entry->langcode, $entry->name, $entry->description__value, $entry->description__format, $entry->weight, $entry->changed, 1));
  }
  $query->execute();

  $query = db_insert('taxonomy_term_hierarchy')->fields(array('tid', 'parent'));
  foreach ($taxonomy_term_hierarcy as $entry) {
    $query->values(array($entry->tid, $entry->parent));
  }
  $query->execute();
  echo "The taxonomy tables have been updated.\n";
}

function update_url_aliases() {
  db_set_active('alpha');
  $aliases = db_select('url_alias', 'ua')->fields('ua')->execute()->fetchAll();

  db_set_active();
  $query = db_insert('url_alias')->fields(array('pid', 'source', 'alias', 'langcode'));
  foreach ($aliases as $entry) {
    $query->values(array($entry->pid, $entry->source, $entry->alias, $entry->langcode));
  }
  $query->execute();
  echo "The url aliases have been updated.\n";
}

function update_users() {
  db_set_active('alpha');
  // We do not need to import the user pictures.
  $users = db_select('users', 'u')->fields('u')->execute()->fetchAllAssoc('uid');
  $users_data = db_select('users_data', 'ud')->fields('ud')->execute()->fetchAll();
  $users_roles = db_select('users_roles', 'ur')->fields('ur')->execute()->fetchAll();

  db_set_active();
  db_truncate('users')->execute();
  db_truncate('users_data')->execute();
  db_truncate('users_field_data')->execute();
  db_truncate('users_roles')->execute();

  $query = db_insert('users')->fields(array('uid', 'uuid', 'langcode'));
  foreach ($users as $entry) {
    $query->values(array($entry->uid, $entry->uuid, $entry->langcode));
  }
  $query->execute();

  $query = db_insert('users_data')->fields(array('uid', 'module', 'name', 'value', 'serialized'));
  foreach ($users_data as $entry) {
    $query->values(array($entry->uid, $entry->module, $entry->name, $entry->value, $entry->serialized));
  }
  $query->execute();

  $query = db_insert('users_field_data')->fields(array('uid', 'langcode', 'preferred_langcode', 'preferred_admin_langcode', 'name', 'pass', 'mail', 'signature', 'signature_format', 'timezone', 'status', 'created', 'changed', 'access', 'login', 'init', 'default_langcode'));
  foreach ($users as $entry) {
    // We will use the created value for the changed, because we do not have one.
    $query->values(array($entry->uid, $entry->langcode, $entry->preferred_langcode, $entry->preferred_admin_langcode, $entry->name, $entry->pass, $entry->mail, $entry->signature, $entry->signature_format, $entry->timezone, $entry->status, $entry->created, $entry->created, $entry->access, $entry->login, $entry->init, 1));
  }
  $query->execute();

  $query = db_insert('users_roles')->fields(array('uid', 'rid'));
  foreach ($users_roles as $entry) {
    $query->values(array($entry->uid, $entry->rid));
  }
  $query->execute();

  echo "The users have been updated.\n";
}

function update_config() {
  db_set_active('alpha');
  $alpha_config = db_select('config', 'c')->fields('c')->execute()->fetchAllAssoc('name');
  $alpha_config_snapshop = db_select('config_snapshot', 'cs')->fields('cs')->execute()->fetchAllAssoc('name');
  $key_value = db_select('key_value', 'kv')->fields('kv')->execute()->fetchAll();

  db_set_active();
  foreach ($alpha_config as $key => $entry) {
    $entry = _update_config_process_entry($entry);
    $alpha_config[$key] = $entry;
    db_merge('config')->keys(array('collection' => $entry->collection, 'name' => $entry->name))
      ->fields(array('collection' => $entry->collection, 'name' => $entry->name, 'data' => $entry->data))->execute();
  }

  foreach ($alpha_config as $entry) {
    if (strpos($entry->name, 'node.type.') === 0) {
      $words = explode('.', $entry->name);
      $node_type = end($words);
      $menu_settings = $alpha_config['menu.entity.node.' . $node_type];
      if (!empty($menu_settings)) {
        $menu_settings->data = unserialize($menu_settings->data);
        if (!empty($menu_settings->data['available_menus'])) {
          $entry->data = unserialize($entry->data);
          $entry->data['third_party_settings']['menu_ui'] = $menu_settings->data;
          $entry->data['dependencies']['module'][] = 'menu_ui';
          $entry->data = serialize($entry->data);
          db_merge('config')->keys(array('collection' => $entry->collection, 'name' => $entry->name))
            ->fields(array('collection' => $entry->collection, 'name' => $entry->name, 'data' => $entry->data))->execute();
          db_delete('config')->condition('collection', $entry->collection)->condition('name', 'menu.entity.node.' . $node_type)->execute();
        }
      }
    }
  }

  foreach ($alpha_config_snapshop as $entry) {
    $entry = _update_config_process_entry($entry);
    $alpha_config_snapshop[$key] = $entry;
    db_merge('config_snapshot')->keys(array('collection' => $entry->collection, 'name' => $entry->name))
      ->fields(array('collection' => $entry->collection, 'name' => $entry->name, 'data' => $entry->data))->execute();
  }

  // This is not so nice, duplicated code, but we ca live with it.
  foreach ($alpha_config_snapshop as $entry) {
    if (strpos($entry->name, 'node.type.') === 0) {
      $words = explode('.', $entry->name);
      $node_type = end($words);
      $menu_settings = $alpha_config_snapshop['menu.entity.node.' . $node_type];
      if (!empty($menu_settings)) {
        $menu_settings->data = unserialize($menu_settings->data);
        if (!empty($menu_settings->data['available_menus'])) {
          $entry->data = unserialize($entry->data);
          $entry->data['third_party_settings']['menu_ui'] = $menu_settings->data;
          $entry->data['dependencies']['module'][] = 'menu_ui';
          $entry->data = serialize($entry->data);
          db_merge('config_snapshot')->keys(array('collection' => $entry->collection, 'name' => $entry->name))
            ->fields(array('collection' => $entry->collection, 'name' => $entry->name, 'data' => $entry->data))->execute();
          db_delete('config')->condition('collection', $entry->collection)->condition('name', 'menu.entity.node.' . $node_type)->execute();
        }
      }
    }
  }

  foreach ($key_value as $entry) {
    $entry = _update_config_process_key_value_entry($entry);
    db_merge('key_value')->keys(array('collection' => $entry->collection, 'name' => $entry->name))
      ->fields(array('collection' => $entry->collection, 'name' => $entry->name, 'value' => $entry->value))->execute();
  }
  echo "The configuration tables have been updated.";
}

function _update_config_routes_map() {
  // @todo: many other routes were changed actually... to complete the list if
  // needed.
  return array(
    'node.content_overview' => 'system.admin_content',
    'node.view' => 'entity.node.canonical',
  );
}

function _update_config_get_new_route_name($old_name) {
  $map = _update_config_routes_map();
  if (!empty($map[$old_name])) {
    return $map[$old_name];
  }
  return $old_name;
}

function _update_config_process_key_value_entry($entry) {
  $entry->value = str_replace('s:12:"custom_block"', 's:13:"block_content"', $entry->value);
  $entry->value = str_replace('s:9:"menu_link"', 's:17:"menu_link_content"', $entry->value);
  foreach (_update_config_routes_map() as $key => $value) {
    $from_string = 's:' . strlen($key) . ':"' . $key . '"';
    $to_string = 's:' . strlen($value) . ':"' . $value . '"';
    $entry->value = str_replace($from_string, $to_string, $entry->value);
  }
  $entry->value = unserialize($entry->value);
  if ($entry->name == 'custom_block') {
    $entry->name = 'block_content';
  }
  if ($entry->name == 'menu_link') {
    $entry->name = 'menu_link_content';
  }
  if ($entry->name == 'routing.non_admin_routes') {
    foreach ($entry->value as $key => $value) {
      $entry->value[$key] = str_replace('custom_block', 'block_content', $entry->value[$key]);
    }
  }
  if ($entry->name == 'system.module.files') {
    $entry->value['block_content'] = str_replace('custom_block', 'block_content', $entry->value['block_content']);
    $entry->value['menu_link_content'] = str_replace('menu_link', 'menu_link_content', $entry->value['menu_link_content']);
  }
  $entry->value = serialize($entry->value);
  return $entry;
}

function _update_config_process_entry($original_entry) {
  $original_entry = _update_config_process_entry_name($original_entry);
  $original_entry = _update_config_process_entry_data($original_entry);
  return $original_entry;
}

function _update_config_process_entry_name($original_entry) {
  // Names in beta which are not found in dcom:
  // - comment.type.comment, core.base_field_override.node.page.promote
  // - menu_link.static.overrides, system.diff

  // Names in dcom which are not found in beta:
  // - the ones that contain the 'breakpoints' in the name.
  // - system.menu.
  $map = _update_config_process_replace_map();
  foreach ($map as $key => $value) {
    if (strpos($original_entry->name, $key) === 0) {
      $original_entry->name = str_replace($key, $value, $original_entry->name);
    }
  }

  return $original_entry;
}

function _update_config_process_entry_data($original_entry) {
  $original_entry->data = str_replace('s:12:"custom_block"', 's:13:"block_content"', $original_entry->data);
  $original_entry->data = str_replace('s:19:"entity_custom_block"', 's:20:"entity_block_content"', $original_entry->data);
  $original_entry->data = unserialize($original_entry->data);

  // For blocks, the visibility is now inside the settings array, and also has
  // a bit of a different structure.
  if (strpos($original_entry->name, 'block.block.') === 0) {
    $original_entry->data['visibility']['node_type'] = array(
      'id' => 'node_type',
      'bundles' => $original_entry->data['visibility']['node_type']['types']
    );
    $original_entry->data['visibility']['request_path'] = $original_entry->data['visibility']['path'];
    $original_entry->data['visibility']['request_path']['id'] = 'request_path';
    unset($original_entry->data['visibility']['path']);

    $original_entry->data['visibility']['user_role'] = $original_entry->data['visibility']['role'];
    $original_entry->data['visibility']['user_role']['id'] = 'user_role';
    unset($original_entry->data['visibility']['role']);

    $original_entry->data['settings']['visibility'] = $original_entry->data['visibility'];
    unset($original_entry->data['visibility']);
    $original_entry->data['plugin'] = str_replace('custom_block', 'block_content', $original_entry->data['plugin']);
    $original_entry->data['settings']['id'] = str_replace('custom_block', 'block_content', $original_entry->data['settings']['id']);
  }

  // Some changes in the field settings.
  if (strpos($original_entry->name, 'core.entity_form_display.') === 0) {
    foreach ($original_entry->data['content'] as $key => $value) {
      // Only strings checked so far, we could have more.
      if (!empty($value['type']) && $value['type'] == 'string') {
        $original_entry->data['content'][$key]['type'] = 'string_textfield';
      }
      if (!isset($value['third_party_settings']) && !empty($value['type'])) {
        $original_entry->data['content'][$key]['third_party_settings'] = array();
      }
    }
    if (!isset($original_entry->data['third_party_settings'])) {
      $original_entry->data['third_party_settings'] = array();
    }
    if (!isset($original_entry->data['langcode'])) {
      $original_entry->data['langcode'] = 'und';
    }
  }
  // Some specific changes for comment forms.
  if (strpos($original_entry->name, 'core.entity_form_display.comment.comment') === 0) {
    $original_entry->data['content']['subject']['type'] = 'string_textfield';
    $original_entry->data['content']['subject']['settings'] = array('size' => 60, 'placeholder' => '');
    $original_entry->data['content']['subject']['third_party_settings'] = array();
    $original_entry->data['bundle'] = 'comment';
    $original_entry->data['id'] = str_replace('comment.node__comment.', 'comment.comment.', $original_entry->data['id']);
    $original_entry->data['dependencies']['entity'][] = 'comment.type.comment';
    foreach ($original_entry->data['dependencies']['module'] as $key => $value) {
      if ($value == 'comment') {
        unset($original_entry->data['dependencies']['module'][$key]);
      }
    }
  }

  // Specific changes for node forms.
  if (strpos($original_entry->name, 'core.entity_form_display.node.') === 0) {
    // Add the uid, created, promote and sticky fields.
    $original_entry->data['content']['uid'] = array(
      'type' => 'entity_reference_autocomplete',
      'weight' => 5,
      'settings' => array(
        'match_operator' => 'CONTAINS',
        'size' => 60,
        'autocomplete_type' => 'tags',
        'placeholder' => '',
      ),
      'third_party_settings' => array(),
    );
    $original_entry->data['content']['created'] = array(
      'type' => 'datetime_timestamp',
      'weight' => 10,
      'settings' => array(),
      'third_party_settings' => array(),
    );
    $original_entry->data['content']['promote'] = array(
      'type' => 'boolean_checkbox',
      'weight' => 15,
      'settings' => array('display_label' => 1),
      'third_party_settings' => array(),
    );
    $original_entry->data['content']['sticky'] = array(
      'type' => 'boolean_checkbox',
      'weight' => 16,
      'settings' => array('display_label' => 1),
      'third_party_settings' => array(),
    );
    $original_entry->data['dependencies']['module'][] = 'entity_reference';
  }

  if (strpos($original_entry->name, 'core.entity_view_display.') === 0) {
    foreach ($original_entry->data['content'] as $key => $value) {
      if (!isset($value['third_party_settings']) && !empty($value['type'])) {
        $original_entry->data['content'][$key]['third_party_settings'] = array();
      }
    }
  }

  if (strpos($original_entry->name, 'core.entity_view_display.block_content.basic.') === 0) {
    if (!isset($original_entry->data['content']['body']['third_party_settings'])) {
      $original_entry->data['content']['body']['third_party_settings'] = array();
    }
    if (!isset($original_entry->data['label'])) {
      $original_entry->data['label'] = NULL;
    }
    if (!isset($original_entry->data['langcode'])) {
      $original_entry->data['langcode'] = 'und';
    }
    if (!isset($original_entry->data['third_party_settings'])) {
      $original_entry->data['third_party_settings'] = array();
    }
  }

  if (strpos($original_entry->name, 'core.entity_view_display.comment.comment.') === 0) {
    $original_entry->data['bundle'] = 'comment';
    if (!isset($original_entry->data['content']['comment_body']['third_party_settings'])) {
      $original_entry->data['content']['comment_body']['third_party_settings'] = array();
    }
    if (!isset($original_entry->data['conetent']['links'])) {
      $original_entry->data['content']['links'] = array('weight' => 100);
    }
    $original_entry->data['dependencies']['entity'][] = 'comment.type.comment';
    foreach ($original_entry->data['dependencies']['module'] as $key => $value) {
      if ($value == 'comment') {
        unset($original_entry->data['dependencies']['module'][$key]);
      }
    }
    $original_entry->data['id'] = str_replace('comment.node__comment.', 'comment.comment.', $original_entry->data['id']);
    if (!isset($original_entry->data['label'])) {
      $original_entry->data['label'] = NULL;
    }
    if (!isset($original_entry->data['langcode'])) {
      $original_entry->data['langcode'] = 'und';
    }
    if (!isset($original_entry->data['third_party_settings'])) {
      $original_entry->data['third_party_settings'] = array();
    }
  }

  if (strpos($original_entry->name, 'core.entity_view_display.node.') === 0) {
    // @todo: this is hard to make automatically, so this needs to be checked
    // after migration.
    if (!isset($original_entry->data['label'])) {
      $original_entry->data['label'] = NULL;
    }
    if (!isset($original_entry->data['langcode'])) {
      $original_entry->data['langcode'] = 'und';
    }
    if (!isset($original_entry->data['third_party_settings'])) {
      $original_entry->data['third_party_settings'] = array();
    }
  }

  if (strpos($original_entry->name, 'core.entity_view_display.user.') === 0) {
    if (!isset($original_entry->data['content']['user_picture']['third_party_settings'])) {
      $original_entry->data['content']['user_picture']['third_party_settings'] = array();
    }
    if (!isset($original_entry->data['label'])) {
      $original_entry->data['label'] = NULL;
    }
    if (!isset($original_entry->data['langcode'])) {
      $original_entry->data['langcode'] = 'und';
    }
    if (!isset($original_entry->data['third_party_settings'])) {
      $original_entry->data['third_party_settings'] = array();
    }
  }

  if ($original_entry->name == 'core.extension') {
    $original_entry->data['module']['menu_link_content'] = $original_entry->data['module']['menu_link'];
    unset($original_entry->data['module']['menu_link']);
    // Not sure if these is needed actually.
    $original_entry->data['module']['link'] = 0;
    $original_entry->data['module']['standard'] = 1000;
  }

  // Field instances.
  if (strpos($original_entry->name, 'field.field.') === 0) {
    if (!isset($original_entry->data['third_party_settings'])) {
      $original_entry->data['third_party_settings'] = array();
    }
    unset($original_entry->data['field_uuid']);
    if (!isset($original_entry->data['translatable'])) {
      // @todo: also, not 100% this should be set all the time, but on a new
      // drupal instance, they are set to TRUE.
      $original_entry->data['translatable'] = TRUE;
    }
  }

  if (strpos($original_entry->name, 'field.field.comment.comment.') === 0) {
    $original_entry->data['bundle'] = 'comment';
    $original_entry->data['dependencies']['entity'][] = 'comment.type.comment';
    $original_entry->data['id'] = str_replace('node__comment', 'comment', $original_entry->data['id']);
  }

  // Field storage.
  if (strpos($original_entry->name, 'field.storage.') === 0) {
    $original_entry->data['field_name'] = $original_entry->data['name'];
    unset($original_entry->data['name']);
    $original_entry->data['id'] = str_replace('custom_block.', 'block_content.', $original_entry->data['id']);
  }

  if (strpos($original_entry->name, 'filter.format.') === 0) {
    $original_entry->data['dependencies']['module'][] = 'editor';
  }

  if (strpos($original_entry->name, 'image.style.') === 0) {
    if (!isset($original_entry->data['third_party_settings'])) {
      $original_entry->data['third_party_settings'] = array();
    }
  }

  if (strpos($original_entry->name, 'node.type.') === 0) {
    $original_entry->data['display_submitted'] = $original_entry->data['settings']['node']['submitted'];
    $original_entry->data['new_revision'] = $original_entry->data['settings']['node']['options']['revision'];
    $original_entry->data['preview_mode'] = $original_entry->data['settings']['node']['preview'];
    // The promote and sticky settings were now moved into separate settings, and
    // we should actually resave the node type pages to recreate them after
    // migration.
  }

  if (strpos($original_entry->name, 'rdf.mapping.') === 0) {
    if (strpos($original_entry->name, 'rdf.mapping.comment.comment') === 0) {
      $original_entry->data['bundle'] = 'comment';
      $original_entry->data['dependencies']['entity'][] = 'comment.type.comment';
      $original_entry->data['id'] = str_replace('node__comment', 'comment', $original_entry->data['id']);
    }
    foreach ($original_entry->data['fieldMappings'] as $key => $value) {
      if (isset($value['datatype_callback']['callable']) && $value['datatype_callback']['callable'] == 'date_iso8601') {
        $original_entry->data['fieldMappings'][$key]['datatype_callback']['callable'] = 'Drupal\rdf\CommonDataConverter::dateIso8601Value';
      }
    }
    if (!isset($original_entry->data['status'])) {
      $original_entry->data['status'] = TRUE;
    }
    if (!isset($original_entry->data['langcode'])) {
      $original_entry->data['langcode'] = 'en';
    }
  }

  if (strpos($original_entry->name, 'taxonomy.vocabulary.') === 0) {
    if (!isset($original_entry->data['third_party_settings'])) {
      $original_entry->data['third_party_settings'] = array();
    }
  }

  if (strpos($original_entry->name, 'user.role.') === 0) {
    foreach ($original_entry->data['permissions'] as $key => &$value) {
      $original_entry->data['permissions'][$key] = str_replace('custom_block', 'block_content', $original_entry->data['permissions'][$key]);
    }
  }

  // Recursively replace the configuration using the _upate_config_process_replace_map().
  // This will search in all the values of the configuration and replace them
  // with the corresponding ones from the map.
  _update_config_process_replace_recursive($original_entry->data, _update_config_process_replace_map());

  $original_entry->data = serialize($original_entry->data);
  return $original_entry;
}

function _update_config_process_replace_map() {
  return array(
    'contact.category.' => 'contact.form.',
    'system.date_format.' => 'core.date_format.',
    'entity.form_display.' => 'core.entity_form_display.',
    'core.entity_form_display.custom_block.' => 'core.entity_form_display.block_content.',
    'core.entity_form_display.comment.node__comment.' => 'core.entity_form_display.comment.comment.',
    'entity.form_mode.' => 'core.entity_form_mode.',
    'entity.view_display.' => 'core.entity_view_display.',
    'core.entity_view_display.custom_block.' => 'core.entity_view_display.block_content.',
    'core.entity_view_display.comment.node__comment.' => 'core.entity_view_display.comment.comment.',
    'entity.view_mode.' => 'core.entity_view_mode.',
    'core.entity_view_mode.custom_block.' => 'core.entity_view_mode.block_content.',
    'field.field.' => 'field.storage.',
    'field.storage.custom_block.' => 'field.storage.block_content.',
    'field.instance.' => 'field.field.',
    'field.field.custom_block.' => 'field.field.block_content.',
    'field.field.comment.node__comment.' => 'field.field.comment.comment.',
    // This is without the dot!
    'rdf.mapping.comment.node__comment' => 'rdf.mapping.comment.comment',
    'custom_block.type.basic' => 'block_content.type.basic',
    'custom_block.basic.body' => 'block_content.basic.body',
    'custom_block.basic.default' => 'block_content.basic.default',
    'custom_block.full' => 'block_content.full'
  );
}

function _update_config_process_replace_recursive(&$array, $map) {
  foreach ($array as $key => &$value) {
    if (is_array($value)) {
      _update_config_process_replace_recursive($value, $map);
    }
    if (is_string($value)) {
      foreach ($map as $_key => $_value) {
        if (strpos($value, $_key) === 0) {
          $array[$key] = str_replace($_key, $_value, $value);
        }
      }
    }
    // @todo: if needed we can also replace the name of the keys.
  }
}