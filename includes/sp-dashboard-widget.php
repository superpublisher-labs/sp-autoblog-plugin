<?php

if (! defined('ABSPATH')) {
    exit;
}

function super_publisher_widget()
{
    global $UpdateChecker;

    $plugin_data = get_plugin_data(SUPER_PUBLISHER_PLUGIN_FILE);
    $current_version = $plugin_data['Version'] ?? 'N/A';

    $token = get_option('super_publisher_token', '');

    $update_available = false;
    $latest_version = $current_version;
    $update_status_class = 'status-updated';
    $update_status_text = '✅ Atualizado';

    if (isset($UpdateChecker) && is_object($UpdateChecker)) {
        $update_info = $UpdateChecker->getUpdate();
        if ($update_info !== null) {
            $update_available = true;
            $latest_version = $update_info->version;
            $update_status_class = 'status-outdated';
            $update_status_text = '🚨 Desatualizado';
        }
    }

    echo '<style>
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f6f7f7;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #646970;
            font-weight: 500;
        }

        .info-value {
            color: #1d2327;
            font-weight: 600;
        }

        .status-updated {
            color: #00a32a;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .status-outdated {
            color: #d63638;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .config-button {
            display: inline-block;
            background: #2271b1;
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 3px;
            font-size: 13px;
            font-weight: 500;
            margin-top: 15px;
            transition: background-color 0.2s;
            text-align: center;
            width: 100%;
            box-sizing: border-box;
        }

        .config-button:hover {
            background: #135e96;
            color: white;
        }

        .config-button:before {
            content: "⚙️ ";
            margin-right: 5px;
        }

        .update-notice {
            background: #fcf9e8;
            border: 1px solid #f0b849;
            border-radius: 3px;
            padding: 10px;
            margin: 10px 0;
            font-size: 12px;
        }

        .connection-status {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .status-dot.connected {
            background: #00a32a;
        }

        .status-dot.disconnected {
            background: #d63638;
        }

        .version-info {
            font-family: "Courier New", monospace;
            font-size: 12px;
            background: #f6f7f7;
            padding: 2px 6px;
            border-radius: 3px;
        }

        .progress-container {
            margin-top: 15px;
            margin-bottom: 5px;
        }

        .progress-bar {
            width: 100%;
            height: 24px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            position: relative;
            border: 1px solid #ddd;
        }

        .progress-bar-fill {
            height: 100%;
            background-color: #2271b1;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: width 0.5s ease-in-out;
        }

        .progress-bar-text {
            color: white;
            font-weight: bold;
            font-size: 12px;
        }

        .progress-label {
            text-align: center;
            font-size: 12px;
            color: #646970;
            margin-top: 5px;
        }
    </style>';

    echo '<div class="info-item">';
    echo '<span class="info-label">Versão Atual:</span>';
    echo '<span class="info-value version-info">v' . esc_html($current_version) . '</span>';
    echo '</div>';

    echo '<div class="info-item">';
    echo '<span class="info-label">Status:</span>';
    echo '<span class="' . $update_status_class . '">' . $update_status_text . '</span>';
    echo '</div>';

    if ($update_available) {
        echo '<div class="update-notice">';
        echo '<strong>Nova versão disponível: v' . esc_html($latest_version) . '</strong><br>';
        echo '<small>Atualize agora mesmo!</small>';
        echo '</div>';
    }

    echo '<div class="info-item">';
    echo '<span class="info-label">Token:</span>';
    echo '<div class="connection-status">';
    echo '<span class="status-dot ' . ($token ? 'connected' : 'disconnected') . '"></span>';
    echo '<span class="info-value">' . ($token ? 'Configurado' : 'Não configurado') . '</span>';
    echo '</div>';
    echo '</div>';

    echo '<div class="info-item">';
    echo '<span class="info-label">URL:</span>';
    echo '<span class="info-value">' . get_home_url() . '</span>';
    echo '</div>';

    $total_itens_plugin = sp_autoblog_contar_posts_do_plugin();
    $contagem_obj = wp_count_posts('post');
    $total_de_posts_no_blog = $contagem_obj->publish + $contagem_obj->draft + $contagem_obj->future + $contagem_obj->pending + $contagem_obj->private;

    $porcentagem = 0;
    if ($total_de_posts_no_blog > 0) {
        $porcentagem = ($total_itens_plugin / $total_de_posts_no_blog) * 100;
    }

    $porcentagem_final = min($porcentagem, 100);

    echo '<div class="progress-container">';
    echo '<div class="progress-bar">';
    echo '<div class="progress-bar-fill" style="width: ' . esc_attr($porcentagem_final) . '%;">';
    echo '<span class="progress-bar-text">' . round($porcentagem_final) . '%</span>';
    echo '</div>';
    echo '</div>';
    echo '<div class="progress-label">' . esc_html($total_itens_plugin) . ' de ' . esc_html($total_de_posts_no_blog) . ' posts automatizados</div>';
    echo '</div>';

    echo '<a href="' . esc_url(admin_url('admin.php?page=super-publisher')) . '" class="config-button">Configurações</a>';
}

function sp_autoblog_contar_posts_do_plugin()
{
    $args = [
        'post_type'      => 'any',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'meta_query'     => [
            [
                'key'     => '_super_publisher_post',
                'compare' => 'EXISTS',
            ],
        ],
    ];

    $query = new WP_Query($args);

    return $query->found_posts;
}
