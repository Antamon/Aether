<?php

// Create connection
$servername = "localhost";
$username = "oneiros_be_aether";
$password = ":ZG+Yn{{~Hr4a]gR";
$dbname = "oneiros_be_aether";

$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function getRowFromDatabase($conn, $qry)
{
    $rst = $conn->query($qry);
    if ($rst->num_rows === 1) {
        return $rst->fetch_assoc();
    }
}

function getArrayFromDatabase($conn, $qry)
{
    $returnArray = array();
    $rst = $conn->query($qry);
    if ($rst->num_rows > 0) {
        while ($row = $rst->fetch_assoc()) {
            $returnArray[] = $row;
        }
    }
    return $returnArray;
}

function insertData($conn, $table, $insertData)
{

    //Create query
    $numberOfItems = count($insertData);
    $columns = implode(", ", array_keys($insertData));
    $questionmarks = implode(',', array_fill(0, $numberOfItems, '?'));
    $dataTypes = str_repeat("s", $numberOfItems);
    $qry = "INSERT INTO $table ($columns) VALUES ($questionmarks)";

    //Prepare and bind
    $newData[0] = $dataTypes;

    $stmt = $conn->prepare($qry) or die($conn->error);
    foreach ($insertData as $value) {
        $newData[] = &$value;
        unset($value);
    }

    //$stmt->bind_param($dataTypes,$newData);
    call_user_func_array(array(&$stmt, 'bind_param'), $newData);
    if ($stmt->execute()) {
        return $stmt->insert_id;
    } else {
        return "Insert failed: " . $conn->error;
    }

}

function updateData($conn, $table, $updateData)
{

    //Create query
    $id = $updateData['id'];
    $columns = $updateData;
    array_pop($columns);
    $numberOfItems = count($columns);
    $columns = implode("=?, ", array_keys($columns)) . "=?";
    $dataTypes = "s" . str_repeat("s", $numberOfItems);
    $qry = "UPDATE $table SET $columns WHERE id = ?";

    //Prepare and bind
    $newData[0] = $dataTypes;

    $stmt = $conn->prepare($qry) or die($conn->error);
    foreach ($updateData as $value) {
        $newData[] = &$value;
        unset($value);
    }

    //$stmt->bind_param($dataTypes,$newData);
    call_user_func_array(array(&$stmt, 'bind_param'), $newData);
    if ($stmt->execute()) {
        return $stmt->affected_rows;
    } else {
        return "Update failed: " . $conn->error;
    }
}

function deleteData($conn, $table, $whereConditions)
{
    // Aantal condities tellen
    $numberOfItems = count($whereConditions);
    if ($numberOfItems === 0) {
        return "Delete failed: No conditions provided";
    }

    // Kolommen en vraagtekens voorbereiden
    $conditions = implode(" AND ", array_map(fn($key) => "$key = ?", array_keys($whereConditions)));

    $dataTypes = str_repeat("s", $numberOfItems);
    $qry = "DELETE FROM $table WHERE $conditions";

    // Prepare statement
    $stmt = $conn->prepare($qry) or die($conn->error);

    // Prepare binding data
    $bindData[0] = $dataTypes;
    foreach ($whereConditions as $value) {
        $bindData[] = &$value;
        unset($value);
    }

    // Bind parameters en uitvoeren
    call_user_func_array([$stmt, 'bind_param'], $bindData);

    if ($stmt->execute()) {
        return $stmt->affected_rows;
    } else {
        return "Delete failed: " . $conn->error;
    }
}
