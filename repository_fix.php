    // If we have student_ids referenced, fetch those students in batch to map names/strands
    $studentIds = [];
    $researchIds = [];
    foreach ($research_papers as $r) {
        // treat student_id as valid when it's a non-empty string
        if (!empty($r['student_id'])) {
            $studentIds[] = $r['student_id'];
        }
        $researchIds[] = $r['id'];
    }
