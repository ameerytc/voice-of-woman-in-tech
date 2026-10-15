<?php
// Usage: php scripts/seed.php   — adds ONE draft example quiz with non-sensitive cybersecurity-awareness questions. Never run on data you care about unless you want the sample.
require __DIR__ . '/_cli.php';
if (db_val("SELECT 1 FROM quizzes WHERE title LIKE '[Sample]%'")) { echo "Sample quiz already exists.\n"; exit; }
$items = json_decode((string)file_get_contents(BASE_PATH . '/database/seeds/sample_questions.json'), true);
tx(function () use ($items) {
    $id = db_insert('INSERT INTO quizzes(title,description,category,duration_minutes,opens_at,closes_at,status) VALUES(?,?,?,?,?,?,?)',
        ['[Sample] Cybersecurity Awareness Quiz', 'Example quiz seeded for testing. Edit or delete before real use.', 'Cybersecurity Awareness', 10, gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s', time() + 86400 * 30), 'draft']);
    foreach ($items as $i => $q) {
        $qid = db_insert('INSERT INTO questions(quiz_id,type,body,marks,sort_order,explanation) VALUES(?,?,?,?,?,?)', [$id, 'mcq', $q['question'], 1, $i + 1, $q['explanation']]);
        foreach ($q['options'] as $k => $o) db_exec('INSERT INTO question_options(question_id,body,is_correct,sort_order) VALUES(?,?,?,?)', [$qid, $o, $k === (int)$q['correct'] ? 1 : 0, $k + 1]);
    }
});
echo count($items) . " sample questions added to a DRAFT quiz.\n";
