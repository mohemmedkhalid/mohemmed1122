<?php
function family_h($value) { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function family_admin_people($db) {
    $result = $db->query('SELECT id, full_name, full_name_en, gender, status, father_id, mother_id FROM people2 ORDER BY full_name, id');
    if (!$result) throw new RuntimeException('people_read_failed');
    $map = [];
    while ($row = $result->fetch_assoc()) $map[(int) $row['id']] = $row;
    return $map;
}
function family_triple($person, $map) {
    $name = get_person_name($person); $seen = [(int)$person['id'] => true];
    for ($i = 0; $i < 2; $i++) {
        $pid = (int) ($person['father_id'] ?? 0);
        if (!$pid || !isset($map[$pid]) || isset($seen[$pid])) break;
        $seen[$pid] = true; $person = $map[$pid]; $part = get_person_name($person);
        if ($part !== '' && strpos(' '.$name.' ', ' '.$part.' ') === false) $name .= ' '.$part;
    }
    return $name;
}
function family_delete_one($db, $id) {
    if ($id < 1) throw new InvalidArgumentException('invalid_person');
    $run = function ($sql, $types, ...$params) use ($db) {
        $stmt = $db->prepare($sql);
        if (!$stmt) throw new RuntimeException('prepare_failed');
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) throw new RuntimeException('write_failed');
        return $stmt;
    };
    $db->begin_transaction();
    try {
        $locked = $run('SELECT id FROM people2 WHERE id = ? FOR UPDATE', 'i', $id);
        if (!$locked->get_result()->fetch_assoc()) throw new RuntimeException('person_missing');
        $locked->close();
        // Detach references before deleting the selected row, including databases
        // whose parent foreign keys were declared ON DELETE CASCADE.
        $run('UPDATE people2 SET father_id = NULL WHERE father_id = ?', 'i', $id)->close();
        $run('UPDATE people2 SET mother_id = NULL WHERE mother_id = ?', 'i', $id)->close();
        $run('DELETE FROM marriages WHERE husband_id = ? OR wife_id = ?', 'ii', $id, $id)->close();
        $tables = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('group_members','group_leaders','trip_group_members')");
        if (!$tables) throw new RuntimeException('memberships_read_failed');
        while ($table = $tables->fetch_assoc()) {
            $name = $table['TABLE_NAME'];
            if (in_array($name, ['group_members','group_leaders','trip_group_members'], true)) $run('DELETE FROM `'.$name.'` WHERE person_id = ?', 'i', $id)->close();
        }
        $stmt = $run('DELETE FROM people2 WHERE id = ? LIMIT 1', 'i', $id);
        if ($stmt->affected_rows !== 1) throw new RuntimeException('person_missing');
        $stmt->close(); $db->commit();
    } catch (Throwable $error) { $db->rollback(); throw $error; }
}
