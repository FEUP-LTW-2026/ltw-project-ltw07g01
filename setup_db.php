<?php
    $db = new PDO('sqlite:app.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA foreign_keys = ON;');
    $sql = file_get_contents('schema.sql');
    $db->exec($sql);
    echo "DB created!\n";