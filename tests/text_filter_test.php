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

/**
 * Tests for the lecture video links filter.
 *
 * @package   filter_lecturelinks
 * @copyright 2026 misko92
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(text_filter::class)]
final class text_filter_test extends \advanced_testcase {
    /** @var string A review link with an AP and a Chem A/B destination, as stored in question feedback. */
    private const LINK = '<p>🎬 <strong>Review in the lecture:</strong> ' .
        '<a href="/mod/lecturevideos/goto.php?cm=lv-ap-28&amp;v=28.01&amp;t=5m03s&amp;cm2=lv-chem-09&amp;v2=9.01&amp;t2=1m33s" ' .
        'target="_blank">Lecture 28.01 at 5:03</a> (atm, mmHg and torr)</p>';

    /**
     * Two courses, each with a lecture-video activity holding one titled video.
     *
     * @return array [ap course, chem course]
     */
    private function make_courses(): array {
        global $DB;
        $gen = $this->getDataGenerator();
        $lv = $gen->get_plugin_generator('mod_lecturevideos');
        $ap = $gen->create_course();
        $chem = $gen->create_course();
        $pairs = [[$ap, 'lv-ap-28', '28.01 Pressure'], [$chem, 'lv-chem-09', '9.01 Converting Pressure Units']];
        foreach ($pairs as [$course, $idn, $title]) {
            $playlist = $lv->create_instance(['course' => $course->id, 'idnumber' => $idn]);
            $video = $lv->create_video($playlist->id, 600);
            $DB->set_field('lecturevideos_videos', 'title', $title, ['id' => $video->id]);
        }
        return [$ap, $chem];
    }

    /**
     * Run the filter as the current user.
     *
     * @param string $text
     * @return string
     */
    private function filter(string $text): string {
        text_filter::reset_cache();
        return (new text_filter(\context_system::instance(), []))->filter($text);
    }

    public function test_each_student_sees_their_own_video(): void {
        $this->resetAfterTest();
        [$ap, $chem] = $this->make_courses();

        $this->setUser($this->getDataGenerator()->create_and_enrol($chem, 'student'));
        $out = $this->filter(self::LINK);
        $this->assertStringContainsString('>9.01 Converting Pressure Units at 1:33</a> (atm, mmHg and torr)', $out);
        $this->assertStringContainsString('href="/mod/lecturevideos/goto.php?cm=lv-ap-28&amp;v=28.01', $out);

        $this->setUser($this->getDataGenerator()->create_and_enrol($ap, 'student'));
        $this->assertStringContainsString('>28.01 Pressure at 5:03</a>', $this->filter(self::LINK));

        $this->setAdminUser();
        $this->assertStringContainsString('>Related videos (choose one)</a>', $this->filter(self::LINK));
    }

    public function test_spanish_label_and_links_left_alone(): void {
        $this->resetAfterTest();
        [, $chem] = $this->make_courses();
        $this->setUser($this->getDataGenerator()->create_and_enrol($chem, 'student'));
        // The Spanish label (language packs are not installed in test sites, so read the plugin's own string files).
        foreach (['es', 'es_mx'] as $lang) {
            $string = [];
            include(__DIR__ . "/../lang/$lang/filter_lecturelinks.php");
            $this->assertSame('{$a->title}, minuto {$a->time}', $string['videoat']);
        }

        // One destination only, or nothing the user can open: unchanged.
        $single = '<a href="/mod/lecturevideos/goto.php?cm=lv-ap-28&amp;v=28.01">Lecture 28.01</a>';
        $this->assertSame($single, $this->filter($single));
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertSame(self::LINK, $this->filter(self::LINK));
        $this->assertSame('No links here.', $this->filter('No links here.'));
    }

    public function test_times(): void {
        $this->assertSame(303, text_filter::seconds('5m03s'));
        $this->assertSame(307, text_filter::seconds('307'));
        $this->assertSame(307, text_filter::seconds('5:07'));
        $this->assertSame(3723, text_filter::seconds('1:02:03'));
        $this->assertSame(0, text_filter::seconds('0m00s'));
        $this->assertSame(0, text_filter::seconds('junk'));
        $this->assertSame('Intro', text_filter::describe('Intro', '0m00s'));
    }
}
