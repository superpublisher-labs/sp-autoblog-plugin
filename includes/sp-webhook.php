<?php

if (! defined('ABSPATH')) {
    exit;
}

function super_publisher_handle_webhook()
{
    $plugin_data = get_plugin_data(SUPER_PUBLISHER_PLUGIN_FILE);
    $current_version = $plugin_data['Version'] ?? 'N/A';

    return new WP_REST_Response([
        'message' => 'connected!',
        'version' => $current_version,
    ], 200);
}

//CATEGORIAS
function super_publisher_category_create($request)
{
    if (!current_user_can('manage_categories')) {
        return new WP_Error(
            'rest_forbidden',
            'Você não tem permissão para criar categorias.',
            ['status' => 403]
        );
    }

    $params = $request->get_json_params();

    $name = sanitize_text_field($params['name'] ?? '');
    $slug = sanitize_title($params['slug'] ?? '');

    if (empty($name)) {
        return new WP_REST_Response(['error' => 'category name is required'], 400);
    }

    $exist = term_exists($name, 'category');

    if ($exist && !is_wp_error($exist)) {
        return new WP_REST_Response(['error' => 'category already exists'], 400);
    }

    $result = wp_insert_term($name, 'category', [
        'description' => '',
        'slug'        => $slug,
    ]);

    if (is_wp_error($result)) {
        return new WP_REST_Response(['error' => $result->get_error_message()], 500);
    }

    return new WP_REST_Response(['id' => $result['term_id']], 201);
}

function super_publisher_category_destroy($request)
{
    if (!current_user_can('manage_categories')) {
        return new WP_Error(
            'rest_forbidden',
            'Você não tem permissão para deletar categorias.',
            ['status' => 403]
        );
    }

    $id = intval($request->get_param('id'));

    if ($id <= 0) {
        return new WP_REST_Response(['error' => 'invalid id'], 400);
    }

    $categoria = get_term($id, 'category');

    if (!$categoria || is_wp_error($categoria)) {
        return new WP_REST_Response(['error' => 'category not found'], 404);
    }

    $result = wp_delete_term($id, 'category');

    if (is_wp_error($result) || !$result) {
        return new WP_REST_Response(['error' => 'unable to delete category'], 500);
    }

    return new WP_REST_Response(['message' => 'category deleted successfully'], 200);
}

function super_publisher_category_export()
{
    if (!current_user_can('edit_posts')) {
        return new WP_Error('rest_forbidden', 'Permissão insuficiente.', ['status' => 403]);
    }

    $default_cat_id = get_option('default_category');

    $categorias = get_categories([
        'hide_empty' => false,
    ]);

    $resultado = [];

    foreach ($categorias as $categoria) {
        $resultado[] = [
            'id' => $categoria->term_id,
            'name' => $categoria->name,
            'slug' => $categoria->slug,
            'description' => $categoria->description,
            'is_default' => ($categoria->term_id == $default_cat_id),
        ];
    }

    return new WP_REST_Response(['categories' => $resultado]);
}

function super_publisher_category_check($request)
{
    if (!current_user_can('edit_posts')) {
        return new WP_Error('rest_forbidden', 'Permissão insuficiente.', ['status' => 403]);
    }
    
    $category_id = $request->get_param('id');

    if (empty($category_id) || !is_numeric($category_id) || $category_id <= 0) {
        return new WP_REST_Response(['error' => 'invalid id'], 400);
    }

    $term = get_term($category_id, 'category');

    if (is_wp_error($term) || !$term || $term->taxonomy !== 'category') {
        return new WP_REST_Response(false, 200);
    }

    return new WP_REST_Response(true, 200);
}

//POST
function super_publisher_post_create_edit($request)
{
    $params = $request->get_json_params();

    $title = sanitize_text_field($params['title'] ?? '');
    $content = wp_kses_post($params['content'] ?? '');
    $category_id = intval($params['category'] ?? 0);
    $user = sanitize_text_field($params['user'] ?? '');
    $allowed_statuses = ['publish', 'future', 'draft', 'pending', 'private'];
    $raw_status = $params['status'] ?? 'publish';
    $status = in_array($raw_status, $allowed_statuses) ? $raw_status : 'publish';
    $slug = sanitize_title($params['slug'] ?? '');
    $edit = boolval($params['edit'] ?? false);
    $schedule_date = sanitize_text_field($params['schedule_date'] ?? '');
    $keywords = $params['keywords'] ?? [];

    if (!is_array($keywords)) {
        $decoded_keywords = json_decode($keywords, true);
        $keywords = is_array($decoded_keywords) ? $decoded_keywords : [];
    }

    $meta_keyword = sanitize_text_field($keywords[0] ?? '');

    $tags = $params['tags'] ?? [];
    if (!is_array($tags)) {
        $decoded_tags = json_decode($tags, true);
        $tags = is_array($decoded_tags) ? $decoded_tags : [];
    }

    $meta_title = sanitize_text_field($params['meta_title'] ?? '');
    $meta_description = sanitize_textarea_field($params['meta_description'] ?? '');

    $post_id = $params['id'] ?? 0;

    if (empty($title)) {
        return new WP_REST_Response(['error' => 'title is required'], 400);
    }

    if (empty($content)) {
        return new WP_REST_Response(['error' => 'content is required'], 400);
    }

    $category_array = [];

    if ($category_id > 0) {
        $category_array[] = $category_id;
    }

    $author_id = intval($user ?? get_option('default_author', 0));

    if ($author_id > 0) {
        if (!user_can($author_id, 'publish_posts')) {
            return new WP_REST_Response([
                'error' => 'Provided user ID does not exist or lacks permission to publish posts.'
            ], 403);
        }
    }

    $post = [
        'post_title'    => $title,
        'post_content'  => $content,
        'post_status'   => $status,
        'post_type'     => 'post',
        'post_name'     => $slug,
        'post_author'   => $author_id,
    ];

    if ($schedule_date) {
        $dt = new DateTime($schedule_date, wp_timezone());

        $post['post_date'] = $dt->format('Y-m-d H:i:s');
        $post['post_date_gmt'] = get_gmt_from_date($post['post_date']);
        $post['post_status'] = 'future';
    } else {

        $post['post_status'] = 'publish';
        $post['post_date'] = current_time('mysql');
        $post['post_date_gmt'] = current_time('mysql', 1);
    }

    if ($edit && !empty($params['id'])) {
        $post['ID'] = intval($params['id']);
    }

    $post_id = $edit ? wp_update_post($post) : wp_insert_post($post);

    if (!is_wp_error($post_id)) {
        if (!empty($category_array)) {
            wp_set_post_categories($post_id, $category_array);
        }

        update_post_meta($post_id, '_super_publisher_post', true);

        if (!empty($tags) && is_array($tags)) {
            wp_set_post_tags($post_id, $tags, true);
        }

        if (class_exists('RankMath')) {
            if (!empty($meta_title)) {
                update_post_meta($post_id, 'rank_math_title', $meta_title);
            }
            if (!empty($meta_description)) {
                update_post_meta($post_id, 'rank_math_description', $meta_description);
            }
            if (!empty($meta_keyword)) {
                update_post_meta($post_id, 'rank_math_focus_keyword', $meta_keyword);
            }
        } elseif (defined('WPSEO_VERSION')) {
            if (!empty($meta_title)) {
                update_post_meta($post_id, '_yoast_wpseo_title', $meta_title);
            }
            if (!empty($meta_description)) {
                update_post_meta($post_id, '_yoast_wpseo_metadesc', $meta_description);
            }
            if (!empty($meta_keyword)) {
                update_post_meta($post_id, '_yoast_wpseo_focuskw', $meta_keyword);
            }
        } elseif (function_exists('aioseo')) {
            if (!empty($meta_title)) {
                update_post_meta($post_id, '_aioseo_title', $meta_title);
            }
            if (!empty($meta_description)) {
                update_post_meta($post_id, '_aioseo_description', $meta_description);
            }
            if (!empty($meta_keyword)) {
                update_post_meta($post_id, '_aioseo_focuskw', $meta_keyword);
            }
        } else {
            if (!empty($meta_title)) {
                update_post_meta($post_id, '_custom_meta_title', $meta_title);
            }
            if (!empty($meta_description)) {
                update_post_meta($post_id, '_custom_meta_description', $meta_description);
            }
            if (!empty($meta_keyword)) {
                update_post_meta($post_id, '_custom_meta_keyword', $meta_keyword);
            }
        }

        $new_content = substituir_imgs_do_conteudo($content, $post_id);
        if ($new_content !== $content) {
            wp_update_post([
                'ID' => $post_id,
                'post_content' => $new_content,
            ]);
        }

        if (!empty($params['thumbnail'])) {
            $alt = $params['thumbnail_alt'] ?? '';
            setar_thumbnail_com_alt($params['thumbnail'], $post_id, $alt);
        }

        return new WP_REST_Response([
            'id' => $post_id,
        ], 201);
    } else {
        return new WP_REST_Response(['error' => $post_id->get_error_message()], 500);
    }
}

function super_publisher_post_unpublish($request)
{
    $post_id = intval($request->get_param('id'));

    if ($post_id <= 0) {
        return new WP_REST_Response(['error' => 'invalid id'], 400);
    }

    $post = get_post($post_id);

    if (!$post) {
        return new WP_REST_Response(['error' => 'post not found'], 404);
    }

    $is_sp_post = get_post_meta($post_id, '_super_publisher_post', true);

    if (!$is_sp_post) {

        return new WP_REST_Response(['error' => 'Permission denied: this post was not created by Super Publisher.'], 403);
    }

    $result = wp_update_post([
        'ID'          => $post_id,
        'post_status' => 'draft',
    ]);

    if (is_wp_error($result)) {
        return new WP_REST_Response(['error' => 'unable to unpublish post'], 500);
    }

    return new WP_REST_Response(['message' => 'post unpublished successfully'], 200);
}

function super_publisher_post_destroy($request)
{
    $post_id = intval($request->get_param('id'));

    if ($post_id <= 0) {
        return new WP_REST_Response(['error' => 'invalid id'], 400);
    }

    $post = get_post($post_id);
    if (!$post) {
        return new WP_REST_Response(['error' => 'post not found'], 404);
    }

    $is_sp_post = get_post_meta($post_id, '_super_publisher_post', true);
    if (!$is_sp_post) {
        return new WP_REST_Response(['error' => 'Permission denied: this post was not created by Super Publisher.'], 403);
    }

    $result = wp_delete_post($post_id, true);

    if (!$result) {
        return new WP_REST_Response(['error' => 'unable to delete post'], 500);
    }

    return new WP_REST_Response(['message' => 'post deleted successfully'], 200);
}

function super_publisher_post_check($request)
{
    $post_id = intval($request->get_param('id'));

    if ($post_id <= 0) {
        return new WP_REST_Response(['error' => 'invalid id'], 400);
    }

    $post_exists = (get_post_status($post_id) !== false);

    $is_sp_post = (bool) get_post_meta($post_id, '_super_publisher_post', true);

    $response_data = ($post_exists && $is_sp_post);

    return new WP_REST_Response($response_data, 200);
}

//USERS
function super_publisher_user_export()
{
    $users = get_users([
        'role__in' => ['administrator', 'editor', 'author'],
        'fields'   => ['ID', 'display_name'],
    ]);

    $result = array_map(function ($user) {
        return [
            'id'   => $user->ID,
            'name' => $user->display_name,
        ];
    }, $users);

    return new WP_REST_Response(['users' => $result]);
}

//SCHEDULED
function super_publisher_change_to_published($new_status, $old_status, $post)
{
    if ('future' === $old_status && 'publish' === $new_status) {
        $token = get_option('super_publisher_token');
        if (empty($token)) {
            return;
        }

        $is_sp_post = get_post_meta($post->ID, '_super_publisher_post', true);
        if (!$is_sp_post) {
            return;
        }

        $webhook_url = 'https://autoblog.superpublisher.net/api/post/published';

        $response = wp_remote_request($webhook_url, [
            'method'  => 'PATCH',
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => json_encode([
                'wp_post_id' => $post->ID,
                'url'        => home_url(),
            ]),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            error_log('Super Publisher Webhook error: ' . $response->get_error_message());
        }
    }
}

//UTILS
function baixar_e_anexar_imagem($url, $post_id, $alt = '')
{
    $hash = md5($url);

    $query = new WP_Query([
        'post_type'      => 'attachment',
        'meta_key'       => '_remote_image_url_md5',
        'meta_value'     => $hash,
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ]);

    if (!empty($query->posts)) {
        return $query->posts[0];
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return new WP_Error('invalid_url', 'A URL fornecida não tem um formato válido.');
    }

    $path_parts = pathinfo(wp_parse_url($url, PHP_URL_PATH));
    $extension = strtolower($path_parts['extension'] ?? '');
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($extension, $allowed_extensions)) {
        return new WP_Error('invalid_extension', 'A URL não aponta para um tipo de imagem permitido (.jpg, .png, etc).');
    }

    $head_response = wp_remote_head($url, ['timeout' => 5]);
    if (is_wp_error($head_response)) {
        return new WP_Error('head_request_failed', 'Não foi possível verificar a URL remota.');
    }

    $content_type = wp_remote_retrieve_header($head_response, 'content-type');
    if (empty($content_type) || strpos($content_type, 'image/') !== 0) {
        return new WP_Error('invalid_content_type', 'O conteúdo da URL não foi identificado como uma imagem.');
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $tmp = download_url($url);

    if (is_wp_error($tmp)) {
        return $tmp;
    }

    $file_name = sanitize_file_name(wp_basename(wp_parse_url($url, PHP_URL_PATH)));

    $file_array = [
        'name'     => $file_name,
        'tmp_name' => $tmp,
    ];

    $id = media_handle_sideload($file_array, $post_id);

    if (is_wp_error($id)) {
        @unlink($tmp);
        return $id;
    }

    update_post_meta($id, '_remote_image_url_md5', $hash);
    if (!empty($alt)) {
        update_post_meta($id, '_wp_attachment_image_alt', sanitize_text_field($alt));
    }

    return $id;
}

function substituir_imgs_do_conteudo($conteudo, $post_id)
{
    if (preg_match_all('/<img[^>]+>/i', $conteudo, $matches)) {
        $tags_originais = $matches[0];

        foreach ($tags_originais as $tag_original) {
            $url_original = '';
            $alt_text = '';

            if (preg_match('/src=[\'"]([^\'"]+)[\'"]/', $tag_original, $src_match)) {
                $url_original = $src_match[1];
            }

            if (preg_match('/alt=[\'"]([^\'"]*)[\'"]/', $tag_original, $alt_match)) {
                $alt_text = $alt_match[1];
            }

            if (empty($url_original)) {
                continue;
            }

            $anexo_id = baixar_e_anexar_imagem($url_original, $post_id, $alt_text);

            if ($anexo_id) {
                $nova_tag_img = wp_get_attachment_image($anexo_id, 'large', false);

                if ($nova_tag_img) {
                    $conteudo = str_replace($tag_original, $nova_tag_img, $conteudo);
                }
            }
        }
    }

    return $conteudo;
}

function setar_thumbnail_com_alt($url, $post_id, $alt = '')
{
    $anexo_id = baixar_e_anexar_imagem($url, $post_id, $alt);
    if ($anexo_id) {
        set_post_thumbnail($post_id, $anexo_id);
    }
}

function super_publisher_force_editor_choice($use_block_editor, $post)
{
    $is_super_publisher_post = get_post_meta($post->ID, '_super_publisher_post', true);

    if (!$is_super_publisher_post) {
        return $use_block_editor;
    }

    $author_id = $post->post_author;
    $author_prefers_classic = get_user_meta($author_id, 'wp_disable_block_editor', true);
    return !$author_prefers_classic;
}
