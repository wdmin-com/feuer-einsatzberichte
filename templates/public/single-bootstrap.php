<?php
if (!defined('ABSPATH')) {
    exit;
}

global $post;

$context = FEU_Einsatz_Template_Helpers::get_single_context($post);

if (empty($context) || empty($context['post']) || !($context['post'] instanceof WP_Post)) {
    return;
}

$post = $context['post'];
$report = isset($context['report']) && is_array($context['report']) ? $context['report'] : [];
$map = isset($context['map']) && is_array($context['map']) ? $context['map'] : [];
$gallery = isset($context['gallery']) && is_array($context['gallery']) ? $context['gallery'] : [];
$comments = isset($context['comments']) && is_array($context['comments']) ? $context['comments'] : [];

$strasse = isset($report['street']) ? $report['street'] : '';
$plz = isset($report['postcode']) ? $report['postcode'] : '';
$stadt = isset($report['city']) ? $report['city'] : '';
$datum = isset($report['date_raw']) ? $report['date_raw'] : '';
$uhrzeit = isset($report['time']) ? $report['time'] : '';
$deepest_category = !empty($report['category']) && is_array($report['category'])
    ? (object) $report['category']
    : null;
$organization_details = isset($report['organizations']) && is_array($report['organizations']) ? $report['organizations'] : [];
$teilnehmer_namen = isset($report['participant_names']) && is_array($report['participant_names']) ? $report['participant_names'] : [];
$teilnehmer_anzahl = isset($report['participant_count']) ? (int) $report['participant_count'] : count($teilnehmer_namen);

$map_height = isset($map['height']) ? (int) $map['height'] : 500;
$map_canvas_id = isset($map['canvas_id']) ? $map['canvas_id'] : '';
$map_config = isset($map['config']) && is_array($map['config']) ? $map['config'] : [];
$has_live_map_data = !empty($map['has_live_data']);
$local_map_preview_markup = isset($map['preview_markup']) ? $map['preview_markup'] : '';
$map_fallback_image_url = isset($map['fallback_image_url']) ? $map['fallback_image_url'] : '';
$map_fallback_alt = isset($map['fallback_alt']) ? $map['fallback_alt'] : '';
$latitude = isset($map['latitude']) ? $map['latitude'] : null;
$longitude = isset($map['longitude']) ? $map['longitude'] : null;

$gallery_items = isset($gallery['items']) && is_array($gallery['items']) ? $gallery['items'] : [];
$photo_watermark_enabled = !empty($gallery['watermark_enabled']);
$photo_watermark_text = isset($gallery['watermark_text']) ? $gallery['watermark_text'] : '';

$report_comments = isset($comments['items']) && is_array($comments['items']) ? $comments['items'] : [];
$report_comments_count = isset($comments['count']) ? (int) $comments['count'] : count($report_comments);
$report_comments_title = isset($comments['title']) ? $comments['title'] : '';
$comments_enabled = !empty($comments['enabled']);
