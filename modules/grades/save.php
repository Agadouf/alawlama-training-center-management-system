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


/*
|--------------------------------------------------------------------------
| Instructor Only
|--------------------------------------------------------------------------
*/

$role = $_SESSION['role'] ?? '';

if ($role !== 'instructor') {
    http_response_code(403);
    die('Access denied.');
}


$userId = (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| Only POST Requests
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Submitted Data
|--------------------------------------------------------------------------
*/

$batchId = isset($_POST['batch_id'])
    ? (int) $_POST['batch_id']
    : 0;

$scores = $_POST['scores'] ?? [];

$notes = $_POST['notes'] ?? [];


/*
|--------------------------------------------------------------------------
| Validate Batch
|--------------------------------------------------------------------------
*/

if ($batchId <= 0) {
    die('Invalid batch.');
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
| Verify Instructor Is Assigned to Batch
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM batch_instructors
    WHERE batch_id = ?
    AND instructor_id = ?
");

$stmt->execute([
    $batchId,
    $instructorId
]);

$isAssigned = (int) $stmt->fetchColumn();

if ($isAssigned === 0) {
    http_response_code(403);
    die('You are not assigned to this batch.');
}


/*
|--------------------------------------------------------------------------
| No Scores Submitted
|--------------------------------------------------------------------------
*/

if (empty($scores)) {
    header(
        'Location: index.php?batch_id=' .
        $batchId .
        '&message=no_scores'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Begin Transaction
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Prepare Enrollment Verification
    |--------------------------------------------------------------------------
    |
    | This makes sure the instructor can only save grades
    | for students who are actually enrolled in the selected batch.
    |
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
    | Check Existing Grade
    |--------------------------------------------------------------------------
    */

    $gradeCheckStmt = $pdo->prepare("
        SELECT id
        FROM grades
        WHERE enrollment_id = ?
        LIMIT 1
    ");


    /*
    |--------------------------------------------------------------------------
    | Insert Grade
    |--------------------------------------------------------------------------
    */

    $insertStmt = $pdo->prepare("
        INSERT INTO grades (
            enrollment_id,
            score,
            notes,
            recorded_by,
            created_at,
            updated_at
        )
        VALUES (?, ?, ?, ?, NOW(), NOW())
    ");


    /*
    |--------------------------------------------------------------------------
    | Update Grade
    |--------------------------------------------------------------------------
    */

    $updateStmt = $pdo->prepare("
        UPDATE grades
        SET
            score = ?,
            notes = ?,
            recorded_by = ?,
            updated_at = NOW()
        WHERE id = ?
    ");


    /*
    |--------------------------------------------------------------------------
    | Process Each Student
    |--------------------------------------------------------------------------
    */

    foreach ($scores as $enrollmentId => $score) {

        $enrollmentId = (int) $enrollmentId;


        /*
        |--------------------------------------------------------------------------
        | Verify Enrollment
        |--------------------------------------------------------------------------
        */

        if ($enrollmentId <= 0) {
            continue;
        }

        $enrollmentStmt->execute([
            $enrollmentId,
            $batchId
        ]);

        $enrollmentExists = $enrollmentStmt->fetch();

        if (!$enrollmentExists) {
            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Empty Score
        |--------------------------------------------------------------------------
        |
        | If the instructor leaves the score empty,
        | we simply skip it.
        |
        */

        if ($score === '' || $score === null) {
            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Convert Score to Number
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
        | Validate Score Range
        |--------------------------------------------------------------------------
        */

        if ($score < 0 || $score > 100) {
            throw new Exception(
                'Score must be between 0 and 100.'
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
        | Check Existing Grade
        |--------------------------------------------------------------------------
        */

        $gradeCheckStmt->execute([
            $enrollmentId
        ]);

        $existingGrade = $gradeCheckStmt->fetch();


        /*
        |--------------------------------------------------------------------------
        | Update Existing Grade
        |--------------------------------------------------------------------------
        */

        if ($existingGrade) {

            $updateStmt->execute([
                $score,
                $studentNote !== ''
                    ? $studentNote
                    : null,
                $userId,
                (int) $existingGrade['id']
            ]);

        }


        /*
        |--------------------------------------------------------------------------
        | Insert New Grade
        |--------------------------------------------------------------------------
        */

        else {

            $insertStmt->execute([
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
    | Commit Transaction
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Redirect Back
    |--------------------------------------------------------------------------
    */

    header(
        'Location: index.php?batch_id=' .
        $batchId .
        '&message=saved'
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


    /*
    |--------------------------------------------------------------------------
    | Development Error
    |--------------------------------------------------------------------------
    |
    | During development, showing the error is useful.
    |
    */

    die(
        'Failed to save grades: ' .
        htmlspecialchars($e->getMessage())
    );

}