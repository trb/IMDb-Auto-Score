<?php
/*
Plugin Name: Imdb Auto Score
Plugin URI: https://github.com/trb/IMDb-Auto-Score
Description: Displays the imdb score for tagged items in a post, and
    links to the imdb page. Example: [imdb]The Walking Dead[/imdb]
Version: 0.1
Author: Thomas Rubbert
Author URI: https://github.com/trb
License: MIT
*/


function _ias_imdb_query($name, $year = '') {
    $url = 'http://www.imdbapi.com/?t='
        . urlencode($name);

    if (!empty($year)) {
        $url.= '&y=' . urlencode($year);
    }

    return json_decode(file_get_contents($url));
}


function _ias_get_names($string) {
    $matches = array();
    preg_match_all('/\[imdb\](.*?)\[\/imdb\]/i', $string, $matches);

    if (empty($matches[1])) {
        return array();
    }

    return $matches[1];
}


function _ias_get_year($string) {
    $year = array();
    preg_match('/\((\d{4})\)/i', $string, $year);

    if (empty($year)) {
        $year = '';
    } else {
        $year = $year[1];
    }

    return $year;
}


function _ias_make_tag($string) {
    return '[imdb]' . $string . '[/imdb]';
}


function _ias_make_link($name, $id, $rating, $plot = '') {
    return '<a href="http://www.imdb.com/title/'
        . $id . '" target="_blank" '
        .' title="' . $plot . '">' . $name . ' <span class="ias_score">'
        . '(' . $rating . ')</span></a>';
}


function _ias_get_rating($info) {
    return ($info->Rating == 'N/A')
            ? 'no votes'
            : $info->Rating;
}


function _ias_get_id($info) {
    return $info->ID;
}


function _ias_get_plot($info) {
    return $info->Plot;
}


function ias_parse_tags($content) {
    $names = _ias_get_names($content);
    $needles = array();
    $replacements = array();
    foreach ($names as $name) {
        $info = _ias_imdb_query($name, _ias_get_year($name));

        if (!empty($info) && ($info->Response == 'True')) {
            $link = _ias_make_link($name, _ias_get_id($info),
                        _ias_get_rating($info), _ias_get_plot($info));

            $needles[] = _ias_make_tag($name);
            $replacements[] = $link;
        } else {
            $needles[] = _ias_make_tag($name);
            $replacements[] = $name;
        }
    }

    return array(
        'needles' => $needles,
        'replacements' => $replacements
    );
}


function ias_store_tags($post_id) {
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $safe = base64_encode(json_encode(ias_parse_tags($_REQUEST['content'])));

    update_post_meta($post_id, 'ias_tags', $safe);
}


function ias_replace_tags($content) {
    global $id;

    $safe = get_post_meta($id, 'ias_tags');

    if (empty($safe)) {
        return $content;
    }

    $tags = json_decode(base64_decode($safe[0]), true);

    return str_replace($tags['needles'], $tags['replacements'], $content);
}


add_action('save_post', 'ias_store_tags');
add_filter('the_content', 'ias_replace_tags');
