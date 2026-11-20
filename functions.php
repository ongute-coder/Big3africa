<?php
/** Big3Africa Portal integration for WordPress themes. */

if (!defined('ABSPATH')) exit;

add_action('init', function () {
  // Revoke legacy shared passkeys from earlier package builds.
  if (!get_option('b3a_security_cleanup_v280')) {
    delete_option('b3a_admin_passkey');
    delete_option('b3a_role_keys');
    add_option('b3a_security_cleanup_v280', 1, '', false);
  }
  if (!get_role('b3a_reporter')) {
    add_role('b3a_reporter', 'Big3Africa Field Reporter', array(
      'read' => true, 'edit_posts' => true, 'upload_files' => true, 'publish_posts' => false,
    ));
  }
  register_post_type('b3a_opportunity', array(
    'labels' => array('name' => 'Green Market Opportunities', 'singular_name' => 'Opportunity', 'add_new_item' => 'Add Opportunity', 'edit_item' => 'Review Opportunity'),
    'public' => false, 'show_ui' => true, 'show_in_menu' => true, 'menu_icon' => 'dashicons-megaphone',
    'supports' => array('title'), 'capability_type' => 'post', 'map_meta_cap' => true,
  ));
});

add_action('add_meta_boxes', function () {
  add_meta_box('b3a_opportunity_details', 'Opportunity details', function ($post) {
    wp_nonce_field('b3a_save_opportunity', 'b3a_opportunity_nonce');
    $submitter = get_post_meta($post->ID, '_b3a_submitterName', true);
    $submitter_email = get_post_meta($post->ID, '_b3a_submitterEmail', true);
    if ($submitter || $submitter_email) echo '<p><strong>Private suggestion contact:</strong> ' . esc_html($submitter) . ' · ' . esc_html($submitter_email) . '</p>';
    $fields = array('organiser' => 'Organiser', 'type' => 'Type (Grant, Tender, Fund, Call, Partnership)', 'sector' => 'Sector', 'county' => 'County / location', 'deadline' => 'Deadline (YYYY-MM-DD)', 'eligibility' => 'Eligibility (one per line)', 'summary' => 'Summary', 'source_url' => 'Original source URL', 'checked_at' => 'Date checked (YYYY-MM-DD)', 'status' => 'Listing state');
    foreach ($fields as $key => $label) {
      $value = get_post_meta($post->ID, '_b3a_' . $key, true);
      echo '<p><label style="display:block;font-weight:600;margin-bottom:4px" for="b3a_' . esc_attr($key) . '">' . esc_html($label) . '</label>';
      if ($key === 'status') echo '<select style="width:100%" id="b3a_status" name="b3a_status"><option value="open" ' . selected($value, 'open', false) . '>Open</option><option value="closed" ' . selected($value, 'closed', false) . '>Closed by organiser</option></select>';
      elseif ($key === 'source_url') echo '<input type="url" style="width:100%" id="b3a_' . esc_attr($key) . '" name="b3a_' . esc_attr($key) . '" value="' . esc_attr(get_post_meta($post->ID, '_b3a_source_url', true)) . '" />';
      elseif ($key === 'summary' || $key === 'eligibility') echo '<textarea style="width:100%" rows="' . ($key === 'summary' ? '4' : '3') . '" id="b3a_' . esc_attr($key) . '" name="b3a_' . esc_attr($key) . '">' . esc_textarea($value) . '</textarea>';
      else echo '<input style="width:100%" id="b3a_' . esc_attr($key) . '" name="b3a_' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
      echo '</p>';
    }
    echo '<p><strong>Approval:</strong> Keep this item Pending Review until the source and deadline have been checked. Publish only after staff approval.</p>';
  }, 'b3a_opportunity', 'normal', 'high');
});

add_action('save_post_b3a_opportunity', function ($post_id) {
  if (!isset($_POST['b3a_opportunity_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['b3a_opportunity_nonce'])), 'b3a_save_opportunity')) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (!current_user_can('edit_post', $post_id)) return;
  foreach (array('organiser','type','sector','county','deadline','checked_at','summary','eligibility','status') as $key) {
    if (!isset($_POST['b3a_' . $key])) continue;
    $value = sanitize_textarea_field(wp_unslash($_POST['b3a_' . $key]));
    update_post_meta($post_id, '_b3a_' . $key, $value);
  }
  if (isset($_POST['b3a_source_url'])) update_post_meta($post_id, '_b3a_source_url', esc_url_raw(wp_unslash($_POST['b3a_source_url'])));
});

add_action('rest_api_init', function () {
  register_rest_route('big3africa/v1', '/workspace', array(
    array(
      'methods' => 'GET',
      'callback' => function () {
        $data = array(
          'bannerConfig' => get_option('b3a_masthead_banner_config', array()),
          'commercialSlots' => get_option('b3a_commercial_slots', array()),
          'customArticles' => get_option('b3a_custom_articles', array()),
          'videos' => get_option('b3a_custom_videos', array()),
          'fieldPhotos' => get_option('b3a_field_photos', array()),
          'events' => get_option('b3a_custom_events', array()),
          'magazineIssues' => get_option('b3a_magazine_issues', array()),
          'opportunities' => array_map(function ($post) {
            $deadline = get_post_meta($post->ID, '_b3a_deadline', true);
            $state = get_post_meta($post->ID, '_b3a_status', true) === 'closed' ? 'closed' : 'open';
            if ($state !== 'closed' && $deadline && strtotime($deadline . ' 23:59:59') < current_time('timestamp')) $state = 'expired';
            return array('id' => (string) $post->ID, 'title' => get_the_title($post), 'organiser' => get_post_meta($post->ID, '_b3a_organiser', true), 'type' => get_post_meta($post->ID, '_b3a_type', true), 'sector' => get_post_meta($post->ID, '_b3a_sector', true), 'county' => get_post_meta($post->ID, '_b3a_county', true), 'deadline' => $deadline, 'eligibility' => array_values(array_filter(array_map('trim', explode("\n", get_post_meta($post->ID, '_b3a_eligibility', true))))), 'summary' => get_post_meta($post->ID, '_b3a_summary', true), 'sourceUrl' => get_post_meta($post->ID, '_b3a_source_url', true), 'checkedAt' => get_post_meta($post->ID, '_b3a_checked_at', true), 'status' => $state);
          }, get_posts(array('post_type' => 'b3a_opportunity', 'post_status' => 'publish', 'numberposts' => 200, 'orderby' => 'date', 'order' => 'DESC'))),
        );
        if (current_user_can('publish_posts')) {
          $data['pendingArticles'] = get_option('b3a_pending_articles', array());
        }
        if (current_user_can('publish_posts')) {
          $roster = get_option('b3a_newsroom_roster', array());
          $data['staffRoster'] = is_array($roster) ? array_map(function ($member) {
            if (is_array($member)) unset($member['passcode']);
            return $member;
          }, $roster) : array();
        }
        if (current_user_can('manage_options')) {
          $data['registrations'] = get_option('b3a_event_registrations', array());
          $data['financeDeals'] = get_option('b3a_finance_deals', array());
        }
        return $data;
      },
      'permission_callback' => '__return_true',
    ),
    array(
      'methods' => 'POST',
      'callback' => function (WP_REST_Request $request) {
        $params = $request->get_json_params();
        $pending_only = is_array($params) && empty(array_diff(array_keys($params), array('pendingArticles')));
        $sensitive = is_array($params) && !empty(array_intersect(array_keys($params), array('registrations', 'financeDeals')));
        $allowed = $pending_only ? current_user_can('publish_posts') : ($sensitive ? current_user_can('manage_options') : current_user_can('publish_posts'));
        if (!$allowed) {
          return new WP_Error('rest_forbidden', 'Unauthorized', array('status' => 403));
        }
        $map = array(
          'bannerConfig' => 'b3a_masthead_banner_config',
          'commercialSlots' => 'b3a_commercial_slots',
          'customArticles' => 'b3a_custom_articles',
          'pendingArticles' => 'b3a_pending_articles',
          'staffRoster' => 'b3a_newsroom_roster',
          'videos' => 'b3a_custom_videos',
          'fieldPhotos' => 'b3a_field_photos',
          'events' => 'b3a_custom_events',
          'registrations' => 'b3a_event_registrations',
          'financeDeals' => 'b3a_finance_deals',
          'magazineIssues' => 'b3a_magazine_issues',
        );
        foreach ($map as $key => $option) {
          if (isset($params[$key]) && is_array($params[$key])) {
            $value = $params[$key];
            if ($key === 'staffRoster') {
              $value = array_map(function ($member) {
                if (is_array($member)) unset($member['passcode']);
                return $member;
              }, $value);
            }
            update_option($option, $value, false);
          }
        }
        return array('success' => true, 'timestamp' => current_time('mysql'));
      },
      'permission_callback' => function (WP_REST_Request $request) {
        $params = $request->get_json_params();
        $pending_only = is_array($params) && empty(array_diff(array_keys($params), array('pendingArticles')));
        $sensitive = is_array($params) && !empty(array_intersect(array_keys($params), array('registrations', 'financeDeals')));
        return $pending_only ? current_user_can('publish_posts') : ($sensitive ? current_user_can('manage_options') : current_user_can('publish_posts'));
      },
    ),
  ));

  register_rest_route('big3africa/v1', '/opportunity-submissions', array(
    'methods' => 'POST',
    'callback' => function (WP_REST_Request $request) {
      $data = $request->get_json_params();
      $title = sanitize_text_field($data['title'] ?? '');
      $organiser = sanitize_text_field($data['organiser'] ?? '');
      $email = sanitize_email($data['submitterEmail'] ?? '');
      $source = esc_url_raw($data['sourceUrl'] ?? '');
      if (!$title || !$organiser || !$email || !is_email($email) || !$source || !wp_http_validate_url($source) || empty($data['consent'])) return new WP_Error('invalid_submission', 'Please provide the required details, a valid source link, and consent.', array('status' => 400));
      if (strlen(wp_json_encode($data)) > 12000) return new WP_Error('submission_too_large', 'Submission is too large.', array('status' => 413));
      $rate_key = 'b3a_opp_' . md5(sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
      if (get_transient($rate_key)) return new WP_Error('rate_limited', 'Please wait before sending another suggestion.', array('status' => 429));
      set_transient($rate_key, 1, 60);
      $post_id = wp_insert_post(array('post_type' => 'b3a_opportunity', 'post_title' => $title, 'post_status' => 'pending', 'post_author' => 0), true);
      if (is_wp_error($post_id)) return $post_id;
      $fields = array('organiser','type','sector','county','deadline','eligibility','summary','submitterName','submitterEmail');
      foreach ($fields as $field) update_post_meta($post_id, '_b3a_' . $field, sanitize_textarea_field($data[$field] ?? ''));
      update_post_meta($post_id, '_b3a_source_url', $source);
      update_post_meta($post_id, '_b3a_submitted_at', current_time('mysql', true));
      return new WP_REST_Response(array('status' => 'pending_review'), 201);
    },
    'permission_callback' => '__return_true',
  ));

  register_rest_route('big3africa/v1', '/submissions', array(
    'methods' => 'POST',
    'callback' => function (WP_REST_Request $request) {
      $params = $request->get_json_params();
      $article = $params['article'] ?? null;
      if (!is_array($article) || empty($article['title'])) {
        return new WP_Error('bad_request', 'A story title is required.', array('status' => 400));
      }
      $title = sanitize_text_field($article['title']);
      $content = isset($article['bodyParagraphs']) && is_array($article['bodyParagraphs'])
        ? wp_kses_post(implode("\n\n", array_map('sanitize_textarea_field', $article['bodyParagraphs']))) : '';
      $post_id = wp_insert_post(array(
        'post_title' => $title,
        'post_excerpt' => sanitize_textarea_field($article['deck'] ?? ''),
        'post_content' => $content,
        'post_status' => 'pending',
        'post_type' => 'post',
      ), true);
      if (is_wp_error($post_id)) return $post_id;
      $article['title'] = $title;
      $article['bodyParagraphs'] = isset($article['bodyParagraphs']) && is_array($article['bodyParagraphs'])
        ? array_map('sanitize_textarea_field', $article['bodyParagraphs']) : array();
      $pending = get_option('b3a_pending_articles', array());
      if (!is_array($pending)) $pending = array();
      array_unshift($pending, $article);
      update_option('b3a_pending_articles', array_slice($pending, 0, 100), false);
      return array('status' => 'pending_review', 'postId' => $post_id);
    },
    'permission_callback' => function () { return current_user_can('edit_posts'); },
  ));

  register_rest_route('big3africa/v1', '/rsvp', array(
    'methods' => 'POST',
    'callback' => function (WP_REST_Request $request) {
      $record = $request->get_param('record');
      if (!is_array($record) || strlen(wp_json_encode($record)) > 10000) {
        return new WP_Error('bad_request', 'Invalid registration.', array('status' => 400));
      }
      $email = sanitize_email($record['email'] ?? '');
      $event_id = sanitize_text_field($record['eventId'] ?? '');
      if (!$email || !is_email($email) || !$event_id) {
        return new WP_Error('bad_request', 'A valid email and event are required.', array('status' => 400));
      }
      $rate_key = 'b3a_rsvp_' . md5(sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
      if (get_transient($rate_key)) return new WP_Error('rate_limited', 'Please wait before registering again.', array('status' => 429));
      set_transient($rate_key, 1, 30);
      $record = map_deep($record, 'sanitize_text_field');
      $record['email'] = $email;
      $record['eventId'] = $event_id;
      $items = get_option('b3a_event_registrations', array());
      if (!is_array($items)) $items = array();
      array_unshift($items, $record);
      update_option('b3a_event_registrations', array_slice($items, 0, 500), false);
      return array('status' => 'registered');
    },
    'permission_callback' => '__return_true',
  ));
});

add_action('after_setup_theme', function () {
  add_theme_support('title-tag');
  add_theme_support('post-thumbnails');
});
