Amazee Labs Drupal 8 Migration Scripts
==========

This is a collection of scripts we have used to migrate Drupal 8 production websites from Alpha to Beta Releases.

Don't expect drop-in solutions but finding ideas for implementing your own solution. Since beta9, you can use [HEAD 2 HEAD](https://www.drupal.org/project/head2head) for updating your Drupal 8 sites. Before that it is usually a process of fiddling around with the database configuration or as we prefer: re-building the site and migrating.

Here are the scripts:

* [Migration from April 2014 Alpha to Beta 9](migrate_alpha_beta9.php)
* [Migration from Fall 2014 Beta to Beta 11](migrate_alpha_beta11.php)
* [Update Script from Alpha to Beta 1](update_alpha_beta1.php)

CAUTION: This may break your site and should only be used with understanding of technical implications! Don't forget to backup before you try :)

Want to help or looking for help? You can [support Migrate in Core](https://groups.drupal.org/node/422253) and [contact us](http://www.amazeelabs.com/en/contact) if have interesting projects in that area.
