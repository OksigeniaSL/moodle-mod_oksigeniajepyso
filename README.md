# Exercise routine (Oksigenia Jepyso)

[Leer en español](README.es.md)

An activity for Moodle. The teacher sets an exercise routine; each student does it on a phone or a computer,
following a 3D figure that keeps the tempo, the side and the sets the routine says. Afterwards the student notes down
the repetitions done and the discomfort felt (0 to 10). Each session is saved to Moodle, counts towards completion and
grade, and the teacher sees the follow-up of each student.

It is made for any school or training centre: physical education classes, and schools of physiotherapy, coaching or
sports science.

## Requirements

- Moodle 4.5 to 5.3.
- No external service: the player is served by your own site, and nothing leaves it.

## Install

1. Copy the plugin into `mod/oksigeniajepyso` (in Moodle 5.1 and later, `public/mod/oksigeniajepyso`), or install the
   ZIP from *Site administration > Plugins > Install plugins*.
2. Visit *Site administration > Notifications*, or run `admin/cli/upgrade.php`.

## How it works

- **The routine.** In the settings of the activity, paste the link of a routine made with Jepyso
  (`https://app.jepyso.com/#r=…`) or its code, or press «Build the routine» to make one: it comes back to the form by
  itself. Set the number of sessions each student is expected to do.
- **The sessions.** «Start» opens the player (`reproductor/`, in the plugin). It follows the tempo, side and sets of
  each exercise; at the end the student notes down what was done. The player saves through the web services
  `mod_oksigeniajepyso_save_sessions` and `mod_oksigeniajepyso_get_sessions`.
- **Completion and grade.** Completion rule «Do the expected sessions». With a grade in points and a number of
  sessions, the grade is the maximum grade times the share of sessions done (never more than the maximum); otherwise
  the teacher grades in the gradebook.
- **Follow-up.** Teachers see, for each student, the sessions done, the last one, the days above the acceptable
  discomfort, the exercises found hard or not done and the notes, and can open the days of each student. Separate and
  visible groups are respected. Teachers can try the routine; what they do is not saved.
- **The exercises.** The plugin comes with a free sample of the exercise library. The full library is served by
  Oksigenia with a key per site, for a fee; this version does not include it yet. The exercises of a routine that are
  not in the sample are shown to the teacher.

## In the Moodle app

The activity works in the official Moodle app, with nothing to configure. Its page shows the description, the
exercises with their dose and the progress; «Start» opens the player in the browser of the app, with the session
already open, and the sessions are saved as on the web.

## Privacy

The sessions are kept in the site (`oksigeniajepyso_session`), declared in the Privacy API, exported and deleted with
it. Backup and restore include them with user data; the course reset can delete them.

## The player

`reproductor/` is not edited here: it is built from the Jepyso repository with
`tools/construir-moodle.sh <this folder>`, which brings the free sample of twelve exercises and `catalogo.json`.

## Licence

GNU GPL v3 or later (`LICENSE`). Copyright 2026 Oksigenia SL. The player in `reproductor/` is the Jepyso web app
(https://github.com/OksigeniaSL/jepyso), GPL v3 or later, with three.js (MIT); its 3D figures are content under their
own terms (see `thirdpartylibs.xml`).
