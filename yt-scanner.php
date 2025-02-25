<?php
/*
Plugin Name: YouTube Post Scanner
Description: Scans published posts for YouTube links and fetches video details using the YouTube API.
Version: 1.0
Author: Vestra Interactive
*/

if (!defined('ABSPATH')) exit;

class YouTubePostScanner {
    private $option_name = 'yt_api_key';

    public function __construct() {
        add_action('admin_menu', [$this, 'create_admin_page']);
        add_action('wp_ajax_start_scan', [$this, 'start_scan']);
        add_action('wp_ajax_save_api_key', [$this, 'save_api_key']);
    }

    public function create_admin_page() {
        add_menu_page('YouTube Scanner', 'YouTube Scanner', 'manage_options', 'youtube-scanner', [$this, 'render_admin_page']);
    }

    public function render_admin_page() {
        $api_key = get_option($this->option_name, '');
        ?>
        <div class="wrap">
            <h1>YouTube Post Scanner</h1>
            <label for="yt_api_key">YouTube API Key:</label>
            <input type="text" id="yt_api_key" value="<?php echo esc_attr($api_key); ?>">
            <button id="save_api_key" class="button button-primary">Save API Key</button>
            <button id="start_scan" class="button button-secondary">Start Scan</button>
            <button id="stop_scan" class="button button-secondary">Stop Scan</button>
            <div id="scan_results"></div>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('#save_api_key').click(function() {
                $.post(ajaxurl, {action: 'save_api_key', api_key: $('#yt_api_key').val()}, function(response) {
                    alert(response);
                });
            });
            
            $('#start_scan').click(function() {
                $('#scan_results').html('<p>Scanning...</p>');
                scanPosts(0);
            });
        
            function scanPosts(offset) {
                $.post(ajaxurl, {action: 'start_scan', offset: offset}, function(response) {
                    $('#scan_results').append(response.html);
                    if (response.next_offset !== false) {
                        scanPosts(response.next_offset);
                    }
                }, 'json');
            }
        });
        </script>
        <?php
    }

    public function save_api_key() {
        if (isset($_POST['api_key'])) {
            update_option($this->option_name, sanitize_text_field($_POST['api_key']));
            wp_send_json_success('API Key saved.');
        }
        wp_die();
    }

    public function start_scan() {
        global $wpdb;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $limit = 50;
        $posts = $wpdb->get_results($wpdb->prepare("SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post' LIMIT %d OFFSET %d", $limit, $offset));
        $api_key = get_option($this->option_name, '');
        if (!$api_key) wp_send_json_error('API Key missing.');

        $results = [];
        foreach ($posts as $post) {
            if (preg_match_all('/(youtube.com\/watch\?v=|youtu.be\/|googlevideo.com\/videoplayback\?id=)([\w-]+)/', $post->post_content, $matches)) {
                foreach ($matches[2] as $video_id) {
                    $video_data = $this->get_youtube_data($video_id, $api_key);
                    $status = $this->determine_status($video_data);
                    $color = $this->get_status_color($status);
                    $title = esc_html($post->post_title);
                    $edit_link = get_edit_post_link($post->ID);
                    $results[] = "<p style='color: $color;'><a href='$edit_link'>$title</a> - $status</p>";
                }
            }
        }

        wp_send_json(['html' => implode('', $results), 'next_offset' => count($posts) < $limit ? false : $offset + $limit]);
    }

    private function get_youtube_data($video_id, $api_key) {
        $url = "https://www.googleapis.com/youtube/v3/videos?id=$video_id&part=snippet,contentDetails,status,statistics&key=$api_key";
        $response = wp_remote_get($url);
        if (is_wp_error($response)) return null;
        $data = json_decode(wp_remote_retrieve_body($response), true);
        return $data['items'][0] ?? null;
    }

    private function determine_status($video_data) {
        if (!$video_data) return 'UNKNOWN';
        $snippet = $video_data['snippet'];
        $status = $video_data['status'];
        $stats = $video_data['statistics'];

        if (strtotime($snippet['publishedAt']) < strtotime('-6 months') && ($stats['viewCount'] ?? 0) < 100) {
            return 'LOW VIEWS';
        }
        if ($status['privacyStatus'] === 'private' || $status['privacyStatus'] === 'unlisted') {
            return 'PRIVATE';
        }
        if ($status['uploadStatus'] === 'deleted') {
            return 'DELETED';
        }
        if (stripos($snippet['title'], '[moved]') !== false) {
            return 'MOVED';
        }
        if (($stats['dislikeCount'] ?? 0) > 5) {
            return 'SHITTY';
        }
        return 'GOOD';
    }

    private function get_status_color($status) {
        switch ($status) {
            case 'LOW VIEWS': return 'purple';
            case 'PRIVATE': return 'red';
            case 'DELETED': return 'bold red';
            case 'MOVED': return 'orange';
            case 'SHITTY': return 'brown';
            case 'GOOD': return 'bold green';
            default: return 'bold green';
        }
    }
}

new YouTubePostScanner();
