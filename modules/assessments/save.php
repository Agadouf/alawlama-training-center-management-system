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
| Get Batch
|--------------------------------------------------------------------------
*/

$batchId = isset($_POST['batch_id'])
    ? (int) $_POST['batch_id']
    : 0;


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
| Verify Instructor Assignment
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


if ((int) $stmt->fetchColumn() === 0) {

    http_response_code(403);

    die('You are not assigned to this batch.');

}


/*
|--------------------------------------------------------------------------
| Get Action
|--------------------------------------------------------------------------
*/

$action = $_POST['action'] ?? '';


/*
|--------------------------------------------------------------------------
| SAVE MULTIPLE ASSIGNMENTS
|--------------------------------------------------------------------------
*/

if ($action === 'save') {

    $assignments = $_POST['assignments'] ?? [];


    if (!is_array($assignments)) {
        die('Invalid assignments data.');
    }


    try {

        $pdo->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | Insert New Assignment
        |--------------------------------------------------------------------------
        */

        $insertStmt = $pdo->prepare("
            INSERT INTO assessments (
                batch_id,
                title,
                type,
                max_score,
                due_date,
                description,
                status,
                created_by,
                created_at,
                updated_at
            )
            VALUES (
                ?,
                ?,
                'assignment',
                ?,
                ?,
                ?,
                'active',
                ?,
                NOW(),
                NOW()
            )
        ");


        /*
        |--------------------------------------------------------------------------
        | Update Existing Assignment
        |--------------------------------------------------------------------------
        */

        $updateStmt = $pdo->prepare("
            UPDATE assessments

            SET
                title = ?,
                max_score = ?,
                due_date = ?,
                description = ?,
                updated_at = NOW()

            WHERE id = ?

            AND batch_id = ?

            AND type = 'assignment'
        ");


        foreach ($assignments as $assignment) {

            if (!is_array($assignment)) {
                continue;
            }


            $id = isset($assignment['id'])
                ? (int) $assignment['id']
                : 0;


            $title = trim(
                $assignment['title'] ?? ''
            );


            $maxScore = $assignment['max_score']
                ?? '';


            $dueDate = trim(
                $assignment['due_date'] ?? ''
            );


            $description = trim(
                $assignment['description'] ?? ''
            );


            /*
            |--------------------------------------------------------------------------
            | Skip Empty Row
            |--------------------------------------------------------------------------
            */

            if ($title === '') {
                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | Validate Max Score
            |--------------------------------------------------------------------------
            */

            if (
                $maxScore === '' ||
                !is_numeric($maxScore) ||
                (float) $maxScore <= 0
            ) {

                throw new Exception(
                    'Maximum score must be greater than 0.'
                );

            }


            $maxScore = (float) $maxScore;


            /*
            |--------------------------------------------------------------------------
            | Validate Due Date
            |--------------------------------------------------------------------------
            */

            if ($dueDate === '') {
                $dueDate = null;
            }


            /*
            |--------------------------------------------------------------------------
            | Update
            |--------------------------------------------------------------------------
            */

            if ($id > 0) {

                $updateStmt->execute([
                    $title,
                    $maxScore,
                    $dueDate,
                    $description !== ''
                        ? $description
                        : null,
                    $id,
                    $batchId
                ]);

            }


            /*
            |--------------------------------------------------------------------------
            | Insert
            |--------------------------------------------------------------------------
            */

            else {

                $insertStmt->execute([
                    $batchId,
                    $title,
                    $maxScore,
                    $dueDate,
                    $description !== ''
                        ? $description
                        : null,
                    $userId
                ]);

            }

        }


        $pdo->commit();


        /*
        |--------------------------------------------------------------------------
        | Return to Page
        |--------------------------------------------------------------------------
        */

        header(
            'Location: index.php?batch_id=' .
            $batchId .
            '&message=assignments_saved'
        );

        exit;


    } catch (Throwable $e) {


        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }


        die(
            'Failed to save assignments: ' .
            htmlspecialchars(
                $e->getMessage()
            )
        );

    }

}


/*
|--------------------------------------------------------------------------
| SAVE MIDTERM / FINAL
|--------------------------------------------------------------------------
*/

if ($action === 'save_single') {

    $type = $_POST['type'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | Only Midterm or Final
    |--------------------------------------------------------------------------
    */

    if (
        $type !== 'midterm' &&
        $type !== 'final'
    ) {

        die('Invalid assessment type.');

    }


    $title = trim(
        $_POST['title'] ?? ''
    );


    $maxScore = $_POST['max_score']
        ?? '';


    $dueDate = trim(
        $_POST['due_date'] ?? ''
    );


    /*
    |--------------------------------------------------------------------------
    | Validate Title
    |--------------------------------------------------------------------------
    */

    if ($title === '') {
        die('Title is required.');
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Score
    |--------------------------------------------------------------------------
    */

    if (
        $maxScore === '' ||
        !is_numeric($maxScore) ||
        (float) $maxScore <= 0
    ) {

        die(
            'Maximum score must be greater than 0.'
        );

    }


    $maxScore = (float) $maxScore;


    /*
    |--------------------------------------------------------------------------
    | Empty Date
    |--------------------------------------------------------------------------
    */

    if ($dueDate === '') {
        $dueDate = null;
    }


    try {

        /*
        |--------------------------------------------------------------------------
        | Check Existing Exam
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT id
            FROM assessments
            WHERE batch_id = ?
            AND type = ?
            LIMIT 1
        ");

        $stmt->execute([
            $batchId,
            $type
        ]);

        $existing = $stmt->fetch();


        /*
        |--------------------------------------------------------------------------
        | Update Existing
        |--------------------------------------------------------------------------
        */

        if ($existing) {

            $stmt = $pdo->prepare("
                UPDATE assessments

                SET
                    title = ?,
                    max_score = ?,
                    due_date = ?,
                    updated_at = NOW()

                WHERE id = ?

                AND batch_id = ?

                AND type = ?
            ");

            $stmt->execute([
                $title,
                $maxScore,
                $dueDate,
                (int) $existing['id'],
                $batchId,
                $type
            ]);

        }


        /*
        |--------------------------------------------------------------------------
        | Create New
        |--------------------------------------------------------------------------
        */

        else {

            $stmt = $pdo->prepare("
                INSERT INTO assessments (
                    batch_id,
                    title,
                    type,
                    max_score,
                    due_date,
                    description,
                    status,
                    created_by,
                    created_at,
                    updated_at
                )

                VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    NULL,
                    'active',
                    ?,
                    NOW(),
                    NOW()
                )
            ");

            $stmt->execute([
                $batchId,
                $title,
                $type,
                $maxScore,
                $dueDate,
                $userId
            ]);

        }


        /*
        |--------------------------------------------------------------------------
        | Redirect
        |--------------------------------------------------------------------------
        */

        $message =
            $type === 'midterm'
                ? 'midterm_saved'
                : 'final_saved';


        header(
            'Location: index.php?batch_id=' .
            $batchId .
            '&message=' .
            $message
        );

        exit;


    } catch (Throwable $e) {

        die(
            'Failed to save assessment: ' .
            htmlspecialchars(
                $e->getMessage()
            )
        );

    }

}


/*
|--------------------------------------------------------------------------
| Invalid Action
|--------------------------------------------------------------------------
*/

die('Invalid action.');