<?php

/**
 * Plugin Name: Super Publisher
 * Description: Conecte seu site WordPress ao Super Publisher para automatizar a criação e publicação de conteúdos.
 * Version: 2.1.0
 * Author: Super Publisher
 * Author URI: https://autoblog.superpublisher.net/
 * License: GPL2
 * Text Domain: superpublisher
 * Domain Path: /languages
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

if (! defined('ABSPATH')) {
	exit;
}

if (! file_exists(__DIR__ . '/vendor/autoload.php')) {
	return;
}

require 'vendor/autoload.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$UpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/superpublisher-labs/sp-autoblog-plugin',
	__FILE__,
	'super-publisher'
);

$UpdateChecker->setBranch('main');

define('SUPER_PUBLISHER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SUPER_PUBLISHER_PLUGIN_FILE', __FILE__);

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'super_publisher_adicionar_link_configuracoes');
add_filter('use_block_editor_for_post', 'super_publisher_force_editor_choice', 10, 2);

function super_publisher_adicionar_link_configuracoes($links)
{
	$configuracoes = '<a href="admin.php?page=super-publisher">Configurações</a>';
	array_unshift($links, $configuracoes);
	return $links;
}

function super_publisher_ativar_plugin()
{
	error_log('Plugin ativado');
}

function super_publisher_desativar_plugin()
{
	delete_option('super_publisher_token');
	delete_option('default_author');
}

register_activation_hook(__FILE__, 'super_publisher_ativar_plugin');
register_deactivation_hook(__FILE__, 'super_publisher_desativar_plugin');

require_once SUPER_PUBLISHER_PLUGIN_DIR . 'includes/sp-admin-page.php';
require_once SUPER_PUBLISHER_PLUGIN_DIR . 'includes/sp-webhook.php';
require_once SUPER_PUBLISHER_PLUGIN_DIR . 'includes/sp-dashboard-widget.php';

function super_publisher_registrar_dashboard_widget()
{
	wp_add_dashboard_widget(
		'super_publisher_dashboard_widget',
		'Super Publisher - Autoblog',
		'super_publisher_widget'
	);
}
add_action('wp_dashboard_setup', 'super_publisher_registrar_dashboard_widget');

function super_publisher_admin_styles()
{
	// Prepara a string do SVG em base64
	$svg_icon_raw = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 640 640'><g transform='rotate(90, 320, 320)'><path fill='black' d='M432.5 82.3L382.4 132.4L507.7 257.7L557.8 207.6C579.7 185.7 579.7 150.3 557.8 128.4L511.7 82.3C489.8 60.4 454.4 60.4 432.5 82.3zM343.3 161.2L342.8 161.3L198.7 204.5C178.8 210.5 163 225.7 156.4 245.5L67.8 509.8C64.9 518.5 65.9 528 70.3 535.8L225.7 380.4C224.6 376.4 224.1 372.3 224.1 368C224.1 341.5 245.6 320 272.1 320C298.6 320 320.1 341.5 320.1 368C320.1 394.5 298.6 416 272.1 416C267.8 416 263.6 415.4 259.7 414.4L104.3 569.7C112.1 574.1 121.5 575.1 130.3 572.2L394.6 483.6C414.3 477 429.6 461.2 435.6 441.3L478.8 297.2L478.9 296.7L343.4 161.2z'/></g></svg>";
	$icon_data_uri = 'data:image/svg+xml;base64,' . base64_encode($svg_icon_raw);

	// Cria o CSS que vamos injetar
	$custom_css = "
        #toplevel_page_super-publisher .wp-menu-image {
			-webkit-mask-image: url(\"{$icon_data_uri}\");
			mask-image: url(\"{$icon_data_uri}\");

			-webkit-mask-size: 20px;
			mask-size: 20px;

			-webkit-mask-repeat: no-repeat;
			mask-repeat: no-repeat;
			
			-webkit-mask-position: center;
			mask-position: center;

			background-color: #a7aaad; 
		}

		#toplevel_page_super-publisher:hover .wp-menu-image {
			background-color: #72aee6;
		}

		#toplevel_page_super-publisher.wp-has-current-submenu .wp-menu-image,
		#toplevel_page_super-publisher.current .wp-menu-image {
			background-color: #f0f0f1;
		}
    ";

	wp_add_inline_style('wp-admin', $custom_css);
}

add_action('admin_enqueue_scripts', 'super_publisher_admin_styles');

function super_publisher_api_permission_check(WP_REST_Request $request)
{
	$auth_header = $request->get_header('Authorization');
	$token = get_option('super_publisher_token', '');

	return ! empty($token) && ! is_null($auth_header) && $auth_header === 'Bearer ' . $token;
}

add_action('rest_api_init', function () {

	/*
	 * Rota para verificar conexão 
	 * 
	 * Method: GET
	 * Url: <url do blog>/wp-json/super-publisher/v1/webhook
	 * Header: Authorization: Bearer <token>
	 * Body: empty
	 * Return:
	 * {
	 * 	"message": "Webhook conectado!"
	 * }
	 */
	register_rest_route(
		'super-publisher/v1',
		'/webhook',
		[
			'methods'  => 'GET',
			'callback' => 'super_publisher_handle_webhook',
			'permission_callback' => 'super_publisher_api_permission_check',
		]
	);

	//CATEGORIAS

	/*
	 * Rota para criação de categorias
	 * 
	 * Method: POST
	 * Url: <url do blog>/wp-json/super-publisher/v1/categoria
	 * Header: Authorization: Bearer <token>
	 * Body:
	 * {
	 * 	"name": string
	 * 	"slug": string
	 * }
	 * Return:
	 * {
		* "message" => "Categoria criada com sucesso!",
		* "data"=> [
			* "id"	 => 1,
			* "name" => "Nome da categoria",
			* "slug" => "slug-da-categoria",
		* ]
	 * }

	 */

	register_rest_route(
		'super-publisher/v1',
		'/categoria',
		[
			'methods'  => 'POST',
			'callback' => 'super_publisher_category_create',
			'permission_callback' => 'super_publisher_api_permission_check',
		]
	);

	/*
	 * Rota para remoção de categorias
	 * 
	 * Method: DELETE
	 * Url: <url do blog>/wp-json/super-publisher/v1/categoria/{id}
	 * Header: Authorization: Bearer <token>
	 * Body: empty
	 * Return:
	 * {
	 * 	"message": "Categoria deletada com sucesso!"
	 * }
	 */
	register_rest_route(
		'super-publisher/v1',
		'/categoria/(?P<id>\d+)',
		[
			'methods'  => 'DELETE',
			'callback' => 'super_publisher_category_destroy',
			'permission_callback' => 'super_publisher_api_permission_check',
		]
	);

	/*
	 * Rota para importação de categorias
	 * 
	 * Method: GET
	 * Url: <url do blog>/wp-json/super-publisher/v1/categorias
	 * Header: Authorization: Bearer <token>
	 * Body: empty
	 * Return:
	 * {
	 * 	"categorias": [
	 * 		{
	 * 			"id": 1,
	 * 			"nome": "Nome da categoria",
	 * 			"slug": "slug-da-categoria",
	 * 		},
	 * 		{
	 * 			"id": 2,
	 * 			"nome": "Nome da categoria 2",
	 * 			"slug": "slug-da-categoria-2",
	 * 		},
	 * 		...
	 * 	]
	 * }
	 */
	register_rest_route(
		'super-publisher/v1',
		'/categorias',
		[
			'methods'  => 'GET',
			'callback' => 'super_publisher_category_export',
			'permission_callback' => 'super_publisher_api_permission_check',
		]
	);

	/*
	 * Rota para verificação de categorias
	 * 
	 * Method: GET
	 * Url: <url do blog>/wp-json/super-publisher/v1/categoria/{id}
	 * Header: Authorization: Bearer <token>
	 * Body: empty
	 * Return:
	 * {
	 * 	true
	 * }
	 */
	register_rest_route(
		'super-publisher/v1',
		'/categoria/(?P<id>\d+)',
		[
			'methods'  => 'GET',
			'callback' => 'super_publisher_category_check',
			'permission_callback' => 'super_publisher_api_permission_check',
		]
	);

	//POSTS

	/*
	 * Rota para criação e edição de posts
	 * 
	 * Method: POST
	 * Url: <url do blog>/wp-json/super-publisher/v1/post
	 * Header: Authorization: Bearer <token>
	 * Body:
	 * {
	 * 	"title": string,
	 * 	"content": string,
	 * 	"categoriy": string,
	 * 	"status": string,
	 * 	"slug": string,
	 * 	"edit": boolean,
	 * 	"id": int => nullable
	 * 	"date": timestamp => nullable,
	 * 	"tags": array,
	 * 	"meta_title": string,
	 * 	"meta_description": string,
	 * 	"thumbnail": string,
	 * }
	 * Response:
	 * {
	 * 	'message' => 'Post criado com sucesso!',
        'id' => 1,
	 * }
	 */
	register_rest_route(
		'super-publisher/v1',
		'/post',
		[
			'methods'  => 'POST',
			'callback' => 'super_publisher_post_create_edit',
			'permission_callback' => 'super_publisher_api_permission_check',
		]
	);

	/*
	 * Rota para despublicação de posts
	 * 
	 * Method: POST
	 * Url: <url do blog>/wp-json/super-publisher/v1/post/{id}/unpublish
	 * Header: Authorization: Bearer <token>
	 * Body: empty
	 * Return:
	 * {
	 * 	"message": "Post despublicado com sucesso!"
	 * }
	 */
	register_rest_route(
		'super-publisher/v1',
		'/post/(?P<id>\d+)/unpublish',
		[
			'methods' => 'POST',
			'callback' => 'super_publisher_post_unpublish',
			'permission_callback' => 'super_publisher_api_permission_check',
		]
	);

	/*
	 * Rota para remoção de posts
	 * 
	 * Method: DELETE
	 * Url: <url do blog>/wp-json/super-publisher/v1/post/{id}
	 * Header: Authorization: Bearer <token>
	 * Body: empty
	 * Return:
	 * {
	 * 	"message": "Post deletado com sucesso!"
	 * }
	 */
	register_rest_route(
		'super-publisher/v1',
		'/post/(?P<id>\d+)',
		[
			'methods' => 'DELETE',
			'callback' => 'super_publisher_post_destroy',
			'permission_callback' => 'super_publisher_api_permission_check',
		]
	);

	/*
	 * Rota para verificação de posts
	 * 
	 * Method: GET
	 * Url: <url do blog>/wp-json/super-publisher/v1/post/{id}
	 * Header: Authorization: Bearer <token>
	 * Body: empty
	 * Return:
	 * {
	 * 	true
	 * }
	 */
	register_rest_route(
		'super-publisher/v1',
		'/post/(?P<id>\d+)',
		[
			'methods'  => 'GET',
			'callback' => 'super_publisher_post_check',
			'permission_callback' => 'super_publisher_api_permission_check',
		]
	);

	//USERS

	/*
	 * Rota para importação de usuários
	 * 
	 * Method: GET
	 * Url: <url do blog>/wp-json/super-publisher/v1/users
	 * Header: Authorization: Bearer <token>
	 * Body: empty
	 * Return:
	 * 'usuarios': [
	 * 	{
	 * 		'id': 1,
	 * 		'nome': 'Carlos',
	 * 		'role': 'admin',
	 * 	},
	 * 	...
	 * ]
	 */
	register_rest_route(
		'super-publisher/v1',
		'/users',
		[
			'methods'  => 'GET',
			'callback' => 'super_publisher_user_export',
			'permission_callback' => 'super_publisher_api_permission_check',
		]
	);
});

/*
 * Rota para mudar o status de posts para publicado no sistema
 * 
 * Envia para o endpoint do nosso sitema as informações: id do post e url do blog
*/
add_action('transition_post_status', 'super_publisher_change_to_published', 10, 3);
