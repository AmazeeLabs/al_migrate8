Amazee Labs Drupal 8 Migration Scripts
==========

This is a collection of scripts we have used to migrate Drupal 8 production websites from Alpha to Beta Releases.

Don't expect drop-in solutions but finding ideas for implementing your own solution. Since beta9, you can use [HEAD 2 HEAD](https://www.drupal.org/project/head2head) for updating your Drupal 8 sites. Before that it is usually a process of fiddling around with the database configuration or as we prefer: re-building the site and migrating.

## Scripts

* [Migration from Beta1 to Beta 11 (drupal.com)](migrate_beta1_beta11.php)
* [Migration from Alpha11 to Beta 9 (amazeelabs.com)](migrate_alpha11_beta9.php)
* [Update from Alpha13 to Beta 1 (drupal.com)](update_alpha13_beta1.php)

CAUTION: This may break your site and should only be used with understanding of technical implications! Don't forget to backup before you try :)

Want to help or looking for help? You can [support Migrate in Core](https://groups.drupal.org/node/422253) and [contact us](http://www.amazeelabs.com/en/contact) if have interesting projects in that area.
