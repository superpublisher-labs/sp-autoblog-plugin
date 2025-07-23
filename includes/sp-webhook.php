<?php

function super_publisher_handle_webhook()
{
    return new WP_REST_Response(['message' => 'Webhook conectado!'], 200);
}

//CATEGORIAS
function super_publisher_criar_categoria($request)
{
    $params = $request->get_json_params();

    $nome = sanitize_text_field($params['name'] ?? '');
    $slug = sanitize_title($params['slug'] ?? '');

    if (empty($nome)) {
        return new WP_REST_Response(['error' => 'Nome da categoria é obrigatório!'], 400);
    }

    $categoria_existente = term_exists($nome, 'category');

    if ($categoria_existente && !is_wp_error($categoria_existente)) {
        return new WP_REST_Response(['error' => 'Categoria já existe!'], 400);
    }

    $resultado = wp_insert_term($nome, 'category', [
        'description' => '',
        'slug'        => $slug,
    ]);

    if (is_wp_error($resultado)) {
        return new WP_REST_Response(['error' => $resultado->get_error_message()], 500);
    }

    $categoria = get_term($resultado['term_id'], 'category');

    return new WP_REST_Response([
        'message' => 'Categoria criada com sucesso!',
        'data'    => [
            'id'   => $categoria->term_id,
            'name' => $categoria->name,
            'slug' => $categoria->slug,
        ]
    ], 201);
}

function super_publisher_remove_categoria($request)
{
    $id = intval($request->get_param('id'));

    if ($id <= 0) {
        return new WP_REST_Response(['error' => 'ID da categoria inválido'], 400);
    }

    $categoria = get_term($id, 'category');

    if (!$categoria || is_wp_error($categoria)) {
        return new WP_REST_Response(['error' => 'Categoria não encontrada'], 404);
    }

    $result = wp_delete_term($categoria->term_id, 'category');

    if (is_wp_error($result)) {
        return new WP_REST_Response(['error' => 'Erro ao deletar categoria'], 500);
    }

    return new WP_REST_Response(['message' => 'Categoria deletada com sucesso!'], 200);
}

function super_publisher_importa_categoria()
{
    $default_cat_id = get_option('default_category');

    $categorias = get_categories([
        'hide_empty' => false,
    ]);

    $resultado = [];

    foreach ($categorias as $categoria) {
        $resultado[] = [
            'id' => $categoria->term_id,
            'nome' => $categoria->name,
            'slug' => $categoria->slug,
            'descricao' => $categoria->description,
            'is_default' => ($categoria->term_id == $default_cat_id),
        ];
    }

    return new WP_REST_Response(['categorias' => $resultado]);
}

function super_publisher_verifica_categoria($request)
{
    $category_id = $request->get_param('id');

    if (empty($category_id) || !is_numeric($category_id) || $category_id <= 0) {
        return new WP_REST_Response(['error' => 'ID inválido'], 400);
    }

    $term = get_term($category_id, 'category');

    if (is_wp_error($term) || !$term || $term->taxonomy !== 'category') {
        return new WP_REST_Response(false, 200);
    }

    return new WP_REST_Response(true, 200);
}

//POST
function super_publisher_criar_editar_post($request)
{
    $params = $request->get_json_params();

    $titulo = sanitize_text_field($params['title'] ?? '');
    $conteudo = wp_kses_post($params['content'] ?? '');
    $categoria_nome = sanitize_text_field($params['category'] ?? '');
    $status = sanitize_text_field($params['status'] ?? 'publish');
    $slug = sanitize_title($params['slug'] ?? '');
    $edit = boolval($params['edit'] ?? false);
    $schedule_date = sanitize_text_field($params['schedule_date'] ?? '');
    $keywords = $params['keywords'] ?? [];

    if (!is_array($keywords)) {
        $decoded_keywords = json_decode($keywords, true);
        $keywords = is_array($decoded_keywords) ? $decoded_keywords : [];
    }

    $meta_keyword = $keywords[0] ?? null;

    $tags = $params['tags'] ?? $params['keywords'] ?? [];
    if (!is_array($tags)) {
        $decoded_tags = json_decode($tags, true);
        $tags = is_array($decoded_tags) ? $decoded_tags : [];
    }

    $meta_title = $params['meta_title'] ?? '';
    $meta_description = $params['meta_description'] ?? '';

    $post_id = $params['id'] ?? 0;

    if (empty($titulo)) {
        return new WP_REST_Response(['error' => 'Título do post é obrigatório!'], 400);
    }

    if (empty($conteudo)) {
        return new WP_REST_Response(['error' => 'Conteúdo do post é obrigatório!'], 400);
    }

    $categoria_ids = [];

    if (!empty($categoria_nome)) {
        $categoria_obj = get_term_by('name', $categoria_nome, 'category');
        // if (!$categoria_obj || is_wp_error($categoria_obj)) {
        //     return new WP_REST_Response(['error' => 'Categoria não existe!'], 400);
        // }
        $categoria_ids[] = intval($categoria_obj->term_id);
    }

    $post = [
        'post_title'    => $titulo,
        'post_content'  => $conteudo,
        'post_status'   => $status,
        'post_category' => $categoria_ids,
        'post_type'     => 'post',
        'post_name'     => $slug,
        'post_author'   => intval(get_option('super_publisher_autor', 0)),
    ];

    if ($schedule_date) {
        $dt = new DateTime($schedule_date, new DateTimeZone('America/Sao_Paulo'));

        $dt_utc = clone $dt;
        $dt_utc->setTimezone(new DateTimeZone('UTC'));

        $post['post_date'] = $dt->format('Y-m-d H:i:s');
        $post['post_date_gmt'] = $dt_utc->format('Y-m-d H:i:s');
        $post['post_status'] = 'future';
    }

    if ($edit && !empty($params['id'])) {
        $post['ID'] = intval($params['id']);
    }

    $post_id = $edit ? wp_update_post($post) : wp_insert_post($post);

    if (!is_wp_error($post_id)) {
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

        $conteudo_novo = substituir_imgs_do_conteudo($conteudo, $post_id);
        if ($conteudo_novo !== $conteudo) {
            wp_update_post([
                'ID' => $post_id,
                'post_content' => $conteudo_novo,
            ]);
        }

        if (!empty($params['thumbnail'])) {
            $alt = $params['thumbnail_alt'] ?? '';
            setar_thumbnail_com_alt($params['thumbnail'], $post_id, $alt);
        }

        return new WP_REST_Response([
            'message' => 'Post criado com sucesso!',
            'id' => $post_id,
        ], 201);
    } else {
        return new WP_REST_Response(['error' => $post_id->get_error_message()], 500);
    }
}

function super_publisher_unpublish_post($request)
{
    $post_id = $request->get_param('id');

    if (empty($post_id) || !is_numeric($post_id) || $post_id <= 0) {
        return new WP_REST_Response(['error' => 'ID inválido'], 400);
    }

    $post = get_post($post_id);

    if (!$post) {
        return new WP_REST_Response(['error' => 'Post não encontrado'], 404);
    }


    $result = wp_update_post([
        'ID' => $post_id,
        'post_status' => 'draft',
    ]);

    if (is_wp_error($result)) {
        return new WP_REST_Response(['error' => 'Erro ao despublicar post'], 500);
    }

    return new WP_REST_Response(['message' => 'Post despublicado com sucesso'], 200);
}

function super_publisher_remove_post($request)
{
    $post_id = $request->get_param('id');

    if (empty($post_id) || !is_numeric($post_id) || $post_id <= 0) {
        return new WP_REST_Response(['error' => 'ID inválido'], 400);
    }

    $result = wp_delete_post($post_id, true);

    if (is_wp_error($result)) {
        return new WP_REST_Response(['error' => 'Erro ao deletar post'], 500);
    }

    return new WP_REST_Response(['message' => 'Post deletado com sucesso!'], 200);
}

function super_publisher_verifica_post($request)
{
    $post_id = $request->get_param('id');

    if (empty($post_id) || !is_numeric($post_id) || $post_id <= 0) {
        return new WP_REST_Response(['error' => 'ID inválido'], 400);
    }

    return new WP_REST_Response(get_post_status($post_id) !== false, 200);
}

//USUARIOS
function super_publisher_importa_usuario()
{
    $usuarios = get_users();

    $resultado = [];

    foreach ($usuarios as $user) {
        $resultado[] = [
            'id' => $user->ID,
            'nome' => $user->display_name,
            'role' => $user->roles[0] ?? 'sem cargo',

        ];
    }

    return new WP_REST_Response(['usuarios' => $resultado]);
}

//AGENDADOS
function super_publisher_change_to_published($new_status, $old_status, $post)
{
    if ($old_status === 'future' && $new_status === 'publish') {

        $token = get_option('super_publisher_token');
        $webhook_url = 'https://autoblog.superpublisher.net/api/post/published';

        $response = wp_remote_request($webhook_url, array(
            'method'  => 'PATCH',
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ),
            'body'    => json_encode(array(
                'wp_post_id' => $post->ID,
                'url' => home_url(),
            )),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            error_log('Webhook falhou: ' . $response->get_error_message());
        } else {
            error_log('Webhook enviado: ' . wp_remote_retrieve_response_code($response));
        }
    }
}

//AUXILIARES
function baixar_e_anexar_imagem($url, $post_id, $alt = '')
{
    $hash = md5($url);

    $query = new WP_Query([
        'post_type' => 'attachment',
        'meta_key' => '_remote_image_url_md5',
        'meta_value' => $hash,
        'posts_per_page' => 1,
        'fields' => 'ids',
    ]);

    if (!empty($query->posts)) {
        return $query->posts[0];
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $tmp = download_url($url);

    if (is_wp_error($tmp)) {
        return 0;
    }

    $file_array = [
        'name'     => basename($url),
        'tmp_name' => $tmp,
    ];

    $id = media_handle_sideload($file_array, $post_id);

    if (is_wp_error($id)) {
        @unlink($tmp);
        return 0;
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

function super_publisher_force_editor_choice($use_block_editor, $post) {
    $is_super_publisher_post = get_post_meta($post->ID, '_super_publisher_post', true);

    if (!$is_super_publisher_post) {
        return $use_block_editor;
    }

    $author_id = $post->post_author;
    $author_prefers_classic = get_user_meta($author_id, 'wp_disable_block_editor', true);
    return !$author_prefers_classic; 
}
