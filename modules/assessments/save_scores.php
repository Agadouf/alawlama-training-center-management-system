<?php

session_start();

require_once '../../config/database.php';

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$role = $_SESSION['role'] ?? '';

/*
|--------------------------------------------------------------------------
| Instructor Only
|--------------------------------------------------------------------------
*/

if ($role !== 'instructor') {
    http_response_code(403);
    die('Access denied.');
}

$userId = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| POST Only
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Assessment ID
|--------------------------------------------------------------------------
*/

$assessmentId = isset($_POST['assessment_id'])
    ? (int) $_POST['assessment_id']
    : 0;

if ($assessmentId <= 0) {
    die('Invalid assessment.');
}

/*
|--------------------------------------------------------------------------
| Get Instructor
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id
    FROM instructors
    WHERE user_id = ?
    LIMIT 1
");

$stmt->execute([$userId]);

$instructor = $stmt->fetch();

if (!$instructor) {
    die('Instructor profile not found.');
}

$instructorId = (int) $instructor['id'];

/*
|--------------------------------------------------------------------------
| Verify Assessment Belongs to Instructor's Batch
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        a.id,
        a.batch_id,
        a.title,
        a.type,
        a.max_score
    FROM assessments a

    INNER JOIN batch_instructors bi
        ON bi.batch_id = a.batch_id

    WHERE a.id = ?
    AND bi.instructor_id = ?

    LIMIT 1
");

$stmt->execute([
    $assessmentId,
    $instructorId
]);

$assessment = $stmt->fetch();

if (!$assessment) {
    http_response_code(403);
    die('Assessment not found or access denied.');
}

$batchId = (int) $assessment['batch_id'];
$maxScore = (float) $assessment['max_score'];

/*
|--------------------------------------------------------------------------
| Get Submitted Scores
|--------------------------------------------------------------------------
*/

$scores = $_POST['scores'] ?? [];
$notes = $_POST['notes'] ?? [];

if (!is_array($scores)) {
    die('Invalid scores data.');
}

/*
|--------------------------------------------------------------------------
| Save Scores
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Verify Enrollment
    |--------------------------------------------------------------------------
    */

    $enrollmentStmt = $pdo->prepare("
        SELECT id
        FROM enrollments
        WHERE id = ?
        AND batch_id = ?
        AND status != 'cancelled'
        LIMIT 1
    ");

    /*
    |--------------------------------------------------------------------------
    | Check Existing Score
    |--------------------------------------------------------------------------
    */

    $existingStmt = $pdo->prepare("
        SELECT id
        FROM assessment_scores
        WHERE assessment_id = ?
        AND enrollment_id = ?
        LIMIT 1
    ");

    /*
    |--------------------------------------------------------------------------
    | Insert Score
    |--------------------------------------------------------------------------
    */

    $insertStmt = $pdo->prepare("
        INSERT INTO assessment_scores (
            assessment_id,
            enrollment_id,
            score,
            notes,
            recorded_by,
            created_at,
            updated_at
        )
        VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            NOW(),
            NOW()
        )
    ");

    /*
    |--------------------------------------------------------------------------
    | Update Score
    |--------------------------------------------------------------------------
    */

    $updateStmt = $pdo->prepare("
        UPDATE assessment_scores
        SET
            score = ?,
            notes = ?,
            recorded_by = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    /*
    |--------------------------------------------------------------------------
    | Process Students
    |--------------------------------------------------------------------------
    */

    foreach ($scores as $enrollmentId => $score) {

        $enrollmentId = (int) $enrollmentId;

        if ($enrollmentId <= 0) {
            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | Verify Enrollment Belongs to Batch
        |--------------------------------------------------------------------------
        */

        $enrollmentStmt->execute([
            $enrollmentId,
            $batchId
        ]);

        $enrollment = $enrollmentStmt->fetch();

        if (!$enrollment) {
            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | Skip Empty Scores
        |--------------------------------------------------------------------------
        */

        if ($score === '' || $score === null) {
            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Numeric Score
        |--------------------------------------------------------------------------
        */

        if (!is_numeric($score)) {
            throw new Exception(
                'Invalid score for enrollment ID ' .
                $enrollmentId
            );
        }

        $score = (float) $score;

        /*
        |--------------------------------------------------------------------------
        | Validate Score
        |--------------------------------------------------------------------------
        */

        if ($score < 0) {
            throw new Exception(
                'Score cannot be less than 0.'
            );
        }

        if ($score > $maxScore) {
            throw new Exception(
                'Score for "' .
                $assessment['title'] .
                '" cannot be greater than ' .
                $maxScore .
                '.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Get Notes
        |--------------------------------------------------------------------------
        */

        $studentNote = '';

        if (isset($notes[$enrollmentId])) {
            $studentNote = trim(
                (string) $notes[$enrollmentId]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Check Existing Score
        |--------------------------------------------------------------------------
        */

        $existingStmt->execute([
            $assessmentId,
            $enrollmentId
        ]);

        $existingScore = $existingStmt->fetch();

        /*
        |--------------------------------------------------------------------------
        | UPDATE
        |--------------------------------------------------------------------------
        */

        if ($existingScore) {

            $updateStmt->execute([
                $score,
                $studentNote !== ''
                    ? $studentNote
                    : null,
                $userId,
                (int) $existingScore['id']
            ]);

        }

        /*
        |--------------------------------------------------------------------------
        | INSERT
        |--------------------------------------------------------------------------
        */

        else {

            $insertStmt->execute([
                $assessmentId,
                $enrollmentId,
                $score,
                $studentNote !== ''
                    ? $studentNote
                    : null,
                $userId
            ]);

        }

    }

    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    */

    header(
        'Location: scores.php?assessment_id=' .
        $assessmentId .
        '&message=scores_saved'
    );

    exit;

} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Rollback
    |--------------------------------------------------------------------------
    */

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die(
        'Failed to save scores: ' .
        htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        )
    );
}