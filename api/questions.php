<?php

declare(strict_types=1);

require_once __DIR__ . "/bootstrap.php";


if (($_SERVER["REQUEST_METHOD"] ?? "") !== "GET") {
    header("Allow: GET, OPTIONS");

    respond(405, [
        "success" => false,
        "code" => "method_not_allowed"
    ]);
}

require_once __DIR__ . "/../config/database.php";

try {

    $sql = "
        SELECT
            s.id AS section_number,
            s.title_en,
            s.title_ar,
            q.id AS question_id,
            q.question_type,
            q.question_en,
            q.question_ar,
            q.display_order
        FROM survey_sections s
        INNER JOIN survey_questions q
            ON q.section_number = s.id
        WHERE s.is_active = 1
          AND q.is_active = 1
        ORDER BY
            s.display_order,
            q.display_order,
            q.id
    ";

    $stmt = $pdo->query($sql);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sections = [];

    foreach ($rows as $row) {

        $sectionId = (int) $row["section_number"];

        if (!isset($sections[$sectionId])) {
            $sections[$sectionId] = [
                "number" => $sectionId,
                "title_en" => $row["title_en"],
                "title_ar" => $row["title_ar"],
                "questions" => []
            ];
        }

        $sections[$sectionId]["questions"][] = [
            "id" => (int) $row["question_id"],
            "type" => $row["question_type"],
            "en" => $row["question_en"],
            "ar" => $row["question_ar"],
            "order" => (int) $row["display_order"]
        ];
    }

    respond(200, [
        "success" => true,
        "sections" => array_values($sections)
    ]);

} catch (Throwable $e) {

    error_log("Question retrieval failed: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "code" => "questions_unavailable"
    ]);
}