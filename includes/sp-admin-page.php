<?php

if (! defined('ABSPATH')) {
    exit;
}

// admin page
add_action('admin_menu', function () {
    add_menu_page(
        'Super Publisher',
        'Super Publisher',
        'manage_options',
        'super-publisher',
        'super_publisher_admin_page',
        'none',
        25
    );
});

function super_publisher_enqueue_font_awesome()
{
    wp_enqueue_style('font-awesome-cdn', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', array(), '6.4.0');
}

add_action('admin_enqueue_scripts', 'super_publisher_enqueue_font_awesome');

function super_publisher_admin_page()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token  = sanitize_text_field($_POST['token'] ?? '');
        $author = sanitize_text_field($_POST['author'] ?? '');

        update_option('super_publisher_token', $token);
        update_option('default_author', $author);

        $success = true;
    } else {
        $success = false;
    }

    // Pega os valores salvos
    $token = get_option('super_publisher_token', '');
    $author = get_option('default_author', '');
?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Mona+Sans:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
    <style>
        .mona {
            font-family: 'Mona Sans', sans-serif;
        }

        input,
        select,
        button {
            all: unset !important;
        }
    </style>

    <div class="w-full flex flex-col justify-center items-center px-4 py-8 mona">
        <div class="flex flex-row items-center mb-6 gap-2">
            <div class="relative flex items-center justify-center rounded-xl bg-blue-500 py-2 px-3"><i class="fa-solid fa-pen-nib text-white rotate-90 text-2xl"></i></div>
            <p class="text-3xl font-bold text-gray-800">Super<span class="text-blue-600">Publisher</span></p>
        </div>

        <div class="bg-white shadow-xl rounded-xl p-8 w-full max-w-lg border border-gray-100">
            <h2 class="text-2xl font-semibold text-gray-900 mb-2">Integração</h2>
            <p class="text-sm text-gray-600 mb-6">
                Deixe seu WordPress pronto para funcionar com o Super Publisher.
            </p>

            <form method="post" class="space-y-6">
                <!-- TOKEN -->
                <div class="space-y-2">
                    <label for="token" class="block text-sm font-medium text-gray-700">Token</label>
                    <input type="text" id="token" name="token" value="<?php echo esc_attr($token); ?>"
                        class="w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:ring-2 !focus:ring-blue-500 !focus:border-blue-500 !outline-none !block !box-border !bg-white !text-gray-900 !font-normal"
                        style="display: block !important; box-sizing: border-box !important;" />
                    <p class="text-xs text-gray-500 mb-8">
                        Este token pode ser obtido no Super Publisher em
                        <a href="https://minhaurl.aqui.com" target="_blank" class="text-blue-600 hover:text-blue-800 font-medium hover:underline transition">Configurações > Integrações</a>.
                    </p>
                </div>

                <!-- URL -->
                <div class="space-y-2">
                    <label for="url" class="block text-sm font-medium text-gray-700">URL</label>
                    <div class="flex flex-row gap-2">
                        <input type="text" id="url" name="url" value="<?php echo esc_attr(get_home_url()); ?>"
                            class="w-full p-3 bg-gray-50 border border-gray-300 rounded-lg shadow-sm text-gray-500 !block !box-border !font-normal"
                            style="display: block !important; box-sizing: border-box !important;" readonly />
                        <button id="copyBtn" type="button"
                            class="flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-200 !cursor-pointer !inline-flex"
                            style="cursor: pointer !important; display: inline-flex !important;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                            Copiar
                        </button>
                    </div>
                    <p class="text-xs text-gray-500">
                        Esta URL precisa ser colada no campo "URL do Webhook"
                    </p>
                </div>

                <!-- AUTOR -->
                <div class="space-y-2">
                    <label for="author" class="block text-sm font-medium text-gray-700">Autor padrão</label>
                    <div class="relative w-full">
                        <select name="author" id="author"
                            class="w-full p-3 border border-gray-300 rounded-lg shadow-sm !block !box-border !text-gray-900 !bg-white !font-normal"
                            style="display: block !important; box-sizing: border-box !important; appearance: none !important; -webkit-appearance: none !important; -moz-appearance: none !important;">
                            <?php
                            $authors = get_users([
                                'role__in' => ['administrator', 'editor', 'author'],
                                'fields'   => ['ID', 'display_name'],
                            ]);

                            if (!empty($authors)) {
                                $selected_author = get_option('default_author', $authors[0]->ID);

                                foreach ($authors as $user) {
                                    $selected = selected($selected_author, $user->ID, false);
                            ?>
                                    <option value="<?php echo esc_attr($user->ID); ?>" <?php echo $selected; ?>>
                                        <?php echo esc_html($user->display_name); ?>
                                    </option>
                                <?php
                                }
                            } else {
                                ?>
                                <option value="" disabled>Nenhum autor disponível encontrado.</option>
                            <?php
                            }
                            ?>
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500">
                        Se a automação não especificar um autor para o post, este usuário será usado como padrão. Garante que nenhum post fique sem autor.
                    </p>
                </div>

                <!-- BOTÃO -->
                <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white text-lg font-medium px-4 rounded-lg transition duration-200 transform hover:scale-[1.01] mt-4 !block !text-center !cursor-pointer"
                    style="display: block !important; text-align: center !important; cursor: pointer !important;">
                    Salvar configurações
                </button>

                <!-- SUCESSO -->
                <?php if (!empty($success)) { ?>
                    <div class="flex items-center gap-2 bg-green-50 border border-green-200 rounded-lg p-4 mt-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <p class="text-sm text-green-700 font-medium m-0">Configurações atualizadas</p>
                    </div>
                <?php } ?>
            </form>
        </div>
    </div>

    <style>
        /* Estilos !important após o reset */
        input,
        select,
        button {
            font-family: inherit !important;
        }

        input[type="text"] {
            width: 100% !important;
            padding: 0.75rem !important;
            border: 1px solid #d1d5db !important;
            border-radius: 0.5rem !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
            font-size: 0.875rem !important;
            line-height: 1.25rem !important;
        }

        input[type="text"]:focus {
            outline: none !important;
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25) !important;
        }

        input[readonly] {
            background-color: #f9fafb !important;
            color: #6b7280 !important;
        }

        select {
            width: 100% !important;
            padding: 0.75rem !important;
            padding-right: 2rem !important;
            border: 1px solid #d1d5db !important;
            border-radius: 0.5rem !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
            font-size: 0.875rem !important;
            line-height: 1.25rem !important;
            background-color: #fff !important;
            appearance: none !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
        }

        select:focus {
            outline: none !important;
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25) !important;
        }

        button[type="submit"] {
            display: block !important;
            width: 100% !important;
            padding: 0.75rem 0 !important;
            background-color: #2563eb !important;
            color: #fff !important;
            font-weight: 500 !important;
            font-size: 1rem !important;
            text-align: center !important;
            border-radius: 0.5rem !important;
            transition: background-color 0.2s ease !important;
            cursor: pointer !important;
            margin-top: 1.5rem !important;
        }

        button[type="submit"]:hover {
            background-color: #1d4ed8 !important;
            transform: scale(1.01) !important;
        }

        button#copyBtn {
            display: inline-flex !important;
            gap: 0.25rem !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 0.5rem 1rem !important;
            background-color: #2563eb !important;
            color: #fff !important;
            font-weight: 500 !important;
            font-size: 0.875rem !important;
            border-radius: 0.5rem !important;
            transition: background-color 0.2s ease !important;
            cursor: pointer !important;
        }

        button#copyBtn:hover {
            background-color: #1d4ed8 !important;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const copyBtn = document.getElementById('copyBtn');
            const urlInput = document.getElementById('url');

            let initialHtml = copyBtn.innerHTML;

            copyBtn.addEventListener('click', function() {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(urlInput.value).then(() => {
                        copyBtn.innerHTML = '<i class="fa-solid fa-check"></i> <span>Copiado</span>';
                        setTimeout(() => {
                            copyBtn.innerHTML = initialHtml;
                        }, 2000);
                    }).catch(err => {
                        alert('Erro ao copiar para a área de transferência.');
                        console.error('Erro ao copiar:', err);
                    });
                } else {
                    // fallback para navegadores antigos ou sem suporte HTTPS
                    const textArea = document.createElement('textarea');
                    textArea.value = urlInput.value;
                    document.body.appendChild(textArea);
                    textArea.select();

                    try {
                        const successful = document.execCommand('copy');
                        if (successful) {
                            copyBtn.innerHTML = '<i class="fa-solid fa-check"></i> <span>Copiado</span>';
                            setTimeout(() => {
                                copyBtn.innerHTML = initialHtml;
                            }, 2000);
                        } else {
                            alert('Não foi possível copiar o texto.');
                        }
                    } catch (err) {
                        alert('Erro ao copiar para a área de transferência.');
                        console.error('Erro ao copiar:', err);
                    }

                    document.body.removeChild(textArea);
                }
            });

        });
    </script>
<?php
}
