<?php
/*
Plugin Name: AI Excerpt Generator
Description: Generates authentic post excerpts based on post content, images, and metadata using the OpenAI API.
Version: 1.0
Author: Imokol Faith Ruth
*/

defined('ABSPATH') || exit;

// Register an OpenAI key option in WordPress settings
add_action('admin_menu', 'ai_excerpt_generator_settings_page');
function ai_excerpt_generator_settings_page() {
	add_options_page(
		'AI Excerpt Generator Settings',
		'AI Excerpt Settings',
		'manage_options',
		'ai-excerpt-settings',
		'ai_excerpt_settings_page_content'
	);
}

function ai_excerpt_settings_page_content() {
	?>
	<div class="wrap">
		<h1>AI Excerpt Generator Settings</h1>
		<form method="post" action="options.php">
			<?php
			settings_fields('ai_excerpt_settings');
			do_settings_sections('ai-excerpt-settings');
			submit_button();
			?>
		</form>
	</div>
	<?php
}

add_action('admin_init', 'ai_excerpt_generator_settings_init');
function ai_excerpt_generator_settings_init() {
	register_setting('ai_excerpt_settings', 'ai_openai_api_key');

	add_settings_section(
		'ai_excerpt_section',
		'OpenAI API Key',
		null,
		'ai-excerpt-settings'
	);

	add_settings_field(
		'ai_openai_api_key',
		'API Key',
		'ai_openai_api_key_field_callback',
		'ai-excerpt-settings',
		'ai_excerpt_section'
	);
}

function ai_openai_api_key_field_callback() {
	$api_key = get_option('ai_openai_api_key');
	echo '<input type="text" name="ai_openai_api_key" value="' . esc_attr($api_key) . '" size="40">';
}
function generate_ai_excerpt($post_id, $post) {
	if (wp_is_post_revision($post_id) || 'auto-draft' === $post->post_status) {
		return;
	}

	// Get OpenAI API Key
	$api_key = get_option('ai_openai_api_key');
	if (empty($api_key)) return;

	// Gather context from the post
	$content = $post->post_content;
	$title = $post->post_title;
	$meta_description = get_post_meta($post_id, '_meta_description', true);
	$image_urls = wp_get_attachment_image_src(get_post_thumbnail_id($post_id), 'full');

	// Prepare content to send to OpenAI
	$prompt = "Generate a brief, authentic excerpt for a blog post titled '{$title}', using this content: '{$content}', metadata description: '{$meta_description}', and main image: '{$image_urls[0]}'.";

	$url        = 'https://api.openai.com/v1/chat/completions';
    $headers    = array(
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type'  => 'application/json',
    );
    $web_data   = [];
    $web_data[] = array(
        'type' => 'text',
        'text' => $prompt,
    );
    $body       = array(
        'model'      => 'gpt-4o', // Replace with the model you want to use
        'messages'   => array(
            array(
                'role'    => 'user',
                'content' => $web_data,
            ),
        ),
        'max_tokens' => 4096,
    );
    $response   = wp_remote_post(
        $url,
        array(
            'headers'     => $headers,
            'body'        => wp_json_encode( $body ),
            'method'      => 'POST',
            'data_format' => 'body',
            'timeout'     => 5000,
        )
    );
    if ( is_wp_error( $response ) ) {
        return null;
    }
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
	$excerpt = trim($body->choices[0]->text);

	if ($excerpt) {
		// Update post excerpt
		wp_update_post([
			'ID' => $post_id,
			'post_excerpt' => $excerpt,
		]);
	}
}
?>
