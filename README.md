# Lecture video links filter (filter_lecturelinks)

Companion to [mod_lecturevideos](https://github.com/misko92/moodle-mod_lecturevideos).

A "review in the lecture" link in a quiz question can name several destinations, for example
the same moment in the AP lecture and in the Chem A/B topic video:

    /mod/lecturevideos/goto.php?cm=lv-ap-28&v=28.01&t=5m03s&cm2=lv-chem-09&v2=9.01&t2=1m33s

`goto.php` already sends each student to the first video they can open. This filter makes the
link's **text** match: an AP student reads "28.01 Pressure at 5:03", a Chem A/B student reads
"9.01 Converting Pressure Units at 1:33" (in Spanish: "…, minuto 1:33"), and a teacher who can
open both sees "Related videos (choose one)" and gets a chooser. Links with a single destination, or none the viewer can open, are left alone.
The stored text is not changed.

Requires mod_lecturevideos 0.5.1 or later. Enable it under Site administration → Plugins →
Filters → Manage filters.

## License

GNU GPL v3 or later.
