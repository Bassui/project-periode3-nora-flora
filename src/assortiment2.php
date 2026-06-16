<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
      <form action="assortiment2.php" method="GET"> 
                <div class="field">
                <input type="text" name="zoekterm" id="zoekterm" placeholder="Zoek planten...">
                </div>
                <input type="submit" value="Zoek">
            </form>
            
    <?php


$conn = require_once 'partials/dbconnection.php';




    if (isset( $_GET['zoekterm'])) {
    $zoekterm = $_GET['zoekterm'] ;

    if (!empty($zoekterm)) {
        echo "Zoekterm: " . htmlspecialchars($zoekterm);


$stmt = $conn->prepare("SELECT * FROM planten_met_afbeeldingen_zip WHERE naam LIKE ? LIMIT 20");
$zoekterm = "%" . $zoekterm . "%";
$stmt->bind_param("s", $zoekterm);
$stmt->execute();
$result = $stmt->get_result();
    if ($result->num_rows === 0) {
      exit('No rows');
}else {
    while ($row = $result->fetch_assoc()) {
        echo "Plant: " . htmlspecialchars($row['naam']) . "<br>";
    }
}


    }



}
 ?>
</body>
</html>
