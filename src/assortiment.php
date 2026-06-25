<?php
$conn = new mysqli("mysql", "root", "password", "nora_flora");

// Haal alle GET parameters op
$zoekterm = trim($_GET['zoekterm'] ?? '');
$standplaats_filter = $_GET['standplaats'] ?? '';
$sorteer_optie = $_GET['sorteer'] ?? 'naam_asc'; // Standaard op naam A-Z

$where_parts = [];
$bind_values = [];
$bind_types  = '';

// Filter by name
if ($zoekterm !== '') {
    $where_parts[] = 'naam LIKE ?';
    $bind_values[] = '%' . $zoekterm . '%';
    $bind_types   .= 's';
}

// Filter by standplaats
if ($standplaats_filter !== '') {
    $where_parts[] = 'standplaats = ?';
    $bind_values[] = $standplaats_filter;
    $bind_types   .= 's';
}

$where = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

// Bepaal de veilige ORDER BY clause op basis van de geselecteerde optie
$order_by = 'naam ASC'; // Standaardwaarde
if ($sorteer_optie === 'prijs_desc') {
    $order_by = 'verkoopprijs_eur DESC'; // Prijs Hoog - Laag
} elseif ($sorteer_optie === 'prijs_asc') {
    $order_by = 'verkoopprijs_eur ASC'; // Prijs Laag - Hoog[cite: 1]
} elseif ($sorteer_optie === 'naam_desc') {
    $order_by = 'naam DESC'; // Naam Z-A
}

// Pas de SQL query aan zodat de variabele {$order_by} wordt gebruikt
$sql = "
    SELECT naam, verkoopprijs_eur, overview_image, voorraad, standplaats
    FROM planten_met_afbeeldingen_zip
    {$where}
    ORDER BY {$order_by}
    LIMIT 20
";

$stmt = $conn->prepare($sql);
if ($bind_values) {
    $stmt->bind_param($bind_types, ...$bind_values);
}
$stmt->execute();
$result = $stmt->get_result();

$plants = [];
while ($row = $result->fetch_assoc()) $plants[] = $row;
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <script src="script.js" defer></script>
    <title>Assortiment – Nora's Flora</title>
</head>

<body>
    <header>
        <img src="img/norasFloraLogo.png" class="Logo" loading="lazy" alt="Nora's Flora logo">
        <div class="icon-cart">
            <a href="Shoppingcart.html">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="42.5" height="40"
                     fill="currentColor" viewBox="0 0 24 24">
                    <path fill-rule="evenodd"
                          d="M4 4a1 1 0 0 1 1-1h1.5a1 1 0 0 1 .979.796L7.939 6H19a1 1 0 0 1 .979 1.204l-1.25 6a1 1 0 0 1-.979.796H9.605l.208 1H17a3 3 0 1 1-2.83 2h-2.34a3 3 0 1 1-4.009-1.76L5.686 5H5a1 1 0 0 1-1-1Z"
                          clip-rule="evenodd"/>
                </svg>
                <span>0</span>
            </a>
        </div>
    </header>

    <nav>
        <ul>
            <li><a href="index.html" class="navText">| Home |</a></li>
            <li><a href="assortiment.php" class="navText">| Assortiment |</a></li>
            <li><a href="contact.html" class="navText">| Contact |</a></li>
        </ul>
    </nav>

    <div class="assortment-page">
        <div class="container">

            <form method="GET">
                <input type="text" name="zoekterm" placeholder="Zoek planten..." value="<?= htmlspecialchars($zoekterm) ?>">
                <select name="standplaats">
                    <option value="">Alle standplaatsen</option>
                    <option value="Kamerplanten" <?= $standplaats_filter === 'Kamerplanten' ? 'selected' : '' ?>>Kamerplanten</option>
                    <option value="Tussenvariant" <?= $standplaats_filter === 'Tussenvariant' ? 'selected' : '' ?>>Tussenvariant</option>
                    <option value="Buitenplanten" <?= $standplaats_filter === 'Buitenplanten' ? 'selected' : '' ?>>Buitenplanten</option>
                </select>
                <select name="sorteer">
        <option value="naam_asc" <?= $sorteer_optie === 'naam_asc' ? 'selected' : '' ?>>Naam (A-Z)</option>
        <option value="prijs_asc" <?= $sorteer_optie === 'prijs_asc' ? 'selected' : '' ?>>Prijs (Laag naar Hoog)</option>
        <option value="prijs_desc" <?= $sorteer_optie === 'prijs_desc' ? 'selected' : '' ?>>Prijs (Hoog naar Laag)</option>
    </select>
                <button type="submit">Zoeken</button>
            </form><br>

            <p class="resultaten-info">
                <?= count($plants) ?> plant<?= count($plants) !== 1 ? 'en' : '' ?> gevonden
                <?php if ($zoekterm !== ''): ?>
                    · Zoekterm: <strong><?= htmlspecialchars($zoekterm) ?></strong>
                <?php endif; ?>
            </p>

            <div class="assortment-grid">
                <?php foreach ($plants as $plant):
                    $uitverkocht = (int)$plant['voorraad'] === 0;
                ?>
                    <div class="assortment-item<?= $uitverkocht ? ' uitverkocht' : '' ?>">
                        <img src="plant_images/<?= htmlspecialchars($plant['overview_image']) ?>"
                             loading="lazy"
                             alt="<?= htmlspecialchars($plant['naam']) ?>">
                        <div class="assortment-caption">
                            <?php if ($uitverkocht): ?>
                                <span class="uitverkocht-badge">Uitverkocht</span>
                            <?php endif; ?>
                            <span class="plant-naam"><?= htmlspecialchars($plant['naam']) ?></span>
                            <span class="plant-prijs">
                                Al vanaf €<?= number_format($plant['verkoopprijs_eur'], 2, ',', '.') ?>,-
                            </span>
                            <span class="standplaats"><?= htmlspecialchars($plant['standplaats']) ?></span>
                            <button class="add-to-cart-btn" <?= $uitverkocht ? 'disabled' : '' ?>>+</button>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($plants)): ?>
                    <p class="geen-resultaten">
                        Geen planten gevonden met deze filters.
                    </p>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <footer>
        <div class="footer-container">

            <div class="opening">
                <h2>Openingstijden</h2>
                <div class="row">
                    <span>Ma–vr</span>
                    <span>12:00 – 17:00</span>
                </div>
                <div class="row">
                    <span>Zaterdag</span>
                    <span>10:00 – 17:00</span>
                </div>
            </div>

            <div class="contact">
                <p><span class="contact-label">Email</span> contact@noraflora.com</p>
                <p><span class="contact-label">Telefoon</span> 06 12 34 56 78</p>
                <p><span class="contact-label">Adres</span> Pannekoekendijk 420B, Zwolle</p>
            </div>

        </div>
    </footer>

    <script src="cart-icon-counter.js"></script>
</body>

</html>
