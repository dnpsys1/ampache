<?php
/* vim:set softtabstop=4 shiftwidth=4 expandtab: */
/**
 *
 * LICENSE: GNU General Public License, version 2 (GPLv2)
 * Copyright 2001 - 2013 Ampache.org
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License v2
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 */

/**
 * Sub-Ajax page, requires AJAX_INCLUDE
 */
if (!defined('AJAX_INCLUDE')) { exit; }

switch ($_REQUEST['action']) {
    case 'random_albums':
        $albums = Album::get_random(6, true);
        if (count($albums) AND is_array($albums)) {
            ob_start();
            require_once AmpConfig::get('prefix') . '/templates/show_random_albums.inc.php';
            $results['random_selection'] = ob_get_clean();
        } else {
            $results['random_selection'] = '<!-- None found -->';
        }
    break;
    case 'artist_info':
        if (AmpConfig::get('lastfm_api_key') && (isset($_REQUEST['artist']) || isset($_REQUEST['fullname']))) {
            if ($_REQUEST['artist']) {
                $artist = new Artist($_REQUEST['artist']);
                $artist->format();
                $biography = Recommendation::get_artist_info($artist->id);
            } else {
                $biography = Recommendation::get_artist_info(null, rawurldecode($_REQUEST['fullname']));
            }
            ob_start();
            require_once AmpConfig::get('prefix') . '/templates/show_artist_info.inc.php';
            $results['artist_biography'] = ob_get_clean();
        }
    break;
    case 'similar_artist':
        if (AmpConfig::get('show_similar') && isset($_REQUEST['artist'])) {
            $artist = new Artist($_REQUEST['artist']);
            $artist->format();
            $object_ids = array();
            $missing_objects = array();
            if ($similars = Recommendation::get_artists_like($artist->id, 10, !AmpConfig::get('wanted'))) {
                foreach ($similars as $similar) {
                    if ($similar['id']) {
                        $object_ids[] = $similar['id'];
                    } else {
                        $missing_objects[] = $similar;
                    }
                }
            }
            ob_start();
            require_once AmpConfig::get('prefix') . '/templates/show_recommended_artists.inc.php';
            $results['similar_artist'] = ob_get_clean();
        }
    break;
    case 'similar_now_playing':
        $media_id = $_REQUEST['media_id'];
        if (AmpConfig::get('show_similar') && isset($media_id) && isset($_REQUEST['media_artist'])) {
            $artists = Recommendation::get_artists_like($_REQUEST['media_artist'], 3, false);
            $songs = Recommendation::get_songs_like($media_id, 3);
            ob_start();
            require_once AmpConfig::get('prefix') . '/templates/show_now_playing_similar.inc.php';
            $results['similar_items_' . $media_id] = ob_get_clean();
        }
    break;
    case 'concerts':
        if (AmpConfig::get('show_concerts') && isset($_REQUEST['artist'])) {
            $artist = new Artist($_REQUEST['artist']);
            $artist->format();
            if ($artist->id) {
                $up_concerts = Artist_Event::get_upcoming_events($artist);
                $past_concerts = Artist_Event::get_past_events($artist);
                $concerts = array();
                if ($up_concerts) {
                    foreach ($up_concerts->children() as $item) {
                        if ($item->getName() == 'event') {
                            $concerts[] = $item;
                        }
                    }
                }
                if ($past_concerts) {
                    foreach ($past_concerts->children() as $item) {
                        if ($item->getName() == 'event') {
                            $concerts[] = $item;
                        }
                    }
                }
            }
            ob_start();
            require_once AmpConfig::get('prefix') . '/templates/show_concerts.inc.php';
            $results['concerts'] = ob_get_clean();
        }
    break;
    case 'wanted_missing_albums':
        if (AmpConfig::get('wanted') && (isset($_REQUEST['artist']) || isset($_REQUEST['artist_mbid']))) {
            if (isset($_REQUEST['artist'])) {
                $artist = new Artist($_REQUEST['artist']);
                $artist->format();
                if ($artist->mbid) {
                    $walbums = Wanted::get_missing_albums($artist);
                } else {
                    debug_event('wanted', 'Cannot get missing albums: MusicBrainz ID required.', '5');
                }
            } else {
                $walbums = Wanted::get_missing_albums(null, $_REQUEST['artist_mbid']);
            }

            ob_start();
            require_once AmpConfig::get('prefix') . '/templates/show_missing_albums.inc.php';
            $results['missing_albums'] = ob_get_clean();
        }
    break;
    case 'add_wanted':
        if (AmpConfig::get('wanted') && isset($_REQUEST['mbid'])) {
            $mbid = $_REQUEST['mbid'];
            if (empty($_REQUEST['artist'])) {
                $artist_mbid = $_REQUEST['artist_mbid'];
            } else {
                $artist = $_REQUEST['artist'];
                $aobj = new Artist($artist);
                $artist_mbid = $aobj->mbid;
            }
            $name = $_REQUEST['name'];
            $year = $_REQUEST['year'];

            if (!Wanted::has_wanted($mbid)) {
                Wanted::add_wanted($mbid, $artist, $artist_mbid, $name, $year);
                ob_start();
                $walbum = new Wanted(Wanted::get_wanted($mbid));
                $walbum->show_action_buttons();
                $results['wanted_action_' . $mbid] = ob_get_clean();
            } else {
                debug_event('wanted', 'Already wanted, skipped.', '5');
            }
        }
    break;
    case 'remove_wanted':
        if (AmpConfig::get('wanted') && isset($_REQUEST['mbid'])) {
            $mbid = $_REQUEST['mbid'];

            $walbum = new Wanted(Wanted::get_wanted($mbid));
            Wanted::delete_wanted($mbid);
            ob_start();
            $walbum->accepted = false;
            $walbum->id = 0;
            $walbum->show_action_buttons();
            $results['wanted_action_' . $mbid] = ob_get_clean();
        }
    break;
    case 'accept_wanted':
        if (AmpConfig::get('wanted') && isset($_REQUEST['mbid'])) {
            $mbid = $_REQUEST['mbid'];

            $walbum = new Wanted(Wanted::get_wanted($mbid));
            $walbum->accept();
            ob_start();
            $walbum->show_action_buttons();
            $results['wanted_action_' . $mbid] = ob_get_clean();
        }
    break;
    case 'reloadnp':
        ob_start();
        show_now_playing();
        $results['now_playing'] = ob_get_clean();
        ob_start();
        $data = Song::get_recently_played();
        Song::build_cache(array_keys($data));
        require_once AmpConfig::get('prefix') . '/templates/show_recently_played.inc.php';
        $results['recently_played'] = ob_get_clean();
    break;
    case 'sidebar':
        switch ($_REQUEST['button']) {
            case 'home':
            case 'modules':
            case 'localplay':
            case 'player':
            case 'preferences':
                $button = $_REQUEST['button'];
            break;
            case 'admin':
                if (Access::check('interface','100')) { $button = $_REQUEST['button']; } else { exit; }
            break;
            default:
                exit;
            break;
        } // end switch on button

        ob_start();
        $_SESSION['state']['sidebar_tab'] = $button;
        require_once AmpConfig::get('prefix') . '/templates/sidebar.inc.php';
        $results['sidebar'] = ob_get_contents();
        ob_end_clean();
    break;
    case 'shoutbox':
        ob_start();
        $since = $_REQUEST['since'];
        if ($since) {
            $shouts = Shoutbox::get_shouts_since(intval($since / 1000));
            echo "<script language='javascript' type='text/javascript'>";
            foreach ($shouts as $id) {
                $shout = new Shoutbox($id);
                echo "noty({text: '" . addslashes($shout->get_display()) . "',
                    type: 'alert', layout: 'bottomRight',
                    template: '<div class=\"noty_message noty_ampache\"><span class=\"noty_text noty_ampache\"></span><div class=\"noty_close noty_ampache\"></div></div>',
                });";
            }
            echo "</script>";
        }
        $results['live_shoutbox'] = ob_get_clean();
    break;
    case 'start_channel':
        if (Access::check('interface','75')) {
            ob_start();
            $channel = new Channel($_REQUEST['id']);
            if ($channel->id) {
                if ($channel->check_channel()) {
                    $channel->stop_channel();
                }
                $channel->start_channel();
                echo $channel->get_channel_state();
            }
            $results['channel_state_' . $_REQUEST['id']] = ob_get_clean();
        }
    break;
    case 'stop_channel':
        if (Access::check('interface','75')) {
            ob_start();
            $channel = new Channel($_REQUEST['id']);
            if ($channel->id) {
                $channel->stop_channel();
                echo $channel->get_channel_state();
            }
            $results['channel_state_' . $_REQUEST['id']] = ob_get_clean();
        }
    break;
    case 'slideshow':
        ob_start();
        $fsname = 'fslider_' . time();
        echo "<div id='" . $fsname . "'>";
        $images = Slideshow::get_current_slideshow();
        foreach ($images as $image) {
            echo "<a href='#'><img src='" . $image['url'] . "' alt='' /></a>";
        }
        echo "</div>";
        $results['fslider'] = ob_get_clean();
        ob_start();
        echo "<script language='javascript' type='text/javascript'>";
        echo "$('#" . $fsname . "').rhinoslider({
            effect: 'shuffle',
            showTime: 15000,
            randomOrder: true,
            controlsMousewheel: false,
            controlsKeyboard: false,
            controlsPrevNext: false,
            controlsPlayPause: false,
            autoPlay: true,
            pauseOnHover: false,
            showControls: 'never'
        });";
        echo "</script>";
        $results['fslider_script'] = ob_get_clean();
    break;
    default:
        $results['rfc3514'] = '0x1';
    break;
} // switch on action;

// We always do this
echo xoutput_from_array($results);
