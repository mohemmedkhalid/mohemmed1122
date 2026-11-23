<?php
// Repair legacy users.id values without changing personal access codes or roles.
function key_user_columns($db) {
    $result = $db->query('SHOW COLUMNS FROM users');
    if (!$result) throw new RuntimeException('Could not inspect users schema.');
    $columns = [];
    while ($row = $result->fetch_assoc()) $columns[$row['Field']] = $row;
    $result->free(); return $columns;
}
function key_users_need_repair($db, $columns) {
    if (!isset($columns['id'], $columns['access_code'], $columns['role'])) throw new RuntimeException('Required users columns are missing.');
    if (stripos($columns['id']['Extra'], 'auto_increment') === false) return true;
    return (bool)key_rows($db, 'SELECT id FROM users GROUP BY id HAVING id<=0 OR COUNT(*)>1 LIMIT 1');
}
function key_users_ready($db) {
    static $ready = false;
    if ($ready) return;
    $columns = key_user_columns($db);
    if (!key_users_need_repair($db, $columns)) { $ready = true; return; }
    $database = key_rows($db, 'SELECT DATABASE() AS db')[0]['db'];
    $lock = 'jotiari_user_ids_'.substr(hash('sha256', $database), 0, 24);
    $locked = key_rows($db, 'SELECT GET_LOCK(?, 10) AS acquired', 's', [$lock]);
    if ((int)($locked[0]['acquired'] ?? 0) !== 1) throw new RuntimeException('User identity repair is busy.');
    $transaction = false;
    try {
        $columns = key_user_columns($db);
        if (!key_users_need_repair($db, $columns)) { $ready = true; return; }
        $type = strtolower($columns['id']['Type']);
        if (preg_match('/\A(?:tinyint|smallint|mediumint|int|bigint)(?:\([0-9]+\))?(?: unsigned)?\z/D', $type) !== 1) {
            throw new RuntimeException('Unsupported users identity column.');
        }
        $db->begin_transaction(); $transaction = true;
        $users = key_rows($db, 'SELECT id, access_code, role FROM users ORDER BY id, access_code FOR UPDATE');
        $counts = []; $maximum = 0; $codes = [];
        foreach ($users as $user) {
            $id = (int)$user['id']; $counts[$id] = ($counts[$id] ?? 0) + 1;
            $maximum = max($maximum, $id);
            if (isset($codes[$user['access_code']])) throw new RuntimeException('Duplicate access codes require individual review.');
            $codes[$user['access_code']] = true;
        }
        $affected = [];
        foreach ($users as $user) {
            $oldId = (int)$user['id'];
            if ($oldId > 0 && $counts[$oldId] === 1) continue;
            $newId = ++$maximum;
            $stmt = key_query($db, 'UPDATE users SET id=? WHERE BINARY access_code=BINARY ?', 'is', [$newId, $user['access_code']]);
            $changed = $stmt->affected_rows; $stmt->close();
            if ($changed !== 1) throw new RuntimeException('User identity repair did not update exactly one record.');
            $affected[$oldId] = true;
        }
        if ($affected) {
            // Old duplicate IDs cannot establish which person owned a device.
            $exists = key_rows($db, "SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='site_access_tokens'");
            if ($exists) foreach (array_keys($affected) as $id) key_query($db, 'DELETE FROM site_access_tokens WHERE user_id=?', 'i', [(int)$id])->close();
            // Feedback records retain their original sender IDs. Never guess an old sender.
        }
        $db->commit(); $transaction = false;
        $indexes = $db->query('SHOW INDEX FROM users');
        if (!$indexes) throw new RuntimeException('Could not inspect users indexes.');
        $groups = []; $hasPrimary = false;
        while ($row = $indexes->fetch_assoc()) {
            if ($row['Key_name'] === 'PRIMARY') $hasPrimary = true;
            if ((int)$row['Non_unique'] === 0) $groups[$row['Key_name']][(int)$row['Seq_in_index']] = $row['Column_name'];
        }
        $indexes->free(); $uniqueId = false;
        foreach ($groups as $fields) if (count($fields) === 1 && ($fields[1] ?? null) === 'id') $uniqueId = true;
        $alter = [];
        if (!$uniqueId) $alter[] = $hasPrimary ? 'ADD UNIQUE KEY jotiari_user_id (id)' : 'ADD PRIMARY KEY (id)';
        if (stripos($columns['id']['Extra'], 'auto_increment') === false) $alter[] = 'MODIFY COLUMN id '.$type.' NOT NULL AUTO_INCREMENT';
        if ($alter && !$db->query('ALTER TABLE users '.implode(', ', $alter))) throw new RuntimeException('Could not enable users identity numbering.');
        $ready = true;
    } catch (Throwable $e) {
        if ($transaction) $db->rollback();
        throw $e;
    } finally {
        key_query($db, 'SELECT RELEASE_LOCK(?)', 's', [$lock])->close();
    }
}
function key_code_limit($db) {
    $columns = key_user_columns($db);
    if (preg_match('/\A(?:var)?char\(([0-9]+)\)/i', $columns['access_code']['Type'] ?? '', $m)) return min(255, (int)$m[1]);
    return 255;
}
function key_new_personal_code($db) {
    for ($attempt=0; $attempt<32; $attempt++) {
        $code = (string)random_int(100000, 999999);
        if (!key_rows($db, 'SELECT id FROM users WHERE access_code=? LIMIT 1', 's', [$code])) return $code;
    }
    throw new RuntimeException('Could not generate an unused personal code.');
}
function key_insert_user($db, $code, $role) {
    $columns = key_user_columns($db);
    // The uploaded legacy schema requires email even though key access does not use it.
    if (isset($columns['email'])) key_query($db, 'INSERT INTO users (access_code, email, role) VALUES (?, ?, ?)', 'sss', [$code,'',$role])->close();
    else key_query($db, 'INSERT INTO users (access_code, role) VALUES (?, ?)', 'ss', [$code,$role])->close();
}
