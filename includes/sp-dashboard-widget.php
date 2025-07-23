<?php

function super_publisher_widget()
{
    global $UpdateChecker;

    // Obter dados do plugin
    $plugin_data = get_plugin_data(SUPER_PUBLISHER_PLUGIN_DIR . 'super-publisher.php');
    $current_version = $plugin_data['Version'] ?? 'N/A';
    
    // Verificar se há configurações salvas
    $token = get_option('super_publisher_token', '');;
    
    // Verificar atualizações
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

    // CSS inline (pode ser movido para um arquivo separado)
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
    </style>';

    // Versão atual
    echo '<div class="info-item">';
    echo '<span class="info-label">Versão Atual:</span>';
    echo '<span class="info-value version-info">v' . esc_html($current_version) . '</span>';
    echo '</div>';

    // Status de atualização
    echo '<div class="info-item">';
    echo '<span class="info-label">Status:</span>';
    echo '<span class="' . $update_status_class . '">' . $update_status_text . '</span>';
    echo '</div>';

    // Notificação de atualização se disponível
    if ($update_available) {
        echo '<div class="update-notice">';
        echo '<strong>Nova versão disponível: v' . esc_html($latest_version) . '</strong><br>';
        echo '<small>Clique em "Configurações" para mais detalhes</small>';
        echo '</div>';
    }

    // Status de conexão
    echo '<div class="info-item">';
    echo '<span class="info-label">Token:</span>';
    echo '<div class="connection-status">';
    echo '<span class="status-dot ' . ($token ? 'connected' : 'disconnected') . '"></span>';
    echo '<span class="info-value">' . ($token ? 'Configurado' : 'Não configurado') . '</span>';
    echo '</div>';
    echo '</div>';

    // URL configurada
    echo '<div class="info-item">';
    echo '<span class="info-label">URL:</span>';
    echo '<span class="info-value">' . get_home_url() . '</span>';
    echo '</div>';

    // Botão de configurações
    echo '<a href="' . esc_url(admin_url('admin.php?page=super-publisher')) . '" class="config-button">Configurações</a>';
}
?>