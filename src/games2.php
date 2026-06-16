<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Games; met SQL prepared statement en partial</title>
  <link rel="stylesheet" href="css/style.css">
</head>

<body>
  <table>
    <tr>
      <th>ID</th>
      <th>username</th>
      <th>password</th>
      <th>account made</th>
    </tr>

    <?php
$conn = require_once "partials/dbconnection.php";

$stmt = $conn->prepare("SELECT * FROM loginpage");
$stmt->execute();
$result = $stmt->get_result();
    if ($result->num_rows === 0)
      exit('No rows');

    while ($row = $result->fetch_assoc()) {
    $keys = array_keys($row);
    echo "<tr>";
    echo "<td><a href='details.php?id=" . $row[$keys[0]] . "'>" . $row[$keys[0]] . "</a></td>";
    echo "<td>" . $row[$keys[1]] . "</td>";
    echo "<td>" . $row[$keys[2]] . "</td>";
    echo "<td>" . $row[$keys[3]] . "</td>";
    echo "</tr>";
}
    echo "</table>";

    $stmt->close();
    ?>
</body>

</html>