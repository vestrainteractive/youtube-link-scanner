<?php
/**
 * Plugin Name: YouTube Link Scanner
 * Description: Scans all published posts for YouTube links that are broken, under 1 minute, have '[moved]' in the title, or have < 100 views and are older than 6 months.
 * Version: 0.0.90
 * Author: Vestra Interactive
 */

if (!defined('ABSPATH')) exit;

add_action('admin_menu', function() {
    add_menu_page('YouTube Scanner', 'YouTube Scanner', 'manage_options', 'youtube-scanner', 'yt_scanner_page');
});

add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'toplevel_page_youtube-scanner') return;
    wp_enqueue_script('yt-scanner-js', plugin_dir_url(__FILE__) . 'yt-scanner.js', ['jquery'], null, true);
    wp_localize_script('yt-scanner-js', 'ytScannerAjax', ['ajax_url' => admin_url('admin-ajax.php')]);
});

function yt_scanner_page() {
    echo '<div class="wrap"><h1>YouTube Link Scanner <span style="font-size:0.8em">by <a href="https://vestrainteractive.com/?mtm_campaign=PluginLinks&mtm_source=yt-link-scanner&mtm_medium=wordpress" target="_blank"</a></span></h1>';
    
    $api_key = get_option('yt_scanner_api_key', '');
    $api_error_message = '';
    
    if (isset($_POST['yt_api_key'])) {
        update_option('yt_scanner_api_key', sanitize_text_field($_POST['yt_api_key']));
        $api_key = get_option('yt_scanner_api_key', '');
        
        // Quick API check
        $test_url = "https://www.googleapis.com/youtube/v3/videos?part=id&id=dQw4w9WgXcQ&key={$api_key}";
        $response = wp_remote_get($test_url, ['timeout' => 10]);
        
        if (is_wp_error($response)) {
            $api_error_message = $response->get_error_message();
        } else {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($data['error'])) {
                $api_error_message = $data['error']['message'];
            }
        }
    }
    
    echo '<form method="post">
            <input type="text" name="yt_api_key" value="' . esc_attr($api_key) . '" placeholder="Enter YouTube API Key" required> 
            <button type="submit">Save</button>
          </form>';
    
    if ($api_error_message) {
        echo '<div style="color: red; margin-top: 10px;">API Error: ' . esc_html($api_error_message) . '</div>';
    }
    
    echo '<hr><button id="start-scan">Start Scan</button>';
    echo '<div id="scan-status" style="margin-top: 20px;"></div>';
    echo '</div>';
}

add_action('wp_ajax_yt_scan_batch', function() {
    global $wpdb;
    $api_key = get_option('yt_scanner_api_key', '');
    
    if (!$api_key) {
        wp_send_json_error('No API key set.');
    }
    
    $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;
    $posts_per_batch = 50;
    $posts = $wpdb->get_results($wpdb->prepare("SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_status = 'publish' LIMIT %d OFFSET %d", $posts_per_batch, $offset));
    
    if (empty($posts)) {
        wp_send_json_success(['done' => true]);
    }
    
    $scanned = [];
    $youtube_regex = '/(?:https?:\\/\\/)?(?:www\\.)?(?:youtube\\.com\\/watch\\?v=|youtu\\.be\\/|googlevideo\\.com\\/videoplayback\\?.*?id=)([a-zA-Z0-9_-]{11})/i';
    
    foreach ($posts as $post) {
        if (!isset($post->post_content)) continue;
        $matches = [];
        
        preg_match_all($youtube_regex, $post->post_content, $matches);
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $video_id) {
                $api_url = "https://www.googleapis.com/youtube/v3/videos?part=snippet,contentDetails,statistics&id={$video_id}&key={$api_key}";
                $response = wp_remote_get($api_url, ['timeout' => 10]);
                $data = json_decode(wp_remote_retrieve_body($response), true);
                
                if (isset($data['items'][0])) {
                    $video = $data['items'][0];
                    $title = $video['snippet']['title'];
                    $duration = $video['contentDetails']['duration'];
                    $views = isset($video['statistics']['viewCount']) ? (int)$video['statistics']['viewCount'] : 0;
                    $published_at = strtotime($video['snippet']['publishedAt']);
                    $six_months_ago = strtotime('-6 months');
                    
                    if (strpos(strtolower($title), '[moved]') !== false ||
                        $views < 100 && $published_at < $six_months_ago || //change 100 to your preferred view count.
                        preg_match('/PT([0-9]{1,2})M?([0-9]{1,2})?S?/', $duration, $time_matches) && ($time_matches[1] == 0 && (!isset($time_matches[2]) || $time_matches[2] < 60))) {
                        $scanned[] = "<a href='" . get_edit_post_link($post->ID) . "' target='_blank'>Edit Post: " . esc_html($post->post_title) . "</a> - Video ID: " . esc_html($video_id);
                    }
                }
            }
        }
        $scanned[] = "Scanned post: " . esc_html($post->post_title);
        usleep(50000);
    }
    
    wp_send_json_success(['done' => false, 'offset' => $offset + $posts_per_batch, 'scanned' => $scanned]);
});
