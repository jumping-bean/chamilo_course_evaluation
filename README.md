# Course evaluation plugin for Chamilo 3

This plugin was written with the help of an AI coding assistant. Read the code and try it on a test site before you install it in production.

Teachers use it to collect one end-of-course evaluation from each learner. The questions cover the instructor, the content, the delivery, and the organization, and every submission ends with a comment on how to improve the course. Reports turn the scores into averages by category, question, coach, course, and period.

Chamilo already includes the Survey tool. That tool stays the right place for general questionnaires. This plugin is the end-of-course evaluation. It stays off until a teacher copies a template into the course, and the teacher enables it again for each session.

## How it works

A platform admin keeps one or more global questionnaire templates. Copying a template into a course enables the evaluation for that course. Until a teacher does that, the course has no evaluation, and none of its sessions can have one either. The teacher can then customise the questions for that course.

The teacher enables the evaluation separately for each session with **Use the course evaluation**. That copies the current course questions, the anonymous setting, and whether it is added to the assessment, and it takes the open and close dates from the session. The teacher can customise that session copy on its own. A later change to the course evaluation, or to the global template, leaves the session evaluation as it was. The self-paced question that asks which instructors the learner consulted is left off the session copy, because the session already has a coach.

A course opened with no session is the self-paced evaluation. It has no dates and stays available. A session evaluation accepts answers only between its start and end. A teacher can extend the end date for one learner.

Each learner submits once. After that, the evaluation stays closed for them and they can review their own answers. A learner who has started and not yet submitted can continue. Answers are stored when they submit.

Question types are a 1–5 scale, yes/no, an open comment, and, on the self-paced evaluation only, a list of instructors the learner consulted. Categories are Instructor, Content, Delivery, Organization, and Improvement. The student form is one step per category, and the improvement step always includes the course-improvement comment.

A session evaluation locks its questions after the first submission. The self-paced evaluation stays editable. Adding, removing, replacing, or restoring questions saves a new version. The teacher can return a self-paced evaluation to an earlier version. Session scores count toward the session coach. Self-paced scores count toward the instructors the learner named.

The evaluation does not create an assessment. The teacher creates that in Assessments, separately for the course and for each session. That setup is independent of this plugin, and it can be done before the evaluation is enabled or afterwards. Until an assessment exists, **Add to assessment** stays disabled. Learners can still submit. Once an assessment exists, the teacher edits the evaluation and turns the option on. Learners who already submitted are marked complete at that point. The option can be turned off later.

A course evaluation uses the course assessment. A session evaluation uses that session's assessment when one exists, and otherwise the course assessment. The plugin adds a zero-weight item, so the grade does not change. Attendance and any other requirements stay on the assessment and are set there, not in this plugin. The teacher also decides there whether a certificate is issued. An assessment can keep results and never issue one. If certificates are turned on, Chamilo waits until the learner submits.

Anonymous evaluations hide the learner's name on the response view. The teacher still sees who has submitted and who has not. The submission time is hidden in that list so it cannot be matched to an anonymous answer. The learner can still open their own answers.

Deleting a course removes its evaluations, including the ones for its sessions. Deleting a session removes that session's evaluation. Global templates stay.

## How this differs from Survey

Survey is Chamilo's general questionnaire. A course can hold many surveys. Each survey has its own title, questions, dates, and invitations. Question types include multiple choice, open answers, scores, percentages, and page breaks. A teacher can invite people to answer, and can link a survey into the assessments.

This plugin does not do that job. It keeps one evaluation on the course and one on each session, built from a shared template, with the five categories above. The reports compare coaches and courses. When the teacher adds the evaluation to an assessment that issues certificates, the certificate waits until the learner submits. Use Survey for any other questionnaire.

## What each role does

- **Platform admin** (`admin.php`): create global templates, add or edit questions, and run reports across courses, instructors, and periods. Export CSV from the report page.
- **Session administrator** and **HR manager**: open the course report and the instructor report, and export CSV. They do not edit templates or questions. A session administrator finds **Course report** under Administration → Sessions management. An HR manager opens `/plugin/CourseEvaluation/admin.php?action=courses`.
- **Teacher or session coach** (course tool): copy a global template into the current course, add questions, and set the evaluation period for the current session. With no session, the same screen opens the self-paced evaluation. The instructor is taken from the session course coach, then the session general coach, then a course teacher. Reports on the course tool stay limited to that course.
- **Learner**: starts the open evaluation, continues it if they left, and reviews it after submitting.

## Install

Copy this directory to `public/plugin/CourseEvaluation` inside a Chamilo 3 installation.

From the Chamilo root:

```bash
composer dump-autoload
php bin/console cache:clear
```

Then install and enable **Course evaluation** in Administration → Plugins. Installing creates the tables, adds the course tool to existing courses, and seeds a standard global template. Courses created later get the tool from the plugin subscriber, which is only registered after the commands above.

`composer dump-autoload` has to finish. If it stops because `public/main/install` is missing, create that empty directory and run it again. The course home loads `CourseEvaluationPlugin` from the Composer classmap. Without that, the tool record exists but no card is drawn.

## Uninstall

Disabling the plugin keeps the data. Uninstalling it drops the plugin tables, removes the course tool, and deletes the assessment items this plugin added to the gradebook, including their results.

## Database changes in a later release

Chamilo does not migrate these tables. `CourseEvaluationPlugin::update()` does. It runs from `install()` and on the first page load after the new files are copied. The applied version is stored in `plugin_course_evaluation_schema`.

A new table or column is a new step in `schemaSteps()`, for example `'1.2.0' => 'migrate120'`, plus the same version in `CourseEvaluationPlugin::VERSION` and the fallback in `plugin.php`. The step checks that the table or column is missing, then adds it. Changing only the `CREATE TABLE` text leaves an existing database as it is. Version 1.1.0 renames `plugin_course_evaluation_campaign` to `plugin_course_evaluation_evaluation`.

## License

Copyright (C) 2026 Mark Clarke. This plugin is free software under the GNU General Public License, version 3 or any later version. The terms are in the LICENSE file.
