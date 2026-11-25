<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace filter_lecturelinks;

use mod_lecturevideos\local\review_link;

/**
 * Labels each "review in the lecture" link with the video this viewer will actually get.
 *
 * A link to mod/lecturevideos/goto.php can name several destinations (cm/v/t, cm2/v2/t2, cm3/v3/t3), for example
 * the same moment in the AP lecture and in the Chem A/B topic video, so one question works in both courses.
 * goto.php sends each student to the first video they can open; this filter makes the link's text match:
 * the video title and start time of the destination the viewer can open (or "Related videos (choose one)" for a
 * teacher who can open several). Links with a single destination,
 * and links none of whose videos the viewer can open, are left as they are.
 *
 * @package    filter_lecturelinks
 * @copyright  2026 misko92
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class text_filter extends \core_filters\text_filter {
    /** @var string An <a> to goto.php with its query string, and the link text. */
    private const PATTERN = '~(<a\b[^>]*\bhref="[^"]*/mod/lecturevideos/goto\.php\?([^"]*)"[^>]*>)(.*?)(</a>)~is';

    /** @var array Labels already worked out in this request, by query string (and language). */
    private static array $cache = [];

    #[\Override]
    public function filter($text, array $options = []) {
        if (!is_string($text) || strpos($text, 'goto.php?') === false || !preg_match('/[?&](?:amp;)?cm2=/', $text)) {
            return $text;
        }
        return preg_replace_callback(self::PATTERN, [$this, 'relabel'], $text);
    }

    /**
     * Replace one link's text.
     *
     * @param array $m regex match: 1 opening tag, 2 query string, 3 link text, 4 closing tag
     * @return string
     */
    private function relabel(array $m): string {
        $label = self::label_for($m[2]);
        return $label === null ? $m[0] : $m[1] . $label . $m[4];
    }

    /**
     * The link text for a goto.php query string, for the current user and language.
     *
     * @param string $query the query string as it appears in the href (may contain &amp;)
     * @return string|null HTML, or null to leave the link alone
     */
    public static function label_for(string $query): ?string {
        global $USER;
        $key = ($USER->id ?? 0) . '|' . current_language() . '|' . $query;
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }
        parse_str(html_entity_decode($query, ENT_QUOTES | ENT_HTML5), $params);
        $targets = [];
        foreach (['', '2', '3'] as $n) {
            $idnumber = clean_param($params['cm' . $n] ?? '', PARAM_ALPHANUMEXT);
            if ($idnumber !== '') {
                $targets[] = [$idnumber, clean_param($params['v' . $n] ?? '', PARAM_TEXT),
                    clean_param($params['t' . $n] ?? '', PARAM_RAW_TRIMMED)];
            }
        }
        $label = null;
        if (count($targets) > 1) {
            $parts = [];
            foreach (review_link::resolve_all($targets) as $choice) {
                $parts[] = self::describe($choice['video'] !== '' ? $choice['video'] : $choice['activity'], $choice['start']);
            }
            $parts = array_unique($parts);
            if (count($parts) === 1) {
                $label = reset($parts);
            } else if ($parts) {
                // Several videos (a teacher who sees both courses): goto.php shows a chooser.
                $label = get_string('choosevideo', 'filter_lecturelinks');
            }
        }
        return self::$cache[$key] = $label;
    }

    /**
     * "9.01 Converting Pressure Units at 1:33" (or without the time when it starts at the beginning).
     *
     * @param string $title the video title, already formatted
     * @param string $start the start time as in the link: seconds, m:ss, h:mm:ss or 5m03s
     * @return string
     */
    public static function describe(string $title, string $start): string {
        $seconds = self::seconds($start);
        if ($seconds < 1) {
            return $title;
        }
        $time = $seconds >= 3600 ? gmdate('G:i:s', $seconds) : ((int)floor($seconds / 60)) . ':' . sprintf('%02d', $seconds % 60);
        return get_string('videoat', 'filter_lecturelinks', (object)['title' => $title, 'time' => $time]);
    }

    /**
     * Seconds from a start time in any of the forms the player accepts.
     *
     * @param string $start
     * @return int
     */
    public static function seconds(string $start): int {
        $start = trim($start);
        if (preg_match('/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s?)?$/', $start, $m) && $start !== '') {
            return (int)($m[1] ?? 0) * 3600 + (int)($m[2] ?? 0) * 60 + (int)($m[3] ?? 0);
        }
        if (preg_match('/^(\d+):(\d{1,2})(?::(\d{1,2}))?$/', $start, $m)) {
            return isset($m[3]) ? (int)$m[1] * 3600 + (int)$m[2] * 60 + (int)$m[3] : (int)$m[1] * 60 + (int)$m[2];
        }
        return 0;
    }

    /**
     * Forget the labels worked out so far (for tests, which switch users within one request).
     */
    public static function reset_cache(): void {
        self::$cache = [];
    }
}
