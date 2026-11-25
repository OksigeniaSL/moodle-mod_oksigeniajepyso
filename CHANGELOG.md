# Changelog

## 0.2.0 (2026-11-25)

After the review of the Moodle Marketplace (issues #1, #2 and #3):

- The player that comes with the plugin has only what a course uses. The people, plans, licences and cloud storage of
  the web app, with their help, their texts and their links, are not in it: no screen leads out of the site, and the
  content policy of the page lets it load and call nothing outside it. The QR library is gone too. A test looks for
  any of it in every file of the player.
- The player and the builder are now pages of Moodle (`player.php`, `builder.php`), with their login and capability
  checks, painted from a template (`templates/player.mustache`, `classes/output/player_page.php`). The static
  `reproductor/index.html` is gone, and the builder opens only for who can set up the activity.
- `sesion.php` is gone: the page says who is there, and the sessions are read and saved through the external functions
  `mod_oksigeniajepyso_get_sessions` and `mod_oksigeniajepyso_save_sessions`. An expired session key is asked again,
  so nothing noted is lost.
- The script of the activity form is an AMD module (`amd/src/form.js`), and the HTML that was in PHP is in templates.

And besides:

- Circuits: a routine can go in rounds, with every exercise once in each, a short rest between exercises and a longer
  one between rounds. The builder offers it, and the activity, the form and the app show it.
- The help of the builder answers what a teacher asks, and its steps say what they are in an activity.
- A browser never mixes the files of two versions of the player after an update.

## 0.1.12 (2026-11-20)

- The figures wear light sportswear, and the working muscles are drawn over it as in an anatomy plate: their shape
  with its outline, their heads and their fibres, never mixed with the clothes. The drawing breathes with the effort,
  from the outline at rest to the whole muscle while holding.
- The player follows the app's latest: a light and dark switch, kept on the device, and thin scrollbars in the
  player's colours.

## 0.1.11 (2026-11-16)

- Guests can view the activity, as with a page: on a course open to guests they see the routine and try it as a
  preview, and nothing is saved. Before, they found the activity "currently hidden". New sites get it; on a site that
  already has the plugin, give `mod/oksigeniajepyso:view` to the guest role if you want it.

## 0.1.10 (2026-11-16)

- `db/upgrade.php`, with no steps yet, as the Moodle plugins directory asks of every activity.

## 0.1.9 (2026-11-16)

- "Build the routine" on an activity that already has a routine opens the builder with that routine, to change it,
  instead of an empty builder or the last routine built in that browser.

## 0.1.8 (2026-11-16)

- Beta: the activity has run on development and test sites with the automated tests green on Moodle 4.5, 5.3 and the
  development branch. First version for the Moodle plugins directory.

## 0.1.7 (2026-11-16)

- The routine builder, before any exercise is chosen, shows the figure standing at rest. With the free sample it
  showed the bare body, headless and cut, from a default camera.
- English texts quote with “ ” instead of « ».
- `cli/demo.php --lang=en` makes a sample course in English, with its own accounts, beside the Spanish one; running it
  again no longer sends the course welcome message again.

## 0.1.6 (2026-11-16)

- The exercises of the sample no longer sink into the mat or the floor: a leg, a hand or a knee went through it in
  some clips (bird dog, cat-camel, clamshell, knee plank, knee to chest…). Made again with the fixed recipe.

## 0.1.5 (2026-11-16)

- The player in English as well as Spanish, with its exercises and its recorded voice (Mira and Altair in English). It
  follows each user's language in Moodle, and the student can switch it beside «Accessibility».
- The activity's pages show the exercises' names, positions and materials in the user's language.
- The mouse wheel over the figure scrolls the page; a trackpad pinch still zooms.

## 0.1.4 (2026-11-15)

- The player guides the sets with a recorded voice: a woman's or a man's, as each student chooses, or none. It speaks
  in the Moodle app too, which has no voices of its own.
- Inside the Moodle app, no «Back to the course» in the player: the window's × is the way back, and the player says so.
- The content of the player (the figures and stills of the sample, the plaster headers, the icon, the body map and the
  voice) is under CC BY-SA 4.0, compatible with the GPL, with the sheets the twelve movements are built from.
- The description for the Moodle Marketplace.

## 0.1.3 (2026-11-15)

- On a dark page, the icon of the activity takes the light of the plaster: in graphite it hardly showed.
- The readme in English and Spanish, with the figures in motion, the pages of the activity and the Moodle app; its
  pictures stay out of the plugin's package (`.gitattributes`).

## 0.1.2 (2026-11-15)

- In the Moodle app, the page of the activity asks for itself again when the routine's in-app browser closes, so
  the session just saved shows in the progress without a pull to refresh. It is the only script the page brings to
  the app, fixed and the same on every site.

## 0.1.1 (2026-11-15)

- The purpose of the activity is «other»: its icon keeps the graphite of Jepyso instead of the theme's colour for
  assessment. It still counts for completion and grade.
- The example routine of `cli/demo.php` is signed by the teacher of the example course.
- The pages look like Jepyso, not like the theme, with any theme, in light and dark: its plaster and graphite, its
  graphite button and Red Hat type, on a plaster panel with registration marks:
  - the activity: the plaster cast, the sessions as marks, the size of the routine («3 exercises · about 9 minutes»),
    how it is done with the acceptable discomfort on its ruler, and the stills of the exercises with their band;
  - the follow-up: what there is to look at in each student, in words and only when there is something, the days above
    the acceptable discomfort first; a card for each student on a phone;
  - the file of a student: the discomfort week by week against the acceptable level, as the app shows it, and each day
    with what was done in each set against what was asked;
  - the form: what the routine has, under its code;
  - the Moodle app: the cast, a progress bar, the size of the routine and the stills of the exercises, in the
    colours of the app, as Aty asks of every plugin.
- Red Hat Display, Text and Mono come with the plugin (OFL, `fonts/`), under names of their own.

## 0.1.0 (2026-11-15)

First version.

- The activity: the teacher sets an exercise routine, from a link of the Jepyso app or with the builder of the player,
  and the sessions each student is expected to do.
- Students do the routine with the player of Jepyso, served by the site itself; each session is saved to Moodle.
- Completion rule «Do the expected sessions», and an automatic grade from the share of sessions done.
- Follow-up for teachers: sessions done, last session, days above the acceptable discomfort, exercises found hard or
  not done, notes; and the days of each student. Group modes respected.
- In the Moodle app: the page of the activity, and the player in the browser of the app with the session.
- Privacy API, backup and restore with the sessions, course reset.
