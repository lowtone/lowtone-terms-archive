<?php
/*
 * Plugin Name: Term Archives
 * Plugin URI: http://wordpress.lowtone.nl/plugins/terms-archive/
 * Description: Create archives for terms.
 * Version: 1.0
 * Author: Lowtone <info@lowtone.nl>
 * Author URI: http://lowtone.nl
 * License: http://wordpress.lowtone.nl/license
 */
/**
 * @author Paul van der Meijs <code@lowtone.nl>
 * @copyright Copyright (c) 2013, Paul van der Meijs
 * @license http://wordpress.lowtone.nl/license/
 * @version 1.0
 * @package wordpress\plugins\lowtone\terms\archive
 */

namespace lowtone\terms\archive {

	use lowtone\content\packages\Package,
		lowtone\ui\forms\Form,
		lowtone\ui\forms\Input;

	// Includes
	
	if (!include_once WP_PLUGIN_DIR . "/lowtone-content/lowtone-content.php") 
		return trigger_error("Lowtone Content plugin is required", E_USER_ERROR) && false;

	$__i = Package::init(array(
			Package::INIT_PACKAGES => array("lowtone", "lowtone\\wp"),
			Package::INIT_MERGED_PATH => __NAMESPACE__,
			Package::INIT_SUCCESS => function() {

				add_action("init", function() {

					register_setting("lowtone_terms_archive", "lowtone_terms_archive_taxonomies");

					$taxonomyArchives = get_option("lowtone_terms_archive_taxonomies") ?: array();

					$taxonomies = get_taxonomies(NULL, "objects");

					// Add rewrite rules
					
					add_rewrite_tag("%lowtone_terms_archive_taxonomy%", "([^&]+)");

					$addRewriteRules = function() use ($taxonomies, $taxonomyArchives) {

						foreach ($taxonomies as $taxonomy) {
							if (!isset($taxonomy->rewrite["slug"]))
								continue;

							if (!(isset($taxonomyArchives[$taxonomy->name]) && $taxonomyArchives[$taxonomy->name]))
								continue;

							add_rewrite_rule($taxonomy->rewrite["slug"] . "/?$", "index.php?lowtone_terms_archive_taxonomy=" . $taxonomy->name, "top");
						}

					};
					
					$addRewriteRules();

					add_action("admin_init", function() use ($taxonomies, $taxonomyArchives) {
						$form = function($taxonomy) use ($taxonomyArchives) {

							$taxonomy = get_taxonomy($taxonomy);

							echo '<div class="form-wrap term_archive">' . 
								'<h3>' . sprintf(__("%s archive"), $taxonomy->labels->singular_name) . '</h3>' . 
								sprintf('<form action="%s" method="post">', esc_url(admin_url("options.php"))) . 
								'<input type="hidden" name="option_page" value="lowtone_terms_archive" />' .
								'<input type="hidden" name="action" value="update" />' . 
								sprintf('<input type="hidden" id="_wpnonce" name="_wpnonce" value="%s" />', esc_attr(wp_create_nonce("lowtone_terms_archive-options"))) .
								sprintf('<input type="hidden" name="_wp_http_referer" value="%s" />', esc_url($_SERVER["REQUEST_URI"]));

							$form = new Form();

							$form
								->createInput(Input::TYPE_HIDDEN, array(
									Input::PROPERTY_NAME => "lowtone_terms_archive_taxonomies[taxonomy]",
									Input::PROPERTY_VALUE => $taxonomy->name,
								))
								->out();

							echo '<div class="form-field">';

							echo '<style>.lowtone.comment {font-size: 12px;}</style>';

							$form
								->createInput(Input::TYPE_CHECKBOX, array(
									Input::PROPERTY_NAME => "lowtone_terms_archive_taxonomies[create]",
									Input::PROPERTY_VALUE => "1",
									Input::PROPERTY_LABEL => sprintf(__("Create %s archive", "lowtone_terms_archive"), $taxonomy->labels->singular_name),
									Input::PROPERTY_COMMENT => sprintf(__('If selected, an overview of terms will be available at <a href="%1$s">%1$s</a>.', "lowtone_terms_archive"), url($taxonomy)),
									Input::PROPERTY_SELECTED => isset($taxonomyArchives[$taxonomy->name]) && $taxonomyArchives[$taxonomy->name],
								))
								->out();

							echo '</div>';

							echo '<p class="submit">';

							$form
								->createInput(Input::TYPE_SUBMIT, array(
									Input::PROPERTY_VALUE => __("Save", "lowtone_terms_archive"),
									Input::PROPERTY_CLASS => "button button-primary",
								))
								->out();

							echo '</p>';

							echo '</form>';

							echo '</div>';

						};

						foreach ($taxonomies as $taxonomy) 
							add_action($taxonomy->name . "_pre_add_form", $form);

						add_filter("pre_update_option_lowtone_terms_archive_taxonomies", function($new, $old) {
							$old = $old ?: array();
							
							$old[$new["taxonomy"]] = isset($new["create"]) && $new["create"];

							return array_filter($old);
						}, 10, 2);
					});

					add_action("update_option_lowtone_terms_archive_taxonomies", function($old, $new) use (&$taxonomyArchives, $addRewriteRules) {
						$addRewriteRules();

						flush_rewrite_rules();
					}, 10, 2);
				}, 9999);

				// Add taxonomy to $wp_query

				add_action("wp", function() {
					global $wp_query;

					if (!($taxonomy = $wp_query->get("lowtone_terms_archive_taxonomy")))
						return;

					if (isset($wp_query->taxonomy))
						return;

					if (false === ($taxonomy = get_taxonomy($taxonomy)))
						return;

					$options = array(
							"order" => "ASC",
							"orderby" => isset($wp_query->query_vars["orderby"]) ? $wp_query->get("orderby") : "name",
							"hide_empty" => false,
						);

					$options = apply_filters("lowtone_terms_archive_options", $options, $taxonomy);

					$taxonomy->terms = get_terms($taxonomy->query_var, $options);

					$wp_query->taxonomy = $taxonomy;
				});

				// Menu items

				/*add_action("load-nav-menus.php", function() {
					
					return;

					add_meta_box( 
							"add-taxonomy", 
							__("Taxonomies", "lowtone_terms_archive"), 
							function() {
								$currentTab = "all";

								echo '<div id="taxonomies" class="taxonomydiv">' . 
									'<ul id="taxonomy--tabs" class="taxonomy-tabs add-menu-item-tabs">' .
									// '<li ' . ("most-used" == $currentTab ? ' class="tabs"' : '') . '><a class="nav-tab-link" href="#tabs-panel--pop">' . __("Most Used") . '</a></li>' . 
									'<li ' . ("all" == $currentTab ? ' class="tabs"' : '') . '><a class="nav-tab-link" href="#tabs-panel--all">' . __("View All") . '</a></li>' . 
									// '<li ' . ("search" == $currentTab ? ' class="tabs"' : '') . '><a class="nav-tab-link" href="#tabs-panel-search-taxonomy-">' . __("Search") . '</a></li>' . 
									'</ul>';

								echo '<div id="tabs-panel--pop" class="tabs-panel ' . ('all' == $currentTab ? 'tabs-panel-active' : 'tabs-panel-inactive') . '">' .
									'<ul id="checklist-pop" class="categorychecklist form-no-clear" >' . 
									implode(array_map(function($taxonomy) {
										return '<li>' .
											sprintf('<label class="menu-item-title"><input type="checkbox" class="menu-item-checkbox" name="menu-item[-2][menu-item-object-id]" value="%s"> ', esc_attr($taxonomy->name)) . esc_html($taxonomy->labels->name) . '</label>' . 
											'<input type="hidden" class="menu-item-db-id" name="menu-item[-2][menu-item-db-id]" value="0">' . 
											'<input type="hidden" class="menu-item-object" name="menu-item[-2][menu-item-object]" value="taxonomy">' . 
											'<input type="hidden" class="menu-item-parent-id" name="menu-item[-2][menu-item-parent-id]" value="0">' . 
											'<input type="hidden" class="menu-item-type" name="menu-item[-2][menu-item-type]" value="post_type">' . 
											sprintf('<input type="hidden" class="menu-item-title" name="menu-item[-2][menu-item-title]" value="%s">', esc_attr($taxonomy->labels->name)) . 
											sprintf('<input type="hidden" class="menu-item-url" name="menu-item[-2][menu-item-url]" value="%s">', esc_attr(url($taxonomy))) . 
											'<input type="hidden" class="menu-item-target" name="menu-item[-2][menu-item-target]" value="">' . 
											'<input type="hidden" class="menu-item-attr_title" name="menu-item[-2][menu-item-attr_title]" value="">' . 
											'<input type="hidden" class="menu-item-classes" name="menu-item[-2][menu-item-classes]" value="">' . 
											'<input type="hidden" class="menu-item-xfn" name="menu-item[-2][menu-item-xfn]" value="">' . 
											'</li>';
									}, get_taxonomies(NULL, "objects"))) . 
									'</ul>' .
									'</div>';

								echo '<p class="button-controls">' . 
									'<span class="list-controls">' . 
									'<a href="#taxonomies" class="select-all">' . __('Select All') . '</a>' . 
									'</span>' . 
									'<span class="add-to-menu">' . 
									sprintf('<input type="submit" class="button-secondary submit-add-to-menu right" value="%s" name="add-taxonomies-menu-item" id="submit-taxonomies" />', __("Add to Menu")) . 
									'<span class="spinner"></span>' . 
									'</span>' . 
									'</p>';

								echo '</div>';
							},
							"nav-menus", 
							"side", 
							"default"
						);

				});*/

			}
		));

	// Functions

	function url($taxonomy) {
		return site_url(sprintf("/%s/", $taxonomy->rewrite["slug"]));
	}

	function isTermsArchive() {
		global $wp_query;

		if (!$wp_query->get("lowtone_terms_archive_taxonomy"))
			return false;

		if (!isset($wp_query->taxonomy))
			return false;

		return true;
	}

}