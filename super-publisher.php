<?php

/**
 * Plugin Name: Super Publisher
 * Description: Conecte seu site WordPress ao Super Publisher para automatizar a criação e publicação de conteúdos.
 * Version: 1.0.0
 * Author: Super Publisher
 * Author URI: https://sp-autoblog.test/
 * License: GPL2
 * Text Domain: superpublisher
 * Domain Path: /languages
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */


if (! defined('ABSPATH')) {
	exit;
}

define('SUPER_PUBLISHER_PLUGIN_DIR', plugin_dir_path(__FILE__));

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'super_publisher_adicionar_link_configuracoes');

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
	delete_option('super_publisher_autor');
}

register_activation_hook(__FILE__, 'super_publisher_ativar_plugin');
register_deactivation_hook(__FILE__, 'super_publisher_desativar_plugin');

require_once SUPER_PUBLISHER_PLUGIN_DIR . 'includes/sp-admin-page.php';
require_once SUPER_PUBLISHER_PLUGIN_DIR . 'includes/sp-webhook.php';

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
			'permission_callback' => function ($request) {
				$headers = getallheaders();
				return isset($headers['Authorization']) && $headers['Authorization'] === 'Bearer ' . get_option('super_publisher_token', '');
			},
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
			'callback' => 'super_publisher_criar_categoria',
			'permission_callback' => function ($request) {
				$headers = getallheaders();
				return isset($headers['Authorization']) && $headers['Authorization'] === 'Bearer ' . get_option('super_publisher_token', '');
			},
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
			'callback' => 'super_publisher_remove_categoria',
			'permission_callback' => function ($request) {
				$headers = getallheaders();
				return isset($headers['Authorization']) && $headers['Authorization'] === 'Bearer ' . get_option('super_publisher_token', '');
			},
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
			'callback' => 'super_publisher_importa_categoria',
			'permission_callback' => function ($request) {
				$headers = getallheaders();
				return isset($headers['Authorization']) && $headers['Authorization'] === 'Bearer ' . get_option('super_publisher_token', '');
			},
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
			'callback' => 'super_publisher_verifica_categoria',
			'permission_callback' => function ($request) {
				$headers = getallheaders();
				return isset($headers['Authorization']) && $headers['Authorization'] === 'Bearer ' . get_option('super_publisher_token', '');
			},
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
			'callback' => 'super_publisher_criar_editar_post',
			'permission_callback' => function ($request) {
				$headers = getallheaders();
				return isset($headers['Authorization']) && $headers['Authorization'] === 'Bearer ' . get_option('super_publisher_token', '');
			},
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
			'callback' => 'super_publisher_unpublish_post',
			'permission_callback' => function ($request) {
				$headers = getallheaders();
				return isset($headers['Authorization']) && $headers['Authorization'] === 'Bearer ' . get_option('super_publisher_token', '');
			},
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
			'callback' => 'super_publisher_remove_post',
			'permission_callback' => function ($request) {
				$headers = getallheaders();
				return isset($headers['Authorization']) && $headers['Authorization'] === 'Bearer ' . get_option('super_publisher_token', '');
			},
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
			'callback' => 'super_publisher_verifica_post',
			'permission_callback' => function ($request) {
				$headers = getallheaders();
				return isset($headers['Authorization']) && $headers['Authorization'] === 'Bearer ' . get_option('super_publisher_token', '');
			},
		]
	);
});

/*
 * Rota para mudar o status de posts para publicado no sistema
 * 
 * Envia para o endpoint do nosso sitema as informações: id do post e url do blog
*/
add_action('transition_post_status', 'super_publisher_change_to_published', 10, 3);